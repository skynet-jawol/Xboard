const SerialPort = require('serialport');
const { Parser } = require('@serialport/parser-nmea');
const EventEmitter = require('events');

class GPSService extends EventEmitter {
    constructor() {
        super();
        this.port = null;
        this.parser = null;
        this.config = {
            portPath: '',
            baudRate: 9600,
            updateInterval: 1000 // 默认更新频率1秒
        };
        this.currentPosition = {
            latitude: null,
            longitude: null,
            altitude: null,
            speed: null,
            timestamp: null,
            isValid: false
        };
    }

    async configure(config) {
        this.config = { ...this.config, ...config };
        await this.initializePort();
    }

    async initializePort() {
        if (this.port) {
            await this.close();
        }

        try {
            this.port = new SerialPort({
                path: this.config.portPath,
                baudRate: this.config.baudRate
            });

            this.parser = this.port.pipe(new Parser());
            this.setupListeners();
        } catch (error) {
            throw new Error(`GPS端口初始化失败: ${error.message}`);
        }
    }

    setupListeners() {
        this.parser.on('data', (data) => {
            const parsed = this.parseNMEA(data);
            if (parsed) {
                this.currentPosition = parsed;
                this.emit('position', parsed);
            }
        });

        this.port.on('error', (error) => {
            this.emit('error', error);
        });
    }

    parseNMEA(data) {
        // 解析NMEA数据
        if (data.type === 'GGA') {
            return {
                latitude: this.convertToDecimalDegrees(data.lat, data.latPole),
                longitude: this.convertToDecimalDegrees(data.lon, data.lonPole),
                altitude: parseFloat(data.alt),
                timestamp: new Date(),
                isValid: data.quality > 0
            };
        }
        return null;
    }

    convertToDecimalDegrees(value, pole) {
        if (!value || !pole) return null;
        
        const degrees = Math.floor(value / 100);
        const minutes = value - (degrees * 100);
        let decimal = degrees + (minutes / 60);
        
        if (pole === 'S' || pole === 'W') {
            decimal = -decimal;
        }
        
        return decimal;
    }

    getCurrentPosition() {
        return this.currentPosition;
    }

    async close() {
        if (this.port) {
            return new Promise((resolve) => {
                this.port.close(() => {
                    this.port = null;
                    this.parser = null;
                    resolve();
                });
            });
        }
    }
}

module.exports = GPSService;