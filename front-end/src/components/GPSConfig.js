import React, { useState, useEffect } from 'react';
import {
  Card,
  CardContent,
  Typography,
  TextField,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Button,
  Grid,
  Slider
} from '@mui/material';

const GPSConfig = () => {
  const [config, setConfig] = useState({
    portPath: '',
    baudRate: 9600,
    updateInterval: 1000
  });

  const [availablePorts, setAvailablePorts] = useState([]);
  const baudRates = [4800, 9600, 19200, 38400, 57600, 115200];

  useEffect(() => {
    // 获取可用串口列表
    fetch('/api/gps/ports')
      .then(res => res.json())
      .then(data => setAvailablePorts(data))
      .catch(err => console.error('获取串口列表失败:', err));

    // 获取当前GPS配置
    fetch('/api/gps/config')
      .then(res => res.json())
      .then(data => setConfig(data))
      .catch(err => console.error('获取GPS配置失败:', err));
  }, []);

  const handleSave = () => {
    fetch('/api/gps/config', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(config)
    })
      .then(res => res.json())
      .then(data => {
        console.log('GPS配置已更新');
      })
      .catch(err => console.error('保存GPS配置失败:', err));
  };

  return (
    <Card>
      <CardContent>
        <Typography variant="h6" gutterBottom>
          GPS配置
        </Typography>
        <Grid container spacing={3}>
          <Grid item xs={12} md={4}>
            <FormControl fullWidth>
              <InputLabel>串口</InputLabel>
              <Select
                value={config.portPath}
                onChange={(e) => setConfig({ ...config, portPath: e.target.value })}
              >
                {availablePorts.map(port => (
                  <MenuItem key={port.path} value={port.path}>
                    {port.path}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>
          <Grid item xs={12} md={4}>
            <FormControl fullWidth>
              <InputLabel>波特率</InputLabel>
              <Select
                value={config.baudRate}
                onChange={(e) => setConfig({ ...config, baudRate: e.target.value })}
              >
                {baudRates.map(rate => (
                  <MenuItem key={rate} value={rate}>
                    {rate}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>
          <Grid item xs={12}>
            <Typography gutterBottom>
              更新频率 (ms)
            </Typography>
            <Slider
              value={config.updateInterval}
              onChange={(e, value) => setConfig({ ...config, updateInterval: value })}
              min={100}
              max={5000}
              step={100}
              valueLabelDisplay="auto"
            />
          </Grid>
          <Grid item xs={12}>
            <Button variant="contained" color="primary" onClick={handleSave}>
              保存配置
            </Button>
          </Grid>
        </Grid>
      </CardContent>
    </Card>
  );
};

export default GPSConfig;