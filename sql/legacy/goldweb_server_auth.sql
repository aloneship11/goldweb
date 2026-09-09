-- 服务器管理 - 后台权限节点（可选执行）
-- 父级菜单 pid 请根据实际的后台"黄金管理"或"系统"目录ID调整
-- 先查一下 gb_auth_rule 里 plan 等记录的 pid，替换此处的 @parent_id
SET @parent_id = 0; -- TODO: 改成对应父级菜单ID（如"系统管理"或"黄金管理"的ID），若 0 则在顶级

-- 服务器管理 主菜单
INSERT INTO `gb_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @parent_id, 'server', '服务器管理', 'fa fa-server', '', '远程服务器心跳与清理记录', 1, NULL, '', 'fwqgl', 'fuwuqiguanli', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 120, 'normal');

SET @server_id = LAST_INSERT_ID();

-- 服务器管理子权限
INSERT INTO `gb_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @server_id, 'server/index',       '服务器列表', '', '', '', 1, NULL, '', 'fwqlb', 'fuwuqiliebiao', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 80, 'normal'),
('file', @server_id, 'server/del',         '删除记录',   '', '', '', 0, NULL, '', 'scjl',   'shanchujilu',   UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 70, 'normal'),
('file', @server_id, 'server/markWipe',    '标记清空',   '', '', '', 0, NULL, '', 'bjqq',   'biaojiqingkong', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 60, 'normal'),
('file', @server_id, 'server/cancelWipe',  '取消清空',   '', '', '', 0, NULL, '', 'qxqq',   'quxiaoqingkong', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 50, 'normal');
