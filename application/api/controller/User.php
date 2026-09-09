<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use fast\Random;
use think\Config;
use think\Validate;

/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
         //   $this->error(__('User center already closed'));
        }

    }

    /**
     * 会员中心
     */
    public function index()
    {
        $user = $this->auth->getUser();
        $userinfo = $this->auth->getUserinfo();
        $userinfo['alert_email'] = $user->alert_email;
        $userinfo['expiretime'] = $user->expiretime;
        $userinfo['expiretime_text'] = $user->expiretime ? date('Y-m-d', $user->expiretime) : '未开通';
        $userinfo['base_alert'] = $user->base_alert;
        $userinfo['highlow_alert'] = $user->highlow_alert;
        $userinfo['alert_threshold'] = $user->alert_threshold;
        $userinfo['highlow_step'] = $user->highlow_step;
        $userinfo['dnd_start'] = $user->dnd_start;
        $userinfo['dnd_end'] = $user->dnd_end;
        $this->success('', ['userinfo' => $userinfo]);
    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="account", type="string", required=true, description="账号")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 手机验证码登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function mobilelogin()
    {
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret) {
            Sms::flush($mobile, 'mobilelogin');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @ApiMethod (POST)
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="email", type="string", required=false, description="邮箱")
     * @ApiParams (name="mobile", type="string", required=false, description="手机号（register_need_captcha=1 时必填）")
     * @ApiParams (name="code", type="string", required=false, description="短信验证码（register_need_captcha=1 时必填）")
     */
    public function register()
    {
        // 开放注册开关：从 gb_config 读取，未配置默认关闭
        $goldConfig = \think\Db::name('config')->where('group', 'gold')->column('value', 'name');
        $openRegister = isset($goldConfig['open_register']) ? intval($goldConfig['open_register']) : 0;
        if (!$openRegister) {
            $this->error('开放注册已关闭，请联系管理员开通账号');
        }
        $needCaptcha = isset($goldConfig['register_need_captcha']) ? intval($goldConfig['register_need_captcha']) : 0;

        $username = $this->request->post('username');
        $password = $this->request->post('password');
        $email    = $this->request->post('email');
        $mobile   = $this->request->post('mobile');
        $code     = $this->request->post('code');

        if (!$username || !$password) {
            $this->error(__('Invalid parameters'));
        }
        // 用户名长度限制 3-32
        $usernameLen = strlen($username);
        if ($usernameLen < 3 || $usernameLen > 32) {
            $this->error('用户名长度需为 3-32 位');
        }
        // 密码长度限制 6-32
        if (strlen($password) < 6 || strlen($password) > 32) {
            $this->error('密码长度需为 6-32 位');
        }
        if ($email && !Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }

        // 仅当 register_need_captcha=1 时强制短信验证
        if ($needCaptcha) {
            if (!$mobile || !$code) {
                $this->error('请填写手机号和短信验证码');
            }
            if (!Sms::check($mobile, $code, 'register')) {
                $this->error(__('Captcha is incorrect'));
            }
        }

        $ret = $this->auth->register($username, $password, $email, $mobile, []);
        if ($ret) {
            // 注册成功后若使用过验证码，清理掉
            if ($needCaptcha && $mobile) {
                Sms::flush($mobile, 'register');
            }
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 修改会员个人信息
     *
     * @ApiMethod (POST)
     * @ApiParams (name="avatar", type="string", required=true, description="头像地址")
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="nickname", type="string", required=true, description="昵称")
     * @ApiParams (name="bio", type="string", required=true, description="个人简介")
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $nickname = $this->request->post('nickname');
        $email = $this->request->post('email');
        $alert_email = $this->request->post('alert_email');
        if ($nickname) {
            $user->nickname = $nickname;
        }
        if ($email !== null) {
            if ($email && !Validate::is($email, "email")) {
                $this->error('邮箱格式错误');
            }
            $user->email = $email;
        }
        if ($alert_email !== null) {
            if ($alert_email && !Validate::is($alert_email, "email")) {
                $this->error('提醒邮箱格式错误');
            }
            $user->alert_email = $alert_email;
        }
        $user->save();
        $this->success('修改成功');
    }

    /**
     * 修改邮箱
     *
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->post('captcha');
        if (!$email || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 第三方登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="platform", type="string", required=true, description="平台名称")
     * @ApiParams (name="code", type="string", required=true, description="Code码")
     */
    public function third()
    {
        $url = url('user/index');
        $platform = $this->request->post("platform");
        $code = $this->request->post("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }

    /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="newpassword", type="string", required=true, description="新密码")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function resetpwd()
    {
        $type = $this->request->post("type", "mobile");
        $mobile = $this->request->post("mobile");
        $email = $this->request->post("email");
        $newpassword = $this->request->post("newpassword");
        $captcha = $this->request->post("captcha");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        //验证Token
        if (!Validate::make()->check(['newpassword' => $newpassword], ['newpassword' => 'require|regex:\S{6,30}'])) {
            $this->error(__('Password must be 6 to 30 characters'));
        }
        if ($type == 'mobile') {
            if (!Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('Mobile is incorrect'));
            }
            $user = \app\common\model\User::getByMobile($mobile);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Sms::check($mobile, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Sms::flush($mobile, 'resetpwd');
        } else {
            if (!Validate::is($email, "email")) {
                $this->error(__('Email is incorrect'));
            }
            $user = \app\common\model\User::getByEmail($email);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Ems::check($email, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Ems::flush($email, 'resetpwd');
        }
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 修改密码
     * @ApiMethod (POST)
     * @ApiParams (name="oldpassword", type="string", required=true, description="旧密码")
     * @ApiParams (name="newpassword", type="string", required=true, description="新密码")
     */
    public function changepwd()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $oldpassword = $this->request->post('oldpassword');
        $newpassword = $this->request->post('newpassword');
        if (!$newpassword || !$oldpassword) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->changepwd($newpassword, $oldpassword);
        if ($ret) {
            $this->success('密码修改成功');
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 提醒设置
     * @ApiMethod (POST)
     */
    public function alertSetting()
    {
        $user = $this->auth->getUser();
        $user->base_alert = (int)$this->request->post('base_alert', 0);
        $user->highlow_alert = (int)$this->request->post('highlow_alert', 0);
        $user->alert_threshold = $this->request->post('alert_threshold', 5);
        $user->highlow_step = $this->request->post('highlow_step', 0.5);
        $user->dnd_start = (int)$this->request->post('dnd_start', 21);
        $user->dnd_end = (int)$this->request->post('dnd_end', 9);
        $user->save();
        $this->success('设置成功');
    }
}
