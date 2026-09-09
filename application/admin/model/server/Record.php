<?php

namespace app\admin\model\server;

use think\Model;

class Record extends Model
{
    protected $name = 'server_record';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
