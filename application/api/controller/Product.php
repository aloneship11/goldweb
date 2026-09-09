<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 黄金产品与订阅接口
 */
class Product extends Api
{
    protected $noNeedLogin = ['lists'];
    protected $noNeedRight = '*';

    /**
     * 产品列表（公开）
     */
    public function lists()
    {
        $list = Db::name('gold_product')->where('status', '1')
            ->field('id,bank_name,product_name,product_sku,record_threshold')
            ->order('weigh,id')->select();
        $this->success('', ['list' => $list]);
    }

    /**
     * 我的订阅
     */
    public function mySubscriptions()
    {
        $uid = $this->auth->id;
        $list = Db::name('user_product')->alias('up')
            ->join('gold_product gp', 'gp.id=up.product_id')
            ->where('up.user_id', $uid)
            ->field('up.id,up.product_id,up.status,gp.bank_name,gp.product_name')
            ->order('up.id desc')->select();
        $this->success('', ['list' => $list]);
    }

    /**
     * 订阅产品
     * @ApiMethod (POST)
     */
    public function subscribe()
    {
        $uid = $this->auth->id;
        $product_id = (int)$this->request->post('product_id');
        if ($product_id <= 0) {
            $this->error('参数错误');
        }
        $exists = Db::name('user_product')->where(['user_id' => $uid, 'product_id' => $product_id])->find();
        if ($exists) {
            $this->error('已订阅该产品');
        }
        Db::name('user_product')->insert([
            'user_id'    => $uid,
            'product_id' => $product_id,
            'status'     => '1',
            'createtime' => time(),
            'updatetime' => time()
        ]);
        $this->success('订阅成功');
    }

    /**
     * 取消订阅
     * @ApiMethod (POST)
     */
    public function unsubscribe()
    {
        $uid = $this->auth->id;
        $id = (int)$this->request->post('id');
        if ($id <= 0) {
            $this->error('参数错误');
        }
        Db::name('user_product')->where(['id' => $id, 'user_id' => $uid])->delete();
        $this->success('取消订阅成功');
    }
}
