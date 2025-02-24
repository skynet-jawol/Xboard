# Xboard 项目文档

## 1. 项目概述

Xboard 是一个基于 Laravel 11 框架开发的现代化 Web 应用平台，采用前后端分离架构，提供用户管理、服务订阅等功能。

### 1.1 核心特性

- 基于 Laravel 11 + Octane 的高性能后端
- React + Shadcn UI 构建的现代化管理界面
- Vue3 + TypeScript + NaiveUI 打造的用户前端
- Docker 容器化部署支持
- Redis + Octane Cache 的高效缓存系统

## 2. 技术架构

### 2.1 后端架构

- **框架**: Laravel 11
- **性能优化**: Laravel Octane
- **缓存系统**: Redis + Octane Cache
- **任务队列**: Laravel Horizon
- **API 认证**: Laravel Sanctum
- **API 文档**: Scribe

### 2.2 前端架构

#### 管理面板
- React
- Shadcn UI
- TailwindCSS

#### 用户界面
- Vue3
- TypeScript
- NaiveUI

### 2.3 部署架构
- Docker
- Docker Compose
- Supervisor

## 3. 核心功能模块

### 3.1 用户管理系统

#### 实现原理
- 基于 Laravel 的认证系统
- 使用 Sanctum 进行 API 认证
- 支持多设备登录限制
- 用户在线状态同步（SyncUserOnlineStatusJob）

### 3.2 订单系统

#### 实现原理
- 订单处理（OrderHandleJob）
- 支持多种支付方式：
  - 支付宝当面付（AlipayF2F）
  - BTCPay
  - CoinPayments
  - Coinbase
  - EPay
  - MGate

### 3.3 服务器管理

#### 实现原理
- 服务器分组管理
- 流量统计（TrafficFetchJob）
- 服务器状态监控（StatServerJob）
- 支持多种协议：
  - Clash
  - ClashMeta
  - Shadowrocket
  - Shadowsocks
  - SingBox
  - Surge
  等

### 3.4 通知系统

#### 实现原理
- 邮件通知（SendEmailJob）
- Telegram 通知（SendTelegramJob）
- 站内通知

### 3.5 统计系统

#### 实现原理
- 用户统计（StatUserJob）
- 服务器统计（StatServerJob）
- 性能指标收集

### 3.6 插件系统

#### 实现原理
- 插件服务提供者（PluginServiceProvider）
- 可扩展的插件接口
- Telegram 机器人集成

## 4. 数据流程

### 4.1 用户请求流程
1. 请求进入 public/index.php
2. 通过 Laravel Kernel 处理
3. 中间件验证（认证、CSRF 等）
4. 路由分发
5. 控制器处理
6. 返回响应

### 4.2 异步任务处理流程
1. 任务创建
2. 推送到 Redis 队列
3. Horizon 处理队列任务
4. 任务执行
5. 结果处理

## 5. 性能优化

### 5.1 服务器端优化
- 使用 Laravel Octane 提升性能
- Redis 缓存优化
- 数据库索引优化
- 异步任务处理

### 5.2 客户端优化
- 前端资源优化
- API 响应缓存
- 按需加载

## 6. 安全特性

- CSRF 保护
- API 认证（Sanctum）
- 请求加密
- 会话安全

## 7. 可扩展性

### 7.1 插件系统
- 标准化的插件接口
- 插件生命周期管理
- 插件配置管理

### 7.2 主题系统
- 可自定义主题
- 主题配置文件
- 资源管理

## 8. 部署说明

### 8.1 环境要求
- PHP 8.x
- Redis
- MySQL/MariaDB
- Node.js

### 8.2 Docker 部署
- 提供完整的 Docker 配置
- 使用 Docker Compose 编排服务
- Supervisor 进程管理

## 9. 开发指南

### 9.1 开发环境搭建
- 安装依赖
- 配置环境变量
- 数据库迁移
- 启动开发服务器

### 9.2 代码规范
- PSR 标准
- TypeScript 类型检查
- ESLint 配置
- 代码格式化

### 9.3 测试
- 单元测试
- 功能测试
- API 测试

## 10. 维护与更新

### 10.1 日常维护
- 日志管理
- 性能监控
- 错误追踪
- 数据备份

### 10.2 版本更新
- 更新脚本
- 数据迁移
- 兼容性处理

## 11. 故障排除

### 11.1 常见问题
- 服务启动问题
- 性能问题
- 网络连接问题

### 11.2 调试工具
- Laravel Debugbar
- 日志系统
- 性能分析工具

## 12. API 文档

- 使用 Scribe 自动生成 API 文档
- 支持多种请求示例（bash、javascript）
- 包含 Postman 集合
- OpenAPI 规范支持