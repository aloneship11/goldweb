<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\library\Auth;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加会员（仅手机号+密码手动输入，其余按默认）
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post('row/a');
            $mobile = trim($data['mobile'] ?? '');
            $password = $data['password'] ?? '';
            if (!$mobile || !$password) {
                $this->error('手机号和密码必填');
            }
            if ($this->model->where('mobile', $mobile)->count() > 0) {
                $this->error('手机号已存在');
            }
            $salt = \fast\Random::alnum();
            $now = time();
            $this->model->save([
                'username'        => $mobile,
                'nickname'        => '用户' . substr($mobile, -4),
                'mobile'          => $mobile,
                'password'        => \app\common\library\Auth::instance()->getEncryptPassword($password, $salt),
                'salt'            => $salt,
                'status'          => 'normal',
                'jointime'        => $now,
                'joinip'          => $this->request->ip(),
                'base_alert'      => 1,
                'highlow_alert'   => 1,
                'alert_threshold' => 5,
                'highlow_step'    => 0.5,
                'dnd_start'       => 21,
                'dnd_end'         => 9,
            ]);
            $this->success('添加成功');
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }

    /**
     * 充值（输入天数延长有效期，并生成订单）
     */
    public function recharge($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $days = (int)$this->request->post('days');
            $amount = floatval($this->request->post('amount'));
            $remark = $this->request->post('remark');
            if ($days <= 0) {
                $this->error('天数必须大于0');
            }
            $now = time();
            $base = ($row['expiretime'] && $row['expiretime'] > $now) ? $row['expiretime'] : $now;
            $newExpire = $base + $days * 86400;
            $row->save(['expiretime' => $newExpire]);
            $order = new \app\admin\model\Order();
            $order->save([
                'order_no'   => 'CZ' . date('YmdHis') . mt_rand(1000, 9999),
                'user_id'    => $row['id'],
                'plan_id'    => 0,
                'amount'     => $amount,
                'pay_amount' => $amount,
                'pay_type'   => 'manual',
                'status'     => 'paid',
                'expiretime' => $newExpire,
                'paytime'    => $now,
                'remark'     => $remark ?: '管理员充值' . $days . '天'
            ]);
            $this->success('充值成功，新到期时间：' . date('Y-m-d H:i:s', $newExpire));
        }
        $this->view->assign('row', $row);
        $this->view->assign('expiretime_text', $row['expiretime'] ? date('Y-m-d H:i:s', $row['expiretime']) : '未设置');
        return $this->view->fetch();
    }

    /**
     * 订阅管理（查看/添加订阅）
     */
    public function subscribe($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $product_id = (int)$this->request->post('product_id');
            if ($product_id <= 0) {
                $this->error('请选择产品');
            }
            $exists = db('user_product')->where(['user_id' => $row['id'], 'product_id' => $product_id])->count();
            if ($exists) {
                $this->error('该产品已订阅');
            }
            db('user_product')->insert([
                'user_id'    => $row['id'],
                'product_id' => $product_id,
                'status'     => '1',
                'createtime' => time(),
                'updatetime' => time()
            ]);
            $this->success('订阅成功');
        }
        $subscribed = db('user_product')->alias('up')
            ->join('gold_product gp', 'gp.id=up.product_id')
            ->where('up.user_id', $row['id'])
            ->field('up.id,up.product_id,up.status,gp.bank_name,gp.product_name')
            ->select();
        $products = db('gold_product')->where('status', '1')->order('weigh,id')
            ->field('id,bank_name,product_name')->select();
        $this->view->assign('row', $row);
        $this->view->assign('subscribed', $subscribed);
        $this->view->assign('products', $products);
        return $this->view->fetch();
    }

    /**
     * 取消订阅
     */
    public function unsubscribe($ids = null)
    {
        $row = db('user_product')->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        db('user_product')->where('id', $ids)->delete();
        $this->success('取消订阅成功');
    }

}
