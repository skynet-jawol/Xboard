const express = require('express');
const SerialPort = require('serialport');
const GPSService = require('./GPSService');

const router = express.Router();
const gpsService = new GPSService();

// 获取可用串口列表
router.get('/ports', async (req, res) => {
    try {
        const ports = await SerialPort.list();
        res.json(ports);
    } catch (error) {
        res.status(500).json({ error: '获取串口列表失败' });
    }
});

// 获取当前GPS配置
router.get('/config', (req, res) => {
    res.json(gpsService.config);
});

// 更新GPS配置
router.post('/config', async (req, res) => {
    try {
        await gpsService.configure(req.body);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 获取当前位置
router.get('/position', (req, res) => {
    const position = gpsService.getCurrentPosition();
    res.json(position);
});

module.exports = router;