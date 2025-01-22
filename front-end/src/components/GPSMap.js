import React, { useEffect, useRef, useState } from 'react';
import { Card, CardContent, Typography } from '@mui/material';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const GPSMap = () => {
  const mapRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const markerRef = useRef(null);
  const pathRef = useRef([]);
  const polylineRef = useRef(null);

  const [position, setPosition] = useState(null);

  useEffect(() => {
    // 初始化地图
    if (!mapInstanceRef.current) {
      mapInstanceRef.current = L.map(mapRef.current).setView([0, 0], 2);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(mapInstanceRef.current);

      // 创建位置标记
      markerRef.current = L.marker([0, 0]).addTo(mapInstanceRef.current);
      // 创建轨迹线
      polylineRef.current = L.polyline([], { color: 'red' }).addTo(mapInstanceRef.current);
    }

    // 建立WebSocket连接
    const ws = new WebSocket(`ws://${window.location.host}/gps`);
    ws.onmessage = (event) => {
      const data = JSON.parse(event.data);
      if (data.isValid) {
        updatePosition(data);
      }
    };

    return () => {
      if (ws) ws.close();
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove();
        mapInstanceRef.current = null;
      }
    };
  }, []);

  const updatePosition = (pos) => {
    setPosition(pos);
    const { latitude, longitude } = pos;

    // 更新标记位置
    markerRef.current.setLatLng([latitude, longitude]);

    // 更新轨迹
    pathRef.current.push([latitude, longitude]);
    polylineRef.current.setLatLngs(pathRef.current);

    // 将地图中心设置到当前位置
    mapInstanceRef.current.setView([latitude, longitude]);
  };

  return (
    <Card>
      <CardContent>
        <Typography variant="h6" gutterBottom>
          GPS位置
        </Typography>
        <div ref={mapRef} style={{ height: '400px' }} />
        {position && (
          <Typography variant="body2" style={{ marginTop: '10px' }}>
            纬度: {position.latitude.toFixed(6)}
            经度: {position.longitude.toFixed(6)}
            {position.altitude && ` 海拔: ${position.altitude.toFixed(1)}m`}
          </Typography>
        )}
      </CardContent>
    </Card>
  );
};

export default GPSMap;