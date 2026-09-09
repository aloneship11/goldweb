-- ======================================================================
-- 补全 gb_config 表中缺失的配置项（宝塔数据库 SQL 窗口执行）
-- 执行完这条，后台 系统管理 → 系统配置 → 基础配置 里就能看到下面这些开关了
-- ======================================================================

-- 1. 后台登录验证码开关（开关型，basic组）
INSERT INTO `gb_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES ('login_captcha', 'basic', '登录验证码', '开启后登录后台需输入图形验证码', 'switch', '0', '', '', '', '{}')
ON DUPLICATE KEY UPDATE
    `title`   = '登录验证码',
    `tip`     = '开启后登录后台需输入图形验证码',
    `type`    = 'switch',
    `value`   = IFNULL(`value`, '0');

-- 2. 同账号同一时间只能在一个地方登录
INSERT INTO `gb_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES ('login_unique', 'basic', '单点登录', '开启后同一账号同一时间只能在一个地方登录', 'switch', '0', '', '', '', '{}')
ON DUPLICATE KEY UPDATE
    `title` = '单点登录', `type`='switch', `value`=IFNULL(`value`, '0');

-- 3. 登录失败超过10次则1天后重试
INSERT INTO `gb_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES ('login_failure_retry', 'basic', '登录失败限制', '开启后登录失败超过10次则1天内禁止重试', 'switch', '1', '', '', '', '{}')
ON DUPLICATE KEY UPDATE
    `title` = '登录失败限制', `type`='switch', `value`=IFNULL(`value`, '1');

-- 完成后到后台：系统管理 → 清除缓存 → 再打开 系统配置 → 基础配置
SELECT '执行完成，刷新后台系统配置即可看到新增项' AS '提示';
