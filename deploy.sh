#!/bin/bash

# 数据库集成版本构建脚本

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 镜像目录
IMAGE_DIR="./image"
IMAGE_NAME="xxgkami-allinone"
IMAGE_TAG="latest"
IMAGE_FILE="${IMAGE_DIR}/${IMAGE_NAME}-${IMAGE_TAG}.tar"

# 获取本机IP地址
get_local_ip() {
    # 尝试多种方法获取本机IP
    local ip=""

    # 方法1: 使用hostname -I
    if command -v hostname >/dev/null 2>&1; then
        ip=$(hostname -I | awk '{print $1}')
    fi

    # 方法2: 使用ip route
    if [ -z "$ip" ] && command -v ip >/dev/null 2>&1; then
        ip=$(ip route get 8.8.8.8 | awk '{print $7; exit}' 2>/dev/null)
    fi

    # 方法3: 使用ifconfig
    if [ -z "$ip" ] && command -v ifconfig >/dev/null 2>&1; then
        ip=$(ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -n1)
    fi

    # 默认使用localhost
    if [ -z "$ip" ]; then
        ip="localhost"
    fi

    echo "$ip"
}

print_message() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}"
    echo "=================================================="
    echo "    小小怪卡密验证系统 - 数据库集成版本"
    echo "=================================================="
    echo -e "${NC}"
}

# 显示帮助信息
show_info() {
    echo "小小怪卡密验证系统 - 单容器集成版本"
    echo "包含: MySQL + Nginx + PHP-FPM"
    echo ""
}

# 创建镜像目录
create_image_dir() {
    if [ ! -d "$IMAGE_DIR" ]; then
        print_message "创建镜像目录: $IMAGE_DIR"
        mkdir -p "$IMAGE_DIR"
    fi
}

# 构建容器并保存镜像
build_container() {
    print_message "构建单容器集成版本..."
    sudo docker-compose -f docker-compose.allinone.yml build --no-cache

    print_message "保存镜像到文件..."
    create_image_dir

    # 获取镜像ID
    local image_id=$(sudo docker images --format "table {{.Repository}}:{{.Tag}}\t{{.ID}}" | grep "xxgkami.*allinone" | awk '{print $2}' | head -n1)

    if [ -n "$image_id" ]; then
        print_message "导出镜像 $image_id 到 $IMAGE_FILE"
        sudo docker save -o "$IMAGE_FILE" "$image_id"
        print_message "镜像已保存到: $IMAGE_FILE"

        # 显示文件大小
        local file_size=$(du -h "$IMAGE_FILE" | cut -f1)
        print_message "镜像文件大小: $file_size"
    else
        print_error "未找到构建的镜像"
        return 1
    fi
}

# 从镜像文件启动容器
start_container() {
    if [ -f "$IMAGE_FILE" ]; then
        print_message "从镜像文件启动容器..."
        print_message "加载镜像文件: $IMAGE_FILE"
        sudo docker load -i "$IMAGE_FILE"

        print_message "启动单容器集成版本..."
        sudo docker-compose -f docker-compose.allinone.yml up -d

        local local_ip=$(get_local_ip)
        print_message "启动完成！访问地址: http://${local_ip}:19999"
    else
        print_warning "镜像文件不存在: $IMAGE_FILE"
        print_message "尝试直接启动容器..."
        sudo docker-compose -f docker-compose.allinone.yml up -d

        local local_ip=$(get_local_ip)
        print_message "启动完成！访问地址: http://${local_ip}:19999"
    fi
}

# 停止容器
stop_container() {
    print_message "停止单容器集成版本..."
    sudo docker-compose -f docker-compose.allinone.yml down
}

# 查看状态
show_status() {
    print_message "查看运行状态..."
    echo ""
    echo "Docker 容器状态："
    sudo docker ps -a | grep xxgkami || echo "没有运行的 xxgkami 容器"
    echo ""
    echo "端口占用情况："
    sudo netstat -tlnp | grep :19999 || echo "端口 19999 未被占用"
    echo ""

    local local_ip=$(get_local_ip)
    echo "访问地址: http://${local_ip}:19999"

    if [ -f "$IMAGE_FILE" ]; then
        local file_size=$(du -h "$IMAGE_FILE" | cut -f1)
        echo "镜像文件: $IMAGE_FILE (大小: $file_size)"
    else
        echo "镜像文件: 不存在"
    fi
}

# 备份数据库
backup_database() {
    local backup_dir="./backup"
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local backup_file="${backup_dir}/xxgkami_backup_${timestamp}.sql"

    print_message "备份数据库..."

    # 检查容器是否运行
    if ! sudo docker ps | grep -q "xxgkami-allinone"; then
        print_error "容器未运行，请先启动容器"
        return 1
    fi

    # 创建备份目录
    if [ ! -d "$backup_dir" ]; then
        mkdir -p "$backup_dir"
        print_message "创建备份目录: $backup_dir"
    fi

    # 执行数据库备份
    print_message "正在备份数据库到: $backup_file"
    sudo docker exec xxgkami-allinone mysqldump -u root -proot123456 xxgkami > "$backup_file"

    if [ $? -eq 0 ]; then
        local file_size=$(du -h "$backup_file" | cut -f1)
        print_message "数据库备份完成！"
        print_message "备份文件: $backup_file"
        print_message "文件大小: $file_size"

        # 列出所有备份文件
        echo ""
        echo "所有备份文件："
        ls -lh "$backup_dir"/*.sql 2>/dev/null || echo "没有找到备份文件"
    else
        print_error "数据库备份失败"
        return 1
    fi
}

# 恢复数据库
restore_database() {
    local backup_dir="./backup"

    print_message "恢复数据库..."

    # 检查容器是否运行
    if ! sudo docker ps | grep -q "xxgkami-allinone"; then
        print_error "容器未运行，请先启动容器"
        return 1
    fi

    # 检查备份目录
    if [ ! -d "$backup_dir" ]; then
        print_error "备份目录不存在: $backup_dir"
        return 1
    fi

    # 列出可用的备份文件
    echo ""
    echo "可用的备份文件："
    local backup_files=($(ls -t "$backup_dir"/*.sql 2>/dev/null))

    if [ ${#backup_files[@]} -eq 0 ]; then
        print_error "没有找到备份文件"
        return 1
    fi

    # 显示备份文件列表
    for i in "${!backup_files[@]}"; do
        local file_info=$(ls -lh "${backup_files[$i]}" | awk '{print $5, $6, $7, $8}')
        echo "  $((i+1)). $(basename "${backup_files[$i]}") ($file_info)"
    done

    # 让用户选择备份文件
    echo ""
    read -p "请选择要恢复的备份文件编号 (1-${#backup_files[@]}): " choice

    if [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -ge 1 ] && [ "$choice" -le ${#backup_files[@]} ]; then
        local selected_file="${backup_files[$((choice-1))]}"
        print_message "选择的备份文件: $(basename "$selected_file")"

        # 确认恢复操作
        echo ""
        print_warning "警告：恢复操作将覆盖当前数据库中的所有数据！"
        read -p "确认要恢复数据库吗？(y/N): " confirm

        if [[ "$confirm" =~ ^[Yy]$ ]]; then
            print_message "正在恢复数据库..."
            sudo docker exec -i xxgkami-allinone mysql -u root -proot123456 xxgkami < "$selected_file"

            if [ $? -eq 0 ]; then
                print_message "数据库恢复完成！"
            else
                print_error "数据库恢复失败"
                return 1
            fi
        else
            print_message "取消恢复操作"
        fi
    else
        print_error "无效的选择"
        return 1
    fi
}

# 显示帮助
show_help() {
    echo "小小怪卡密验证系统 - 单容器集成版本构建脚本"
    echo ""
    echo "用法: $0 [命令]"
    echo ""
    echo "命令:"
    echo "  build     - 构建容器并保存镜像到 image 目录"
    echo "  start     - 从 image 目录的镜像文件启动容器"
    echo "  stop      - 停止容器"
    echo "  restart   - 重启容器"
    echo "  status    - 查看运行状态"
    echo "  backup    - 备份数据库"
    echo "  restore   - 恢复数据库"
    echo "  help      - 显示此帮助信息"
    echo ""
    show_info
    echo "功能说明:"
    echo "  • 构建时自动将镜像保存到 ./image/ 目录"
    echo "  • 启动时优先使用本地镜像文件"
    echo "  • 使用本机IP地址替代localhost，便于跨机器部署"
    echo "  • 支持数据库备份和恢复功能"
    echo ""
    echo "示例:"
    echo "  $0 build      # 构建容器并保存镜像"
    echo "  $0 start      # 从镜像文件启动容器"
    echo "  $0 stop       # 停止容器"
    echo "  $0 restart    # 重启容器"
    echo "  $0 backup     # 备份数据库"
    echo "  $0 restore    # 恢复数据库"
}

# 主函数
main() {
    print_header

    local command=${1:-help}

    case $command in
        "build")
            build_container
            print_message "构建完成！镜像已保存到 $IMAGE_FILE"
            ;;
        "start")
            start_container
            ;;
        "stop")
            stop_container
            print_message "停止完成！"
            ;;
        "restart")
            print_message "重启单容器集成版本..."
            stop_container
            sleep 2
            start_container
            ;;
        "status")
            show_status
            ;;
        "backup")
            backup_database
            ;;
        "restore")
            restore_database
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            print_error "未知命令: $command"
            show_help
            exit 1
            ;;
    esac
}

# 运行主函数
main "$@"
