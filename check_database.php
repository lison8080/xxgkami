<?php
/**
 * 数据库结构检查脚本
 * 检查新功能所需的数据库表和字段是否正确创建
 */

require_once 'config.php';

function checkDatabaseStructure() {
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "🔍 检查数据库结构...\n";
        echo str_repeat("=", 50) . "\n";
        
        $checks = [];
        
        // 检查products表是否存在
        echo "📋 检查products表...\n";
        $stmt = $conn->query("SHOW TABLES LIKE 'products'");
        if ($stmt->rowCount() > 0) {
            echo "✅ products表存在\n";
            $checks['products_table'] = true;
            
            // 检查products表结构
            $stmt = $conn->query("DESCRIBE products");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $required_columns = ['id', 'name', 'description', 'status', 'create_time', 'update_time', 'sort_order'];
            
            foreach ($required_columns as $column) {
                if (in_array($column, $columns)) {
                    echo "  ✅ 字段 {$column} 存在\n";
                } else {
                    echo "  ❌ 字段 {$column} 缺失\n";
                    $checks['products_table'] = false;
                }
            }
            
            // 检查默认商品是否存在
            $stmt = $conn->query("SELECT COUNT(*) FROM products WHERE id = 1");
            if ($stmt->fetchColumn() > 0) {
                echo "  ✅ 默认商品存在\n";
            } else {
                echo "  ⚠️ 默认商品不存在\n";
            }
        } else {
            echo "❌ products表不存在\n";
            $checks['products_table'] = false;
        }
        
        // 检查cards表的product_id字段
        echo "\n📋 检查cards表的product_id字段...\n";
        $stmt = $conn->query("DESCRIBE cards");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $product_id_exists = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'product_id') {
                $product_id_exists = true;
                echo "✅ product_id字段存在\n";
                echo "  类型: {$column['Type']}\n";
                echo "  默认值: {$column['Default']}\n";
                echo "  是否为空: {$column['Null']}\n";
                break;
            }
        }
        
        if (!$product_id_exists) {
            echo "❌ product_id字段不存在\n";
            $checks['product_id_field'] = false;
        } else {
            $checks['product_id_field'] = true;
        }
        
        // 检查外键约束
        echo "\n📋 检查外键约束...\n";
        $stmt = $conn->query("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'cards' 
            AND COLUMN_NAME = 'product_id' 
            AND REFERENCED_TABLE_NAME = 'products'
        ");
        
        if ($stmt->rowCount() > 0) {
            echo "✅ 外键约束存在\n";
            $checks['foreign_key'] = true;
        } else {
            echo "⚠️ 外键约束不存在（可选）\n";
            $checks['foreign_key'] = false;
        }
        
        // 检查索引
        echo "\n📋 检查索引...\n";
        $required_indexes = ['product_id', 'status', 'create_time'];
        $stmt = $conn->query("SHOW INDEX FROM cards");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existing_indexes = array_unique(array_column($indexes, 'Column_name'));
        
        foreach ($required_indexes as $index) {
            if (in_array($index, $existing_indexes)) {
                echo "  ✅ 索引 {$index} 存在\n";
            } else {
                echo "  ⚠️ 索引 {$index} 不存在（建议添加）\n";
            }
        }
        
        // 检查数据完整性
        echo "\n📋 检查数据完整性...\n";
        
        // 检查是否有卡密没有关联商品
        $stmt = $conn->query("SELECT COUNT(*) FROM cards WHERE product_id IS NULL OR product_id = 0");
        $null_product_count = $stmt->fetchColumn();
        if ($null_product_count > 0) {
            echo "⚠️ 有 {$null_product_count} 个卡密没有关联商品\n";
        } else {
            echo "✅ 所有卡密都已关联商品\n";
        }
        
        // 检查是否有卡密关联到不存在的商品
        $stmt = $conn->query("
            SELECT COUNT(*) 
            FROM cards c 
            LEFT JOIN products p ON c.product_id = p.id 
            WHERE c.product_id > 0 AND p.id IS NULL
        ");
        $invalid_product_count = $stmt->fetchColumn();
        if ($invalid_product_count > 0) {
            echo "❌ 有 {$invalid_product_count} 个卡密关联到不存在的商品\n";
            $checks['data_integrity'] = false;
        } else {
            echo "✅ 所有卡密都关联到有效商品\n";
            $checks['data_integrity'] = true;
        }
        
        // 统计信息
        echo "\n📊 统计信息...\n";
        $stmt = $conn->query("SELECT COUNT(*) FROM products");
        $product_count = $stmt->fetchColumn();
        echo "商品总数: {$product_count}\n";
        
        $stmt = $conn->query("SELECT COUNT(*) FROM cards");
        $card_count = $stmt->fetchColumn();
        echo "卡密总数: {$card_count}\n";
        
        $stmt = $conn->query("
            SELECT p.name, COUNT(c.id) as card_count 
            FROM products p 
            LEFT JOIN cards c ON p.id = c.product_id 
            GROUP BY p.id, p.name 
            ORDER BY card_count DESC
        ");
        $product_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n各商品卡密数量:\n";
        foreach ($product_stats as $stat) {
            echo "  {$stat['name']}: {$stat['card_count']} 个卡密\n";
        }
        
        // 总结
        echo "\n" . str_repeat("=", 50) . "\n";
        $passed = array_sum($checks);
        $total = count($checks);
        
        echo "📋 检查结果: {$passed}/{$total} 项通过\n";
        
        if ($passed === $total) {
            echo "🎉 数据库结构完整，所有新功能可正常使用！\n";
            return true;
        } elseif ($passed >= $total * 0.8) {
            echo "✅ 数据库结构基本完整，大部分功能可正常使用。\n";
            return true;
        } else {
            echo "⚠️ 数据库结构存在问题，建议运行升级脚本。\n";
            return false;
        }
        
    } catch (PDOException $e) {
        echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function suggestUpgrade() {
    echo "\n🔧 升级建议:\n";
    echo "1. 如果是新安装，请确保使用最新的install.sql文件\n";
    echo "2. 如果是从旧版本升级，请运行以下命令:\n";
    echo "   mysql -u用户名 -p数据库名 < install/upgrade.sql\n";
    echo "3. 或者在数据库管理工具中执行install/upgrade.sql文件\n";
    echo "4. 升级完成后重新运行此检查脚本\n";
}

// 主程序
echo "🚀 卡密系统数据库结构检查工具\n";
echo "检查新功能所需的数据库表和字段...\n\n";

$result = checkDatabaseStructure();

if (!$result) {
    suggestUpgrade();
}

echo "\n✨ 检查完成！\n";
?>
