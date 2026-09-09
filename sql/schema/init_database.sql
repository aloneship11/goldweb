CREATE DATABASE IF NOT EXISTS `gold_monitor` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `gold_monitor`;

CREATE TABLE IF NOT EXISTS `gold_product` (
    `id` INT NOT NULL AUTO_INCREMENT COMMENT '主键',
    `bank_name` VARCHAR(50) NOT NULL COMMENT '银行名称',
    `product_name` VARCHAR(100) DEFAULT '' COMMENT '产品名称',
    `product_sku` VARCHAR(50) DEFAULT '' COMMENT 'API产品编号',
    `api_url` VARCHAR(500) NOT NULL COMMENT 'API获取地址',
    `api_headers` TEXT DEFAULT NULL COMMENT '请求头(JSON格式)',
    `status` TINYINT(1) DEFAULT 1 COMMENT '状态(1启用/0禁用)',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sku` (`product_sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黄金产品表';

CREATE TABLE IF NOT EXISTS `gold_price_log` (
    `id` INT NOT NULL AUTO_INCREMENT COMMENT '主键',
    `product_id` INT NOT NULL COMMENT '关联产品ID',
    `price` DECIMAL(10,2) NOT NULL COMMENT '价格(元/克)',
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注信息',
    `created_at` DATETIME NOT NULL COMMENT '获取时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_price_product` FOREIGN KEY (`product_id`) REFERENCES `gold_product`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='价格记录表';

CREATE TABLE IF NOT EXISTS `gold_base_price` (
    `id` INT NOT NULL AUTO_INCREMENT COMMENT '主键',
    `product_id` INT NOT NULL COMMENT '关联产品ID',
    `base_price` DECIMAL(10,2) NOT NULL COMMENT '当前基准价',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_product_id` (`product_id`),
    CONSTRAINT `fk_base_product` FOREIGN KEY (`product_id`) REFERENCES `gold_product`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='基准价表';

INSERT IGNORE INTO `gold_product` (`bank_name`, `product_name`, `product_sku`, `api_url`, `api_headers`) 
VALUES (
    '浙商银行', 
    '积存金', 
    '1961543816',
    'https://api.jdjygold.com/gw2/generic/jrm/h5/m/stdLatestPrice',
    '{"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"}'
);