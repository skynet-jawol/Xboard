# Xboard Docker 部署指南

## 1. 环境要求

- 操作系统：支持 Linux、macOS 或 Windows
- Docker 20.10.0 或更高版本
- Docker Compose v2.0.0 或更高版本
- 可访问的服务器（支持x86_64/ARM64架构）
- 开放端口：7001（默认Web端口）

## 2. 快速部署

### 2.1 基础部署（推荐新手使用）

1. 获取项目文件：
```bash
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```

2. 快速安装（使用SQLite数据库）：
```bash
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    web php artisan xboard:install
```

3. 启动服务：
```bash
docker compose up -d
```

4. 访问站点：
- 网站地址：http://服务器IP:7001
- 请务必保存安装完成后显示的管理员登录信息

### 2.2 自定义部署（适合高级用户）

1. 获取项目文件：
```bash
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```

2. 配置环境变量：
```bash
cp .env.example .env
```

3. 编辑.env文件，配置以下必要信息：
- 数据库配置（支持MySQL/MariaDB）
  ```
  DB_CONNECTION=mysql
  DB_HOST=数据库地址
  DB_PORT=3306
  DB_DATABASE=数据库名
  DB_USERNAME=用户名
  DB_PASSWORD=密码
  ```
- Redis配置
  ```
  REDIS_HOST=redis地址
  REDIS_PASSWORD=密码（如果有）
  REDIS_PORT=6379
  ```
- 站点URL配置
  ```
  APP_URL=http://您的域名
  ```

4. 自定义安装：
```bash
docker compose run -it --rm web php artisan xboard:install
```

5. 启动服务：
```bash
docker compose up -d
```

## 3. 目录结构说明

```
├── .docker/            # Docker相关配置文件
├── storage/            # 存储目录
│   ├── logs/          # 日志文件
│   └── theme/         # 主题文件
├── .env               # 环境配置文件
└── docker-compose.yml # Docker编排配置
```

## 4. 常见问题

### 4.1 端口占用
如果7001端口被占用，可以修改docker-compose.yml中的端口映射：
```yaml
ports:
  - 新端口:7001
```

### 4.2 数据持久化
默认情况下，以下目录会被持久化：
- .docker/.data/：数据文件
- storage/logs/：日志文件
- storage/theme/：主题文件

### 4.3 服务管理命令

- 启动服务：`docker compose up -d`
- 停止服务：`docker compose down`
- 查看日志：`docker compose logs -f`
- 重启服务：`docker compose restart`

### 4.4 数据备份

1. 备份数据目录：
```bash
tar -czf xboard-data-backup.tar.gz .docker/data storage
```

2. 备份数据库（如果使用MySQL）：
```bash
docker compose exec mysql mysqldump -u用户名 -p密码 数据库名 > backup.sql
```

## 5. 升级说明

1. 备份数据：
```bash
tar -czf xboard-backup.tar.gz .docker/data storage .env
```

2. 获取最新代码：
```bash
git pull
```

3. 更新依赖：
```bash
docker compose pull
```

4. 重启服务：
```bash
docker compose down
docker compose up -d
```

## 6. 安全建议

1. 修改默认端口
2. 设置强密码
3. 定期备份数据
4. 及时更新系统
5. 配置防火墙规则

## 7. 技术支持

- 官方文档：https://github.com/cedar2025/Xboard/tree/main/docs
- 问题反馈：通过GitHub Issues
- 功能建议：通过GitHub Discussions
- 安全漏洞：请通过私密渠道报告