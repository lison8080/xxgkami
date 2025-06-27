#!/bin/bash

# 单容器启动脚本

set -e

echo "🚀 启动小小怪卡密验证系统 (All-in-One 版本)..."

# 创建必要的目录
mkdir -p /var/log/nginx
mkdir -p /var/log/supervisor
mkdir -p /var/lib/php/sessions
mkdir -p /var/run/mysqld
mkdir -p /var/log/mysql

# 设置权限
chown -R www-data:www-data /var/www/html
chown -R www-data:www-data /var/log/nginx
chown -R www-data:www-data /var/lib/php
chown -R mysql:mysql /var/run/mysqld
chown -R mysql:mysql /var/log/mysql
chown -R mysql:mysql /var/lib/mysql

# 确保上传目录可写
chmod -R 777 /var/www/html/assets/images

# 初始化 MySQL 数据目录（如果需要）
if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "📝 初始化 MySQL 数据目录..."
    mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql
fi

# 启动 MySQL 服务进行初始化
echo "🗄️ 启动 MySQL 进行初始化..."
mysqld_safe --user=mysql --datadir=/var/lib/mysql --socket=/var/run/mysqld/mysqld.sock &
MYSQL_PID=$!

# 等待 MySQL 启动
echo "⏳ 等待 MySQL 启动..."
for i in {1..30}; do
    if mysqladmin ping -h localhost --silent; then
        echo "✅ MySQL 启动成功"
        break
    fi
    echo "等待 MySQL 启动... ($i/30)"
    sleep 2
done

# 创建数据库和用户
echo "📝 配置数据库..."
mysql -e "CREATE DATABASE IF NOT EXISTS xxgkami CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || true
mysql -e "CREATE USER IF NOT EXISTS 'xxgkami_user'@'localhost' IDENTIFIED BY 'Xxgkami123!';" || true
mysql -e "GRANT ALL PRIVILEGES ON xxgkami.* TO 'xxgkami_user'@'localhost';" || true
mysql -e "FLUSH PRIVILEGES;" || true

# 检查是否需要导入初始数据
if [ -f "/var/www/html/install/install.sql" ] && [ ! -f "/var/www/html/install.lock" ]; then
    echo "📊 导入初始数据库结构..."
    mysql xxgkami < /var/www/html/install/install.sql || true
fi

# 创建配置文件
if [ ! -f "/var/www/html/config.php" ]; then
    echo "📝 创建数据库配置文件..."
    cat > /var/www/html/config.php << EOF
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'xxgkami_user');
define('DB_PASS', 'Xxgkami123!');
define('DB_NAME', 'xxgkami');
EOF
    chown www-data:www-data /var/www/html/config.php
    echo "✅ 配置文件创建完成"
fi

# 停止临时 MySQL 进程
echo "🔄 重启服务管理..."
mysqladmin shutdown -h localhost 2>/dev/null || true
sleep 2

# 修复权限问题
echo "🔧 修复权限..."
usermod -a -G mysql www-data
chmod 755 /var/run/mysqld
chown -R www-data:www-data /var/www/html

# 检查是否已安装
if [ ! -f "/var/www/html/install.lock" ]; then
    echo "⚠️  系统尚未安装，请访问 http://your-ip:19999/install/ 进行安装"
else
    echo "✅ 系统已安装"
fi

echo "🎉 启动完成！访问地址: http://your-ip:19999"

# 启动 supervisor 管理所有服务
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
