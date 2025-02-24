# 3x-ui 对接管理方案文档

## 1. 系统架构设计

### 1.1 整体架构

系统采用前后端分离架构，主要包含以下组件：

- Xboard 管理面板（基于 React + Shadcn UI）
  - 用户管理界面
  - 节点管理界面
  - 统计分析界面
  - 系统设置界面

- 3x-ui 节点服务（Go语言开发）
  - 节点状态管理模块
  - 用户配置管理模块
  - 流量统计模块
  - 系统监控模块

- 通信中间件（基于 gRPC）
  - 服务发现与注册
  - 负载均衡
  - 熔断降级
  - 重试机制

- 数据同步服务
  - 实时同步模块
  - 定时同步模块
  - 数据一致性检查
  - 冲突解决机制

### 1.2 技术栈

- 前端：
  - React 18.x
  - TypeScript 5.x
  - Shadcn UI 组件库
  - TanStack Query 数据获取
  - Zustand 状态管理

- 后端：
  - Laravel 11.x (PHP 8.2+)
  - Go 1.21+
  - gRPC + Protocol Buffers 3
  - OpenTelemetry 可观测性

- 数据存储：
  - MySQL 8.0+
  - Redis 7.0+
  - 时序数据库 (InfluxDB)

- 部署：
  - Docker + Docker Compose
  - Kubernetes (可选)
  - Nginx 反向代理

## 2. 接口设计

### 2.1 gRPC 服务定义

```protobuf
service NodeManager {
  // 节点状态管理
  rpc GetNodeStatus (NodeRequest) returns (NodeStatus) {
    option (google.api.http) = {
      get: "/v1/nodes/{node_id}/status"
    };
  }
  
  rpc UpdateNodeConfig (NodeConfig) returns (OperationResult) {
    option (google.api.http) = {
      put: "/v1/nodes/{node_id}/config"
      body: "*"
    };
  }
  
  // 用户管理
  rpc AddUser (UserInfo) returns (OperationResult) {
    option (google.api.http) = {
      post: "/v1/users"
      body: "*"
    };
  }
  
  rpc RemoveUser (UserInfo) returns (OperationResult) {
    option (google.api.http) = {
      delete: "/v1/users/{user_id}"
    };
  }
  
  rpc UpdateUserQuota (QuotaInfo) returns (OperationResult) {
    option (google.api.http) = {
      patch: "/v1/users/{user_id}/quota"
      body: "*"
    };
  }
  
  // 流量统计
  rpc GetTrafficStats (TrafficRequest) returns (TrafficStats) {
    option (google.api.http) = {
      get: "/v1/traffic/{node_id}"
    };
  }
  
  // 系统监控
  rpc GetSystemMetrics (MetricsRequest) returns (SystemMetrics) {
    option (google.api.http) = {
      get: "/v1/metrics/{node_id}"
    };
  }
}
```

### 2.2 数据模型

```protobuf
// 节点状态信息
message NodeStatus {
  string node_id = 1;                // 节点唯一标识
  string status = 2;                 // 运行状态：running|stopped|error
  float cpu_usage = 3;              // CPU 使用率 (0-100)
  float memory_usage = 4;           // 内存使用率 (0-100)
  float network_in = 5;             // 入站流量 (bytes)
  float network_out = 6;            // 出站流量 (bytes)
  int32 online_users = 7;           // 在线用户数
  string version = 8;               // 节点版本号
  google.protobuf.Timestamp last_heartbeat = 9;  // 最后心跳时间
}

// 用户信息
message UserInfo {
  string user_id = 1;               // 用户唯一标识
  string email = 2;                 // 用户邮箱
  string uuid = 3;                  // 用户 UUID
  int64 quota = 4;                  // 流量配额 (bytes)
  int64 used_quota = 5;             // 已用流量 (bytes)
  int64 expire_time = 6;            // 过期时间戳
  repeated string allowed_protocols = 7;  // 允许的协议类型
  map<string, string> settings = 8;  // 用户自定义设置
}

// 配额信息
message QuotaInfo {
  string user_id = 1;               // 用户唯一标识
  int64 quota = 2;                  // 新的流量配额
  bool reset_used = 3;              // 是否重置已用流量
  string quota_type = 4;            // 配额类型：monthly|total
}

// 流量统计
message TrafficStats {
  string node_id = 1;               // 节点唯一标识
  repeated UserTraffic users = 2;    // 用户流量统计
  google.protobuf.Timestamp start_time = 3;  // 统计开始时间
  google.protobuf.Timestamp end_time = 4;    // 统计结束时间
}

// 用户流量统计
message UserTraffic {
  string user_id = 1;               // 用户唯一标识
  int64 upload = 2;                 // 上传流量 (bytes)
  int64 download = 3;               // 下载流量 (bytes)
  repeated ConnectionInfo connections = 4;  // 连接信息
}

// 连接信息
message ConnectionInfo {
  string source_ip = 1;             // 源 IP
  int32 source_port = 2;            // 源端口
  string dest_ip = 3;               // 目标 IP
  int32 dest_port = 4;              // 目标端口
  string protocol = 5;              // 协议类型
  google.protobuf.Timestamp start_time = 6;  // 连接开始时间
  int64 bytes_transferred = 7;      // 传输字节数
}
```

## 3. 安全认证机制

### 3.1 节点认证

#### 3.1.1 mTLS 配置

```go
// mTLS 配置示例
type TLSConfig struct {
    CertFile        string
    KeyFile         string
    CAFile          string
    VerifyPeer      bool
    ValidityPeriod  time.Duration
}

func NewTLSConfig(config TLSConfig) (*tls.Config, error) {
    cert, err := tls.LoadX509KeyPair(config.CertFile, config.KeyFile)
    if err != nil {
        return nil, fmt.Errorf("load keypair: %w", err)
    }

    caCert, err := os.ReadFile(config.CAFile)
    if err != nil {
        return nil, fmt.Errorf("read CA file: %w", err)
    }

    caCertPool := x509.NewCertPool()
    if !caCertPool.AppendCertsFromPEM(caCert) {
        return nil, fmt.Errorf("parse CA certificate")
    }

    return &tls.Config{
        Certificates: []tls.Certificate{cert},
        RootCAs:     caCertPool,
        ClientCAs:   caCertPool,
        MinVersion:  tls.VersionTLS13,
        ClientAuth:  tls.RequireAndVerifyClientCert,
    }, nil
}
```

#### 3.1.2 证书轮换

```go
// 证书轮换管理器
type CertRotator struct {
    config     TLSConfig
    certTTL    time.Duration
    renewBefore time.Duration
}

func (r *CertRotator) Start(ctx context.Context) error {
    ticker := time.NewTicker(r.renewBefore)
    defer ticker.Stop()

    for {
        select {
        case <-ctx.Done():
            return ctx.Err()
        case <-ticker.C:
            if err := r.rotateCertificates(); err != nil {
                log.Printf("Certificate rotation failed: %v", err)
            }
        }
    }
}

func (r *CertRotator) rotateCertificates() error {
    // 生成新的证书密钥对
    privateKey, err := rsa.GenerateKey(rand.Reader, 2048)
    if err != nil {
        return fmt.Errorf("generate key: %w", err)
    }

    // 创建证书模板
    template := x509.Certificate{
        SerialNumber: big.NewInt(time.Now().Unix()),
        Subject: pkix.Name{
            Organization: []string{"Xboard"},
        },
        NotBefore: time.Now(),
        NotAfter:  time.Now().Add(r.certTTL),
        KeyUsage:  x509.KeyUsageKeyEncipherment | x509.KeyUsageDigitalSignature,
        ExtKeyUsage: []x509.ExtKeyUsage{
            x509.ExtKeyUsageServerAuth,
            x509.ExtKeyUsageClientAuth,
        },
    }

    // 签发新证书
    certDER, err := x509.CreateCertificate(
        rand.Reader,
        &template,
        &template,
        &privateKey.PublicKey,
        privateKey,
    )
    if err != nil {
        return fmt.Errorf("create certificate: %w", err)
    }

    // 保存新证书和私钥
    return r.saveCertificateAndKey(certDER, privateKey)
}
```

### 3.2 用户认证

#### 3.2.1 JWT 配置

```php
// JWT 配置
class JWTConfig {
    public function __construct(
        private string $secret,
        private int $ttl,
        private string $issuer,
        private array $algorithms = ['HS256']
    ) {}

    public function createToken(array $claims): string {
        $token = JWT::encode([
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + $this->ttl,
            ...$claims
        ], $this->secret, $this->algorithms[0]);

        return $token;
    }

    public function validateToken(string $token): array {
        try {
            $decoded = JWT::decode($token, $this->secret, $this->algorithms);
            return (array) $decoded;
        } catch (Exception $e) {
            throw new AuthenticationException($e->getMessage());
        }
    }

    public function refreshToken(string $token): string {
        $claims = $this->validateToken($token);
        unset($claims['iat'], $claims['exp']);
        return $this->createToken($claims);
    }
}
```

#### 3.2.2 RBAC 实现

```php
class RBACService {
    private array $roles = [
        'admin' => ['all'],
        'user' => ['read:profile', 'update:profile', 'read:nodes'],
        'guest' => ['read:public']
    ];

    private array $permissions = [
        'read:profile' => '查看个人资料',
        'update:profile' => '更新个人资料',
        'read:nodes' => '查看节点信息',
        'manage:nodes' => '管理节点',
        'manage:users' => '管理用户',
        'view:stats' => '查看统计数据',
        'manage:settings' => '管理系统设置'
    ];

    public function hasPermission(string $userId, string $permission): bool {
        $userRole = $this->getUserRole($userId);
        return in_array('all', $this->roles[$userRole]) ||
               in_array($permission, $this->roles[$userRole]);
    }

    public function validateAccess(string $userId, string $permission): void {
        if (!$this->hasPermission($userId, $permission)) {
            throw new AccessDeniedException(
                sprintf('用户无权限执行此操作: %s', $this->permissions[$permission] ?? $permission)
            );
        }
    }

    public function getUserPermissions(string $userId): array {
        $userRole = $this->getUserRole($userId);
        if (in_array('all', $this->roles[$userRole])) {
            return array_keys($this->permissions);
        }
        return $this->roles[$userRole];
    }

    private function getUserRole(string $userId): string {
        // 从数据库或缓存中获取用户角色
        return 'user'; // 示例返回值
    }
}
```


## 5. 功能模块实现

### 5.1 节点管理

#### 5.1.1 配置管理

- 节点配置结构
  - 基础信息：节点ID、名称、地址、端口
  - 传输配置：协议类型、传输方式、加密方式
  - 性能参数：带宽限制、并发连接数
  - 安全设置：TLS配置、访问控制

- 配置下发流程
  - 配置验证
  - 增量更新
  - 版本控制
  - 回滚机制

#### 5.1.2 状态监控

- 监控指标
  - 系统指标：CPU、内存、磁盘、网络
  - 业务指标：在线用户数、连接数、流量统计
  - 安全指标：异常连接、攻击检测

- 数据采集
  - 采集周期：实时、分钟、小时、天
  - 数据压缩：采样率、聚合计算
  - 存储策略：时序数据库

### 5.2 用户管理

#### 5.2.1 用户同步

- 同步策略
  - 全量同步：定期执行
  - 增量同步：实时触发
  - 冲突处理：版本对比

- 同步内容
  - 用户信息：ID、邮箱、状态
  - 配置信息：协议、端口、密钥
  - 使用限制：流量配额、设备数

#### 5.2.2 配额管理

- 配额类型
  - 流量配额：总量、月度
  - 带宽配额：上传、下载
  - 设备配额：同时在线数

- 配额控制
  - 实时计算：流量统计
  - 超额处理：警告、限速、断开
  - 重置机制：自动、手动

### 5.3 数据同步

#### 5.3.1 实时同步

- 同步机制
  - WebSocket 长连接
  - gRPC 双向流
  - 消息队列

- 数据一致性
  - 乐观锁
  - CAS操作
  - 版本号机制

#### 5.3.2 定时同步

- 同步策略
  - 全量同步：每日凌晨
  - 增量同步：每5分钟
  - 差异校验：每小时

- 任务调度
  - 分布式锁
  - 失败重试
  - 监控告警

### 5.4 错误处理

#### 5.4.1 异常类型

- 系统异常
  - 网络异常：连接超时、断开
  - 资源异常：CPU高负载、内存溢出
  - 存储异常：磁盘满、数据库异常

- 业务异常
  - 配置错误：格式错误、参数无效
  - 权限错误：认证失败、未授权
  - 限额异常：超出配额、资源耗尽

#### 5.4.2 重试策略

- 重试机制
  - 指数退避算法
  - 最大重试次数
  - 超时时间

- 熔断机制
  - 错误计数
  - 熔断阈值
  - 恢复策略

## 6. 部署与运维规划

### 6.1 Docker容器化部署

#### 6.1.1 容器编排

```yaml
version: '3.8'

services:
  xboard-panel:
    image: xboard/panel:latest
    restart: always
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
      - NODE_ENV=production
    volumes:
      - ./config:/app/config
      - ./logs:/app/logs
    depends_on:
      - mysql
      - redis

  3x-ui-node:
    image: xboard/3x-ui:latest
    restart: always
    environment:
      - PANEL_API_URL=http://xboard-panel
      - NODE_ID=${NODE_ID}
    volumes:
      - ./node-config:/etc/3x-ui
      - ./node-logs:/var/log/3x-ui
    ports:
      - "${NODE_PORT}:${NODE_PORT}"

  mysql:
    image: mysql:8.0
    restart: always
    environment:
      - MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
      - MYSQL_DATABASE=xboard
    volumes:
      - mysql-data:/var/lib/mysql

  redis:
    image: redis:7.0
    restart: always
    volumes:
      - redis-data:/data

volumes:
  mysql-data:
  redis-data:
```

#### 6.1.2 部署流程

1. 环境准备
   - Docker Engine 安装
   - Docker Compose 安装
   - 网络端口配置

2. 配置管理
   - 环境变量配置
   - 配置文件挂载
   - 密钥证书管理

3. 部署步骤
   - 镜像构建推送
   - 服务编排启动
   - 健康检查验证

### 6.2 监控告警

#### 6.2.1 监控指标

- 系统层面
  - 容器状态
  - 资源使用率
  - 网络连接数

- 应用层面
  - 请求延迟
  - 错误率
  - 业务指标

#### 6.2.2 告警规则

- 告警级别
  - P0：严重故障
  - P1：重要警告
  - P2：一般提示

- 通知方式
  - 邮件
  - Telegram
  - Webhook

### 6.3 测试方案

#### 6.3.1 测试类型

- 单元测试
  - 接口测试
  - 业务逻辑测试
  - 数据模型测试

- 集成测试
  - API 测试
  - 性能测试
  - 压力测试

#### 6.3.2 测试流程

1. 测试环境
   - 环境隔离
   - 数据准备
   - 监控配置

2. 测试执行
   - 自动化测试
   - 手动测试
   - 回归测试

### 6.4 安全机制

#### 6.4.1 访问控制

- 认证机制
  - JWT Token
  - API Key
  - mTLS 证书

- 权限管理
  - RBAC 模型
  - 资源隔离
  - 操作审计

#### 6.4.2 数据安全

- 传输加密
  - TLS 1.3
  - 加密算法
  - 密钥管理

- 存储安全
  - 数据加密
  - 备份策略
  - 访问控制

## 8. 错误处理与状态码

### 8.1 错误码定义

```protobuf
enum ErrorCode {
  SUCCESS = 0;           // 成功
  UNKNOWN_ERROR = 1;     // 未知错误
  
  // 认证相关 (1000-1999)
  AUTH_FAILED = 1000;    // 认证失败
  TOKEN_EXPIRED = 1001;  // Token过期
  INVALID_TOKEN = 1002;  // 无效Token
  
  // 用户相关 (2000-2999)
  USER_NOT_FOUND = 2000;    // 用户不存在
  QUOTA_EXCEEDED = 2001;    // 配额超限
  INVALID_CONFIG = 2002;    // 配置无效
  
  // 节点相关 (3000-3999)
  NODE_OFFLINE = 3000;      // 节点离线
  NODE_OVERLOAD = 3001;     // 节点超载
  SYNC_FAILED = 3002;       // 同步失败
}

message ErrorResponse {
  int32 code = 1;           // 错误码
  string message = 2;       // 错误信息
  string detail = 3;        // 详细信息
  string request_id = 4;    // 请求ID
}
```

### 8.2 访问控制增强

#### 8.2.1 频率限制

```go
type RateLimiter struct {
    store  *redis.Client
    prefix string
    window time.Duration
}

func (r *RateLimiter) Allow(key string, limit int) bool {
    pipe := r.store.Pipeline()
    now := time.Now().UnixNano()
    windowStart := now - r.window.Nanoseconds()
    
    // 清理过期的访问记录
    pipe.ZRemRangeByScore(r.prefix+key, "0", strconv.FormatInt(windowStart, 10))
    
    // 添加当前访问记录
    pipe.ZAdd(r.prefix+key, &redis.Z{Score: float64(now), Member: now})
    
    // 获取当前窗口的访问次数
    pipe.ZCard(r.prefix+key)
    
    res, err := pipe.Exec()
    if err != nil {
        return false
    }
    
    count := res[2].(*redis.IntCmd).Val()
    return count <= int64(limit)
}
```

#### 8.2.2 IP黑名单

```go
type BlackList struct {
    store  *redis.Client
    prefix string
}

func (b *BlackList) IsBanned(ip string) bool {
    exists, _ := b.store.Exists(b.prefix + ip).Result()
    return exists == 1
}

func (b *BlackList) Ban(ip string, duration time.Duration) error {
    return b.store.Set(b.prefix+ip, 1, duration).Err()
}
```

### 8.3 服务降级策略

```go
type CircuitBreaker struct {
    failureThreshold int
    resetTimeout    time.Duration
    failures        int
    lastFailure     time.Time
    mu              sync.RWMutex
}

func (cb *CircuitBreaker) Execute(fn func() error) error {
    cb.mu.RLock()
    if cb.failures >= cb.failureThreshold {
        if time.Since(cb.lastFailure) < cb.resetTimeout {
            cb.mu.RUnlock()
            return errors.New("circuit breaker is open")
        }
    }
    cb.mu.RUnlock()

    err := fn()
    if err != nil {
        cb.mu.Lock()
        cb.failures++
        cb.lastFailure = time.Now()
        cb.mu.Unlock()
        return err
    }

    cb.mu.Lock()
    cb.failures = 0
    cb.mu.Unlock()
    return nil
}
```

## 9. 自动化运维

### 9.1 部署脚本

```bash
#!/bin/bash

# 部署配置
DEPLOY_ENV=${1:-"production"}
CONFIG_PATH="./config/${DEPLOY_ENV}.yaml"
DOCKER_COMPOSE="docker-compose.${DEPLOY_ENV}.yml"

# 加载环境变量
set -a
source "${CONFIG_PATH}"
set +a

# 健康检查函数
check_health() {
    local service=$1
    local max_retries=${2:-30}
    local retry_interval=${3:-2}
    
    echo "Checking health for ${service}..."
    for i in $(seq 1 $max_retries); do
        if curl -s "http://localhost:${HEALTH_CHECK_PORT:-8080}/health/${service}" | grep -q '"status":"UP"'; then
            echo "${service} is healthy"
            return 0
        fi
        echo "Waiting for ${service} to be healthy (${i}/${max_retries})"
        sleep $retry_interval
    done
    
    echo "${service} health check failed"
    return 1
}

# 备份数据
backup_data() {
    local backup_dir="./backups/$(date +%Y%m%d_%H%M%S)"
    mkdir -p "${backup_dir}"
    
    # 备份数据库
    docker-compose exec -T mysql mysqldump -u root -p"${DB_PASSWORD}" xboard > "${backup_dir}/xboard.sql"
    
    # 备份Redis数据
    docker-compose exec -T redis redis-cli SAVE
    cp "/data/redis/dump.rdb" "${backup_dir}/"
    
    echo "Backup completed: ${backup_dir}"
}

# 主部署流程
main() {
    echo "Starting deployment for ${DEPLOY_ENV} environment"
    
    # 备份当前数据
    backup_data
    
    # 拉取最新代码
    git pull origin master
    
    # 构建和启动服务
    docker-compose -f "${DOCKER_COMPOSE}" build
    docker-compose -f "${DOCKER_COMPOSE}" up -d
    
    # 执行数据库迁移
    docker-compose exec -T php php artisan migrate --force
    
    # 健康检查
    check_health "xboard-panel" || exit 1
    check_health "3x-ui-node" || exit 1
    
    echo "Deployment completed successfully"
}

main
```

### 9.2 监控告警配置

```yaml
# Prometheus告警规则
groups:
- name: node_alerts
  rules:
  - alert: NodeOffline
    expr: up{job="3x-ui-node"} == 0
    for: 5m
    labels:
      severity: critical
    annotations:
      summary: "节点离线告警"
      description: "节点 {{ $labels.instance }} 已离线超过5分钟"

  - alert: HighCPUUsage
    expr: cpu_usage_percent{job="3x-ui-node"} > 80
    for: 10m
    labels:
      severity: warning
    annotations:
      summary: "CPU使用率过高"
      description: "节点 {{ $labels.instance }} CPU使用率超过80%持续10分钟"

  - alert: HighMemoryUsage
    expr: memory_usage_percent{job="3x-ui-node"} > 85
    for: 10m
    labels:
      severity: warning
    annotations:
      summary: "内存使用率过高"
      description: "节点 {{ $labels.instance }} 内存使用率超过85%持续10分钟"

  - alert: AbnormalTraffic
    expr: rate(network_bytes_total{job="3x-ui-node"}[5m]) > 1e9
    for: 5m
    labels:
      severity: warning
    annotations:
      summary: "流量异常告警"
      description: "节点 {{ $labels.instance }} 流量超过1GB/s持续5分钟"
```

### 9.3 常见问题处理流程

#### 9.3.1 故障诊断流程

1. 节点连接异常
   - 检查网络连通性
   - 验证证书有效性
   - 检查防火墙配置
   - 查看错误日志

2. 性能问题
   - 分析系统负载
   - 检查资源使用情况
   - 优化配置参数
   - 考虑扩容方案

3. 数据同步问题
   - 验证数据一致性
   - 检查同步日志
   - 手动触发同步
   - 修复数据差异

#### 9.3.2 应急响应预案

1. 服务中断
   - 启动备用节点
   - 切换流量路由
   - 通知用户
   - 分析根本原因

2. 数据安全事件
   - 隔离受影响系统
   - 评估影响范围
   - 启动恢复程序
   - 加强安全措施

3. 性能退化
   - 启用降级方案
   - 扩展资源配置
   - 优化系统参数
   - 监控恢复情况

## 7. 性能优化方案

### 7.1 缓存策略

#### 7.1.1 多级缓存

- 客户端缓存
  - 浏览器缓存
  - 本地存储
  - Service Worker

- 服务端缓存
  - Redis 缓存
  - 内存缓存
  - 文件缓存

#### 7.1.2 缓存策略

- 缓存类型
  - 全局缓存
  - 用户级缓存
  - 接口缓存

- 更新机制
  - 定时更新
  - 主动失效
  - 被动更新

### 7.2 并发处理

#### 7.2.1 并发控制

- 限流措施
  - 令牌桶算法
  - 漏桶算法
  - 计数器限流

- 队列处理
  - 消息队列
  - 任务队列
  - 延迟队列

#### 7.2.2 性能优化

- 代码层面
  - 算法优化
  - 内存管理
  - 协程池化

- 系统层面
  - 负载均衡
  - 水平扩展
  - 资源隔离

### 7.3 运维支持

#### 7.3.1 运维工具

- 部署工具
  - CI/CD 流水线
  - 配置管理
  - 版本控制

- 监控工具
  - 日志采集
  - 性能分析
  - 链路追踪

#### 7.3.2 运维流程

- 发布流程
  - 灰度发布
  - 回滚机制
  - 验证确认

- 应急响应
  - 故障检测
  - 快速恢复
  - 复盘总结