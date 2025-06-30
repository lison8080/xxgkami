<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../admin.php");
    exit;
}

require_once '../config.php';

// 初始化变量
$products = [];
$error = null;
$success = null;

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 处理商品操作
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sort_order = intval($_POST['sort_order'] ?? 0);
                
                if(empty($name)) {
                    $error = "商品名称不能为空";
                } else {
                    // 检查商品名称是否已存在
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE name = ?");
                    $stmt->execute([$name]);
                    if($stmt->fetchColumn() > 0) {
                        $error = "商品名称已存在";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO products (name, description, sort_order) VALUES (?, ?, ?)");
                        if($stmt->execute([$name, $description, $sort_order])) {
                            $success = "商品添加成功";
                        } else {
                            $error = "商品添加失败";
                        }
                    }
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sort_order = intval($_POST['sort_order'] ?? 0);
                
                if($id <= 0) {
                    $error = "无效的商品ID";
                } elseif(empty($name)) {
                    $error = "商品名称不能为空";
                } else {
                    // 检查商品名称是否已存在（排除当前商品）
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE name = ? AND id != ?");
                    $stmt->execute([$name, $id]);
                    if($stmt->fetchColumn() > 0) {
                        $error = "商品名称已存在";
                    } else {
                        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, sort_order = ? WHERE id = ?");
                        if($stmt->execute([$name, $description, $sort_order, $id])) {
                            $success = "商品更新成功";
                        } else {
                            $error = "商品更新失败";
                        }
                    }
                }
                break;
                
            case 'toggle_status':
                $id = intval($_POST['id'] ?? 0);
                if($id <= 0) {
                    $error = "无效的商品ID";
                } elseif($id == 1) {
                    $error = "默认商品不能禁用";
                } else {
                    $stmt = $conn->prepare("UPDATE products SET status = 1 - status WHERE id = ?");
                    if($stmt->execute([$id])) {
                        $success = "商品状态更新成功";
                    } else {
                        $error = "商品状态更新失败";
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if($id <= 0) {
                    $error = "无效的商品ID";
                } elseif($id == 1) {
                    $error = "默认商品不能删除";
                } else {
                    // 检查是否有卡密关联到此商品
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM cards WHERE product_id = ?");
                    $stmt->execute([$id]);
                    if($stmt->fetchColumn() > 0) {
                        $error = "该商品下还有卡密，无法删除";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                        if($stmt->execute([$id])) {
                            $success = "商品删除成功";
                        } else {
                            $error = "商品删除失败";
                        }
                    }
                }
                break;
        }
    }

    // 获取商品列表
    $stmt = $conn->prepare("
        SELECT p.*, 
               COUNT(c.id) as card_count,
               COUNT(CASE WHEN c.status = 0 THEN 1 END) as unused_count,
               COUNT(CASE WHEN c.status = 1 THEN 1 END) as used_count
        FROM products p 
        LEFT JOIN cards c ON p.id = c.product_id 
        GROUP BY p.id 
        ORDER BY p.sort_order ASC, p.id ASC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error = "数据库错误：" . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品管理 - 小小怪卡密系统</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #f5f6fa;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
            padding-bottom: 60px;
        }

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

        .logo {
            text-align: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo h2 {
            margin: 0;
            font-size: 24px;
            color: #fff;
        }

        .menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .menu li {
            padding: 0;
            margin: 0;
        }

        .menu li a {
            display: block;
            padding: 15px 20px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
        }

        .menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .menu li:hover a,
        .menu li.active a {
            background: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f6fa;
            min-height: calc(100vh - 60px);
            position: relative;
        }

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

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn i {
            margin-right: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #56ab2f, #a8e6cf);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
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

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h4 {
            color: #333;
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #333;
        }

        .modal-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            text-align: right;
        }

        .modal-footer .btn {
            margin-left: 10px;
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
            left: 250px;
            right: 0;
            background: #fff;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #eee;
            height: 60px;
            box-sizing: border-box;
            z-index: 1000;
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
                <li class="active"><a href="products.php"><i class="fas fa-box"></i>商品管理</a></li>
                <li><a href="index.php"><i class="fas fa-key"></i>卡密管理</a></li>
                <li><a href="stats.php"><i class="fas fa-chart-line"></i>数据统计</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i>系统设置</a></li>
                <li><a href="api_settings.php"><i class="fas fa-code"></i>API接口</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i>退出登录</a></li>
            </ul>
            <div class="sidebar-footer">
                &copy; <?php echo date('Y'); ?> 小小怪卡密系统
            </div>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h2><i class="fas fa-box"></i> 商品管理</h2>
                <div class="user-info">
                    <img src="../assets/images/avatar.png" alt="avatar">
                    <span>欢迎，<?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                </div>
            </div>

            <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>商品列表</h3>
                    <button type="button" class="btn btn-primary" onclick="showAddModal()">
                        <i class="fas fa-plus"></i> 添加商品
                    </button>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>商品名称</th>
                                <th>描述</th>
                                <th>状态</th>
                                <th>排序</th>
                                <th>卡密统计</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): ?>
                            <tr>
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['description'] ?: '-'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $product['status'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $product['status'] ? '启用' : '禁用'; ?>
                                    </span>
                                </td>
                                <td><?php echo $product['sort_order']; ?></td>
                                <td>
                                    总计: <?php echo $product['card_count']; ?><br>
                                    <small>未用: <?php echo $product['unused_count']; ?> | 已用: <?php echo $product['used_count']; ?></small>
                                </td>
                                <td><?php echo $product['create_time']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                        <i class="fas fa-edit"></i> 编辑
                                    </button>
                                    <?php if($product['id'] != 1): ?>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="toggleStatus(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-power-off"></i> <?php echo $product['status'] ? '禁用' : '启用'; ?>
                                    </button>
                                    <?php if($product['card_count'] == 0): ?>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash"></i> 删除
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-copyright">
        &copy; <?php echo date('Y'); ?> 小小怪卡密系统 - All Rights Reserved
    </footer>

    <!-- 添加商品模态框 -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>添加商品</h4>
                <button type="button" class="close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="add_name">商品名称 *</label>
                    <input type="text" id="add_name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="add_description">商品描述</label>
                    <textarea id="add_description" name="description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="add_sort_order">排序权重</label>
                    <input type="number" id="add_sort_order" name="sort_order" class="form-control" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">取消</button>
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 编辑商品模态框 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>编辑商品</h4>
                <button type="button" class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                <div class="form-group">
                    <label for="edit_name">商品名称 *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">商品描述</label>
                    <textarea id="edit_description" name="description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_sort_order">排序权重</label>
                    <input type="number" id="edit_sort_order" name="sort_order" class="form-control">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function showEditModal(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_sort_order').value = product.sort_order;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function toggleStatus(id) {
            if(confirm('确定要切换商品状态吗？')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteProduct(id) {
            if(confirm('确定要删除这个商品吗？此操作不可恢复！')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
