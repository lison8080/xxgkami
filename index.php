<?php
session_start();
if(!file_exists("install.lock")){
    header("Location: install/index.php");
    exit;
}

require_once 'config.php';

// 初始化错误变量
$error = null;

// 建立数据库连接
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取统计数据
    $total = $conn->query("SELECT COUNT(*) FROM cards")->fetchColumn();
    $used = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1")->fetchColumn();
    $unused = $total - $used;
    $usage_rate = $total > 0 ? round(($used / $total) * 100, 1) : 0;

    // 获取网站标题和副标题
    $stmt = $conn->prepare("SELECT name, value FROM settings WHERE name IN (
        'site_title', 'site_subtitle', 'copyright_text',
        'contact_qq_group', 'contact_wechat_qr', 'contact_email'
    )");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $site_title = $settings['site_title'] ?? '小小怪卡密验证系统';
    $site_subtitle = $settings['site_subtitle'] ?? '专业的卡密验证解决方案';
    $copyright_text = $settings['copyright_text'] ?? '小小怪卡密系统 - All Rights Reserved';
    $contact_qq_group = $settings['contact_qq_group'] ?? '123456789';
    $contact_wechat_qr = $settings['contact_wechat_qr'] ?? 'assets/images/wechat-qr.jpg';
    $contact_email = $settings['contact_email'] ?? 'support@example.com';

    // 获取轮播图数据
    try {
        $stmt = $conn->query("SELECT * FROM slides WHERE status = 1 ORDER BY sort_order ASC");
        $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $slides = [];
    }

    // 获取系统特点
    try {
        $stmt = $conn->query("SELECT * FROM features WHERE status = 1 ORDER BY sort_order ASC");
        $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $features = [];
    }
} catch(PDOException $e) {
    error_log($e->getMessage());
    $error = "数据库连接失败，请稍后再试";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($site_title); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* 基础样式 */
        :root {
            --primary-color: #3498db;
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

        /* 导航栏基础样式 */
        .navbar {
            position: fixed;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 250px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            z-index: 1000;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: grab;
            user-select: none;
            transition: all 0.3s ease;
        }

        .navbar:active {
            cursor: grabbing;
        }

        .nav-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 5px;
            text-decoration: none;
            color: var(--text-color);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }

        .nav-logo i {
            font-size: 24px;
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-logo span {
            font-size: 18px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-links a,
        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .nav-links a i,
        .dropdown-toggle i {
            font-size: 18px;
            min-width: 25px;
            text-align: center;
        }

        .nav-links a:hover,
        .nav-links a.active,
        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        /* 修改导航栏 */
        .verify-btn-nav {
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white !important;
            border-radius: 25px !important;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            transition: all 0.3s ease;
        }

        .verify-btn-nav:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        /* 主容器样式 */
        .main-container {
            margin-left: 100px;
            margin-right: 20px;
            max-width: none;
        }

        /* 英雄区域拟态效果 */
        .hero-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: var(--shadow-light);
        }

        /* 统计卡片拟态效果 */
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 15px 15px 30px #d1d9e6, -15px -15px 30px #ffffff;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .navbar {
                cursor: default;
            width: 100%;
                left: 0;
                top: 0;
                transform: none;
                border-radius: 0;
                padding: 15px 20px;
        }

            .nav-container {
                flex-direction: row;
                justify-content: space-between;
            align-items: center;
        }

            .nav-logo {
                border-bottom: none;
                padding: 0;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
            background: var(--glass-bg);
            padding: 20px;
                flex-direction: column;
        }

            .nav-links.active {
            display: flex;
            }

            .main-container {
                margin-left: 20px;
                margin-right: 20px;
                margin-top: 80px;
        }
        }

        /* 下拉菜单样式 */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle .fa-chevron-down {
            margin-left: auto;
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .dropdown:hover .fa-chevron-down {
            transform: rotate(-180deg);
        }

        .dropdown-menu {
            position: absolute;
            left: calc(100% + 15px);
            top: 0;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 10px;
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateX(20px);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }

        .dropdown-menu a {
            padding: 10px 15px;
            border-radius: 8px;
        }

        /* 管理员按钮样式 */
        .admin-btn {
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white !important;
            margin-top: auto;
        }

        .admin-btn:hover {
            background: linear-gradient(45deg, #2980b9, #27ae60);
        }

        /* 轮播图样式 */
        .carousel {
            position: relative;
            height: 400px;
            overflow: hidden;
            margin: 30px 0;
            border-radius: 20px;
            box-shadow: var(--shadow-light);
        }

        .carousel-inner {
            height: 100%;
            display: flex;
            transition: transform 0.5s ease-in-out;
        }

        .carousel-item {
            min-width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        /* 特性卡片动画优化 */
        .features-container {
            padding: 40px 0;
        }
        
        .feature-row {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .feature-card {
            flex: 1;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            display: flex;
            gap: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-light);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
        }
        
        .feature-content {
            flex: 1;
        }
        
        .feature-content h3 {
            margin: 0 0 10px;
            color: var(--text-color);
            font-size: 20px;
        }
        
        .feature-content p {
            margin: 0 0 15px;
            color: #666;
            font-size: 14px;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .feature-list li i {
            color: #2ecc71;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .feature-row {
                flex-direction: column;
            }
            
            .feature-card {
                margin-bottom: 20px;
            }
        }

        /* 统计网格 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-light);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 36px;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .stat-card h3 {
            margin: 0 0 10px;
            font-size: 18px;
            color: var(--text-color);
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: var(--text-color);
        }

        /* 联系我们部分样式 */
        .contact-section {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-light);
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .contact-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-light);
        }
        
        .contact-card i {
            font-size: 48px;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        
        .contact-card h3 {
            margin: 0 0 15px;
            color: var(--text-color);
        }
        
        .contact-card p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .contact-btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .contact-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .qr-code {
            max-width: 200px;
            border-radius: 10px;
            margin-top: 10px;
            box-shadow: var(--shadow-light);
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
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <i class="fas fa-key"></i> <?php echo htmlspecialchars($site_title); ?>
            </a>
            <div class="nav-links">
                <a href="index.php" class="active"><i class="fas fa-home"></i> 首页</a>
                <a href="verify.php" class="verify-btn-nav"><i class="fas fa-check-circle"></i> 卡密验证</a>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle">
                        <i class="fas fa-book"></i> 使用文档 <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="#api-docs"><i class="fas fa-code"></i> API文档</a>
                        <a href="#guide"><i class="fas fa-book-open"></i> 使用教程</a>
                        <a href="#faq"><i class="fas fa-question-circle"></i> 常见问题</a>
                    </div>
                </div>
                <a href="#contact"><i class="fas fa-envelope"></i> 联系我们</a>
                <a href="admin.php" class="admin-btn"><i class="fas fa-user-shield"></i> 管理登录</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="hero-section">
            <h1><?php echo htmlspecialchars($site_title); ?></h1>
            <p><?php echo htmlspecialchars($site_subtitle); ?></p>
        </div>

        <?php if(count($slides) > 0): ?>
        <div class="carousel">
            <div class="carousel-inner">
                <?php foreach($slides as $slide): ?>
                <div class="carousel-item">
                    <img src="<?php echo htmlspecialchars($slide['image_url']); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>">
                    <div class="carousel-caption">
                        <h2><?php echo htmlspecialchars($slide['title']); ?></h2>
                        <p><?php echo htmlspecialchars($slide['description']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control prev"><i class="fas fa-chevron-left"></i></button>
            <button class="carousel-control next"><i class="fas fa-chevron-right"></i></button>
            <div class="carousel-indicators"></div>
        </div>
        <?php endif; ?>

        <div class="features-section">
            <h2><i class="fas fa-star"></i> 系统特点</h2>
            <div class="features-container">
                <?php 
                $feature_count = 0;
                foreach($features as $feature):
                    if($feature_count % 2 == 0) echo '<div class="feature-row">';
                ?>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="<?php echo htmlspecialchars($feature['icon']); ?>"></i>
                        </div>
                        <div class="feature-content">
                            <h3><?php echo htmlspecialchars($feature['title']); ?></h3>
                            <?php 
                            $description_parts = explode("\n", $feature['description']);
                            echo '<p>' . htmlspecialchars($description_parts[0]) . '</p>';
                            if(count($description_parts) > 1):
                            ?>
                            <ul class="feature-list">
                                <?php for($i = 1; $i < count($description_parts); $i++): ?>
                                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($description_parts[$i]); ?></li>
                                <?php endfor; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php
                    if($feature_count % 2 == 1 || $feature_count == count($features) - 1) echo '</div>';
                    $feature_count++;
                endforeach;
                ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-key"></i>
                <h3>总卡密数</h3>
                <div class="value"><?php echo $total; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <h3>已使用</h3>
                <div class="value"><?php echo $used; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3>未使用</h3>
                <div class="value"><?php echo $unused; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-percentage"></i>
                <h3>使用率</h3>
                <div class="value"><?php echo $usage_rate; ?>%</div>
            </div>
        </div>

        <div id="contact" class="contact-section">
            <h2><i class="fas fa-envelope"></i> 联系我们</h2>
            <div class="contact-grid">
                <div class="contact-card">
                    <i class="fab fa-qq"></i>
                    <h3>QQ交流群</h3>
                    <p><?php echo htmlspecialchars($contact_qq_group); ?></p>
                    <a href="https://qm.qq.com/cgi-bin/qm/qr?k=<?php echo htmlspecialchars($contact_qq_group); ?>" class="contact-btn" target="_blank">
                        加入群聊
                    </a>
                </div>
                
                <div class="contact-card">
                    <i class="fab fa-weixin"></i>
                    <h3>微信客服</h3>
                    <p>扫码添加客服</p>
                    <img src="<?php echo htmlspecialchars($contact_wechat_qr); ?>" alt="微信二维码" class="qr-code">
                </div>
                
                <div class="contact-card">
                    <i class="fas fa-envelope"></i>
                    <h3>电子邮件</h3>
                    <p><?php echo htmlspecialchars($contact_email); ?></p>
                    <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="contact-btn">
                        发送邮件
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($copyright_text); ?>
        </div>
    </footer>

    <script>
        // 轮播图功能
        document.addEventListener('DOMContentLoaded', function() {
        const carousel = document.querySelector('.carousel');
            if (carousel) {
        const carouselInner = carousel.querySelector('.carousel-inner');
        const items = carousel.querySelectorAll('.carousel-item');
        const prevBtn = carousel.querySelector('.prev');
        const nextBtn = carousel.querySelector('.next');
        const indicators = carousel.querySelector('.carousel-indicators');

        let currentIndex = 0;
        const totalItems = items.length;

        // 创建指示器
        items.forEach((_, index) => {
            const button = document.createElement('button');
            button.addEventListener('click', () => goToSlide(index));
            indicators.appendChild(button);
        });

        const indicatorBtns = indicators.querySelectorAll('button');
        updateIndicators();

        // 自动播放
        let autoplayInterval = setInterval(nextSlide, 5000);

        // 鼠标悬停时暂停自动播放
        carousel.addEventListener('mouseenter', () => clearInterval(autoplayInterval));
        carousel.addEventListener('mouseleave', () => autoplayInterval = setInterval(nextSlide, 5000));

        // 上一张/下一张
        prevBtn.addEventListener('click', prevSlide);
        nextBtn.addEventListener('click', nextSlide);

        function nextSlide() {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        }

        function prevSlide() {
            currentIndex = (currentIndex - 1 + totalItems) % totalItems;
            updateCarousel();
        }

        function goToSlide(index) {
            currentIndex = index;
            updateCarousel();
        }

        function updateCarousel() {
            carouselInner.style.transform = `translateX(-${currentIndex * 100}%)`;
            updateIndicators();
        }

        function updateIndicators() {
            indicatorBtns.forEach((btn, index) => {
                btn.classList.toggle('active', index === currentIndex);
            });
        }
            }
        });
    </script>
</body>
</html> 