<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

/**
 * 服务器管理 - 心跳记录与远程清理
 *
 * @icon fa fa-server
 */
class Server extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\server\Record;
    }

    /**
     * 服务器列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = Db::name('server_record')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as &$row) {
                $arr = [];
                if (!empty($row['websites'])) {
                    $decoded = json_decode($row['websites'], true);
                    if (is_array($decoded)) {
                        $arr = $decoded;
                    } else {
                        // 兼容性：如果是被双重json_encode的字符串，再解一次
                        $decoded2 = json_decode($decoded, true);
                        if (is_array($decoded2)) {
                            $arr = $decoded2;
                        } elseif (is_string($row['websites'])) {
                            // 兜底：按逗号/空格切分
                            $arr = preg_split('/[,\s]+/', trim($row['websites'], " \t\n\r\0\x0B[]\"'"));
                            $arr = array_values(array_filter(array_map('trim', $arr)));
                        }
                    }
                }
                $row['websites_arr']  = $arr;
                $row['website_count'] = count($arr) ?: (int)$row['website_count'];
            }
            $total = Db::name('server_record')->where($where)->count();
            return json(['total' => $total, 'rows' => $list]);
        }
        return $this->view->fetch();
    }

    /**
     * 标记该服务器下次心跳时执行清理
     */
    public function markWipe()
    {
        $ids = $this->request->post('ids', '');
        if (!$ids) {
            $this->error('参数错误');
        }
        $idArr = is_array($ids) ? $ids : explode(',', $ids);
        Db::name('server_record')
            ->where('id', 'in', $idArr)
            ->update([
                'wipe_scheduled' => 1,
                'wipe_done'      => 0,
                'wipe_time'      => 0,
                'updatetime'     => time(),
            ]);
        $this->success('已标记清理，待下一次心跳后执行');
    }

    /**
     * 取消清理标记
     */
    public function cancelWipe()
    {
        $ids = $this->request->post('ids', '');
        if (!$ids) {
            $this->error('参数错误');
        }
        $idArr = is_array($ids) ? $ids : explode(',', $ids);
        Db::name('server_record')
            ->where('id', 'in', $idArr)
            ->update(['wipe_scheduled' => 0, 'updatetime' => time()]);
        $this->success('已取消清理标记');
    }

    /**
     * 删除记录
     */
    public function del($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error('无效请求');
        }
        $ids = $ids ?: $this->request->post('ids', '');
        if (!$ids) {
            $this->error('参数错误');
        }
        $idArr = is_array($ids) ? $ids : explode(',', $ids);
        Db::name('server_record')->where('id', 'in', $idArr)->delete();
        $this->success('已删除');
    }
}
