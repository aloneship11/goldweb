-- 服务器记录与清理控制表
CREATE TABLE IF NOT EXISTS `gb_server_record` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `server_name` varchar(100) NOT NULL DEFAULT '' COMMENT '服务器名称',
    `server_ip` varchar(50) NOT NULL DEFAULT '' COMMENT '服务器IP',
    `websites` text COMMENT '正在运行的网站(JSON)',
    `website_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '网站数量',
    `wipe_scheduled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已标记清理 1是0否',
    `wipe_done` tinyint(1) NOT NULL DEFAULT 0 COMMENT '清理是否已执行 1是0否',
    `wipe_time` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '清理执行时间',
    `last_ping_time` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '最后心跳时间',
    `ping_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '心跳次数',
    `status` enum('normal','hidden','reject') NOT NULL DEFAULT 'normal' COMMENT '状态:normal=正常,hidden=隐藏,reject=拒绝',
    `createtime` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
    `updatetime` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_server_ip` (`server_ip`),
    KEY `idx_wipe_scheduled` (`wipe_scheduled`),
    KEY `idx_last_ping_time` (`last_ping_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='服务器心跳与清理记录';
