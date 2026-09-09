<?php

namespace app\admin\model\alert;

use think\Model;


class Log extends Model
{

    

    

    // 表名
    protected $name = 'alert_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['success' => '成功', 'fail' => '失败'];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




}
