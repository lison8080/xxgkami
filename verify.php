<?php
session_start();
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!file_exists("install.lock")){
    header("Location: install/index.php");
    exit;
}

require_once 'config.php';

// 初始化消息变量
$card_msg = null;
$error = null;
$card_detail = null;

// 调试信息
$debug_info = "";
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info .= "收到POST请求，参数：".print_r($_POST, true);
}

// 建立数据库连接
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取网站标题和副标题
    $stmt = $conn->prepare("SELECT name, value FROM settings WHERE name IN (
        'site_title', 'site_subtitle', 'copyright_text'
    )");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $site_title = $settings['site_title'] ?? '小小怪卡密验证系统';
    $site_subtitle = $settings['site_subtitle'] ?? '专业的卡密验证解决方案';
    $copyright_text = $settings['copyright_text'] ?? '小小怪卡密系统 - All Rights Reserved';
    
    // 处理卡密验证
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_card'])) {
        $debug_info .= "处理验证卡密请求\n";
        try {
            $card_key = isset($_POST['card_key']) ? trim($_POST['card_key']) : '';
            $debug_info .= "卡密: $card_key\n";
            
            if(empty($card_key)) {
                $card_msg = array('type' => 'error', 'msg' => '请输入卡密');
                $debug_info .= "错误: 卡密为空\n";
            } else {
                // 检查卡密是否存在，同时获取商品信息
                $stmt = $conn->prepare("
                    SELECT c.*, p.name as product_name, p.status as product_status
                    FROM cards c
                    LEFT JOIN products p ON c.product_id = p.id
                    WHERE c.card_key = ?
                ");
                $stmt->execute([$card_key]);
                $card = $stmt->fetch(PDO::FETCH_ASSOC);
                $debug_info .= "查询结果: ".($card ? "找到卡密" : "未找到卡密")."\n";
                
                if($card) {
                    if($card['status'] == 0) {
                        // 计算到期时间（如果有设置duration）
                        $expire_time = null;
                        if($card['card_type'] == 'time') {
                            if($card['duration'] > 0) {
                                $expire_time = date('Y-m-d H:i:s', strtotime("+{$card['duration']} days"));
                            }
                            
                            // 更新卡密状态，使用POST方法验证
                            $stmt = $conn->prepare("UPDATE cards SET status = 1, use_time = NOW(), expire_time = ?, verify_method = 'post' WHERE id = ?");
                            $stmt->execute([$expire_time, $card['id']]);
                            
                            // 根据有效期显示不同的成功消息
                            if($card['duration'] > 0) {
                                $card_msg = array('type' => 'success', 'msg' => "卡密验证成功！有效期{$card['duration']}天，到期时间：{$expire_time}");
                            } else {
                                $card_msg = array('type' => 'success', 'msg' => '卡密验证成功！（永久有效）');
                            }
                        } else if($card['card_type'] == 'count') {
                            // 次数卡，减少一次使用次数
                            $remaining_count = $card['remaining_count'] - 1;
                            
                            // 更新卡密状态，使用POST方法验证
                            $stmt = $conn->prepare("UPDATE cards SET status = 1, use_time = NOW(), remaining_count = ?, verify_method = 'post' WHERE id = ?");
                            $stmt->execute([$remaining_count, $card['id']]);
                            
                            $card_msg = array('type' => 'success', 'msg' => "卡密验证成功！剩余使用次数：{$remaining_count}次");
                        }
                    } else {
                        // 已使用的卡密
                        if($card['card_type'] == 'time') {
                            if($card['expire_time']) {
                                $card_msg = array('type' => 'error', 'msg' => "此卡密已被使用，使用时间：{$card['use_time']}，到期时间：{$card['expire_time']}");
                            } else {
                                $card_msg = array('type' => 'error', 'msg' => "此卡密已被使用，使用时间：{$card['use_time']}");
                            }
                        } else if($card['card_type'] == 'count') {
                            $card_msg = array('type' => 'error', 'msg' => "此卡密已被使用，剩余次数：{$card['remaining_count']}次");
                        }
                    }
                    
                    // 保存卡密详情以显示
                    $card_detail = $card;
                } else {
                    $card_msg = array('type' => 'error', 'msg' => '无效的卡密');
                }
            }
        } catch(PDOException $e) {
            error_log($e->getMessage());
            $card_msg = array('type' => 'error', 'msg' => '系统错误，请稍后再试');
            $debug_info .= "数据库错误: ".$e->getMessage()."\n";
        }
    }
    
    // 查询卡密详情
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query_card'])) {
        $debug_info .= "处理查询卡密请求\n";
        try {
            $card_key = isset($_POST['card_key']) ? trim($_POST['card_key']) : '';
            $debug_info .= "卡密: $card_key\n";
            
            if(empty($card_key)) {
                $card_msg = array('type' => 'error', 'msg' => '请输入卡密');
                $debug_info .= "错误: 卡密为空\n";
            } else {
                // 检查卡密是否存在
                $stmt = $conn->prepare("SELECT * FROM cards WHERE card_key = ?");
                $stmt->execute([$card_key]);
                $card = $stmt->fetch(PDO::FETCH_ASSOC);
                $debug_info .= "查询结果: ".($card ? "找到卡密" : "未找到卡密")."\n";
                
                if($card) {
                    // 保存卡密详情以显示
                    $card_detail = $card;
                    $card_msg = array('type' => 'success', 'msg' => '查询成功');
                    $debug_info .= "查询成功，设置了card_detail\n";
                } else {
                    $card_msg = array('type' => 'error', 'msg' => '无效的卡密');
                    $debug_info .= "错误: 无效的卡密\n";
                }
            }
        } catch(PDOException $e) {
            error_log($e->getMessage());
            $card_msg = array('type' => 'error', 'msg' => '系统错误，请稍后再试');
            $debug_info .= "数据库错误: ".$e->getMessage()."\n";
        }
    }

    // 获取最近验证记录
    try {
        $stmt = $conn->query("
            SELECT c.card_key, c.use_time, c.expire_time, c.duration, c.card_type, c.remaining_count,
                    c.verify_method,
                    CASE 
                        WHEN c.card_type = 'time' AND c.expire_time IS NOT NULL AND c.expire_time < NOW() THEN '已过期'
                        WHEN c.card_type = 'time' AND c.expire_time IS NOT NULL THEN '使用中'
                        WHEN c.card_type = 'count' AND c.remaining_count <= 0 THEN '已用完'
                        WHEN c.card_type = 'count' AND c.remaining_count > 0 THEN '使用中'
                        ELSE '永久有效'
                    END as status,
                    CASE c.verify_method
                        WHEN 'web' THEN '网页验证'
                        WHEN 'post' THEN 'POST验证'
                        WHEN 'get' THEN 'GET验证'
                        ELSE '网页验证'
                    END as verify_method_text
            FROM cards c 
            WHERE c.status = 1 
            ORDER BY c.use_time DESC 
            LIMIT 5
        ");
        $recent_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $recent_records = [];
    }
} catch(PDOException $e) {
    error_log($e->getMessage());
    $error = "数据库连接失败，请稍后再试";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>卡密验证 - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* 基础样式 */
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --bg-color: #ecf0f3;
            --text-color: #2c3e50;
            --shadow-light: 10px 10px 20px #d1d9e6, -10px -10px 20px #ffffff;
            --shadow-inset: inset 5px 5px 10px #d1d9e6, inset -5px -5px 10px #ffffff;
            --glass-bg: rgba(255, 255, 255, 0.25);
        }

        body {
            background: var(--bg-color);
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--text-color);
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(52, 152, 219, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(46, 204, 113, 0.1) 0%, transparent 50%);
        }

        /* 导航栏样式 */
        .navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-color);
            font-weight: bold;
            font-size: 20px;
        }

        .navbar-brand i {
            font-size: 24px;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .navbar-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-link {
            color: var(--text-color);
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
        }

        .nav-link.btn-primary {
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white;
            padding: 8px 20px;
        }

        /* 主容器样式 */
        .main-container {
            max-width: 1200px;
            margin: 80px auto 30px;
            padding: 0 20px;
        }

        /* 卡片容器 */
        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card-title {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 24px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary-color);
        }

        /* 表单样式 */
        .verify-form {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid rgba(52, 152, 219, 0.2);
            border-radius: 10px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.2);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-color);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        /* 警告框样式 */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }

        .alert i {
            font-size: 20px;
        }

        /* 卡密详情样式 */
        .card-details {
            margin-top: 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card-details h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-details h3 i {
            color: var(--primary-color);
        }

        .detail-item {
            display: flex;
            margin-bottom: 15px;
            align-items: center;
        }

        .detail-label {
            flex: 0 0 150px;
            font-weight: 600;
            color: var(--text-color);
        }

        .detail-value {
            flex: 1;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .badge-danger {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .badge-warning {
            background: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
        }

        .badge-primary {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }

        /* 历史记录表格 */
        .history-table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }

        .history-table th,
        .history-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .history-table th {
            background: rgba(52, 152, 219, 0.1);
            font-weight: 600;
            color: var(--text-color);
        }

        .history-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .method-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            background: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }

        .status-badge.已过期,
        .status-badge.已用完 {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .status-badge.使用中 {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .status-badge.永久有效 {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }

        .no-records {
            text-align: center;
            padding: 30px 0;
            color: #7f8c8d;
            font-style: italic;
        }

        /* 选项卡样式 */
        .tabs {
            display: flex;
            gap: 2px;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.1);
            padding: 5px;
            border-radius: 10px;
        }

        .tab-item {
            padding: 10px 15px;
            flex: 1;
            text-align: center;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .tab-item.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-light);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .navbar-nav {
                display: none;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .detail-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .detail-label {
                margin-bottom: 5px;
            }
        }

        /* 页脚样式 */
        .footer-copyright {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 0;
            text-align: center;
            color: var(--text-color);
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <i class="fas fa-key"></i> <?php echo htmlspecialchars($site_title); ?>
        </a>
        <div class="navbar-nav">
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> 首页</a>
            <a href="verify.php" class="nav-link active"><i class="fas fa-check-circle"></i> 卡密验证</a>
            <a href="admin.php" class="nav-link btn-primary"><i class="fas fa-user-shield"></i> 管理登录</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="card">
            <h2 class="card-title"><i class="fas fa-check-circle"></i> 卡密验证中心</h2>
            
            <?php if(!empty($debug_info)): ?>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #dee2e6;">
                <h4 style="margin-top:0; color:#dc3545;">调试信息:</h4>
                <pre style="margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($debug_info); ?></pre>
            </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($card_msg)): ?>
                <div class="alert alert-<?php echo $card_msg['type']; ?>">
                    <i class="fas fa-<?php echo $card_msg['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $card_msg['msg']; ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab-item active" data-tab="verify">验证卡密</div>
                <div class="tab-item" data-tab="query">查询卡密</div>
            </div>

            <div class="tab-content active" id="verify-tab">
                <form method="POST" class="verify-form" action="">
                    <input type="hidden" name="form_type" value="verify">
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <input type="text" name="card_key" class="form-control" placeholder="请输入您的卡密" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="verify_card" value="1" class="btn btn-primary">
                            <i class="fas fa-check"></i> 立即验证
                        </button>
                    </div>
                </form>
            </div>

            <div class="tab-content" id="query-tab">
                <form method="POST" class="verify-form" action="">
                    <input type="hidden" name="form_type" value="query">
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <input type="text" name="card_key" class="form-control" placeholder="请输入要查询的卡密" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="query_card" value="1" class="btn btn-primary">
                            <i class="fas fa-search"></i> 查询详情
                        </button>
                    </div>
                </form>
            </div>

            <?php if(isset($card_detail)): ?>
            <div class="card-details">
                <h3><i class="fas fa-info-circle"></i> 卡密详情</h3>
                <div class="detail-item">
                    <div class="detail-label">卡密:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($card_detail['card_key']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">卡密类型:</div>
                    <div class="detail-value">
                        <span class="badge badge-primary">
                            <?php 
                            if($card_detail['card_type'] == 'time') {
                                echo '时间卡';
                            } else if($card_detail['card_type'] == 'count') {
                                echo '次数卡';
                            } else {
                                echo '未知类型';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">状态:</div>
                    <div class="detail-value">
                        <?php if($card_detail['status'] == 0): ?>
                            <span class="badge badge-success">未使用</span>
                        <?php else: ?>
                            <?php if($card_detail['card_type'] == 'time'): ?>
                                <?php if($card_detail['expire_time'] && strtotime($card_detail['expire_time']) < time()): ?>
                                    <span class="badge badge-danger">已过期</span>
                                <?php else: ?>
                                    <span class="badge badge-success">使用中</span>
                                <?php endif; ?>
                            <?php elseif($card_detail['card_type'] == 'count'): ?>
                                <?php if($card_detail['remaining_count'] <= 0): ?>
                                    <span class="badge badge-danger">已用完</span>
                                <?php else: ?>
                                    <span class="badge badge-success">使用中</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if($card_detail['card_type'] == 'time'): ?>
                <div class="detail-item">
                    <div class="detail-label">有效期:</div>
                    <div class="detail-value">
                        <?php if($card_detail['duration'] > 0): ?>
                            <?php echo $card_detail['duration']; ?> 天
                        <?php else: ?>
                            永久有效
                        <?php endif; ?>
                    </div>
                </div>
                <?php if($card_detail['expire_time']): ?>
                <div class="detail-item">
                    <div class="detail-label">到期时间:</div>
                    <div class="detail-value"><?php echo $card_detail['expire_time']; ?></div>
                </div>
                <?php endif; ?>
                <?php elseif($card_detail['card_type'] == 'count'): ?>
                <div class="detail-item">
                    <div class="detail-label">总次数:</div>
                    <div class="detail-value"><?php echo $card_detail['total_count']; ?> 次</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">剩余次数:</div>
                    <div class="detail-value"><?php echo $card_detail['remaining_count']; ?> 次</div>
                </div>
                <?php endif; ?>
                <?php if($card_detail['status'] == 1): ?>
                <div class="detail-item">
                    <div class="detail-label">使用时间:</div>
                    <div class="detail-value"><?php echo $card_detail['use_time']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">验证方式:</div>
                    <div class="detail-value">
                        <?php 
                        $method_text = '未知';
                        switch($card_detail['verify_method']) {
                            case 'web': $method_text = '网页验证'; break;
                            case 'post': $method_text = 'POST验证'; break;
                            case 'get': $method_text = 'GET验证'; break;
                        }
                        echo $method_text;
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <div class="detail-label">创建时间:</div>
                    <div class="detail-value"><?php echo $card_detail['create_time']; ?></div>
                </div>
                <?php if(!empty($card_detail['remark'])): ?>
                <div class="detail-item">
                    <div class="detail-label">备注:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($card_detail['remark']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-title"><i class="fas fa-history"></i> 最近验证记录</h2>
            <?php if(count($recent_records) > 0): ?>
                <div class="history-table-container">
                    <table class="history-table">
                        <tr>
                            <th>卡密</th>
                            <th>类型</th>
                            <th>使用时间</th>
                            <th>状态</th>
                            <th>详情</th>
                            <th>验证方式</th>
                        </tr>
                        <?php foreach($recent_records as $r): ?>
                            <?php $masked_key = substr($r['card_key'], 0, 4) . '****' . substr($r['card_key'], -4); ?>
                            <tr>
                                <td><?php echo $masked_key; ?></td>
                                <td>
                                    <?php 
                                    if($r['card_type'] == 'time') {
                                        echo '时间卡';
                                    } else if($r['card_type'] == 'count') {
                                        echo '次数卡';
                                    } else {
                                        echo '未知';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $r['use_time']; ?></td>
                                <td><span class="status-badge <?php echo $r['status']; ?>"><?php echo $r['status']; ?></span></td>
                                <td>
                                    <?php if($r['card_type'] == 'time'): ?>
                                        <?php echo ($r['duration'] == 0 ? '永久' : $r['duration'] . '天') . ($r['expire_time'] ? '，到期:' . $r['expire_time'] : ''); ?>
                                    <?php elseif($r['card_type'] == 'count'): ?>
                                        剩余次数: <?php echo $r['remaining_count']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="method-badge <?php echo strtolower($r['verify_method']); ?>"><?php echo $r['verify_method_text']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-records">暂无验证记录</p>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($copyright_text); ?>
        </div>
    </footer>

    <script>
        // 选项卡切换
        document.addEventListener('DOMContentLoaded', function() {
            const tabItems = document.querySelectorAll('.tab-item');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabItems.forEach(item => {
                item.addEventListener('click', function() {
                    // 移除所有活动状态
                    tabItems.forEach(tab => tab.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // 添加当前活动状态
                    this.classList.add('active');
                    document.getElementById(`${this.dataset.tab}-tab`).classList.add('active');
                });
            });
            
            // 修改：处理表单提交，确保按钮值被正确传递
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // 不阻止默认提交
                    const button = this.querySelector('button[type="submit"]');
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
                    button.disabled = true;
                    
                    // 获取表单类型
                    const formType = this.querySelector('input[name="form_type"]').value;
                    
                    // 确保相应的按钮值被设置
                    if(formType === 'verify') {
                        // 创建隐藏字段，确保verify_card=1被传递
                        if(!this.querySelector('input[name="verify_card"]')) {
                            const hiddenField = document.createElement('input');
                            hiddenField.type = 'hidden';
                            hiddenField.name = 'verify_card';
                            hiddenField.value = '1';
                            this.appendChild(hiddenField);
                        }
                    } else if(formType === 'query') {
                        // 创建隐藏字段，确保query_card=1被传递
                        if(!this.querySelector('input[name="query_card"]')) {
                            const hiddenField = document.createElement('input');
                            hiddenField.type = 'hidden';
                            hiddenField.name = 'query_card';
                            hiddenField.value = '1';
                            this.appendChild(hiddenField);
                        }
                    }
                    
                    // 允许表单提交
                    return true;
                });
            });
            
            // 处理查询卡密弹窗
            <?php if(isset($card_detail) && isset($_POST['query_card'])): ?>
            showCardDetails();
            <?php endif; ?>
            
            <?php if(isset($card_detail) && isset($_POST['form_type']) && $_POST['form_type'] === 'query'): ?>
            showCardDetails();
            <?php endif; ?>
        });

        // 弹窗显示卡密详情
        function showCardDetails() {
            let modalHtml = `
            <div id="cardModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; display:flex; justify-content:center; align-items:center;">
                <div style="background:white; width:90%; max-width:500px; max-height:80vh; overflow-y:auto; border-radius:15px; padding:20px; box-shadow:0 5px 15px rgba(0,0,0,0.3);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 style="margin:0; color:#2c3e50;"><i class="fas fa-info-circle"></i> 卡密详情</h3>
                        <button onclick="closeModal()" style="border:none; background:none; cursor:pointer; font-size:20px; color:#7f8c8d;"><i class="fas fa-times"></i></button>
                    </div>
                    <div style="margin-bottom:10px;">
                        <strong>卡密:</strong> <?php echo htmlspecialchars($card_detail['card_key']); ?>
                    </div>
                    <div style="margin-bottom:10px;">
                        <strong>卡密类型:</strong> 
                        <span style="display:inline-block; padding:3px 8px; border-radius:12px; font-size:14px; background:rgba(52,152,219,0.2); color:#3498db;">
                            <?php 
                            if($card_detail['card_type'] == 'time') {
                                echo '时间卡';
                            } else if($card_detail['card_type'] == 'count') {
                                echo '次数卡';
                            } else {
                                echo '未知类型';
                            }
                            ?>
                        </span>
                    </div>
                    <div style="margin-bottom:10px;">
                        <strong>状态:</strong>
                        <?php if($card_detail['status'] == 0): ?>
                            <span style="display:inline-block; padding:3px 8px; border-radius:12px; font-size:14px; background:rgba(46,204,113,0.2); color:#2ecc71;">未使用</span>
                        <?php else: ?>
                            <?php if($card_detail['card_type'] == 'time'): ?>
                                <?php if($card_detail['expire_time'] && strtotime($card_detail['expire_time']) < time()): ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:12px; font-size:14px; background:rgba(231,76,60,0.2); color:#e74c3c;">已过期</span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:12px; font-size:14px; background:rgba(46,204,113,0.2); color:#2ecc71;">使用中</span>
                                <?php endif; ?>
                            <?php elseif($card_detail['card_type'] == 'count'): ?>
                                <?php if($card_detail['remaining_count'] <= 0): ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:12px; font-size:14px; background:rgba(231,76,60,0.2); color:#e74c3c;">已用完</span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:12px; font-size:14px; background:rgba(46,204,113,0.2); color:#2ecc71;">使用中</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if($card_detail['card_type'] == 'time'): ?>
                    <div style="margin-bottom:10px;">
                        <strong>有效期:</strong>
                        <?php if($card_detail['duration'] > 0): ?>
                            <?php echo $card_detail['duration']; ?> 天
                        <?php else: ?>
                            永久有效
                        <?php endif; ?>
                    </div>
                    <?php if($card_detail['expire_time']): ?>
                    <div style="margin-bottom:10px;">
                        <strong>到期时间:</strong> <?php echo $card_detail['expire_time']; ?>
                    </div>
                    <?php endif; ?>
                    <?php elseif($card_detail['card_type'] == 'count'): ?>
                    <div style="margin-bottom:10px;">
                        <strong>总次数:</strong> <?php echo $card_detail['total_count']; ?> 次
                    </div>
                    <div style="margin-bottom:10px;">
                        <strong>剩余次数:</strong> <?php echo $card_detail['remaining_count']; ?> 次
                    </div>
                    <?php endif; ?>
                    <?php if($card_detail['status'] == 1): ?>
                    <div style="margin-bottom:10px;">
                        <strong>使用时间:</strong> <?php echo $card_detail['use_time']; ?>
                    </div>
                    <div style="margin-bottom:10px;">
                        <strong>验证方式:</strong>
                        <?php 
                        $method_text = '未知';
                        switch($card_detail['verify_method']) {
                            case 'web': $method_text = '网页验证'; break;
                            case 'post': $method_text = 'POST验证'; break;
                            case 'get': $method_text = 'GET验证'; break;
                        }
                        echo $method_text;
                        ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-bottom:10px;">
                        <strong>创建时间:</strong> <?php echo $card_detail['create_time']; ?>
                    </div>
                    <?php if(!empty($card_detail['remark'])): ?>
                    <div style="margin-bottom:10px;">
                        <strong>备注:</strong> <?php echo htmlspecialchars($card_detail['remark']); ?>
                    </div>
                    <?php endif; ?>
                    <div style="text-align:center; margin-top:20px;">
                        <button onclick="closeModal()" style="padding:8px 20px; background:linear-gradient(45deg,#3498db,#2ecc71); color:white; border:none; border-radius:5px; cursor:pointer;">关闭</button>
                    </div>
                </div>
            </div>`;
            
            // 添加弹窗到页面
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
        function closeModal() {
            const modal = document.getElementById('cardModal');
            if (modal) {
                modal.remove();
            }
        }
    </script>
</body>
</html> 