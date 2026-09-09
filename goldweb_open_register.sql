-- ======================================================================
-- 开放注册开关 + 注册短信验证开关
-- 宝塔数据库 SQL 窗口执行一次即可
-- 执行后：后台 系统管理 → 系统配置 → 黄金 分组里可看到这两个开关
-- ======================================================================

-- 1. 是否开启开放注册（1=允许注册，0=关闭注册）
INSERT INTO `gb_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES ('open_register', 'gold', '开放注册', '开启后允许会员在前台自助注册账号，关闭后只能由管理员后台开通', 'switch', '0', '', '', '', '{}')
ON DUPLICATE KEY UPDATE
    `title` = '开放注册',
    `tip`   = '开启后允许会员在前台自助注册账号，关闭后只能由管理员后台开通',
    `type`  = 'switch',
    `value` = IFNULL(`value`, '0');

-- 2. 注册是否需要手机短信验证码（1=需要，0=不需要，可直接用户名+密码注册）
INSERT INTO `gb_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES ('register_need_captcha', 'gold', '注册短信验证', '开启后注册需手机号+短信验证码；关闭后仅需用户名+密码', 'switch', '0', '', '', '', '{}')
ON DUPLICATE KEY UPDATE
    `title` = '注册短信验证',
    `tip`   = '开启后注册需手机号+短信验证码；关闭后仅需用户名+密码',
    `type`  = 'switch',
    `value` = IFNULL(`value`, '0');

-- 完成后到后台：系统管理 → 清除缓存 → 再打开 系统配置 → 黄金
SELECT '执行完成，刷新后台系统配置即可看到新增项' AS '提示';
