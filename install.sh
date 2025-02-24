#!/bin/bash

# 设置颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# 打印带颜色的信息
info() {
    echo -e "${GREEN}[信息]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[警告]${NC} $1"
}

error() {
    echo -e "${RED}[错误]${NC} $1"
}

# 检查是否为root用户
check_root() {
    if [[ $EUID -ne 0 ]]; then
        error "此脚本需要root权限运行"
        error "请使用 sudo -i 切换到root用户后再运行"
        exit 1
    fi
}

# 检查系统环境
check_system() {
    info "检查系统环境..."
    
    # 检查Docker
    if ! command -v docker &> /dev/null; then
        error "未检测到Docker，开始安装Docker..."
        curl -fsSL https://get.docker.com | sh
        systemctl enable docker
        systemctl start docker
    else
        info "Docker已安装"
    fi

    # 检查Docker Compose
    if ! command -v docker compose &> /dev/null; then
        error "未检测到Docker Compose，开始安装Docker Compose..."
        DOCKER_CONFIG=${DOCKER_CONFIG:-$HOME/.docker}
        mkdir -p $DOCKER_CONFIG/cli-plugins
        curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m) -o $DOCKER_CONFIG/cli-plugins/docker-compose
        chmod +x $DOCKER_CONFIG/cli-plugins/docker-compose
    else
        info "Docker Compose已安装"
    fi
}

# 下载Xboard
download_xboard() {
    info "开始下载Xboard..."
    if [ -d "Xboard" ]; then
        warn "Xboard目录已存在，将被删除"
        rm -rf Xboard
    fi
    git clone -b compose --depth 1 https://github.com/cedar2025/Xboard
    cd Xboard || exit
}

# 配置Xboard
setup_xboard() {
    info "开始配置Xboard..."
    
    # 设置默认配置
    info "使用SQLite数据库进行快速部署"
    
    # 运行安装命令
    docker compose run -it --rm \
        -e ENABLE_SQLITE=true \
        -e ENABLE_REDIS=true \
        -e ADMIN_ACCOUNT=admin@demo.com \
        web php artisan xboard:install

    if [ $? -ne 0 ]; then
        error "Xboard安装失败"
        exit 1
    fi
}

# 启动服务
start_service() {
    info "启动Xboard服务..."
    docker compose up -d
    
    if [ $? -eq 0 ]; then
        info "Xboard服务启动成功！"
        info "请访问 http://服务器IP:7001 进行访问"
        info "请务必保存安装完成后显示的管理员登录信息"
    else
        error "Xboard服务启动失败"
        exit 1
    fi
}

# 主函数
main() {
    echo -e "${GREEN}======================${NC}"
    echo -e "${GREEN}  Xboard 一键安装脚本  ${NC}"
    echo -e "${GREEN}======================${NC}"
    
    check_root
    check_system
    download_xboard
    setup_xboard
    start_service
}

# 执行主函数
main