<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 消息通知接口
 */
class Notify extends Api
{
    protected $noNeedRight = '*';

    /**
     * 我的消息通知列表
     */
    public function lists()
    {
        $uid = $this->auth->id;
        $limit = (int)$this->request->get('limit', 20);
        $list = Db::name('alert_log')->where('user_id', $uid)
            ->field('id,type,price,title,content,status,createtime')
            ->order('id desc')->paginate($limit);
        $typeMap = ['base' => '基准价波动', 'newhigh' => '新高', 'newlow' => '新低'];
        $rows = $list->items();
        foreach ($rows as &$row) {
            $row['type_text'] = $typeMap[$row['type']] ?? $row['type'];
            $row['createtime_text'] = date('Y-m-d H:i', $row['createtime']);
        }
        unset($row);
        $this->success('', ['list' => $rows, 'total' => $list->total()]);
    }

    /**
     * 消息详情
     */
    public function detail()
    {
        $uid = $this->auth->id;
        $id = (int)$this->request->get('id');
        $row = Db::name('alert_log')->where(['id' => $id, 'user_id' => $uid])->find();
        if (!$row) {
            $this->error('消息不存在');
        }
        $typeMap = ['base' => '基准价波动', 'newhigh' => '新高', 'newlow' => '新低'];
        $row['type_text'] = $typeMap[$row['type']] ?? $row['type'];
        $row['createtime_text'] = date('Y-m-d H:i:s', $row['createtime']);
        $this->success('', ['detail' => $row]);
    }
}
