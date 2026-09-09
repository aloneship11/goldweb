<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 用户订阅产品
 *
 * @icon fa fa-circle-o
 */
class Userproduct extends Backend
{

    /**
     * Userproduct模型对象
     * @var \app\admin\model\user\Userproduct
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Userproduct;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看（关联用户与产品）
     */
    public function index()
    {
        $this->relationSearch = ['user' => 'nickname', 'product' => 'product_name'];
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['user', 'product'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = ["total" => $list->total(), "rows" => $list->items()];
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


}
