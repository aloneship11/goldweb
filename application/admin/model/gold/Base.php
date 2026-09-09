<?php

namespace app\admin\model\gold;

use think\Model;


class Base extends Model
{

    

    

    // 表名
    protected $name = 'gold_base';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







}
