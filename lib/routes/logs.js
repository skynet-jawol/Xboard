const express = require('express');
const router = express.Router();
const { db, levels, modules } = require('../logger');

// 基础查询接口
router.get('/query', async (req, res) => {
    try {
        const { 
            startTime, 
            endTime, 
            level, 
            module, 
            keyword,
            page = 1,
            pageSize = 20
        } = req.query;

        let query = 'SELECT * FROM logs WHERE 1=1';
        const params = [];

        if (startTime) {
            query += ' AND timestamp >= ?';
            params.push(startTime);
        }

        if (endTime) {
            query += ' AND timestamp <= ?';
            params.push(endTime);
        }

        if (level) {
            query += ' AND level = ?';
            params.push(level);
        }

        if (module) {
            query += ' AND module = ?';
            params.push(module);
        }

        if (keyword) {
            query += ' AND (message LIKE ? OR data LIKE ?)';
            params.push(`%${keyword}%`);
            params.push(`%${keyword}%`);
        }

        // 获取总数
        const countQuery = query.replace('SELECT *', 'SELECT COUNT(*) as total');
        const [{ total }] = await new Promise((resolve, reject) => {
            db.all(countQuery, params, (err, rows) => {
                if (err) reject(err);
                resolve(rows);
            });
        });

        // 添加分页
        query += ' ORDER BY timestamp DESC LIMIT ? OFFSET ?';
        params.push(pageSize);
        params.push((page - 1) * pageSize);

        const rows = await new Promise((resolve, reject) => {
            db.all(query, params, (err, rows) => {
                if (err) reject(err);
                resolve(rows);
            });
        });

        res.json({
            code: 0,
            data: {
                total,
                list: rows,
                page: parseInt(page),
                pageSize: parseInt(pageSize)
            }
        });
    } catch (error) {
        res.status(500).json({
            code: 500,
            message: error.message
        });
    }
});

// 高级搜索接口
router.post('/search', async (req, res) => {
    try {
        const { conditions, page = 1, pageSize = 20 } = req.body;
        let query = 'SELECT * FROM logs WHERE 1=1';
        const params = [];

        if (conditions && Array.isArray(conditions)) {
            conditions.forEach(condition => {
                const { field, operator, value } = condition;
                switch (operator) {
                    case 'eq':
                        query += ` AND ${field} = ?`;
                        params.push(value);
                        break;
                    case 'like':
                        query += ` AND ${field} LIKE ?`;
                        params.push(`%${value}%`);
                        break;
                    case 'gt':
                        query += ` AND ${field} > ?`;
                        params.push(value);
                        break;
                    case 'lt':
                        query += ` AND ${field} < ?`;
                        params.push(value);
                        break;
                }
            });
        }

        const countQuery = query.replace('SELECT *', 'SELECT COUNT(*) as total');
        const [{ total }] = await new Promise((resolve, reject) => {
            db.all(countQuery, params, (err, rows) => {
                if (err) reject(err);
                resolve(rows);
            });
        });

        query += ' ORDER BY timestamp DESC LIMIT ? OFFSET ?';
        params.push(pageSize);
        params.push((page - 1) * pageSize);

        const rows = await new Promise((resolve, reject) => {
            db.all(query, params, (err, rows) => {
                if (err) reject(err);
                resolve(rows);
            });
        });

        res.json({
            code: 0,
            data: {
                total,
                list: rows,
                page: parseInt(page),
                pageSize: parseInt(pageSize)
            }
        });
    } catch (error) {
        res.status(500).json({
            code: 500,
            message: error.message
        });
    }
});

// 统计数据接口
router.get('/stats', async (req, res) => {
    try {
        const { startTime, endTime } = req.query;
        const params = [];
        let timeCondition = '';

        if (startTime && endTime) {
            timeCondition = ' WHERE timestamp BETWEEN ? AND ?';
            params.push(startTime, endTime);
        }

        // 按级别统计
        const levelStats = await new Promise((resolve, reject) => {
            db.all(
                `SELECT level, COUNT(*) as count FROM logs${timeCondition} GROUP BY level`,
                params,
                (err, rows) => {
                    if (err) reject(err);
                    resolve(rows);
                }
            );
        });

        // 按模块统计
        const moduleStats = await new Promise((resolve, reject) => {
            db.all(
                `SELECT module, COUNT(*) as count FROM logs${timeCondition} GROUP BY module`,
                params,
                (err, rows) => {
                    if (err) reject(err);
                    resolve(rows);
                }
            );
        });

        res.json({
            code: 0,
            data: {
                levelStats,
                moduleStats
            }
        });
    } catch (error) {
        res.status(500).json({
            code: 500,
            message: error.message
        });
    }
});

// 导出数据接口
router.get('/export', async (req, res) => {
    try {
        const { startTime, endTime, format = 'json' } = req.query;
        let query = 'SELECT * FROM logs WHERE 1=1';
        const params = [];

        if (startTime) {
            query += ' AND timestamp >= ?';
            params.push(startTime);
        }

        if (endTime) {
            query += ' AND timestamp <= ?';
            params.push(endTime);
        }

        query += ' ORDER BY timestamp DESC';

        const rows = await new Promise((resolve, reject) => {
            db.all(query, params, (err, rows) => {
                if (err) reject(err);
                resolve(rows);
            });
        });

        if (format === 'csv') {
            const csvContent = rows.map(row => {
                return `${row.timestamp},${row.level},${row.module},${row.message}`;
            }).join('\n');
            
            res.setHeader('Content-Type', 'text/csv');
            res.setHeader('Content-Disposition', 'attachment; filename=logs.csv');
            res.send(csvContent);
        } else {
            res.setHeader('Content-Type', 'application/json');
            res.setHeader('Content-Disposition', 'attachment; filename=logs.json');
            res.json(rows);
        }
    } catch (error) {
        res.status(500).json({
            code: 500,
            message: error.message
        });
    }
});

module.exports = router;