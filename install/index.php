<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// 初始化错误变量
$error = null;

// 如果已安装，直接跳转到首页
if(file_exists("../install.lock")){
    header("Location: ../index.php");
    exit;
}

// 检测是否在容器环境中（通过检查预设的配置文件）
$is_container_env = file_exists("../config.php");
$container_db_config = null;

if($is_container_env) {
    // 读取容器预设的数据库配置
    $config_content = file_get_contents("../config.php");
    if(preg_match("/define\('DB_HOST',\s*'([^']+)'\);/", $config_content, $matches)) {
        $container_db_config['host'] = $matches[1];
    }
    if(preg_match("/define\('DB_USER',\s*'([^']+)'\);/", $config_content, $matches)) {
        $container_db_config['username'] = $matches[1];
    }
    if(preg_match("/define\('DB_PASS',\s*'([^']+)'\);/", $config_content, $matches)) {
        $container_db_config['password'] = $matches[1];
    }
    if(preg_match("/define\('DB_NAME',\s*'([^']+)'\);/", $config_content, $matches)) {
        $container_db_config['database'] = $matches[1];
    }
}

// 每次直接访问install/index.php时重置安装步骤
if($_SERVER['REQUEST_METHOD'] == 'GET'){
    $_SESSION['install_step'] = 1;
}

// 处理步骤跳转
if(isset($_POST['next_step'])){
    error_log("Next step clicked. Current step: " . $_SESSION['install_step']);
    if(!isset($_SESSION['install_step'])) {
        $_SESSION['install_step'] = 1;
        error_log("Session step initialized to 1");
    }
    $_SESSION['install_step']++;

    // 如果是容器环境，跳过数据库配置步骤（步骤3）
    if($is_container_env && $_SESSION['install_step'] == 3) {
        $_SESSION['install_step'] = 4; // 跳到管理员配置步骤
    }

    error_log("Step increased to: " . $_SESSION['install_step']);
}

// 处理返回上一步
if(isset($_POST['prev_step']) && isset($_SESSION['install_step']) && $_SESSION['install_step'] > 1){
    $_SESSION['install_step']--;

    // 如果是容器环境，跳过数据库配置步骤（步骤3）
    if($is_container_env && $_SESSION['install_step'] == 3) {
        $_SESSION['install_step'] = 2; // 回到环境检测步骤
    }
}

// 确保安装步骤有默认值
if(!isset($_SESSION['install_step'])) {
    $_SESSION['install_step'] = 1;
}

// 处理安装请求
if(isset($_POST['install'])){
    header('Content-Type: application/json; charset=utf-8');

    $response = array(
        'status' => 'error',
        'message' => '',
        'step' => '',
        'sql' => ''
    );

    try {
        // 如果是容器环境，使用预设的数据库配置
        if($is_container_env && $container_db_config) {
            $host = $container_db_config['host'];
            $username = $container_db_config['username'];
            $password = $container_db_config['password'];
            $database = $container_db_config['database'];
        } else {
            // 非容器环境，使用用户输入的配置
            $host = trim($_POST['host']);
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $database = trim($_POST['database']);
        }

        $admin_user = trim($_POST['admin_user']);
        $admin_pass = password_hash(trim($_POST['admin_pass']), PASSWORD_DEFAULT);
        
        // 第一步：尝试直接连接到指定数据库
        $response['step'] = '连接数据库';
        try {
            $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
            $dbExists = true;
        } catch(PDOException $e) {
            // 如果连接失败,尝试不指定数据库名连接
            $conn = new PDO("mysql:host=$host", $username, $password);
            $dbExists = false;
        }
        
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8mb4");
        
        // 第二步：检查是否需要创建数据库
        if(!$dbExists) {
            $response['step'] = '创建数据库';
            try {
                // 尝试创建数据库
                $conn->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                // 重新连接到新创建的数据库
                $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $e) {
                throw new Exception("无法创建数据库。请确保数据库已存在或用户具有创建数据库权限。");
            }
        }
        
        // 第三步：检查表是否存在
        $response['step'] = '检查数据表';
        $tables = array('cards', 'admins', 'settings', 'slides', 'features');
        $existingTables = array();
        
        foreach($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if($stmt->rowCount() > 0) {
                $existingTables[] = $table;
            }
        }
        
        if(!empty($existingTables)) {
            // 如果存在表,提示用户
            $tableList = implode(', ', $existingTables);
            $response['step'] = '检测到已存在的表';
            
            // 尝试删除已存在的表
            foreach($existingTables as $table) {
                try {
                    $conn->exec("DROP TABLE IF EXISTS `$table`");
                } catch(PDOException $e) {
                    throw new Exception("无法删除已存在的表 $table。请确保用户具有删除表的权限。");
                }
            }
        }
        
        // 第四步：执行SQL文件
        $response['step'] = '创建数据表';
        $sql_file = file_get_contents('install.sql');
        
        // 分割SQL语句
        $queries = array_filter(array_map('trim', explode(';', $sql_file)));
        
        foreach($queries as $query) {
            if(!empty($query)) {
                try {
                    $conn->exec($query);
                } catch(PDOException $e) {
                    throw new Exception("执行SQL语句失败: " . $e->getMessage() . "\n SQL: " . $query);
                }
            }
        }
        
        // 第五步：创建管理员账号
        $response['step'] = '创建管理员账号';
        try {
            $stmt = $conn->prepare("INSERT INTO `admins` (username, password) VALUES (?, ?)");
            if (!$stmt->execute([$admin_user, $admin_pass])) {
                throw new Exception("创建管理员账号失败");
            }
            
            // 验证管理员账号是否创建成功
            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->execute([$admin_user]);
            if (!$stmt->fetch()) {
                throw new Exception("管理员账号验证失败");
            }
        } catch (Exception $e) {
            throw new Exception("创建管理员账号失败: " . $e->getMessage());
        }
        
        // 第六步：更新API设置
        $response['step'] = '更新系统设置';
        $api_key = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'api_key'");
        $stmt->execute([$api_key]);
        $stmt = $conn->prepare("UPDATE settings SET value = '0' WHERE name = 'api_enabled'");
        $stmt->execute();
        
        // 创建配置文件和锁定文件
        $response['step'] = '生成配置文件';
        $config_content = "<?php
define('DB_HOST', '$host');
define('DB_USER', '$username');
define('DB_PASS', '$password');
define('DB_NAME', '$database');
";
        file_put_contents("../config.php", $config_content);
        file_put_contents("../install.lock", date('Y-m-d H:i:s'));
        
        $response['status'] = 'success';
        $response['message'] = '安装成功';
        
    } catch(PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = "数据库错误: " . $e->getMessage();
        if(isset($response['step'])) {
            $response['message'] = "{$response['step']}: " . $response['message'];
        }
    } catch(Exception $e) {
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
    }
    
    die(json_encode($response, JSON_UNESCAPED_UNICODE));
}

// 系统检测函数
function checkSystem() {
    $requirements = array();
    
    // 检查PHP版本
    $requirements['php_version'] = array(
        'name' => 'PHP版本',
        'required' => '≥ 7.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.0.0', '>=')
    );

    
    // 检查PDO扩展
    $requirements['pdo'] = array(
        'name' => 'PDO扩展',
        'required' => '已安装',
        'current' => extension_loaded('pdo') ? '已安装' : '未安装',
        'status' => extension_loaded('pdo')
    );
    
    // 检查PDO MySQL扩展
    $requirements['pdo_mysql'] = array(
        'name' => 'PDO MySQL扩展',
        'required' => '已安装',
        'current' => extension_loaded('pdo_mysql') ? '已安装' : '未安装',
        'status' => extension_loaded('pdo_mysql')
    );
    
    return $requirements;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>小小怪卡密验证系统-系统安装</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            position: relative;
            padding-bottom: 60px;
            box-sizing: border-box;
        }
        
        /* 步骤导航样式 */
        .step-header {
            background: white;
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .step-header .step {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            border-radius: 20px;
            background: #ecf0f1;
            color: #7f8c8d;
            transition: all 0.3s;
        }
        
        .step-header .step.active {
            background: #3498db;
            color: white;
        }
        
        .step-header .step.completed {
            background: #2ecc71;
            color: white;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* 欢迎内容滚动区域 */
        .welcome-content {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .welcome-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .welcome-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .welcome-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .welcome-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* 标题样式 */
        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            position: relative;
            padding-bottom: 10px;
        }
        
        /* 环境检测项样式 */
        .requirement-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .requirement-item.success {
            border-left: 4px solid #2ecc71;
        }
        
        .requirement-item.error {
            border-left: 4px solid #e74c3c;
        }
        
        .requirement-item .status-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
        }
        
        .success .status-icon {
            color: #2ecc71;
        }
        
        .error .status-icon {
            color: #e74c3c;
        }
        
        /* 卡片样式 */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        /* 表单样式 */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
            outline: none;
        }
        
        /* 按钮样式 */
        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        button {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        button[type="submit"] {
            background: #3498db;
            color: white;
        }
        
        button[type="submit"]:hover {
            background: #2980b9;
        }
        
        button[disabled] {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .prev-btn {
            background: #ecf0f1;
            color: #2c3e50;
        }
        
        .prev-btn:hover {
            background: #bdc3c7;
        }
        
        .footer-copyright {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: #fff;
            text-align: center;
            border-top: 1px solid #eee;
            height: 60px;
            box-sizing: border-box;
            z-index: 1000;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        /* 提示框样式 */
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            position: relative;
            padding-left: 45px;
        }
        
        .alert:before {
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .alert-info {
            background: #e1f0fa;
            border: 1px solid #3498db;
            color: #2980b9;
        }
        
        .alert-info:before {
            content: "\f05a";
            color: #3498db;
        }
        
        .alert-error {
            background: #fae1e1;
            border: 1px solid #e74c3c;
            color: #c0392b;
        }
        
        .alert-error:before {
            content: "\f071";
            color: #e74c3c;
        }
        
        /* 安装步骤样式 */
        .install-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            position: relative;
        }
        
        .install-step {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .install-step .step-icon {
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
            background: #ecf0f1;
            display: inline-block;
            margin-bottom: 10px;
            color: #7f8c8d;
        }
        
        .install-step.active .step-icon {
            background: #3498db;
            color: white;
        }
        
        .install-step.completed .step-icon {
            background: #2ecc71;
            color: white;
        }
    </style>
</head>
<body>
    <div class="step-header">
        <div class="container" style="text-align: center;">
            <div class="step <?php echo $_SESSION['install_step'] >= 1 ? 'completed' : ''; ?>">
                <i class="fas fa-home"></i> 欢迎使用
            </div>
            <div class="step <?php echo $_SESSION['install_step'] == 2 ? 'active' : ($_SESSION['install_step'] > 2 ? 'completed' : ''); ?>">
                <i class="fas fa-check-circle"></i> 环境检测
            </div>
            <?php if(!$is_container_env): ?>
            <div class="step <?php echo $_SESSION['install_step'] == 3 ? 'active' : ($_SESSION['install_step'] > 3 ? 'completed' : ''); ?>">
                <i class="fas fa-database"></i> 数据库配置
            </div>
            <div class="step <?php echo $_SESSION['install_step'] == 4 ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> 系统安装
            </div>
            <?php else: ?>
            <div class="step <?php echo $_SESSION['install_step'] == 4 ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> 系统安装
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if($_SESSION['install_step'] == 1): ?>
        <!-- 步骤1：欢迎页面 -->
        <h2>欢迎安装小小怪卡密验证系统</h2>
        <div class="welcome-content" id="welcome-content">
            <h3>系统介绍</h3>
            <p>这是一个安全可靠的卡密验证系统，主要功能包括：</p>
            <ul>
                <li>安全的卡密生成和验证</li>
                <li>完善的后台管理功能</li>
                <li>直观的数据统计</li>
                <li>便捷的用户界面</li>
            </ul>
            
            <h3>安装须知</h3>
            <p>安装本系统需要满足以下条件：</p>
            <ul>
                <li>PHP版本 ≥ 7.0</li>
                <li>PDO扩展支持</li>
                <li>PDO MySQL扩展支持</li>
            </ul>
            
            <h3>使用协议</h3>
            <p>1. 本系统仅供学习交流使用</p>
            <p>2. 请勿用于非法用途</p>
            <p>3. 使用本系统造成的任何问题，开发者不承担责任</p>
            
            <h3>安装说明</h3>
            <p>1. 请确保您的服务器环境满足上述要求</p>
            <p>2. 安装过程中请保持网络连接稳定</p>
            <p>3. 请准备好数据库连接信息</p>
            <p>4. 安装完成后请妥善保管管理员账号信息</p>
            
            <h3>注意事项</h3>
            <p>1. 安装前请备份重要数据</p>
            <p>2. 如果已有数据库，系统会先删除再创建</p>
            <p>3. 请确保数据库用户具有创建数据库的权限</p>
            <p>4. 安装完成后会自动创建配置文件</p>
        </div>
        <form method="POST" id="welcome-form">
            <div class="button-group">
                <div></div> <!-- 空div用于占位，保持按钮右对齐 -->
                <button type="submit" name="next_step" id="next-step" disabled>下一步</button>
            </div>
        </form>
        
        <?php elseif($_SESSION['install_step'] == 2): ?>
        <!-- 步骤2：环境检测 -->
        <h2>系统环境检测</h2>
        <?php
        $requirements = checkSystem();
        $all_passed = true;
        foreach($requirements as $req):
            $class = $req['status'] ? 'success' : 'error';
            if(!$req['status']) $all_passed = false;
        ?>
        <div class="requirement-item <?php echo $class; ?>">
            <span class="status-icon"><?php echo $req['status'] ? '✓' : '×'; ?></span>
            <h4><?php echo $req['name']; ?></h4>
            <p>要求：<?php echo $req['required']; ?></p>
            <p>当前：<?php echo $req['current']; ?></p>
        </div>
        <?php endforeach; ?>
        
        <form method="POST" class="step-form">
            <div class="button-group">
                <button type="submit" name="prev_step" class="prev-btn">上一步</button>
                <button type="submit" name="next_step" <?php echo $all_passed ? '' : 'disabled'; ?>>
                    <?php echo $all_passed ? '下一步' : '环境检测未通过'; ?>
                </button>
            </div>
        </form>
        
        <!-- 添加调试信息 -->
        <script>
        console.log('Current step: <?php echo $_SESSION['install_step']; ?>');
        console.log('All passed: <?php echo $all_passed ? 'true' : 'false'; ?>');
        </script>
        
        <?php elseif($_SESSION['install_step'] == 3 && !$is_container_env): ?>
        <!-- 步骤3：数据库配置（仅非容器环境） -->
        <h2>数据库配置</h2>
        <div class="alert alert-info">
            <h4>安装说明：</h4>
            <ul>
                <li>您可以使用已存在的数据库或让系统创建新数据库</li>
                <li>如果数据库不存在且用户有创建权限,系统会自动创建</li>
                <li>如果数据库已存在,请确保用户具有以下权限:
                    <ul>
                        <li>CREATE TABLE - 创建表</li>
                        <li>DROP TABLE - 删除表(如果需要覆盖安装)</li>
                        <li>INSERT - 插入数据</li>
                        <li>SELECT/UPDATE/DELETE - 基本的数据操作权限</li>
                    </ul>
                </li>
                <li>如果您没有创建数据库的权限,请先手动创建数据库</li>
                <li>如果数据库中已存在相关表,系统会尝试删除并重新创建</li>
                <li>请确保填写的数据库信息正确</li>
            </ul>
        </div>
        <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="POST" id="install-form">
            <div class="form-group">
                <label>数据库地址：</label>
                <input type="text" name="host" value="localhost" required>
            </div>
            <div class="form-group">
                <label>数据库用户名：</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>数据库密码：</label>
                <input type="password" name="password">
            </div>
            <div class="form-group">
                <label>数据库名：</label>
                <input type="text" name="database" required>
            </div>
            <div class="form-group">
                <label>管理员用户名：</label>
                <input type="text" name="admin_user" required>
            </div>
            <div class="form-group">
                <label>管理员密码：</label>
                <input type="password" name="admin_pass" required>
            </div>
            <div class="button-group">
                <button type="submit" name="prev_step" class="prev-btn">上一步</button>
                <button type="submit" name="install" id="install-btn">开始安装</button>
            </div>
        </form>

        <?php else: ?>
        <!-- 步骤4：管理员配置（容器环境）或最终安装步骤 -->
        <h2><?php echo $is_container_env ? '管理员配置' : '系统安装'; ?></h2>

        <?php if($is_container_env): ?>
        <div class="alert alert-info">
            <h4>容器环境检测：</h4>
            <ul>
                <li>✅ 检测到您正在使用Docker容器部署</li>
                <li>✅ 数据库已自动配置：<?php echo $container_db_config['database']; ?></li>
                <li>✅ 数据库连接已就绪，无需手动配置</li>
                <li>🔧 请设置管理员账号以完成安装</li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="POST" id="install-form">
            <?php if(!$is_container_env): ?>
            <!-- 非容器环境显示完整的数据库配置 -->
            <div class="form-group">
                <label>数据库地址：</label>
                <input type="text" name="host" value="localhost" required>
            </div>
            <div class="form-group">
                <label>数据库用户名：</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>数据库密码：</label>
                <input type="password" name="password">
            </div>
            <div class="form-group">
                <label>数据库名：</label>
                <input type="text" name="database" required>
            </div>
            <?php endif; ?>

            <!-- 管理员配置（所有环境都需要） -->
            <div class="form-group">
                <label>管理员用户名：</label>
                <input type="text" name="admin_user" required>
            </div>
            <div class="form-group">
                <label>管理员密码：</label>
                <input type="password" name="admin_pass" required>
            </div>
            <div class="button-group">
                <button type="submit" name="prev_step" class="prev-btn">上一步</button>
                <button type="submit" name="install" id="install-btn">开始安装</button>
            </div>
        </form>

        <div class="install-progress" id="install-progress">
            <div class="progress-bar">
                <div class="progress-bar-fill" id="progress-bar-fill"></div>
            </div>
            <div class="progress-text" id="progress-text">正在安装中...</div>
            <div class="install-steps">
                <div class="install-step" data-step="1">
                    <span class="step-icon">○</span>连接数据库
                </div>
                <?php if(!$is_container_env): ?>
                <div class="install-step" data-step="2">
                    <span class="step-icon">○</span>创建数据库
                </div>
                <?php endif; ?>
                <div class="install-step" data-step="3">
                    <span class="step-icon">○</span>创建数据表
                </div>
                <div class="install-step" data-step="4">
                    <span class="step-icon">○</span>创建管理员账号
                </div>
                <div class="install-step" data-step="5">
                    <span class="step-icon">○</span>生成配置文件
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> 小小怪卡密系统 - All Rights Reserved
        </div>
    </footer>

    <script>
        // 滚动检测代码
        const welcomeContent = document.getElementById('welcome-content');
        const nextStepBtn = document.getElementById('next-step');
        
        if(welcomeContent && nextStepBtn) {
            welcomeContent.addEventListener('scroll', function() {
                if(welcomeContent.scrollHeight - welcomeContent.scrollTop <= welcomeContent.clientHeight + 1) {
                    nextStepBtn.removeAttribute('disabled');
                    nextStepBtn.style.display = 'block';
                }
            });
        }

        // 修改安装进度显示代码
        const installForm = document.getElementById('install-form');
        const installProgress = document.getElementById('install-progress');
        const progressBarFill = document.getElementById('progress-bar-fill');
        const progressText = document.getElementById('progress-text');
        const installSteps = document.querySelectorAll('.install-step');

        if(installForm) {
            installForm.addEventListener('submit', async function(e) {
                // 如果点击的是返回按钮，直接返回
                if(e.submitter && e.submitter.name === 'prev_step') {
                    return true;
                }
                
                // 如果点击的是安装按钮
                if(e.submitter && e.submitter.name === 'install') {
                    e.preventDefault();
                    
                    // 显示进度条
                    installProgress.style.display = 'block';
                    document.getElementById('install-btn').disabled = true;
                    document.querySelector('.prev-btn').style.display = 'none';
                    
                    try {
                        const formData = new FormData(installForm);
                        formData.append('install', 'true');
                        
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        // 更新进度显示
                        progressText.textContent = result.step || '正在处理...';
                        
                        if(result.status === 'success') {
                            progressText.innerHTML = '<div class="success-message">安装成功！正在跳转...</div>';
                            setTimeout(() => {
                                window.location.href = '../admin.php';
                            }, 1500);
                        } else {
                            throw new Error(result.message);
                        }
                    } catch(error) {
                        progressText.innerHTML = `<div class="error-message">
                            <strong>安装失败：</strong><br>
                            ${error.message}
                        </div>`;
                        document.getElementById('install-btn').disabled = false;
                        document.querySelector('.prev-btn').style.display = 'block';
                    }
                    
                    return false;
                }
            });
        }
    </script>

    <style>
        .success-message {
            color: #28a745;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin-top: 10px;
            border-radius: 3px;
        }

        .error-message {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-top: 10px;
            border-radius: 3px;
        }
    </style>

    <?php if($error) {
        echo '<div class="alert alert-danger">';
        echo '<h4>安装错误</h4>';
        echo '<p>' . $error . '</p>';
        echo '<div class="help-text">';
        echo '<p>可能的解决方案：</p>';
        echo '<ul>';
        echo '<li>确保数据库用户具有足够权限（CREATE, DROP, ALTER等）</li>';
        echo '<li>手动创建数据库后再安装</li>';
        echo '<li>使用其他有足够权限的数据库用户</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
    } ?>
</body>
</html> 