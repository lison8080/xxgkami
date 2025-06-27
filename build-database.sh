#!/bin/bash

# 数据库集成版本构建脚本

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# 显示可用版本
show_versions() {
    echo "可用版本："
    echo "  1. allinone   - 单容器集成版本 (MySQL + Nginx + PHP-FPM)"
    echo "  2. preloaded  - 数据预装版本 (包含完整数据库和配置)"
    echo "  3. optimized  - 优化版本 (最小化镜像大小)"
    echo "  4. portable   - 便携版本 (完全自包含，无外部依赖)"
    echo ""
}

# 构建指定版本
build_version() {
    local version=$1
    
    case $version in
        "allinone")
            print_message "构建单容器集成版本..."
            sudo docker-compose -f docker-compose.allinone.yml build --no-cache
            ;;
        "preloaded")
            print_message "构建数据预装版本..."
            sudo docker build -f Dockerfile.preloaded -t xxgkami:preloaded .
            ;;
        "optimized")
            print_message "构建优化版本..."
            sudo docker build -f Dockerfile.optimized -t xxgkami:optimized .
            ;;
        "portable")
            print_message "构建便携版本..."
            sudo docker-compose -f docker-compose.portable.yml build --no-cache
            ;;
        *)
            print_error "未知版本: $version"
            show_versions
            exit 1
            ;;
    esac
}

# 启动指定版本
start_version() {
    local version=$1
    
    case $version in
        "allinone")
            print_message "启动单容器集成版本..."
            sudo docker-compose -f docker-compose.allinone.yml up -d
            ;;
        "preloaded")
            print_message "启动数据预装版本..."
            sudo docker run -d --name xxgkami-preloaded -p 19999:19999 xxgkami:preloaded
            ;;
        "optimized")
            print_message "启动优化版本..."
            sudo docker run -d --name xxgkami-optimized -p 19999:19999 xxgkami:optimized
            ;;
        "portable")
            print_message "启动便携版本..."
            sudo docker-compose -f docker-compose.portable.yml up -d
            ;;
        *)
            print_error "未知版本: $version"
            show_versions
            exit 1
            ;;
    esac
}

# 停止指定版本
stop_version() {
    local version=$1
    
    case $version in
        "allinone")
            sudo docker-compose -f docker-compose.allinone.yml down
            ;;
        "preloaded")
            sudo docker stop xxgkami-preloaded 2>/dev/null || true
            sudo docker rm xxgkami-preloaded 2>/dev/null || true
            ;;
        "optimized")
            sudo docker stop xxgkami-optimized 2>/dev/null || true
            sudo docker rm xxgkami-optimized 2>/dev/null || true
            ;;
        "portable")
            sudo docker-compose -f docker-compose.portable.yml down
            ;;
        *)
            print_error "未知版本: $version"
            show_versions
            exit 1
            ;;
    esac
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
}

# 显示帮助
show_help() {
    echo "数据库集成版本构建脚本"
    echo ""
    echo "用法: $0 [命令] [版本]"
    echo ""
    echo "命令:"
    echo "  build [版本]    - 构建指定版本"
    echo "  start [版本]    - 启动指定版本"
    echo "  stop [版本]     - 停止指定版本"
    echo "  restart [版本]  - 重启指定版本"
    echo "  status          - 查看运行状态"
    echo "  versions        - 显示可用版本"
    echo "  help            - 显示此帮助信息"
    echo ""
    show_versions
    echo "示例:"
    echo "  $0 build allinone     # 构建单容器版本"
    echo "  $0 start preloaded    # 启动预装版本"
    echo "  $0 stop portable      # 停止便携版本"
}

# 主函数
main() {
    print_header
    
    local command=${1:-help}
    local version=${2:-}
    
    case $command in
        "build")
            if [ -z "$version" ]; then
                print_error "请指定要构建的版本"
                show_versions
                exit 1
            fi
            build_version "$version"
            print_message "构建完成！"
            ;;
        "start")
            if [ -z "$version" ]; then
                print_error "请指定要启动的版本"
                show_versions
                exit 1
            fi
            start_version "$version"
            print_message "启动完成！访问地址: http://localhost:19999"
            ;;
        "stop")
            if [ -z "$version" ]; then
                print_error "请指定要停止的版本"
                show_versions
                exit 1
            fi
            stop_version "$version"
            print_message "停止完成！"
            ;;
        "restart")
            if [ -z "$version" ]; then
                print_error "请指定要重启的版本"
                show_versions
                exit 1
            fi
            stop_version "$version"
            sleep 2
            start_version "$version"
            print_message "重启完成！"
            ;;
        "status")
            show_status
            ;;
        "versions")
            show_versions
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
