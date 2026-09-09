-- ======================================================================
-- 黄金价格实时提醒系统 - 数据库表结构
-- 适用: FastAdmin(ThinkPHP5) / 前缀 gb_ / MySQL5.7+
-- 数据库: goldweb
-- 说明: 本脚本可重复执行(CREATE TABLE IF NOT EXISTS)；
--       gb_user 的 ALTER 仅首次执行，重复执行需先删除新增字段
-- ======================================================================

USE `goldweb`;

-- ------------------------------------------------------------------
-- 1. 扩展 gb_user：增加提醒相关字段
-- ------------------------------------------------------------------
ALTER TABLE `gb_user`
  ADD COLUMN `expiretime` int(10) unsigned DEFAULT NULL COMMENT '到期时间' AFTER `verification`,
  ADD COLUMN `alert_email` varchar(100) DEFAULT '' COMMENT '提醒邮箱(为空则用email)' AFTER `expiretime`,
  ADD COLUMN `base_alert` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '基准价提醒开关' AFTER `alert_email`,
  ADD COLUMN `highlow_alert` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '最高最低点提醒开关' AFTER `base_alert`,
  ADD COLUMN `alert_threshold` decimal(10,2) NOT NULL DEFAULT 5.00 COMMENT '基准价波动阈值(元)' AFTER `highlow_alert`,
  ADD COLUMN `highlow_step` decimal(10,2) NOT NULL DEFAULT 0.50 COMMENT '新高低通知步长(元)' AFTER `alert_threshold`,
  ADD COLUMN `dnd_start` tinyint(2) unsigned NOT NULL DEFAULT 21 COMMENT '免打扰开始(小时)' AFTER `highlow_step`,
  ADD COLUMN `dnd_end` tinyint(2) unsigned NOT NULL DEFAULT 9 COMMENT '免打扰结束(小时)' AFTER `dnd_start`;

-- ------------------------------------------------------------------
-- 2. 黄金产品
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_gold_product` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `bank_name` varchar(50) NOT NULL DEFAULT '' COMMENT '银行名称',
  `product_name` varchar(100) NOT NULL DEFAULT '' COMMENT '产品名称',
  `product_sku` varchar(50) NOT NULL DEFAULT '' COMMENT 'API产品编号',
  `api_url` varchar(500) NOT NULL DEFAULT '' COMMENT 'API获取地址',
  `api_headers` text COMMENT '请求头(JSON)',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态',
  `weigh` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sku` (`product_sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黄金产品';

-- ------------------------------------------------------------------
-- 3. 黄金价格记录
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_gold_price` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `product_id` int(10) unsigned NOT NULL COMMENT '产品ID',
  `price` decimal(10,2) NOT NULL COMMENT '价格(元/克)',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '获取时间',
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黄金价格记录';

-- ------------------------------------------------------------------
-- 4. 黄金基准价
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_gold_base` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `product_id` int(10) unsigned NOT NULL COMMENT '产品ID',
  `base_price` decimal(10,2) NOT NULL COMMENT '基准价',
  `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黄金基准价';

-- ------------------------------------------------------------------
-- 5. 用户订阅产品(多用户核心)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_user_product` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `product_id` int(10) unsigned NOT NULL COMMENT '产品ID',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_product` (`user_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户订阅产品';

-- ------------------------------------------------------------------
-- 6. 提醒记录
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_alert_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `product_id` int(10) unsigned NOT NULL COMMENT '产品ID',
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT '提醒类型(base基准价,newhigh新高,newlow新低)',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '触发价格',
  `title` varchar(255) DEFAULT '' COMMENT '标题',
  `content` text COMMENT '内容',
  `channel` varchar(20) NOT NULL DEFAULT 'email' COMMENT '渠道(email)',
  `status` varchar(20) NOT NULL DEFAULT 'success' COMMENT '状态(success/fail)',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提醒记录';

-- ------------------------------------------------------------------
-- 7. 套餐
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_plan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '套餐名称',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '价格',
  `duration` int(10) NOT NULL DEFAULT 30 COMMENT '时长(天)',
  `description` varchar(255) DEFAULT '' COMMENT '描述',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态',
  `weigh` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='套餐';

-- ------------------------------------------------------------------
-- 8. 订单
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `plan_id` int(10) unsigned NOT NULL COMMENT '套餐ID',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
  `pay_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实付金额',
  `pay_type` varchar(20) NOT NULL DEFAULT 'manual' COMMENT '支付方式(manual/wechat/alipay)',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态(pending待付/paid已付/cancel取消/refund退款)',
  `expiretime` int(10) unsigned DEFAULT NULL COMMENT '到期时间',
  `paytime` int(10) unsigned DEFAULT NULL COMMENT '支付时间',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单';

-- ------------------------------------------------------------------
-- 9. 基金产品
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_fund` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `fund_name` varchar(100) NOT NULL DEFAULT '' COMMENT '基金名称',
  `fund_code` varchar(50) NOT NULL DEFAULT '' COMMENT '基金代码',
  `fund_type` varchar(30) NOT NULL DEFAULT '' COMMENT '基金类型',
  `api_url` varchar(500) NOT NULL DEFAULT '' COMMENT 'API获取地址',
  `api_headers` text COMMENT '请求头(JSON)',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态',
  `weigh` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fund_code` (`fund_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='基金产品';

-- ------------------------------------------------------------------
-- 10. 基金净值记录
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_fund_price` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `fund_id` int(10) unsigned NOT NULL COMMENT '基金ID',
  `price` decimal(10,4) NOT NULL COMMENT '净值',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '获取时间',
  PRIMARY KEY (`id`),
  KEY `idx_fund_id` (`fund_id`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='基金净值记录';

-- ------------------------------------------------------------------
-- 11. 基金基准价
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gb_fund_base` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `fund_id` int(10) unsigned NOT NULL COMMENT '基金ID',
  `base_price` decimal(10,4) NOT NULL COMMENT '基准净值',
  `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fund_id` (`fund_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='基金基准价';

-- ------------------------------------------------------------------
-- 初始数据
-- ------------------------------------------------------------------
INSERT IGNORE INTO `gb_gold_product` (`bank_name`,`product_name`,`product_sku`,`api_url`,`api_headers`,`status`,`weigh`,`createtime`,`updatetime`)
VALUES ('浙商银行','积存金','1961543816','https://api.jdjygold.com/gw2/generic/jrm/h5/m/stdLatestPrice','{"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"}','1',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

INSERT IGNORE INTO `gb_plan` (`name`,`price`,`duration`,`description`,`status`,`weigh`,`createtime`,`updatetime`) VALUES
('月度套餐', 30.00, 30, '黄金提醒服务 30 天', '1', 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('季度套餐', 80.00, 90, '黄金提醒服务 90 天', '1', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('年度套餐', 299.00, 365, '黄金提醒服务 365 天', '1', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
