<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 站点公共配置接口
 */
class Site extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 获取充值二维码、联系方式等公共配置
     */
    public function index()
    {
        $config = Db::name('config')->where('group', 'gold')->column('value', 'name');
        $this->success('', [
            'recharge_qrcode' => isset($config['recharge_qrcode']) ? cdnurl($config['recharge_qrcode'], true) : '',
            'contact_wechat'  => $config['contact_wechat'] ?? '',
            'contact_phone'   => $config['contact_phone'] ?? '',
            'contact_qq'      => $config['contact_qq'] ?? '',
            // 开放注册开关：1=允许前台注册，0=关闭注册
            'open_register'        => isset($config['open_register']) ? intval($config['open_register']) : 0,
            // 注册是否需要手机短信验证码：1=需要，0=仅需用户名+密码
            'register_need_captcha' => isset($config['register_need_captcha']) ? intval($config['register_need_captcha']) : 0,
        ]);
    }
}
