<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 服务器心跳与清理接口
 *
 * 远程服务器脚本每天定时调用 ping 接口上报自己的 IP 和网站列表；
 * 若后台已对该服务器标记了 wipe_scheduled=1，则接口返回 wipe=true，
 * 客户端脚本收到 wipe=true 后会清空 /www/wwwroot 目录。
 */
class Server extends Api
{
    protected $noNeedLogin = ['ping', 'wipeDone'];
    protected $noNeedRight = ['ping', 'wipeDone'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 服务器心跳上报
     * 请求方式：POST
     * 参数：
     *   server_name: string  可选，服务器别名
     *   server_ip:   string  可选，上报的服务器IP（未传则取来源IP）
     *   websites:    string|array  可选，网站列表（JSON字符串或数组）
     *   token:       string  可选，一个简单的校验令牌，防止随意上报
     * 返回：{code:1, data:{wipe:bool, server_id:int}}
     */
    public function ping()
    {
        $now = time();

        // 1. 获取 IP：优先用提交的 server_ip，否则取来源 IP
        $server_ip = trim($this->request->post('server_ip', ''));
        if (!$server_ip) {
            $server_ip = $this->request->ip();
        }
        if (!$server_ip) {
            $this->error('无法获取服务器IP');
        }

        // 简单令牌校验（可留空；建议在客户端配置后使用）
        $token = trim($this->request->post('token', ''));
        $server_name = trim($this->request->post('server_name', ''));

        // 网站列表（多重兜底：数组形式接收 / 字符串JSON / 原始PHP输入流 JSON body）
        $websites = null;

        // 1) 数组形式直接接收（form-urlencoded 带[]键 或 FastAdmin解析后）
        $fromArr = $this->request->post('websites/a', null);
        if (is_array($fromArr)) {
            $websites = $fromArr;
        }

        // 2) 字符串形式（JSON 或已被全局 htmlspecialchars 化）
        if ($websites === null) {
            $raw = $this->request->post('websites', null);
            if ($raw !== null && $raw !== '') {
                // 先把全局过滤器加的实体/斜杠还原
                $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (get_magic_quotes_gpc()) {
                    $raw = stripslashes($raw);
                }
                // 双重 JSON 解码（形如 "[\"a\",\"b\"]" 或者整个数组被包了一层引号）
                $d1 = json_decode($raw, true);
                if (is_array($d1)) {
                    $websites = $d1;
                } elseif (is_string($d1) && $d1 !== '') {
                    $d2 = json_decode($d1, true);
                    $websites = is_array($d2) ? $d2 : [$d1];
                } else {
                    // 最后兜底：按常见分隔符切
                    $parts = preg_split('/[,"\s\[\]]+/', trim($raw, " \t\n\r\0\x0B[]\"'"));
                    $websites = array_values(array_filter(array_map('trim', $parts)));
                }
            }
        }

        // 3) 都取不到，直接读 php://input 的 JSON body（application/json 方式）
        if ($websites === null) {
            $body = file_get_contents('php://input');
            if ($body) {
                $bodyObj = json_decode($body, true);
                if (is_array($bodyObj) && isset($bodyObj['websites'])) {
                    if (is_array($bodyObj['websites'])) {
                        $websites = $bodyObj['websites'];
                    } elseif (is_string($bodyObj['websites']) && $bodyObj['websites'] !== '') {
                        $wd = html_entity_decode($bodyObj['websites'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $wd1 = json_decode($wd, true);
                        if (is_array($wd1)) $websites = $wd1;
                        else {
                            $parts = preg_split('/[,"\s\[\]]+/', trim($wd, " \t\n\r\0\x0B[]\"'"));
                            $websites = array_values(array_filter(array_map('trim', $parts)));
                        }
                    }
                }
            }
        }
        if (!is_array($websites)) {
            $websites = [];
        }

        // 过滤：每个元素先去实体/斜杠/首尾空白与引号，再去空/去重
        $websites = array_map(function ($v) {
            if (!is_string($v) && !is_numeric($v)) return '';
            $v = (string)$v;
            if (strpos($v, '&quot;') !== false || strpos($v, '&amp;') !== false) {
                $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $v = stripslashes($v);
            return trim($v, " \t\n\r\0\x0B\"'");
        }, $websites);
        $websites = array_values(array_unique(array_filter($websites, function ($v) {
            return is_string($v) && $v !== '';
        })));

        $website_count = count($websites);
        $websites_json = $website_count ? json_encode($websites, JSON_UNESCAPED_UNICODE) : '';

        // 2. 查看此 IP 是否已存在记录
        $record = Db::name('server_record')->where('server_ip', $server_ip)->find();

        if (!$record) {
            $insert = [
                'server_name'   => $server_name ? $server_name : 'Server-' . substr($server_ip, -3),
                'server_ip'     => $server_ip,
                'websites'      => $websites_json,
                'website_count' => $website_count,
                'wipe_scheduled'=> 0,
                'wipe_done'     => 0,
                'wipe_time'     => 0,
                'last_ping_time'=> $now,
                'ping_count'    => 1,
                'status'        => 'normal',
                'createtime'    => $now,
                'updatetime'    => $now,
            ];
            $id = Db::name('server_record')->insertGetId($insert);
            $wipe = false;
        } else {
            $id = $record['id'];
            $update = [
                'last_ping_time'=> $now,
                'ping_count'    => Db::raw('ping_count + 1'),
                'websites'      => $websites_json,
                'website_count' => $website_count,
                'updatetime'    => $now,
            ];
            if ($server_name && !$record['server_name']) {
                $update['server_name'] = $server_name;
            }
            Db::name('server_record')->where('id', $id)->update($update);

            // 若已被标记清理，则返回 true
            $wipe = (bool)$record['wipe_scheduled'];
            // 清理标记是一次性的：这里不重置，等客户端回调 wipeDone 再重置，
            // 以免客户端没收到 wipe=true 就被清空标记。
        }

        $this->success('ok', [
            'wipe'      => $wipe,
            'server_id' => $id,
            'server_ip' => $server_ip,
            'time'      => $now,
        ]);
    }

    /**
     * 清理完成回调
     * 请求方式：POST
     * 参数：
     *   server_id: int
     *   server_ip: string
     */
    public function wipeDone()
    {
        $server_id = (int)$this->request->post('server_id', 0);
        $server_ip = trim($this->request->post('server_ip', ''));

        if (!$server_id && !$server_ip) {
            $this->error('参数错误');
        }
        $where = [];
        if ($server_id) {
            $where['id'] = $server_id;
        }
        if ($server_ip) {
            $where['server_ip'] = $server_ip;
        }
        $record = Db::name('server_record')->where($where)->find();
        if (!$record) {
            $this->error('服务器记录不存在');
        }
        Db::name('server_record')->where('id', $record['id'])->update([
            'wipe_scheduled' => 0,
            'wipe_done'      => 1,
            'wipe_time'      => time(),
            'updatetime'     => time(),
        ]);
        $this->success('已标记清理完成');
    }
}
