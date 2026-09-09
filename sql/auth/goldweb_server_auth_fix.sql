-- =============================================================================
-- 服务器管理菜单 + 权限自动安装脚本（推荐用这个）
-- 使用：宝塔左侧 → 数据库 → 点 goldweb 对应的「管理」→ SQL 标签 → 粘贴 → 执行
-- =============================================================================

-- 1. 找到父菜单：挂到「系统管理」(name='general')下面，找不到就挂到顶级
SET @parent_id := COALESCE(
    (SELECT id FROM (SELECT id FROM `gb_auth_rule` WHERE name='general' LIMIT 1) AS t1),
    (SELECT id FROM (SELECT id FROM `gb_auth_rule` WHERE title LIKE '%系统%' AND pid=0 LIMIT 1) AS t2),
    0
);
SELECT @parent_id AS '父菜单ID';

-- 2. 主菜单（服务器管理）——存在则更新，不存在则插入
INSERT INTO `gb_auth_rule`
    (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`menutype`,`extend`,`py`,`pinyin`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES
    ('file', @parent_id, 'server', '服务器管理', 'fa fa-server', '', '远程服务器心跳与清理记录', 1, NULL, '', 'fwqgl', 'fuwuqiguanli', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 120, 'normal')
ON DUPLICATE KEY UPDATE
    `pid`       = @parent_id,
    `title`     = '服务器管理',
    `icon`      = 'fa fa-server',
    `ismenu`    = 1,
    `status`    = 'normal',
    `updatetime`= UNIX_TIMESTAMP();

SET @server_id := (SELECT id FROM (SELECT id FROM `gb_auth_rule` WHERE name='server') AS x);
SELECT @server_id AS '服务器管理菜单ID';

-- 3. 4 个子权限（服务器列表/删除/标记清空/取消清空）
INSERT INTO `gb_auth_rule`
    (`type`,`pid`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`menutype`,`extend`,`py`,`pinyin`,`createtime`,`updatetime`,`weigh`,`status`)
VALUES
    ('file', @server_id, 'server/index',      '服务器列表', '', '', '', 1, NULL, '', 'fwqlb', 'fuwuqiliebiao',    UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 80, 'normal'),
    ('file', @server_id, 'server/del',        '删除记录',   '', '', '', 0, NULL, '', 'scjl',  'shanchujilu',      UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 70, 'normal'),
    ('file', @server_id, 'server/markWipe',   '标记清空',   '', '', '', 0, NULL, '', 'bjqq',  'biaojiqingkong',  UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 60, 'normal'),
    ('file', @server_id, 'server/cancelWipe', '取消清空',   '', '', '', 0, NULL, '', 'qxqq',  'quxiaoqingkong',  UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 50, 'normal')
ON DUPLICATE KEY UPDATE
    `pid`       = @server_id,
    `title`     = VALUES(`title`),
    `ismenu`    = VALUES(`ismenu`),
    `status`    = 'normal',
    `updatetime`= UNIX_TIMESTAMP();

-- 4. 把 server/* 所有权限，追加到超级管理员组（默认 group_id=1）的 rules 字段
--    若你的超级管理员组 ID 不是 1，先 SELECT id,title FROM gb_auth_group 看一下
SET @gid := 1;

-- 4a. 追加 server 主菜单ID
UPDATE `gb_auth_group` SET
    rules = CASE
        WHEN FIND_IN_SET(@server_id, rules) THEN rules
        WHEN rules IS NULL OR rules = '' THEN CAST(@server_id AS CHAR)
        ELSE CONCAT(rules, ',', @server_id)
    END
WHERE id = @gid;

-- 4b. 追加 4 个子权限ID
UPDATE `gb_auth_group` g
JOIN `gb_auth_rule` r ON r.name IN ('server/index','server/del','server/markWipe','server/cancelWipe')
SET g.rules = CASE
        WHEN FIND_IN_SET(r.id, g.rules) THEN g.rules
        WHEN g.rules IS NULL OR g.rules = '' THEN CAST(r.id AS CHAR)
        ELSE CONCAT(g.rules, ',', r.id)
    END
WHERE g.id = @gid;

-- 完成后在后台：系统管理 → 清除缓存 → 重新登录
SELECT '执行完毕，请在后台：系统管理 → 清除缓存 → 重新登录' AS '下一步';
