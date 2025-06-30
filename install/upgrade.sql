-- 数据库升级脚本 - 添加商品管理功能
-- 适用于从旧版本升级到支持商品分类的新版本

-- 创建商品表
CREATE TABLE IF NOT EXISTS `products` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT '商品名称',
    `description` text DEFAULT NULL COMMENT '商品描述',
    `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:0禁用,1启用',
    `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序权重',
    PRIMARY KEY (`id`),
    KEY `status` (`status`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认商品（如果不存在）
INSERT IGNORE INTO `products` (`id`, `name`, `description`, `sort_order`) VALUES 
(1, '默认商品', '系统默认商品，用于兼容旧版本卡密', 1);

-- 检查并添加product_id字段到cards表
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cards'
    AND COLUMN_NAME = 'product_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `cards` ADD COLUMN `product_id` int(11) NOT NULL DEFAULT 1 COMMENT ''关联商品ID'' AFTER `encrypted_key`',
    'SELECT ''Column product_id already exists'' as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加索引（如果不存在）
SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cards'
    AND INDEX_NAME = 'product_id'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `cards` ADD KEY `product_id` (`product_id`)',
    'SELECT ''Index product_id already exists'' as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加外键约束（如果不存在）
SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cards'
    AND CONSTRAINT_NAME = 'cards_ibfk_1'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `cards` ADD FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT ''Foreign key constraint already exists'' as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加其他必要的索引
SET @status_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cards'
    AND INDEX_NAME = 'status'
);

SET @sql = IF(@status_index_exists = 0,
    'ALTER TABLE `cards` ADD KEY `status` (`status`)',
    'SELECT ''Index status already exists'' as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加create_time索引
SET @create_time_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cards'
    AND INDEX_NAME = 'create_time'
);

SET @sql = IF(@create_time_index_exists = 0,
    'ALTER TABLE `cards` ADD KEY `create_time` (`create_time`)',
    'SELECT ''Index create_time already exists'' as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 更新现有卡密的product_id为默认商品ID（如果为NULL或0）
UPDATE `cards` SET `product_id` = 1 WHERE `product_id` IS NULL OR `product_id` = 0;
