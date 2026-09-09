# coding=utf-8
"""
黄金价格实时监控 + 多用户提醒脚本
整合自 gold/huangjin_email.py，改造为：
  - 多产品采集（读取 gb_gold_product 所有启用产品）
  - 多用户提醒（按订阅关系 gb_user_product + 用户独立阈值/开关/免打扰）
  - 写入 gb_gold_price / gb_gold_base / gb_alert_log（int 时间戳，适配 FastAdmin）

运行：
  pip install pymysql requests
  python gold_monitor.py

守护建议：宝塔计划任务每分钟拉起，或用 Supervisor 守护常驻。
"""

import requests
import json
import time
import smtplib
import pymysql
from datetime import datetime
from email.header import Header
from email.mime.text import MIMEText

# ================== 配置区域 ==================

# --- goldweb 数据库 ---
DB_CONFIG = {
    'host': '8.217.120.52',
    'port': 3306,
    'user': 'aloneship',
    'password': 'Ff2022888',
    'database': 'goldweb',
    'charset': 'utf8mb4'
}

# --- 邮件 SMTP ---
MAIL_HOST = 'smtp.163.com'
MAIL_PORT = 465
MAIL_USER = 'networksentmessage@163.com'
MAIL_PASS = 'IEUPOYPTPVVNTJPE'
MAIL_SENDER = 'networksentmessage@163.com'

# --- 监控参数 ---
# 产品基准价更新阈值（元）：价格波动超过此值则更新产品基准价
BASE_UPDATE_THRESHOLD = 5.0
# 采集间隔（秒）
INTERVAL = 60

# 内存去重表：避免同一用户对同一产品在阈值内重复提醒
# {(user_id, product_id, type): last_notified_price}
_alert_memory = {}


# ================== 数据库 ==================
def get_conn():
    return pymysql.connect(
        host=DB_CONFIG['host'], port=DB_CONFIG['port'],
        user=DB_CONFIG['user'], password=DB_CONFIG['password'],
        database=DB_CONFIG['database'], charset=DB_CONFIG['charset']
    )


# ================== 邮件 ==================
def send_email(to_email, title, content):
    msg = MIMEText(content, 'plain', 'utf-8')
    msg['From'] = MAIL_SENDER
    msg['To'] = to_email
    msg['Subject'] = Header(title, 'utf-8')
    try:
        smtp = smtplib.SMTP_SSL(MAIL_HOST, MAIL_PORT)
        smtp.login(MAIL_USER, MAIL_PASS)
        smtp.sendmail(MAIL_SENDER, [to_email], msg.as_string())
        smtp.quit()
        return True, ''
    except Exception as e:
        return False, str(e)


# ================== 产品与价格 ==================
def get_products():
    """获取所有启用的黄金产品"""
    conn = get_conn()
    try:
        cur = conn.cursor(pymysql.cursors.DictCursor)
        cur.execute(
            "SELECT id,bank_name,product_name,product_sku,api_url,api_headers,record_threshold "
            "FROM gb_gold_product WHERE status='1' ORDER BY weigh,id"
        )
        rows = cur.fetchall()
        for r in rows:
            r['api_headers'] = json.loads(r['api_headers']) if r['api_headers'] else {}
        return rows
    finally:
        conn.close()


def fetch_price(product):
    """调用 API 获取价格"""
    url = "%s?productSku=%s" % (product['api_url'], product['product_sku'])
    try:
        resp = requests.get(url, headers=product['api_headers'], timeout=8)
        data = resp.json()
        if data.get('resultCode') == 0 or data.get('success'):
            price = data.get('resultData', {}).get('datas', {}).get('price')
            return float(price) if price else None
        print("⚠️ API返回异常:", data)
    except Exception as e:
        print("❌ 获取价格异常[%s%s]: %s" % (product['bank_name'], product['product_name'], e))
    return None


def log_price(product_id, price, remark=''):
    ts = int(time.time())
    conn = get_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            "INSERT INTO gb_gold_price(product_id,price,remark,createtime) VALUES(%s,%s,%s,%s)",
            (product_id, price, remark, ts)
        )
        conn.commit()
    finally:
        conn.close()


def get_last_price(product_id):
    """获取最新一次记录的价格"""
    conn = get_conn()
    try:
        cur = conn.cursor()
        cur.execute("SELECT price FROM gb_gold_price WHERE product_id=%s ORDER BY id DESC LIMIT 1", (product_id,))
        r = cur.fetchone()
        return float(r[0]) if r else None
    finally:
        conn.close()


# ================== 基准价 ==================
def get_base_price(product_id):
    conn = get_conn()
    try:
        cur = conn.cursor()
        cur.execute("SELECT base_price FROM gb_gold_base WHERE product_id=%s", (product_id,))
        r = cur.fetchone()
        return float(r[0]) if r else None
    finally:
        conn.close()


def save_base_price(product_id, price):
    ts = int(time.time())
    conn = get_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            "INSERT INTO gb_gold_base(product_id,base_price,updatetime) VALUES(%s,%s,%s) "
            "ON DUPLICATE KEY UPDATE base_price=%s,updatetime=%s",
            (product_id, price, ts, price, ts)
        )
        conn.commit()
    finally:
        conn.close()


# ================== 当日极值 ==================
def get_today_extremes(product_id):
    conn = get_conn()
    try:
        cur = conn.cursor()
        today_start = int(time.mktime(datetime.now().date().timetuple()))
        cur.execute(
            "SELECT MIN(price),MAX(price) FROM gb_gold_price WHERE product_id=%s AND createtime>=%s",
            (product_id, today_start)
        )
        r = cur.fetchone()
        low = float(r[0]) if r and r[0] is not None else None
        high = float(r[1]) if r and r[1] is not None else None
        return low, high
    finally:
        conn.close()


# ================== 订阅用户 ==================
def get_subscribers(product_id):
    """获取订阅该产品且账号有效、未过期的用户"""
    conn = get_conn()
    try:
        cur = conn.cursor(pymysql.cursors.DictCursor)
        now = int(time.time())
        cur.execute(
            "SELECT u.id,u.username,u.nickname,u.email,u.alert_email,"
            "u.alert_threshold,u.highlow_step,u.base_alert,u.highlow_alert,"
            "u.dnd_start,u.dnd_end,u.expiretime "
            "FROM gb_user_product up JOIN gb_user u ON u.id=up.user_id "
            "WHERE up.product_id=%s AND up.status='1' AND u.status='normal' "
            "AND (u.expiretime IS NULL OR u.expiretime=0 OR u.expiretime>%s)",
            (product_id, now)
        )
        return cur.fetchall()
    finally:
        conn.close()


def is_dnd(user):
    """判断当前是否处于用户免打扰时段"""
    hour = datetime.now().hour
    start = int(user['dnd_start'])
    end = int(user['dnd_end'])
    if start == end:
        return False
    if start < end:
        return start <= hour < end
    # 跨天，如 21~9
    return hour >= start or hour < end


def get_alert_email(user):
    return user['alert_email'] if user['alert_email'] else user['email']


# ================== 提醒记录 ==================
def log_alert(user_id, product_id, atype, price, title, content, status):
    ts = int(time.time())
    conn = get_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            "INSERT INTO gb_alert_log(user_id,product_id,type,price,title,content,channel,status,createtime) "
            "VALUES(%s,%s,%s,%s,%s,%s,'email',%s,%s)",
            (user_id, product_id, atype, price, title, content, status, ts)
        )
        conn.commit()
    finally:
        conn.close()


def send_alert(user, product_id, atype, price, title, content):
    email = get_alert_email(user)
    name = user['nickname'] or user['username'] or str(user['id'])
    if not email:
        log_alert(user['id'], product_id, atype, price, title, content + "\n[无邮箱]", 'fail')
        print("⚠️ [%s] %s 无邮箱跳过" % (atype, name))
        return
    ok, err = send_email(email, title, content)
    if not ok:
        content += "\n[发送失败: %s]" % err
    log_alert(user['id'], product_id, atype, price, title, content, 'success' if ok else 'fail')
    print("%s [%s] %s <%s> %s" % ('✅' if ok else '❌', atype, name, email, title))


# ================== 单产品处理 ==================
def process_product(product):
    pid = product['id']
    bank = product['bank_name']
    pname = product['product_name']
    price = fetch_price(product)
    if price is None:
        return

    base = get_base_price(pid)
    if base is None:
        log_price(pid, price)
        save_base_price(pid, price)
        print("🚀 [%s%s] 初始化基准价: %.2f" % (bank, pname, price))
        return

    diff = price - base
    abs_diff = abs(diff)

    # 记录阈值：与上次记录价格变动小于阈值则不插入数据库
    record_threshold = float(product.get('record_threshold') or 0.5)
    last = get_last_price(pid)
    skip_log = (last is not None and abs(price - last) < record_threshold)

    remark = ''
    if abs_diff >= BASE_UPDATE_THRESHOLD:
        remark = "基准价变更: %.2f→%.2f(%+.2f)" % (base, price, diff)

    if skip_log:
        print("⏸️ [%s%s] %.2f 变动%.2f<阈值%.2f 跳过记录" % (bank, pname, price, abs(price - last), record_threshold))
    else:
        log_price(pid, price, remark)

    # 更新产品基准价
    if abs_diff >= BASE_UPDATE_THRESHOLD:
        save_base_price(pid, price)

    subscribers = get_subscribers(pid)
    today_low, today_high = get_today_extremes(pid)

    for u in subscribers:
        # 免打扰时段只记录不发
        if is_dnd(u):
            continue

        threshold = float(u['alert_threshold'])

        # 1) 基准价波动提醒（按用户独立阈值）
        if u['base_alert'] == 1 and abs_diff >= threshold:
            key = (u['id'], pid, 'base')
            last = _alert_memory.get(key)
            if last is None or abs(price - last) >= threshold:
                direction = '📈上涨' if diff > 0 else '📉下跌'
                title = "金价%s%.2f元: %.2f元/克" % (direction, abs_diff, price)
                content = (
                    "金价波动提醒\n\n"
                    "银行：%s%s\n"
                    "方向：%s\n"
                    "波动：%.2f元\n"
                    "当前价：%.2f元/克\n"
                    "基准价：%.2f元/克\n"
                    "阈值：%.2f元\n"
                    "时间：%s"
                ) % (bank, pname, direction, abs_diff, price, base, threshold,
                     datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
                send_alert(u, pid, 'base', price, title, content)
                _alert_memory[key] = price

        # 2) 当日新高/新低提醒（按用户独立步长）
        if u['highlow_alert'] == 1:
            step = float(u['highlow_step'])
            # 新低
            if today_low is not None and price <= today_low:
                lkey = (u['id'], pid, 'low')
                last_low = _alert_memory.get(lkey)
                if last_low is None or (last_low - price) >= step:
                    title = "金价新低: %.2f元/克 📉" % price
                    content = (
                        "金价当日新低提醒\n\n"
                        "银行：%s%s\n"
                        "当前价：%.2f元/克\n"
                        "前低：%.2f元/克\n"
                        "时间：%s"
                    ) % (bank, pname, price, last_low if last_low else today_low,
                         datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
                    send_alert(u, pid, 'newlow', price, title, content)
                    _alert_memory[lkey] = price
            # 新高
            if today_high is not None and price >= today_high:
                hkey = (u['id'], pid, 'high')
                last_high = _alert_memory.get(hkey)
                if last_high is None or (price - last_high) >= step:
                    title = "金价新高: %.2f元/克 📈" % price
                    content = (
                        "金价当日新高提醒\n\n"
                        "银行：%s%s\n"
                        "当前价：%.2f元/克\n"
                        "前高：%.2f元/克\n"
                        "时间：%s"
                    ) % (bank, pname, price, last_high if last_high else today_high,
                         datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
                    send_alert(u, pid, 'newhigh', price, title, content)
                    _alert_memory[hkey] = price

    print("📊 [%s%s] %.2f元/克 基准%.2f(%+.2f) 订阅%d人" % (
        bank, pname, price, base, diff, len(subscribers)))


# ================== 主循环 ==================
def main():
    print("=== 黄金价格监控(多用户)启动 %s ===" % datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
    while True:
        try:
            products = get_products()
            if not products:
                print("⚠️ 无启用的黄金产品")
            for p in products:
                process_product(p)
        except Exception as e:
            print("❌ 循环异常: %s" % e)
        time.sleep(INTERVAL)


if __name__ == '__main__':
    main()
