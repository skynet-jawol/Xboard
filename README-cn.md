# 树莓派网络遥控车软件 Network RC

[English](./README.md) | [文档](https://network-rc.esonwong.com)

Network RC 是运行在树莓派和浏览器上的网络遥控车软件。具备以下特性：

- 低延迟控制和网络图传
- 通道自定义（27 个 高低电平或者 PWM 通道）
- 支持多摄像头，自适应传输分辨率
- 支持触屏操作、游戏手柄、枪控、板控
- 支持实时语音收听和语音喊话/语音对讲
- 内置服务器网络穿透/点对点连接 NAT 网络穿透自动切换
- 系统语音播报
- 播放音频
- 远程分享控制
- GPS定位和轨迹记录
- 高级日志系统
- Cloudflare Zero Trust Tunnels支持
- 优化的4G网络传输

## 系统要求

- 树莓派（已在树莓派3B+及以上版本测试）
- 树莓派操作系统（Debian 12 Bookworm）
- Node.js 18+
- ffmpeg
- pulseaudio（用于音频功能）

## 依赖

- ffmpeg: 运行前请确保树莓派上安装了 ffmpeg，安装方法：
  ```sh
  sudo apt install ffmpeg -y
  ```
- pulseaudio（用于音频功能）：
  ```sh
  sudo apt install pulseaudio -y
  sudo apt install libpulse-dev -y
  ```
- nodejs 18+

## 安装

```bash
bash <(curl -sL https://download.esonwong.com/network-rc/install.sh)
```

## 使用教程

- 改装 RC 遥控车
  - 视频教程: [4G 网络 RC 遥控车 02 - DIY 网络控制改造教程](https://www.bilibili.com/video/BV1iK4y1r7mD)
  - 图文教程: [WiFi 网络遥控车制作教程](https://blog.esonwong.com/WiFi-4G-5G-%E7%BD%91%E7%BB%9C%E9%81%A5%E6%8E%A7%E8%BD%A6%E5%88%B6%E4%BD%9C%E6%95%99%E7%A8%8B/)
- 4G 远程控制
  - 视频教程：[4G 5G 网络 RC 遥控车 03 - 无限距离远程遥控？](https://www.bilibili.com/video/BV1Xp4y1X7fa)
  - 图文教程：[网络遥控车互联网控制教程](https://blog.esonwong.com/%E7%BD%91%E7%BB%9C%E9%81%A5%E6%8E%A7%E8%BD%A6%E4%BA%92%E8%81%94%E7%BD%91%E6%8E%A7%E5%88%B6%E6%95%99%E7%A8%8B/)

## 代码贡献指引

```bash
git clone https://github.com/esonwong/network-rc.git
cd network-rc/front-end
yarn # or npm install
yarn build # or npm run build
cd ..
npm install # only supports npm
node index.js
```

打开 `http://[你的树莓派 ip 地址]:8080`

## 接线图

![GPIO](./gpio.jpg)

## 树莓派软件下载

- <https://download.esonwong.com/network-rc>

## 社群

### 微信群

交流请移步微信群，入群方法添加微信 `EsonWong_` 备注 `Network RC`

### Telegram群组

[链接](https://t.me/joinchat/sOaIYYi2sJJlOWZl)

## 捐赠

[Paypal捐赠链接](https://www.paypal.com/donate?business=27B3QGKHUM2FE&item_name=Buy+me+a+cup+of+coffee&currency_code=USD)
![微信赞赏码](https://blog.esonwong.com/asset/wechat-donate.jpg)
![Paypal捐赠二维码](https://blog.esonwong.com/asset/paypal-donate.png)

## 链接

- [作者B站主页](https://space.bilibili.com/96740361)
- [作者YouTube主页](https://www.youtube.com/c/itiwll)

## Credits

- [ws-avc-player](https://github.com/matijagaspar/ws-avc-player)
- [@clusterws/cws](https://github.com/ClusterWS/cWS)
- [rpio](https://github.com/jperkin/node-rpio)
- [rpio-pwm](https://github.com/xinkaiwang/rpio-pwm)
- [xf-tts-socket](https://github.com/jimuyouyou/xf-tts-socket)
- Eson Wong - 提供免费的FRP服务器
