<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../config.php';

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';
    $format = $_GET['format'] ?? 'line';
    $fields = explode(',', $_GET['fields'] ?? 'card_key');
    $fields = array_filter($fields); // 移除空值
    
    if (empty($fields)) {
        $fields = ['card_key']; // 默认至少导出卡密
    }

    // 获取筛选参数
    $filter_product = isset($_GET['filter_product']) ? intval($_GET['filter_product']) : 0;
    $filter_status = isset($_GET['filter_status']) ? intval($_GET['filter_status']) : -1;
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
    $filter_date_start = isset($_GET['filter_date_start']) ? $_GET['filter_date_start'] : '';
    $filter_date_end = isset($_GET['filter_date_end']) ? $_GET['filter_date_end'] : '';

    // 构建WHERE条件
    $where_conditions = [];
    $params = [];
    
    if($filter_product > 0) {
        $where_conditions[] = "c.product_id = ?";
        $params[] = $filter_product;
    }
    
    if($filter_status >= 0) {
        $where_conditions[] = "c.status = ?";
        $params[] = $filter_status;
    }
    
    if(!empty($filter_type)) {
        $where_conditions[] = "c.card_type = ?";
        $params[] = $filter_type;
    }
    
    if(!empty($filter_date_start)) {
        $where_conditions[] = "DATE(c.create_time) >= ?";
        $params[] = $filter_date_start;
    }
    
    if(!empty($filter_date_end)) {
        $where_conditions[] = "DATE(c.create_time) <= ?";
        $params[] = $filter_date_end;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    if ($action === 'export_filtered_txt') {
        // 导出所有筛选结果
        $sql = "
            SELECT c.*, p.name as product_name 
            FROM cards c 
            LEFT JOIN products p ON c.product_id = p.id 
            $where_clause
            ORDER BY c.create_time DESC, c.id DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($action === 'export_custom_txt') {
        // 导出指定数量
        $count = intval($_GET['count'] ?? 100);
        $count = max(1, min($count, 10000)); // 限制在1-10000之间
        
        $sql = "
            SELECT c.*, p.name as product_name
            FROM cards c
            LEFT JOIN products p ON c.product_id = p.id
            $where_clause
            ORDER BY c.create_time DESC, c.id DESC
            LIMIT ?
        ";
        $stmt = $conn->prepare($sql);

        // 绑定筛选参数
        $param_index = 1;
        foreach($params as $param) {
            $stmt->bindValue($param_index++, $param);
        }

        // 绑定LIMIT参数为整数类型
        $stmt->bindValue($param_index, $count, PDO::PARAM_INT);

        $stmt->execute();
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        http_response_code(400);
        exit('Invalid action');
    }

    // 生成文件内容
    $content = '';
    
    if ($format === 'line') {
        // 每行一个卡密
        foreach ($cards as $card) {
            $content .= $card['card_key'] . "\n";
        }
    } else {
        // 详细信息格式
        $headers = [];
        foreach ($fields as $field) {
            $headers[] = getFieldName($field);
        }
        $content .= implode("\t", $headers) . "\n";
        
        foreach ($cards as $card) {
            $values = [];
            foreach ($fields as $field) {
                $value = getFieldValue($card, $field);
                $values[] = $value;
            }
            $content .= implode("\t", $values) . "\n";
        }
    }

    // 设置响应头
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="cards_export.txt"');
    header('Content-Length: ' . strlen($content));
    
    echo $content;

} catch(PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
} catch(Exception $e) {
    http_response_code(500);
    exit('Error: ' . $e->getMessage());
}

function getFieldName($field) {
    $fieldNames = [
        'card_key' => '卡密',
        'product_name' => '关联商品',
        'status' => '状态',
        'card_type' => '类型',
        'duration' => '有效期/次数',
        'device_id' => '绑定设备',
        'create_time' => '创建时间',
        'use_time' => '使用时间',
        'expire_time' => '到期时间'
    ];
    return $fieldNames[$field] ?? $field;
}

function getFieldValue($card, $field) {
    switch ($field) {
        case 'card_key':
            return $card['card_key'];
        case 'product_name':
            return $card['product_name'] ?: '默认商品';
        case 'status':
            switch ($card['status']) {
                case 0: return '未使用';
                case 1: return '已使用';
                case 2: return '已停用';
                default: return '未知';
            }
        case 'card_type':
            return $card['card_type'] === 'time' ? '时间卡密' : '次数卡密';
        case 'duration':
            if ($card['card_type'] === 'time') {
                return $card['duration'] > 0 ? $card['duration'] . '天' : '永久';
            } else {
                return $card['remaining_count'] . '/' . $card['total_count'] . '次';
            }
        case 'device_id':
            return $card['device_id'] ?: '未绑定';
        case 'create_time':
            return $card['create_time'];
        case 'use_time':
            return $card['use_time'] ?: '未使用';
        case 'expire_time':
            return $card['expire_time'] ?: '无';
        default:
            return $card[$field] ?? '';
    }
}
?>
