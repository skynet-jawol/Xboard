const winston = require('winston');
const path = require('path');
const fs = require('fs');
const sqlite3 = require('sqlite3');

// 日志等级定义
const levels = {
  fatal: 0,   // 系统崩溃或无法恢复的错误
  error: 1,   // 功能模块异常
  warn: 2,    // 性能显著下降
  info: 3,    // 正常操作记录
  debug: 4,   // 详细的操作流程信息
  trace: 5,   // 最详细的调试信息
};

// 日志模块分类
const modules = {
  CONTROLLER: 'controller',  // 控制器模块
  VIDEO: 'video',           // 视频模块
  AUDIO: 'audio',           // 音频模块
  GPS: 'gps',               // GPS模块
  NETWORK: 'network',       // 网络连接
  SYSTEM: 'system',         // 系统运行
};

// 日志颜色配置
const colors = {
  fatal: 'red',
  error: 'red',
  warn: 'yellow',
  info: 'green',
  debug: 'white',
  trace: 'gray'
};

winston.addColors(colors);

// 日志格式定义
const format = winston.format.combine(
  winston.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss.SSS' }),
  winston.format.colorize({ all: true }),
  winston.format.printf(
    (info) => `${info.timestamp} [${info.module}] ${info.level}: ${info.message}`
  )
);

// 确保日志目录存在
const LOG_DIR = '/home/pi/.network-rc/logs';
if (!fs.existsSync(LOG_DIR)) {
  fs.mkdirSync(LOG_DIR, { recursive: true });
}

// 数据库初始化
const DB_PATH = path.join(LOG_DIR, 'logs.db');
const db = new sqlite3.Database(DB_PATH);

// 创建日志表
db.serialize(() => {
  // 日志主表
  db.run(`CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    level TEXT NOT NULL,
    module TEXT NOT NULL,
    event_type TEXT,
    message TEXT NOT NULL,
    data JSON,
    device_id TEXT
  )`);

  // 性能指标表
  db.run(`CREATE TABLE IF NOT EXISTS metrics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    module TEXT NOT NULL,
    metric_type TEXT NOT NULL,
    value REAL NOT NULL,
    unit TEXT,
    threshold REAL,
    device_id TEXT
  )`);
});

// 日志传输配置
const transports = [
  new winston.transports.Console(),
  new winston.transports.File({
    filename: path.join(LOG_DIR, 'error.log'),
    level: 'error',
    maxsize: 100 * 1024 * 1024, // 100MB
    maxFiles: 30,
    tailable: true
  }),
  new winston.transports.File({
    filename: path.join(LOG_DIR, 'all.log'),
    maxsize: 100 * 1024 * 1024,
    maxFiles: 30,
    tailable: true
  })
];

// 创建日志记录器
const Logger = winston.createLogger({
  level: process.env.NODE_ENV === 'development' ? 'trace' : 'info',
  levels,
  format,
  transports
});

// 扩展日志方法
Object.keys(modules).forEach(moduleKey => {
  const moduleName = modules[moduleKey];
  Logger[moduleName] = {};
  
  Object.keys(levels).forEach(level => {
    Logger[moduleName][level] = (message, data = null, deviceId = null) => {
      // 写入控制台和文件
      Logger.log({
        level,
        module: moduleName,
        message,
        data,
        deviceId
      });

      // 写入数据库
      const stmt = db.prepare(
        'INSERT INTO logs (level, module, message, data, device_id) VALUES (?, ?, ?, ?, ?)'
      );
      stmt.run(level, moduleName, message, JSON.stringify(data), deviceId);
      stmt.finalize();
    };
  });
});

// 性能指标记录方法
Logger.recordMetric = (module, type, value, unit = null, threshold = null, deviceId = null) => {
  const stmt = db.prepare(
    'INSERT INTO metrics (module, metric_type, value, unit, threshold, device_id) VALUES (?, ?, ?, ?, ?, ?)'
  );
  stmt.run(module, type, value, unit, threshold, deviceId);
  stmt.finalize();

  // 检查是否超过阈值
  if (threshold && value > threshold) {
    Logger[module].warn(`${type} exceeded threshold: ${value}${unit || ''} (threshold: ${threshold}${unit || ''})`);
  }
};

// 设置全局访问
global.logger = Logger;

module.exports = Logger;
