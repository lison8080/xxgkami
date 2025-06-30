<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../admin.php");
    exit;
}

require_once '../config.php';

function generateKey($length = 20) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $characters[random_int(0, strlen($characters) - 1)];
    }
    // 返回原始卡密和加密后的卡密
    return [
        'original' => $key,
        'encrypted' => encryptCardKey($key)
    ];
}

function encryptCardKey($key) {
    // 使用固定的盐值进行加密，确保同样的卡密加密结果一致
    $salt = 'xiaoxiaoguai_card_system_2024';
    return sha1($key . $salt);
}

function decryptCardKey($encrypted_key) {
    // 由于SHA1是单向加密，这里返回null
    // 验证时需要将输入的卡密加密后与数据库中的加密值比较
    return null;
}

// Initialize variables outside the try block
$cards = [];
$per_page_options = [20, 50, 100, 200]; // Define per_page_options here
$error = null;
$success = null;
$total = 0;
$used = 0;
$unused = 0;
$usage_rate = 0;
$total_pages = 0;
$limit = 20; // Default limit
$page = 1; // Default page

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 获取商品列表
    $products_stmt = $conn->query("SELECT id, name FROM products WHERE status = 1 ORDER BY sort_order ASC, id ASC");
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取统计数据
    $total = $conn->query("SELECT COUNT(*) FROM cards")->fetchColumn();
    $used = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1")->fetchColumn();
    $unused = $total - $used;
    $usage_rate = $total > 0 ? round(($used / $total) * 100, 1) : 0;

    // 添加卡密 - 只在点击生成按钮时执行
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_card']) && isset($_POST['action']) && $_POST['action'] == 'add'){
        $count = intval($_POST['count'] ?? 1);
        $count = max($count, 1); // 确保至少生成1个，移除上限限制
        
        // 获取卡密类型
        $card_type = $_POST['card_type'] ?? 'time';
        
        // 处理时长或次数
        if($card_type == 'time') {
        $duration = $_POST['duration'];
        if($duration === 'custom') {
            $duration = intval($_POST['custom_duration']);
        } else {
            $duration = intval($duration);
            }
            $total_count = 0;
            $remaining_count = 0;
        } else { // 次数卡密
            $duration = 0; // 次数卡密无时长
            $total_count = $_POST['count_value'];
            if($total_count === 'custom') {
                $total_count = intval($_POST['custom_count']);
            } else {
                $total_count = intval($total_count);
            }
            $remaining_count = $total_count;
        }
        
        // 获取商品ID
        $product_id = intval($_POST['product_id'] ?? 1);

        // 验证商品是否存在且启用
        $product_check = $conn->prepare("SELECT COUNT(*) FROM products WHERE id = ? AND status = 1");
        $product_check->execute([$product_id]);
        if($product_check->fetchColumn() == 0) {
            $product_id = 1; // 如果商品不存在或被禁用，使用默认商品
        }

        // 获取是否允许重复验证的设置
        $allow_reverify = isset($_POST['allow_reverify']) && $_POST['allow_reverify'] == '1' ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO cards (card_key, encrypted_key, product_id, duration, allow_reverify, card_type, total_count, remaining_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        for($i = 0; $i < $count; $i++){
            do {
                $key = generateKey();
                $check = $conn->prepare("SELECT COUNT(*) FROM cards WHERE encrypted_key = ?");
                $check->execute([$key['encrypted']]);
            } while($check->fetchColumn() > 0);
            
            $stmt->execute([$key['original'], $key['encrypted'], $product_id, $duration, $allow_reverify, $card_type, $total_count, $remaining_count]);
        }
        
        $success = "成功生成 {$count} 个卡密";

        // 更新统计数据
        $total = $conn->query("SELECT COUNT(*) FROM cards")->fetchColumn();
        $used = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1")->fetchColumn();
        $unused = $total - $used;
        $usage_rate = $total > 0 ? round(($used / $total) * 100, 1) : 0;
    }

    // 删除卡密
    if(isset($_GET['delete'])){
        $stmt = $conn->prepare("DELETE FROM cards WHERE id = ? AND status = 0");
        $stmt->execute([$_GET['delete']]);
        if($stmt->rowCount() > 0){
            $success = "删除成功";
        } else {
            $error = "删除失败，卡密不存在或已被使用";
        }
    }

    // 获取卡密列表
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;  // 默认20条
    if (!in_array($limit, $per_page_options)) {
        $limit = 20;
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

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;

    // 获取总数
    $count_sql = "SELECT COUNT(*) FROM cards c LEFT JOIN products p ON c.product_id = p.id $where_clause";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    $total_pages = ceil($total / $limit);

    // 获取数据
    $data_sql = "
        SELECT c.*, p.name as product_name
        FROM cards c
        LEFT JOIN products p ON c.product_id = p.id
        $where_clause
        ORDER BY c.create_time DESC, c.id DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($data_sql);

    // 绑定筛选参数
    $param_index = 1;
    foreach($params as $param) {
        $stmt->bindValue($param_index++, $param);
    }

    // 绑定LIMIT和OFFSET参数为整数类型
    $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($param_index, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 在try块中添加批量删除处理
    if(isset($_POST['delete_cards']) && isset($_POST['card_ids'])) {
        $ids = array_map('intval', $_POST['card_ids']);
        if(!empty($ids)) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $conn->prepare("DELETE FROM cards WHERE id IN ($placeholders) AND status = 0");
            $stmt->execute($ids);
            $deleted = $stmt->rowCount();
            if($deleted > 0) {
                $success = "成功删除 {$deleted} 个卡密";
            } else {
                $error = "没有卡密被删除，可能卡密不存在或已被使用";
            }
        }
    }

    // 修改卡密有效期
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_expire_time'])) {
        $card_id = intval($_POST['card_id']);
        $expire_time = $_POST['expire_time'];
        
        $stmt = $conn->prepare("UPDATE cards SET expire_time = ? WHERE id = ?");
        $stmt->execute([$expire_time, $card_id]);
        
        $success = "卡密有效期修改成功";
    }

    // 修改次数卡密剩余次数
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_count'])) {
        $card_id = intval($_POST['card_id']);
        $remaining_count = intval($_POST['remaining_count']);
        
        // 确保剩余次数不小于0
        if($remaining_count < 0) {
            $remaining_count = 0;
        }
        
        $stmt = $conn->prepare("UPDATE cards SET remaining_count = ? WHERE id = ?");
        $stmt->execute([$remaining_count, $card_id]);
        
        $success = "卡密剩余次数修改成功";
    }
} catch(PDOException $e) {
    // $error = "系统错误，请稍后再试"; // Temporarily comment out the generic message
    // $error = "数据库错误: " . $e->getMessage(); // Comment out the previous change too
    die("捕获到数据库错误，请检查日志或联系管理员。详细信息： " . $e->getMessage()); // Directly output the error and stop
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>卡密管理 - 卡密验证系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <style>
        /* 后台通用样式 */
        body {
            margin: 0;
            padding: 0;
            background: #f5f6fa;
        }

        /* 侧边栏样式 */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            padding: 20px 0;
        }

        .sidebar .logo {
            text-align: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo h2 {
            margin: 0;
            font-size: 24px;
            color: #fff;
        }

        .sidebar .menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .sidebar .menu li {
            padding: 0;
            margin: 0;
        }

        .sidebar .menu li a {
            display: block;
            padding: 15px 20px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar .menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar .menu li:hover a,
        .sidebar .menu li.active a {
            background: rgba(255, 255, 255, 0.1);
        }

        /* 主内容区域样式 */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
            padding-bottom: 60px; /* 为底部版权留出空间 */
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f6fa;
            min-height: calc(100vh - 60px); /* 减去底部版权的高度 */
            position: relative;
        }

        /* 头部样式 */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        /* 卡片样式 */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .card-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
        }

        /* 版权信息样式 */
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-copyright {
            position: fixed;
            bottom: 0;
            left: 250px; /* 与侧边栏宽度相同 */
            right: 0;
            background: #fff;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #eee;
            height: 60px;
            box-sizing: border-box;
            z-index: 1000;
        }

        /* 卡密管理特定样式 */
        .generate-form {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .card-key-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-key {
            background: transparent;
            border: none;
            padding: 5px;
            font-family: monospace;
            color: #2c3e50;
            width: 180px;
            cursor: pointer;
        }

        .copy-btn {
            padding: 5px 8px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .status-badge.used {
            background: #e74c3c;
            color: white;
        }

        .status-badge.unused {
            background: #2ecc71;
            color: white;
        }

        .duration-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            background: #3498db;
            color: white;
        }

        .duration-badge.permanent {
            background: #2ecc71;
        }

        .product-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            background: #9b59b6;
            color: white;
        }

        .filter-section {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
            flex: 1;
        }

        .filter-group label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .filter-group .form-control {
            padding: 8px 12px;
            font-size: 14px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .admin-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 10px;
            }

            .filter-row {
                flex-direction: column;
                gap: 10px;
            }

            .filter-group {
                min-width: auto;
            }

            .export-controls {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }

            .table-responsive {
                font-size: 12px;
            }

            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
        }

        /* 改进的按钮样式 */
        .btn {
            transition: all 0.3s ease;
            border: none;
            font-weight: 500;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* 改进的表格样式 */
        .table-responsive table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }



        .table-responsive td {
            text-align: center;
            vertical-align: middle;
        }

        .table-responsive tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .table-responsive tr:hover {
            background-color: #e3f2fd;
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        /* 改进的徽章样式 */
        .status-badge, .product-badge, .duration-badge {
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* 改进的表单样式 */
        .form-control {
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        /* 改进的卡片样式 */
        .card {
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        /* 加载动画 */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 改进的分页样式 */
        .pagination a {
            transition: all 0.3s ease;
            border-radius: 6px;
            margin: 0 2px;
        }

        .pagination a:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-1px);
        }

        .pagination a.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* 卡密管理页面补充样式 */
        .export-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .export-controls input[type="text"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: white;
        }

        .btn-primary {
            background: #3498db;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .select-all-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 15px;
        }

        .table-responsive table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-responsive th,
        .table-responsive td {
            padding: 12px;
            border: 1px solid #eee;
            text-align: left;
        }

        .table-responsive th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            text-align: center;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
        }

        .per-page-select {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .per-page-option {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #666;
            text-decoration: none;
        }

        .per-page-option.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pagination a {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #666;
            text-decoration: none;
        }

        .pagination a.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination-ellipsis {
            color: #666;
            padding: 0 5px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* 自定义时长输入框样式 */
        .custom-duration {
            margin-top: 15px;
        }

        /* 表格内的复选框样式 */
        .card-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* 修改统计卡片的样式 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card i {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            margin: 0;
            color: #666;
            font-size: 16px;
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }

        /* 确保内容不会被底部版权遮挡 */
        .card:last-child {
            margin-bottom: 0;
        }
        
        /* 在小屏幕上调整版权样式 */
        @media (max-width: 768px) {
            .footer-copyright {
                left: 0;
            }
        }

        /* 设备ID样式 */
        .device-id {
            display: inline-block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            color: #666;
            cursor: help;
        }

        /* 当鼠标悬停时显示完整设备ID */
        .device-id:hover {
            position: relative;
        }

        .device-id:hover::after {
            content: attr(title);
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 100%;
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 5px;
            min-width: 300px;
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .status-badge.disabled {
            background: #e74c3c;
            color: white;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        /* 切换按钮样式 */
        .toggle-btn {
            cursor: pointer;
            font-size: 22px;
            transition: color 0.3s;
        }
        .toggle-btn.on {
            color: #2ecc71; /* 开启状态颜色 */
        }
        .toggle-btn.off {
            color: #ccc; /* 关闭状态颜色 */
        }
        .toggle-btn:hover {
            opacity: 0.8;
        }

        /* 添加卡密表单样式 */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        #custom-duration-group {
            display: none; /* 默认隐藏 */
        }
        .form-group .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .form-group input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* 新增类型和剩余次数样式 */
        .type-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            background: #3498db;
            color: white;
        }

        .count-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            background: #2ecc71;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="sidebar">
            <div class="logo">
                <h2>管理系统</h2>
            </div>
            <ul class="menu">
                <li><a href="products.php"><i class="fas fa-box"></i>商品管理</a></li>
                <li class="active"><a href="index.php"><i class="fas fa-key"></i>卡密管理</a></li>
                <li><a href="stats.php"><i class="fas fa-chart-line"></i>数据统计</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i>系统设置</a></li>
                <li><a href="api_settings.php"><i class="fas fa-code"></i>API接口</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i>退出登录</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h2><i class="fas fa-key"></i> 卡密管理</h2>
                <div class="user-info">
                    <img src="../assets/images/avatar.png" alt="avatar">
                    <span>欢迎，<?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                </div>
            </div>
            
            <?php 
            if(isset($success)) echo "<div class='alert alert-success'>$success</div>";
            if(isset($error)) echo "<div class='alert alert-error'>$error</div>";
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-key fa-2x" style="color: #3498db; margin-bottom: 10px;"></i>
                    <h3>总卡密数</h3>
                    <div class="value"><?php echo $total; ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle fa-2x" style="color: #2ecc71; margin-bottom: 10px;"></i>
                    <h3>已使用</h3>
                    <div class="value"><?php echo $used; ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock fa-2x" style="color: #f1c40f; margin-bottom: 10px;"></i>
                    <h3>未使用</h3>
                    <div class="value"><?php echo $unused; ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-percentage fa-2x" style="color: #e74c3c; margin-bottom: 10px;"></i>
                    <h3>使用率</h3>
                    <div class="value"><?php echo $usage_rate; ?>%</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> 生成卡密</h3>
                </div>
                <form method="POST" class="form-group" style="padding: 20px;">
                    <input type="hidden" name="action" value="add">
                    <div class="generate-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-list-ol"></i> 生成数量：</label>
                                <input type="number" name="count" min="1" value="1" class="form-control">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-credit-card"></i> 卡密类型：</label>
                                <select name="card_type" id="card_type" class="form-control">
                                    <option value="time">时间卡密</option>
                                    <option value="count">次数卡密</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-box"></i> 关联商品：</label>
                                <select name="product_id" class="form-control">
                                    <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group time-duration">
                                <label><i class="fas fa-clock"></i> 使用时长：</label>
                                <select name="duration" class="form-control">
                                    <option value="0">永久</option>
                                    <option value="1">1天</option>
                                    <option value="7">7天</option>
                                    <option value="30">30天</option>
                                    <option value="90">90天</option>
                                    <option value="180">180天</option>
                                    <option value="365">365天</option>
                                    <option value="custom">自定义</option>
                                </select>
                            </div>
                            <div class="form-group custom-duration" style="display: none;">
                                <label><i class="fas fa-edit"></i> 自定义天数：</label>
                                <input type="number" name="custom_duration" min="1" class="form-control">
                            </div>
                            <div class="form-group count-value" style="display: none;">
                                <label><i class="fas fa-sort-numeric-up"></i> 使用次数：</label>
                                <select name="count_value" class="form-control">
                                    <option value="1">1次</option>
                                    <option value="5">5次</option>
                                    <option value="10">10次</option>
                                    <option value="20">20次</option>
                                    <option value="50">50次</option>
                                    <option value="100">100次</option>
                                    <option value="custom">自定义</option>
                                </select>
                            </div>
                            <div class="form-group custom-count" style="display: none;">
                                <label><i class="fas fa-edit"></i> 自定义次数：</label>
                                <input type="number" name="custom_count" min="1" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="allow_reverify" class="checkbox-label">
                                    <input type="checkbox" id="allow_reverify" name="allow_reverify" value="1" checked>
                                    允许同一设备重复验证
                                </label>
                            </div>
                        </div>
                        <button type="submit" name="generate_card" class="btn btn-primary">
                            <i class="fas fa-plus"></i> 生成卡密
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>卡密列表</h3>
                    <div class="export-controls">
                        <input type="text" id="exportFileName" placeholder="文件名称" value="卡密列表">
                        <button type="button" class="btn btn-primary" onclick="exportSelected()">
                            <i class="fas fa-file-excel"></i> 导出Excel
                        </button>
                        <button type="button" class="btn btn-success" onclick="showTxtExportModal()">
                            <i class="fas fa-file-alt"></i> 导出TXT
                        </button>
                        <button type="button" class="btn btn-danger" onclick="deleteSelected()">
                            <i class="fas fa-trash"></i> 批量删除
                        </button>
                        <label class="select-all-container">
                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                            <span>全选</span>
                        </label>
                    </div>
                </div>

                <!-- 筛选区域 -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label><i class="fas fa-box"></i> 商品筛选：</label>
                                <select name="filter_product" class="form-control">
                                    <option value="0">全部商品</option>
                                    <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" <?php echo $filter_product == $product['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label><i class="fas fa-info-circle"></i> 状态筛选：</label>
                                <select name="filter_status" class="form-control">
                                    <option value="-1">全部状态</option>
                                    <option value="0" <?php echo $filter_status == 0 ? 'selected' : ''; ?>>未使用</option>
                                    <option value="1" <?php echo $filter_status == 1 ? 'selected' : ''; ?>>已使用</option>
                                    <option value="2" <?php echo $filter_status == 2 ? 'selected' : ''; ?>>已停用</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label><i class="fas fa-credit-card"></i> 类型筛选：</label>
                                <select name="filter_type" class="form-control">
                                    <option value="">全部类型</option>
                                    <option value="time" <?php echo $filter_type == 'time' ? 'selected' : ''; ?>>时间卡密</option>
                                    <option value="count" <?php echo $filter_type == 'count' ? 'selected' : ''; ?>>次数卡密</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label><i class="fas fa-calendar"></i> 创建时间：</label>
                                <input type="date" name="filter_date_start" class="form-control" value="<?php echo htmlspecialchars($filter_date_start); ?>" placeholder="开始日期">
                            </div>
                            <div class="filter-group">
                                <label><i class="fas fa-calendar"></i> 至：</label>
                                <input type="date" name="filter_date_end" class="form-control" value="<?php echo htmlspecialchars($filter_date_end); ?>" placeholder="结束日期">
                            </div>
                            <div class="filter-group">
                                <label>&nbsp;</label>
                                <div class="filter-buttons">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> 筛选
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> 清除
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="20">
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                </th>
                                <th>ID</th>
                                <th>卡密</th>
                                <th>关联商品</th>
                                <th>状态</th>
                                <th>类型</th>
                                <th>有效期/剩余次数</th>
                                <th>使用时间</th>
                                <th>到期时间</th>
                                <th>创建时间</th>
                                <th>设备ID</th>
                                <th>允许重复验证</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cards as $card): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="card-checkbox" value="<?php echo $card['card_key']; ?>">
                                </td>
                                <td><?php echo $card['id']; ?></td>
                                <td>
                                    <input type="text" value="<?php echo $card['card_key']; ?>" readonly style="width: 180px;">
                                    <button type="button" class="copy-btn" onclick="copyCardKey(this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <?php if(isset($_SESSION['show_encrypted']) && $_SESSION['show_encrypted']): ?>
                                    <div class="encrypted-key">
                                        加密值: <?php echo $card['encrypted_key']; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="product-badge">
                                        <?php echo htmlspecialchars($card['product_name'] ?: '默认商品'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php
                                        echo $card['status'] == 0 ? 'unused' :
                                            ($card['status'] == 1 ? 'used' : 'disabled');
                                    ?>">
                                        <?php
                                        echo $card['status'] == 0 ? '未使用' :
                                            ($card['status'] == 1 ? '已使用' : '已停用');
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="type-badge <?php echo $card['card_type']; ?>">
                                        <?php echo $card['card_type'] == 'time' ? '时间卡密' : '次数卡密'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($card['card_type'] == 'time'): ?>
                                    <span class="duration-badge">
                                        <?php echo $card['duration'] > 0 ? "{$card['duration']}天" : '永久'; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="count-badge">
                                        <?php echo $card['remaining_count'] . '/' . $card['total_count']; ?>次
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $card['use_time'] ?: '-'; ?></td>
                                <td><?php echo $card['expire_time'] ?: '-'; ?></td>
                                <td><?php echo $card['create_time']; ?></td>
                                <td>
                                    <?php if($card['status'] && $card['device_id']): ?>
                                        <span class="device-id" title="<?php echo htmlspecialchars($card['device_id']); ?>">
                                            <?php 
                                            // 显示设备ID的前6位和后4位，中间用...代替
                                            $device_id = $card['device_id'];
                                            echo strlen($device_id) > 10 ? 
                                                substr($device_id, 0, 6) . '...' . substr($device_id, -4) : 
                                                $device_id;
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas <?php echo $card['allow_reverify'] ? 'fa-toggle-on toggle-btn on' : 'fa-toggle-off toggle-btn off'; ?>"
                                       onclick="toggleReverify(<?php echo $card['id']; ?>, <?php echo $card['allow_reverify'] ? 0 : 1; ?>, this)"></i>
                                </td>
                                <td>
                                    <?php if($card['status'] == 0): // 未使用 ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteUnusedCard(<?php echo $card['id']; ?>)" title="删除未使用卡密">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: // 已使用或已停用 ?>
                                        <?php if($card['status'] == 1): // 正常状态显示停用按钮 ?>
                                            <button type="button" class="btn btn-warning btn-sm" onclick="toggleCardStatus(<?php echo $card['id']; ?>, 'disable')" title="停用卡密">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php elseif($card['status'] == 2): // 停用状态显示启用按钮 ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="toggleCardStatus(<?php echo $card['id']; ?>, 'enable')" title="启用卡密">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-info btn-sm" onclick="promptExpireTime(<?php echo $card['id']; ?>, '<?php echo $card['expire_time']; ?>')" title="修改时间">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <?php if($card['card_type'] == 'count'): // 如果是次数卡密，显示修改次数按钮 ?>
                                        <button type="button" class="btn btn-info btn-sm" onclick="promptRemainingCount(<?php echo $card['id']; ?>, <?php echo $card['remaining_count']; ?>)" title="修改次数">
                                            <i class="fas fa-sort-numeric-up"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if(!empty($card['device_id'])): // 如果已绑定设备，显示解绑按钮 ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="unbindDevice(<?php echo $card['id']; ?>)" title="解绑设备">
                                            <i class="fas fa-unlink"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteUsedCard(<?php echo $card['id']; ?>)" title="删除卡密">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($total_pages > 1 || count($per_page_options) > 1): ?>
                <div class="pagination-container">
                    <!-- 每页显示数量选择 -->
                    <div class="per-page-select">
                        <span>每页显示：</span>
                        <?php foreach($per_page_options as $option): ?>
                        <a href="?limit=<?php echo $option; ?><?php echo $filter_query; ?>"
                           class="per-page-option <?php echo $limit == $option ? 'active' : ''; ?>">
                            <?php echo $option; ?>条
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- 分页链接 -->
                    <?php
                    // 构建筛选参数字符串
                    $filter_params = [];
                    if($filter_product > 0) $filter_params[] = "filter_product=$filter_product";
                    if($filter_status >= 0) $filter_params[] = "filter_status=$filter_status";
                    if(!empty($filter_type)) $filter_params[] = "filter_type=$filter_type";
                    if(!empty($filter_date_start)) $filter_params[] = "filter_date_start=$filter_date_start";
                    if(!empty($filter_date_end)) $filter_params[] = "filter_date_end=$filter_date_end";
                    $filter_query = !empty($filter_params) ? '&' . implode('&', $filter_params) : '';
                    ?>
                    <?php if($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                            <a href="?page=1&limit=<?php echo $limit; ?><?php echo $filter_query; ?>" title="首页">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo ($page-1); ?>&limit=<?php echo $limit; ?><?php echo $filter_query; ?>" title="上一页">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if($start > 1) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        
                        for($i = $start; $i <= $end; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?><?php echo $filter_query; ?>"
                               <?php if($i == $page) echo 'class="active"'; ?>>
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; 
                        
                        if($end < $total_pages) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        ?>

                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page+1); ?>&limit=<?php echo $limit; ?><?php echo $filter_query; ?>" title="下一页">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?>&limit=<?php echo $limit; ?><?php echo $filter_query; ?>" title="末页">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> 小小怪卡密系统 - All Rights Reserved
        </div>
    </footer>

    <!-- 添加模态框 -->
    <div id="editExpireTimeModal" class="modal">
        <div class="modal-content">
            <h3>修改到期时间</h3>
            <form id="editExpireTimeForm">
                <input type="hidden" name="card_id" id="editCardId">
                <div class="form-group">
                    <label>到期时间：</label>
                    <input type="datetime-local" name="expire_time" id="editExpireTime" required>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 修改次数模态框 -->
    <div id="editCountModal" class="modal">
        <div class="modal-content">
            <h3>修改剩余次数</h3>
            <form id="editCountForm">
                <input type="hidden" name="card_id" id="editCountCardId">
                <div class="form-group">
                    <label>剩余次数：</label>
                    <input type="number" name="remaining_count" id="editRemainingCount" min="0" required>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeCountModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 现有时长选择逻辑
            const durationSelect = document.querySelector('select[name="duration"]');
            const customDurationField = document.querySelector('.custom-duration');
            
            if(durationSelect) {
                durationSelect.addEventListener('change', function() {
                    if(this.value === 'custom') {
                        customDurationField.style.display = 'block';
                    } else {
                        customDurationField.style.display = 'none';
                    }
                });
            }
            
            // 次数选择逻辑
            const countSelect = document.querySelector('select[name="count_value"]');
            const customCountField = document.querySelector('.custom-count');
            
            if(countSelect) {
                countSelect.addEventListener('change', function() {
                    if(this.value === 'custom') {
                        customCountField.style.display = 'block';
                    } else {
                        customCountField.style.display = 'none';
                    }
                });
            }
            
            // 卡密类型切换逻辑
            const cardTypeSelect = document.getElementById('card_type');
            const timeDurationField = document.querySelector('.time-duration');
            const countValueField = document.querySelector('.count-value');
            
            if(cardTypeSelect) {
                cardTypeSelect.addEventListener('change', function() {
                    if(this.value === 'time') {
                        timeDurationField.style.display = 'block';
                        countValueField.style.display = 'none';
                        customCountField.style.display = 'none';
                    } else {
                        timeDurationField.style.display = 'none';
                        customDurationField.style.display = 'none';
                        countValueField.style.display = 'block';
                    }
                });
            }
            
            // 其他现有的JavaScript代码...
        });
        
        document.querySelector('select[name="duration"]').addEventListener('change', function() {
            const customDuration = document.querySelector('.custom-duration');
            if(this.value === 'custom') {
                customDuration.style.display = 'block';
            } else {
                customDuration.style.display = 'none';
            }
        });

        function copyCardKey(btn) {
            const input = btn.previousElementSibling;
            input.select();
            document.execCommand('copy');
            
            // 更新按钮状态
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> 已复制';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        }

        function deleteCard(id) {
            if(confirm('确定要删除这个卡密吗？')) {
                window.location.href = '?delete=' + id;
            }
        }

        // 全选功能
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.card-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // 监听单个复选框的变化
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('card-checkbox')) {
                const selectAll = document.getElementById('selectAll');
                const checkboxes = document.querySelectorAll('.card-checkbox');
                const checkedBoxes = document.querySelectorAll('.card-checkbox:checked');
                selectAll.checked = checkboxes.length === checkedBoxes.length;
            }
        });

        // 导出为Excel函数
        function exportSelected() {
            const checkboxes = document.querySelectorAll('.card-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('请至少选择一个卡密');
                return;
            }

            try {
                // 收集选中的卡密信息
                const selectedCards = Array.from(checkboxes).map(checkbox => {
                    const row = checkbox.closest('tr');
                    return {
                        'ID': row.cells[1].textContent,
                        '卡密': checkbox.value,
                        '关联商品': row.querySelector('.product-badge').textContent.trim(),
                        '状态': row.querySelector('.status-badge').textContent.trim(),
                        '有效期': row.querySelector('.duration-badge').textContent.trim(),
                        '使用时间': row.cells[6].textContent.trim(),
                        '到期时间': row.cells[7].textContent.trim(),
                        '创建时间': row.cells[8].textContent.trim(),
                        '设备ID': row.cells[9].textContent.trim()
                    };
                });
                
                // 获取文件名
                let fileName = document.getElementById('exportFileName').value.trim() || '卡密列表';
                if (!fileName.toLowerCase().endsWith('.xlsx')) {
                    fileName += '.xlsx';
                }

                // 创建工作簿
                const wb = XLSX.utils.book_new();
                
                // 添加标题行
                const ws = XLSX.utils.json_to_sheet(selectedCards, {
                    header: ['ID', '卡密', '状态', '有效期', '使用时间', '到期时间', '创建时间', '设备ID']
                });

                // 设置列宽
                const colWidths = [
                    { wch: 8 },   // ID
                    { wch: 25 },  // 卡密
                    { wch: 10 },  // 状态
                    { wch: 10 },  // 有效期
                    { wch: 20 },  // 使用时间
                    { wch: 20 },  // 到期时间
                    { wch: 20 },  // 创建时间
                    { wch: 30 }   // 设备ID
                ];
                ws['!cols'] = colWidths;

                // 添加工作表到工作簿
                XLSX.utils.book_append_sheet(wb, ws, '卡密列表');

                // 导出Excel文件
                XLSX.writeFile(wb, fileName);
            } catch (error) {
                console.error('导出失败:', error);
                alert('导出失败，请稍后重试');
            }
        }

        // 批量删除函数
        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.card-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('请至少选择一个卡密');
                return;
            }

            if(!confirm(`确定要删除选中的 ${checkboxes.length} 个卡密吗？此操作不可恢复！`)) {
                return;
            }

            // 收集选中的卡密ID
            const cardIds = Array.from(checkboxes).map(checkbox => {
                const row = checkbox.closest('tr');
                return row.cells[1].textContent; // ID列
            });

            // 创建表单并提交
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            // 添加操作标识
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_cards';
            actionInput.value = '1';
            form.appendChild(actionInput);

            // 添加卡密ID
            cardIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'card_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            // 提交表单
            document.body.appendChild(form);
            form.submit();
        }

        // 停用卡密
        function disableCard(cardId) {
            if(confirm('确定要停用这个卡密吗？')) {
                fetch('card_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'disable',
                        card_id: cardId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '操作失败');
                    }
                });
            }
        }

        // 修改到期时间
        function editExpireTime(cardId, currentExpireTime) {
            document.getElementById('editCardId').value = cardId;
            document.getElementById('editExpireTime').value = currentExpireTime.replace(' ', 'T');
            document.getElementById('editExpireTimeModal').style.display = 'block';
        }

        // 关闭模态框
        function closeModal() {
            document.getElementById('editExpireTimeModal').style.display = 'none';
        }

        // 处理修改到期时间表单提交
        document.getElementById('editExpireTimeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('card_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_expire_time',
                    card_id: formData.get('card_id'),
                    expire_time: formData.get('expire_time')
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert(data.message || '操作失败');
                }
            });
        });

        // 删除已使用的卡密
        function deleteUsedCard(cardId) {
            if(confirm('确定要删除这个已使用的卡密吗？此操作不可恢复！')) {
                fetch('card_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_used',
                        card_id: cardId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '操作失败');
                    }
                });
            }
        }

        // 启用卡密
        function enableCard(cardId) {
            if(confirm('确定要启用这个卡密吗？')) {
                fetch('card_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'enable',
                        card_id: cardId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '操作失败');
                    }
                });
            }
        }

        // 切换允许重复验证状态
        async function toggleReverify(cardId, newState, iconElement) {
            const actionText = newState === 1 ? '允许' : '禁止';
            // 不需要确认提示，直接切换
            // if (!confirm(`确定要${actionText}此卡密被同一设备重复验证吗？`)) return;

            // 乐观更新 UI (可选，可以增加用户体验)
            iconElement.classList.toggle('on', newState === 1);
            iconElement.classList.toggle('off', newState === 0);
            iconElement.classList.toggle('fa-toggle-on', newState === 1);
            iconElement.classList.toggle('fa-toggle-off', newState === 0);
            // 更新 onclick 事件的参数
             iconElement.setAttribute('onclick', `toggleReverify(${cardId}, ${newState === 1 ? 0 : 1}, this)`);


            const result = await sendActionRequest('toggle_reverify', { card_id: cardId, allow_reverify: newState });
            
            if (!result.success) {
                 alert(result.message);
                 // 如果失败，则恢复 UI
                 const oldState = newState === 1 ? 0 : 1;
                 iconElement.classList.toggle('on', oldState === 1);
                 iconElement.classList.toggle('off', oldState === 0);
                 iconElement.classList.toggle('fa-toggle-on', oldState === 1);
                 iconElement.classList.toggle('fa-toggle-off', oldState === 0);
                 iconElement.setAttribute('onclick', `toggleReverify(${cardId}, ${oldState === 1 ? 0 : 1}, this)`);
            }
            // 不需要刷新页面，除非后端返回需要刷新
            // if (result.success) { window.location.reload(); }
        }

        // 通用AJAX请求函数
        async function sendActionRequest(action, data) {
            try {
                const response = await fetch('card_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ action, ...data })
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error('请求失败:', error);
                alert('操作失败，请检查网络或联系管理员');
                return { success: false, message: '请求失败' };
            }
        }

        // --- 新增解绑设备功能 ---
        async function unbindDevice(cardId) {
            if (!confirm('确定要解绑此卡密的设备吗？解绑后，任何设备都可以使用此卡密重新验证并绑定。')) return;

            const result = await sendActionRequest('unbind_device', { card_id: cardId });
            alert(result.message);
            if (result.success) {
                window.location.reload(); // 刷新页面更新状态
            }
        }

        // --- 重新添加缺失的函数 ---

        // 切换卡密状态（启用/停用）
        async function toggleCardStatus(cardId, statusAction) {
            const actionText = statusAction === 'disable' ? '停用' : '启用';
            if (!confirm(`确定要${actionText}这个卡密吗？`)) return;

            const result = await sendActionRequest(statusAction, { card_id: cardId });
            alert(result.message);
            if (result.success) {
                window.location.reload(); // 刷新页面更新状态
            }
        }

        // 弹出提示框修改到期时间
        async function promptExpireTime(cardId, currentExpireTime) {
            const newExpireTimeStr = prompt("请输入新的到期时间（格式 YYYY-MM-DD HH:MM:SS），留空则清除到期时间", currentExpireTime || '');
            
            // 用户取消
            if (newExpireTimeStr === null) return; 

            let newExpireTime = newExpireTimeStr.trim();

            if (newExpireTime === '') {
                // 清除到期时间 (需要后端支持，例如传递 null 或特定值)
                 if (!confirm(`确定要清除卡密 ${cardId} 的到期时间吗？`)) return;
                 // 假设后端能处理 expire_time 为 null
                 newExpireTime = null; 
            } else {
                // 简单验证日期格式 (更严格的验证应该在后端进行)
                // 注意：直接用 Date.parse 或 new Date() 在不同浏览器可能有兼容性问题
                // 最好是后端进行严格验证
                const dateRegex = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;
                if (!dateRegex.test(newExpireTime)) {
                     alert("日期格式无效，请输入 YYYY-MM-DD HH:MM:SS");
                     return;
                }
            }

            const result = await sendActionRequest('update_expire_time', { card_id: cardId, expire_time: newExpireTime });
            alert(result.message);
            if (result.success) {
                window.location.reload(); // 刷新页面更新状态
            }
        }

        // 删除未使用卡密 (之前可能叫 deleteCard)
        function deleteUnusedCard(id) {
            if(confirm('确定要删除这个未使用的卡密吗？此操作不可恢复！')) {
                window.location.href = '?delete=' + id;
            }
        }

        // 删除已使用/已停用的卡密 (这个函数是存在的)
        // async function deleteUsedCard(cardId) { ... }

        // --- 结束 重新添加缺失的函数 ---

        // 切换允许重复验证状态 (这个函数是存在的)
        // async function toggleReverify(cardId, newState, iconElement) { ... }

        // 通用AJAX请求函数 (这个函数是存在的)
        // async function sendActionRequest(action, data) { ... }

        // 弹出修改到期时间模态框
        function promptExpireTime(id, expireTime) {
            const modal = document.getElementById('editExpireTimeModal');
            const editCardId = document.getElementById('editCardId');
            const editExpireTime = document.getElementById('editExpireTime');
            
            editCardId.value = id;
            
            // 如果有到期时间，则格式化为datetime-local格式
            if(expireTime && expireTime !== '-') {
                // 将MySQL日期格式转换为datetime-local格式
                const date = new Date(expireTime);
                const formattedDate = date.toISOString().slice(0, 16);
                editExpireTime.value = formattedDate;
            } else {
                // 如果没有到期时间，则默认设置为一个月后
                const date = new Date();
                date.setMonth(date.getMonth() + 1);
                editExpireTime.value = date.toISOString().slice(0, 16);
            }
            
            modal.style.display = 'block';
        }
        
        // 关闭模态框
        function closeModal() {
            document.getElementById('editExpireTimeModal').style.display = 'none';
        }
        
        // 弹出修改次数模态框
        function promptRemainingCount(id, remainingCount) {
            const modal = document.getElementById('editCountModal');
            const editCountCardId = document.getElementById('editCountCardId');
            const editRemainingCount = document.getElementById('editRemainingCount');
            
            editCountCardId.value = id;
            editRemainingCount.value = remainingCount;
            
            modal.style.display = 'block';
        }
        
        // 关闭次数模态框
        function closeCountModal() {
            document.getElementById('editCountModal').style.display = 'none';
        }
        
        // 处理表单提交
        document.getElementById('editExpireTimeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const cardId = document.getElementById('editCardId').value;
            const expireTime = document.getElementById('editExpireTime').value;
            
            const form = document.createElement('form');
            form.method = 'POST';
            
            const cardIdInput = document.createElement('input');
            cardIdInput.type = 'hidden';
            cardIdInput.name = 'card_id';
            cardIdInput.value = cardId;
            form.appendChild(cardIdInput);
            
            const expireTimeInput = document.createElement('input');
            expireTimeInput.type = 'hidden';
            expireTimeInput.name = 'expire_time';
            expireTimeInput.value = expireTime;
            form.appendChild(expireTimeInput);
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'edit_expire_time';
            submitInput.value = '1';
            form.appendChild(submitInput);
            
            document.body.appendChild(form);
            form.submit();
        });
        
        // 处理次数表单提交
        document.getElementById('editCountForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const cardId = document.getElementById('editCountCardId').value;
            const remainingCount = document.getElementById('editRemainingCount').value;
            
            const form = document.createElement('form');
            form.method = 'POST';
            
            const cardIdInput = document.createElement('input');
            cardIdInput.type = 'hidden';
            cardIdInput.name = 'card_id';
            cardIdInput.value = cardId;
            form.appendChild(cardIdInput);
            
            const remainingCountInput = document.createElement('input');
            remainingCountInput.type = 'hidden';
            remainingCountInput.name = 'remaining_count';
            remainingCountInput.value = remainingCount;
            form.appendChild(remainingCountInput);
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'edit_count';
            submitInput.value = '1';
            form.appendChild(submitInput);
            
            document.body.appendChild(form);
            form.submit();
        });
        
        // 点击模态框外关闭模态框
        window.onclick = function(event) {
            const expireTimeModal = document.getElementById('editExpireTimeModal');
            const countModal = document.getElementById('editCountModal');
            const txtExportModal = document.getElementById('txtExportModal');

            if (event.target === expireTimeModal) {
                expireTimeModal.style.display = 'none';
            } else if (event.target === countModal) {
                countModal.style.display = 'none';
            } else if (event.target === txtExportModal) {
                txtExportModal.style.display = 'none';
            }
        }

        // TXT导出相关函数
        function showTxtExportModal() {
            updateSelectedCount();
            document.getElementById('txtExportModal').style.display = 'block';
            // 添加动画效果
            const modal = document.getElementById('txtExportModal');
            modal.style.opacity = '0';
            modal.style.display = 'block';
            setTimeout(() => {
                modal.style.opacity = '1';
            }, 10);

            // 添加导出数量选项的事件监听器
            const exportCountRadios = document.querySelectorAll('input[name="exportCount"]');
            exportCountRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    const customCountInput = document.getElementById('customCountInput');
                    const exportTip = document.getElementById('exportTip');

                    if (this.value === 'custom') {
                        customCountInput.style.display = 'block';
                        exportTip.textContent = '请输入要导出的数量';
                    } else {
                        customCountInput.style.display = 'none';
                        if (this.value === 'selected') {
                            exportTip.textContent = '将导出选中的卡密';
                        } else if (this.value === 'filtered') {
                            exportTip.textContent = '将导出当前筛选结果中的所有卡密';
                        }
                    }
                });
            });
        }

        function closeTxtExportModal() {
            const modal = document.getElementById('txtExportModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.card-checkbox:checked');
            document.getElementById('selectedCount').textContent = checkboxes.length;
        }

        // 监听复选框变化更新选中数量
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('card-checkbox')) {
                    updateSelectedCount();
                }
            });
        });

        function exportTxt() {
            const exportBtn = document.getElementById('exportBtn');
            const originalText = exportBtn.innerHTML;

            try {
                // 设置按钮为加载状态
                exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 导出中...';
                exportBtn.disabled = true;

                const fileName = document.getElementById('txtFileName').value.trim() || '卡密列表';
                const exportCount = document.querySelector('input[name="exportCount"]:checked').value;
                const exportFormat = document.querySelector('input[name="exportFormat"]:checked').value;
                const selectedFields = Array.from(document.querySelectorAll('input[name="exportFields"]:checked')).map(cb => cb.value);

                console.log('导出参数:', { fileName, exportCount, exportFormat, selectedFields });

                if (selectedFields.length === 0) {
                    alert('请至少选择一个导出字段！');
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                    return;
                }

                let cardsData = [];

                if (exportCount === 'selected') {
                    const checkboxes = document.querySelectorAll('.card-checkbox:checked');
                    console.log('选中的复选框数量:', checkboxes.length);

                    if (checkboxes.length === 0) {
                        alert('请先选择要导出的卡密！');
                        return;
                    }

                    cardsData = Array.from(checkboxes).map(checkbox => {
                        const row = checkbox.closest('tr');
                        return extractRowData(row);
                    });
                } else if (exportCount === 'filtered') {
                    // 获取当前页面所有卡密数据
                    const allRows = document.querySelectorAll('tbody tr');
                    console.log('当前页面行数:', allRows.length);
                    cardsData = Array.from(allRows).map(row => extractRowData(row));

                    // 如果需要导出所有筛选结果，需要通过AJAX获取
                    if (cardsData.length < <?php echo $total; ?>) {
                        exportFilteredTxt(fileName, exportFormat, selectedFields);
                        return;
                    }
                } else if (exportCount === 'custom') {
                    const customCount = parseInt(document.getElementById('customCount').value);
                    if (!customCount || customCount <= 0) {
                        alert('请输入有效的导出数量！');
                        return;
                    }

                    const allRows = document.querySelectorAll('tbody tr');
                    cardsData = Array.from(allRows).slice(0, customCount).map(row => extractRowData(row));

                    // 如果当前页面数据不够，需要通过AJAX获取
                    if (cardsData.length < customCount) {
                        exportCustomTxt(fileName, exportFormat, selectedFields, customCount);
                        return;
                    }
                }

                console.log('提取的卡密数据:', cardsData);
                generateTxtFile(cardsData, fileName, exportFormat, selectedFields);
            } catch (error) {
                console.error('导出过程中发生错误:', error);
                alert('导出失败：' + error.message);
                // 恢复按钮状态
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }
        }

        function extractRowData(row) {
            const cells = row.cells;
            return {
                card_key: row.querySelector('.card-checkbox').value,
                product_name: cells[3].querySelector('.product-badge') ? cells[3].querySelector('.product-badge').textContent.trim() : cells[3].textContent.trim(),
                status: cells[4].querySelector('.status-badge') ? cells[4].querySelector('.status-badge').textContent.trim() : cells[4].textContent.trim(),
                card_type: cells[5].querySelector('.type-badge') ? cells[5].querySelector('.type-badge').textContent.trim() : cells[5].textContent.trim(),
                duration: cells[6].querySelector('.duration-badge, .count-badge') ? cells[6].querySelector('.duration-badge, .count-badge').textContent.trim() : cells[6].textContent.trim(),
                use_time: cells[7].textContent.trim(),
                expire_time: cells[8].textContent.trim(),
                create_time: cells[9].textContent.trim(),
                device_id: cells[10].textContent.trim()
            };
        }

        function generateTxtFile(cardsData, fileName, exportFormat, selectedFields) {
            let content = '';

            if (exportFormat === 'line') {
                // 每行一个卡密
                content = cardsData.map(card => card.card_key).join('\n');
            } else {
                // 详细信息格式
                const headers = selectedFields.map(field => getFieldName(field));
                content = headers.join('\t') + '\n';

                cardsData.forEach(card => {
                    const values = selectedFields.map(field => {
                        let value = card[field] || '';
                        // 处理特殊字段
                        if (field === 'status') {
                            value = value;
                        } else if (field === 'card_type') {
                            value = value.includes('时间') ? '时间卡密' : '次数卡密';
                        }
                        return value;
                    });
                    content += values.join('\t') + '\n';
                });
            }

            // 创建并下载文件
            const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName.endsWith('.txt') ? fileName : fileName + '.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            // 恢复按钮状态
            const exportBtn = document.getElementById('exportBtn');
            exportBtn.innerHTML = '<i class="fas fa-download"></i> 导出';
            exportBtn.disabled = false;

            closeTxtExportModal();
            alert('导出成功！');
        }

        function getFieldName(field) {
            const fieldNames = {
                'card_key': '卡密',
                'product_name': '关联商品',
                'status': '状态',
                'card_type': '类型',
                'duration': '有效期/次数',
                'device_id': '绑定设备',
                'create_time': '创建时间',
                'use_time': '使用时间'
            };
            return fieldNames[field] || field;
        }

        function exportFilteredTxt(fileName, exportFormat, selectedFields) {
            // 通过AJAX获取所有筛选结果
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'export_filtered_txt');
            params.set('format', exportFormat);
            params.set('fields', selectedFields.join(','));

            fetch('txt_export.php?' + params.toString())
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = fileName.endsWith('.txt') ? fileName : fileName + '.txt';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    closeTxtExportModal();
                    alert('导出成功！');
                })
                .catch(error => {
                    console.error('导出失败:', error);
                    alert('导出失败，请重试！');
                });
        }

        function exportCustomTxt(fileName, exportFormat, selectedFields, count) {
            // 通过AJAX获取指定数量的数据
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'export_custom_txt');
            params.set('format', exportFormat);
            params.set('fields', selectedFields.join(','));
            params.set('count', count);

            fetch('txt_export.php?' + params.toString())
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = fileName.endsWith('.txt') ? fileName : fileName + '.txt';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    closeTxtExportModal();
                    alert('导出成功！');
                })
                .catch(error => {
                    console.error('导出失败:', error);
                    alert('导出失败，请重试！');
                });
        }

        // 添加一些实用功能
        document.addEventListener('DOMContentLoaded', function() {
            // 自动保存筛选条件到localStorage
            const filterForm = document.querySelector('.filter-form');
            if (filterForm) {
                const inputs = filterForm.querySelectorAll('input, select');
                inputs.forEach(input => {
                    // 从localStorage恢复值
                    const savedValue = localStorage.getItem('filter_' + input.name);
                    if (savedValue && !input.value) {
                        input.value = savedValue;
                    }

                    // 保存值到localStorage
                    input.addEventListener('change', function() {
                        localStorage.setItem('filter_' + this.name, this.value);
                    });
                });
            }

            // 添加快捷键支持
            document.addEventListener('keydown', function(e) {
                // Ctrl+A 全选卡密
                if (e.ctrlKey && e.key === 'a' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    const selectAllCheckbox = document.getElementById('selectAll');
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = true;
                        toggleSelectAll();
                    }
                }

                // Ctrl+E 打开导出模态框
                if (e.ctrlKey && e.key === 'e') {
                    e.preventDefault();
                    showTxtExportModal();
                }

                // ESC 关闭模态框
                if (e.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal');
                    modals.forEach(modal => {
                        if (modal.style.display === 'block') {
                            modal.style.display = 'none';
                        }
                    });
                }
            });

            // 添加表格行点击选择功能
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    if (e.target.type !== 'checkbox' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
                        const checkbox = this.querySelector('.card-checkbox');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            updateSelectedCount();
                        }
                    }
                });
            });

            // 添加工具提示
            const tooltips = document.querySelectorAll('[title]');
            tooltips.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = this.title;
                    tooltip.style.cssText = `
                        position: absolute;
                        background: #333;
                        color: white;
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 12px;
                        z-index: 1000;
                        pointer-events: none;
                        opacity: 0;
                        transition: opacity 0.3s;
                    `;
                    document.body.appendChild(tooltip);

                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.left + 'px';
                    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';

                    setTimeout(() => tooltip.style.opacity = '1', 10);

                    this.addEventListener('mouseleave', function() {
                        tooltip.remove();
                    }, { once: true });
                });
            });
        });

        // 添加表单验证
        function validateForm(form) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#28a745';
                }
            });

            return isValid;
        }

        // 添加成功/错误消息显示
        function showMessage(message, type = 'info') {
            const messageDiv = document.createElement('div');
            messageDiv.className = `alert alert-${type}`;
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                min-width: 300px;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
            `;
            messageDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                ${message}
            `;

            document.body.appendChild(messageDiv);

            setTimeout(() => {
                messageDiv.style.opacity = '1';
                messageDiv.style.transform = 'translateX(0)';
            }, 10);

            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateX(100%)';
                setTimeout(() => messageDiv.remove(), 300);
            }, 3000);
        }
    </script>

    <!-- TXT导出模态框 -->
    <div id="txtExportModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h4><i class="fas fa-file-alt"></i> 导出TXT文件</h4>
                <button type="button" class="close" onclick="closeTxtExportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-file-signature"></i> 文件名称：</label>
                    <input type="text" id="txtFileName" class="form-control" value="卡密列表" placeholder="请输入文件名">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-up"></i> 导出数量：</label>
                    <div class="export-count-options">
                        <label class="radio-option">
                            <input type="radio" name="exportCount" value="selected" checked>
                            <span>仅导出选中的卡密 (<span id="selectedCount">0</span>个)</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="exportCount" value="filtered">
                            <span>导出当前筛选结果 (<?php echo $total; ?>个)</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="exportCount" value="custom">
                            <span>自定义数量</span>
                        </label>
                    </div>
                    <div id="customCountInput" style="display: none;">
                        <input type="number" id="customCount" min="1" max="<?php echo $total; ?>" value="100" placeholder="请输入导出数量（1-<?php echo $total; ?>）">
                        <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                            <i class="fas fa-info-circle"></i> 最多可导出 <?php echo $total; ?> 个卡密
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-list-check"></i> 导出字段：</label>
                    <div class="export-fields">
                        <label class="checkbox-option">
                            <input type="checkbox" name="exportFields" value="card_key" checked>
                            <span>卡密</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="exportFields" value="product_name">
                            <span>关联商品</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="exportFields" value="status">
                            <span>状态</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="exportFields" value="card_type">
                            <span>类型</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="exportFields" value="duration">
                            <span>有效期/次数</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="exportFields" value="device_id">
                            <span>绑定设备</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="exportFields" value="create_time">
                            <span>创建时间</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="exportFields" value="use_time">
                            <span>使用时间</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-cog"></i> 导出格式：</label>
                    <div class="export-format-options">
                        <label class="radio-option">
                            <input type="radio" name="exportFormat" value="line" checked>
                            <span>每行一个卡密（仅卡密）</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="exportFormat" value="detailed">
                            <span>详细信息（包含选中字段）</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div style="flex: 1; text-align: left; color: #666; font-size: 14px;">
                    <i class="fas fa-info-circle"></i>
                    <span id="exportTip">请选择导出选项后点击导出按钮</span>
                </div>
                <button type="button" class="btn btn-secondary" onclick="closeTxtExportModal()">
                    <i class="fas fa-times"></i> 取消
                </button>
                <button type="button" class="btn btn-success" onclick="exportTxt()" id="exportBtn">
                    <i class="fas fa-download"></i> 导出
                </button>
            </div>
        </div>
    </div>

    <style>
        /* TXT导出模态框样式优化 */
        #txtExportModal .modal-content {
            max-width: 700px;
            width: 90%;
            margin: 3% auto;
        }

        #txtExportModal .modal-body {
            max-height: 75vh;
            overflow-y: auto;
            padding: 25px;
        }

        #txtExportModal .form-group {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }

        #txtExportModal .form-group label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        #txtExportModal .form-group label i {
            color: #3498db;
            font-size: 18px;
        }

        .export-count-options, .export-format-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
        }

        .export-fields {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .radio-option, .checkbox-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: white;
            border: 2px solid #e9ecef;
            font-weight: 500;
        }

        .radio-option:hover, .checkbox-option:hover {
            background-color: #e3f2fd;
            border-color: #3498db;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
        }

        .radio-option input:checked + span,
        .checkbox-option input:checked + span {
            color: #3498db;
            font-weight: 600;
        }

        .radio-option input, .checkbox-option input {
            margin: 0;
            width: 18px;
            height: 18px;
            accent-color: #3498db;
        }

        #customCountInput {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }

        #customCountInput input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        #customCountInput input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        #txtFileName {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        #txtFileName:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-1px);
        }

        #txtExportModal .modal-footer {
            padding: 20px 25px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        #txtExportModal .btn {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        #txtExportModal .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            #txtExportModal .modal-content {
                width: 95%;
                margin: 2% auto;
            }

            .export-fields {
                grid-template-columns: 1fr;
            }

            #txtExportModal .modal-body {
                padding: 20px;
            }

            #txtExportModal .form-group {
                padding: 15px;
                margin-bottom: 20px;
            }
        }
    </style>
</body>
</html>