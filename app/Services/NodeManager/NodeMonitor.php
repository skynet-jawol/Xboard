<?php

namespace App\Services\NodeManager;

use App\Models\Server;
use App\Services\NodeManagerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class NodeMonitor
{
    private array $metrics = [];
    private array $alerts = [];
    private NodeManagerService $nodeManager;

    public function __construct(NodeManagerService $nodeManager)
    {
        $this->nodeManager = $nodeManager;
    }

    /**
     * 收集节点指标数据
     *
     * @param Server $server 服务器节点
     * @return void
     */
    public function collectMetrics(Server $server): void
    {
        try {
            $status = $this->nodeManager->getNodeStatus($server);
            if (empty($status)) {
                throw new \Exception('获取节点状态失败');
            }

            $this->metrics[$server->id] = [
                'timestamp' => time(),
                'system_load' => $status['system_load'],
                'xray_version' => $status['xray_version'],
                'xray_status' => $status['xray_status']
            ];

            // 检查告警阈值
            $this->checkThresholds($server);

            // 更新缓存
            $cacheKey = sprintf('node_metrics:%d', $server->id);
            Cache::put($cacheKey, $this->metrics[$server->id], now()->addMinutes(5));

        } catch (\Exception $e) {
            Log::error('收集节点指标数据失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 检查告警阈值
     *
     * @param Server $server 服务器节点
     * @return void
     */
    private function checkThresholds(Server $server): void
    {
        $metrics = $this->metrics[$server->id];
        $systemLoad = $metrics['system_load'];

        // CPU使用率告警
        if ($systemLoad['cpu_usage'] > 90) {
            $this->createAlert($server->id, 'HIGH_CPU_USAGE', 
                sprintf('节点CPU使用率过高: %.2f%%', $systemLoad['cpu_usage']));
        }

        // 内存使用率告警
        if ($systemLoad['memory_usage'] > 90) {
            $this->createAlert($server->id, 'HIGH_MEMORY_USAGE',
                sprintf('节点内存使用率过高: %.2f%%', $systemLoad['memory_usage']));
        }

        // 磁盘使用率告警
        if ($systemLoad['disk_usage'] > 90) {
            $this->createAlert($server->id, 'HIGH_DISK_USAGE',
                sprintf('节点磁盘使用率过高: %.2f%%', $systemLoad['disk_usage']));
        }

        // Xray状态检查
        if ($metrics['xray_status'] !== 'running') {
            $this->createAlert($server->id, 'XRAY_NOT_RUNNING',
                sprintf('Xray服务异常: %s', $metrics['xray_status']));
        }
    }

    /**
     * 创建告警
     *
     * @param int $serverId 服务器ID
     * @param string $type 告警类型
     * @param string $message 告警信息
     * @return void
     */
    private function createAlert(int $serverId, string $type, string $message): void
    {
        $alert = [
            'server_id' => $serverId,
            'type' => $type,
            'message' => $message,
            'timestamp' => time(),
            'status' => 'active'
        ];

        $this->alerts[] = $alert;

        // 触发告警事件
        Event::dispatch('node.alert', $alert);

        // 更新告警缓存
        $cacheKey = sprintf('node_alerts:%d', $serverId);
        $alerts = Cache::get($cacheKey, []);
        $alerts[] = $alert;
        Cache::put($cacheKey, $alerts, now()->addDays(7));
    }

    /**
     * 获取节点告警历史
     *
     * @param Server $server 服务器节点
     * @param int $limit 限制数量
     * @return array
     */
    public function getAlertHistory(Server $server, int $limit = 50): array
    {
        $cacheKey = sprintf('node_alerts:%d', $server->id);
        return array_slice(Cache::get($cacheKey, []), 0, $limit);
    }

    /**
     * 清理过期告警
     *
     * @param Server $server 服务器节点
     * @param int $days 保留天数
     * @return void
     */
    public function cleanupAlerts(Server $server, int $days = 7): void
    {
        $cacheKey = sprintf('node_alerts:%d', $server->id);
        $alerts = Cache::get($cacheKey, []);
        $threshold = time() - ($days * 86400);

        $alerts = array_filter($alerts, function($alert) use ($threshold) {
            return $alert['timestamp'] > $threshold;
        });

        Cache::put($cacheKey, $alerts, now()->addDays($days));
    }

    /**
     * 获取节点健康状态
     *
     * @param Server $server 服务器节点
     * @return array
     */
    public function getNodeHealth(Server $server): array
    {
        $metrics = $this->metrics[$server->id] ?? null;
        if (!$metrics) {
            return ['status' => 'unknown'];
        }

        $systemLoad = $metrics['system_load'];
        $health = [
            'status' => 'healthy',
            'checks' => [
                'cpu' => $systemLoad['cpu_usage'] < 90,
                'memory' => $systemLoad['memory_usage'] < 90,
                'disk' => $systemLoad['disk_usage'] < 90,
                'xray' => $metrics['xray_status'] === 'running'
            ]
        ];

        // 如果任何检查项失败，则状态为不健康
        if (in_array(false, $health['checks'], true)) {
            $health['status'] = 'unhealthy';
        }

        return $health;
    }

    /**
     * 获取节点指标数据
     *
     * @param int $serverId 服务器ID
     * @return array|null
     */
    public function getMetrics(int $serverId): ?array
    {
        return $this->metrics[$serverId] ?? null;
    }

    /**
     * 获取节点告警列表
     *
     * @param int $serverId 服务器ID
     * @return array
     */
    public function getAlerts(int $serverId): array
    {
        return array_filter($this->alerts, function($alert) use ($serverId) {
            return $alert['server_id'] === $serverId;
        });
    }
}