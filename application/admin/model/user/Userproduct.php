<?php

namespace app\admin\model\user;

use think\Model;


class Userproduct extends Model
{

    

    

    // 表名
    protected $name = 'user_product';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => '停用', '1' => '启用'];
    }

    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo('app\admin\model\gold\Product', 'product_id', 'id');
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




}
