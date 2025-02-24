# 3x-ui 安装使用指南

## 1. 环境要求

- Linux系统（推荐Ubuntu/Debian）
- Docker环境（如使用Docker部署）
- 开放相应端口（根据具体协议配置）
- 系统内存：建议1GB以上
- 磁盘空间：建议10GB以上

## 2. 安装方式

### 2.1 Docker安装（推荐）

1. 拉取Docker镜像：
```bash
docker pull ghcr.io/cedar2025/3x-ui
```

2. 创建配置目录：
```bash
mkdir -p /etc/3x-ui
mkdir -p /etc/x-ui
```

3. 运行容器：
```bash
docker run -d \
    -e XRAY_VMESS_AEAD_FORCED=false \
    -v /etc/3x-ui/:/etc/3x-ui/ \
    -v /etc/x-ui/:/etc/x-ui/ \
    -p 2053:2053 \
    --name 3x-ui \
    --restart=unless-stopped \
    ghcr.io/cedar2025/3x-ui
```

### 2.2 一键脚本安装

```bash
bash <(curl -Ls https://raw.githubusercontent.com/cedar2025/Xboard/main/server/3x-ui/install.sh)
```

## 3. 面板配置

### 3.1 基础设置

- 面板访问：http://服务器IP:2053
- 默认账号：admin
- 默认密码：admin
- 首次登录后请立即修改密码

### 3.2 节点配置

1. 添加节点：
   - 支持的协议：VMess、VLess、Trojan、Shadowsocks
   - 传输方式：TCP、WebSocket、gRPC等
   - TLS配置：建议开启TLS加密

2. 节点管理：
   - 流量限制：可设置总流量和单用户限速
   - 设备限制：可限制同时在线设备数
   - 节点状态：实时监控在线情况

### 3.3 安全设置

- 面板访问控制
  - 修改面板端口
  - 配置IP访问白名单
  - 启用面板证书加密

- 数据安全
  - 定期备份数据
  - 配置自动备份计划
  - 加密敏感信息

## 4. 系统维护

### 4.1 更新管理

1. Docker版本更新：
```bash
# 拉取新版本
docker pull ghcr.io/cedar2025/3x-ui

# 停止并删除旧容器
docker stop 3x-ui
docker rm 3x-ui

# 使用新镜像重新运行容器
docker run -d \
    -e XRAY_VMESS_AEAD_FORCED=false \
    -v /etc/3x-ui/:/etc/3x-ui/ \
    -v /etc/x-ui/:/etc/x-ui/ \
    -p 2053:2053 \
    --name 3x-ui \
    --restart=unless-stopped \
    ghcr.io/cedar2025/3x-ui
```

2. 一键脚本更新：
```bash
x-ui update
```

### 4.2 数据备份

重要文件路径：
- 配置文件：/etc/3x-ui/
- 数据库文件：/etc/x-ui/x-ui.db

建议定期备份这些文件到安全位置。

### 4.3 性能优化

1. 系统优化：
   - 调整系统参数
   - 优化网络配置
   - 合理分配资源

2. 面板优化：
   - 定期清理日志
   - 优化数据库
   - 合理设置缓存

## 5. 故障排查

### 5.1 常见问题

1. 面板无法访问
   - 检查端口是否开放
   - 确认防火墙设置
   - 验证服务运行状态

2. 节点连接失败
   - 检查端口占用情况
   - 确认协议配置正确
   - 验证证书是否有效

### 5.2 日志查看

- 面板日志：通过Web界面查看
- 系统日志：检查容器日志
```bash
docker logs 3x-ui
```

## 6. 对接Xboard

### 6.1 基础配置

1. 在3x-ui中：
   - 启用gRPC API
   - 配置API密钥
   - 设置允许访问的IP

2. 在Xboard中：
   - 添加3x-ui节点
   - 配置节点信息
   - 测试连接状态

### 6.2 进阶设置

- 配置节点分组
- 设置流量统计
- 配置故障转移
- 启用节点监控

## 7. 技术支持

- 项目地址：https://github.com/cedar2025/Xboard/tree/main/server/3x-ui
- 问题反馈：通过GitHub Issues
- 功能建议：通过GitHub Discussions
- 安全问题：请通过私密渠道报告