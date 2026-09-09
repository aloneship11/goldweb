<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 价格走势图表
 */
class Goldchart extends Api
{
    protected $noNeedLogin = ['data'];
    protected $noNeedRight = ['data'];

    public function _initialize()
    {
        parent::_initialize();
    }

    public function data()
    {
        $product_id = (int)$this->request->request('product_id', 1);
        $start_date = $this->request->request('start_date', date('Y-m-d', strtotime('-7 days')));
        $end_date   = $this->request->request('end_date', date('Y-m-d'));

        $start_ts = strtotime($start_date . ' 00:00:00');
        $end_ts   = strtotime($end_date . ' 23:59:59');

        if (!$start_ts || !$end_ts || $start_ts > $end_ts) {
            $this->error('日期范围无效');
        }

        $days = ($end_ts - $start_ts) / 86400;

        if ($days <= 3) {
            $granularity = 'hour';
            $group_expr  = "FROM_UNIXTIME(createtime, '%m-%d %H:00')";
        } else {
            $granularity = 'day';
            $group_expr  = "FROM_UNIXTIME(createtime, '%Y-%m-%d')";
        }

        $rows = Db::name('gold_price')
            ->where('product_id', $product_id)
            ->where('createtime', 'between', [$start_ts, $end_ts])
            ->field($group_expr . ' as period, AVG(price) as avg_price, MIN(price) as min_price, MAX(price) as max_price')
            ->group('period')
            ->order('createtime', 'asc')
            ->select();

        $product = Db::name('gold_product')->where('id', $product_id)->find();

        $xAxis = [];
        $avgSeries = [];
        $minSeries = [];
        $maxSeries = [];

        foreach ($rows as $row) {
            $xAxis[] = $row['period'];
            $avgSeries[] = round($row['avg_price'], 2);
            $minSeries[] = round($row['min_price'], 2);
            $maxSeries[] = round($row['max_price'], 2);
        }

        $this->success('', [
            'xAxis'  => $xAxis,
            'avg'    => $avgSeries,
            'min'    => $minSeries,
            'max'    => $maxSeries,
            'product_name' => $product ? $product['bank_name'] . ' ' . $product['product_name'] : '',
            'granularity'  => $granularity,
        ]);
    }
}
