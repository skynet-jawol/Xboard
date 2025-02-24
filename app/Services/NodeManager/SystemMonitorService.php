<?php

namespace App\Services\NodeManager;

use App\Services\NodeGrpcClient;
use App\Events\SystemMetricsEvent;
use Illuminate\Support\Facades\Cache;

class SystemMonitorService
{
    private NodeGrpcClient $grpcClient;
    private const CACHE_TTL = 300; // 5分钟缓存

    public function __construct(NodeGrpcClient $grpcClient)
    {
        $this->grpcClient = $grpcClient;
    }

    /**
     * 获取系统指标
     */
    public function getSystemMetrics(string $nodeId): array
    {
        $cacheKey = "system_metrics:{$nodeId}";
        
        try {
            // 优先从缓存获取
            if ($metrics = Cache::get($cacheKey)) {
                return $metrics;
            }

            $metrics = $this->grpcClient->getSystemMetrics(['node_id' => $nodeId]);
            $this->processMetrics($nodeId, $metrics);

            // 缓存结果
            Cache::put($cacheKey, $metrics, self::CACHE_TTL);
            return $metrics;
        } catch (\Exception $e) {
            $this->logError($nodeId, 'METRICS_ERROR', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理系统指标
     */
    private function processMetrics(string $nodeId, array $metrics): void
    {
        // 计算系统负载
        $load = $this->calculateSystemLoad($metrics);
        $metrics['system_load'] = $load;

        // 分析网络性能
        $networkPerformance = $this->analyzeNetworkPerformance($metrics);
        $metrics['network_performance'] = $networkPerformance;

        // 触发指标事件
        event(new SystemMetricsEvent($nodeId, $metrics));
    }

    /**
     * 计算系统负载
     */
    private function calculateSystemLoad(array $metrics): float
    {
        $cpuWeight = 0.4;
        $memoryWeight = 0.3;
        $networkWeight = 0.3;

        $cpuLoad = $metrics['cpu_usage'] / 100;
        $memoryLoad = $metrics['memory_usage'] / 100;
        $networkLoad = min(1, $metrics['network_tx'] / (1024 * 1024 * 1024)); // 按GB计算

        return ($cpuLoad * $cpuWeight) + 
               ($memoryLoad * $memoryWeight) + 
               ($networkLoad * $networkWeight);
    }

    /**
     * 分析网络性能
     */
    private function analyzeNetworkPerformance(array $metrics): array
    {
        return [
            'bandwidth_usage' => $metrics['network_tx'] + $metrics['network_rx'],
            'connection_count' => $metrics['connections'],
            'packet_loss' => $metrics['packet_loss'] ?? 0,
            'latency' => $metrics['latency'] ?? 0
        ];
    }

    /**
     * 记录错误日志
     */
    private function logError(string $nodeId, string $type, string $message): void
    {
        \Log::error("SystemMonitor Error", [
            'node_id' => $nodeId,
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ]);
    }

    /**
     * 获取历史指标数据
     */
    public function getHistoricalMetrics(string $nodeId, int $hours = 24): array
    {
        try {
            $endTime = time();
            $startTime = $endTime - ($hours * 3600);

            return $this->grpcClient->getHistoricalMetrics([
                'node_id' => $nodeId,
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
        } catch (\Exception $e) {
            $this->logError($nodeId, 'HISTORICAL_METRICS_ERROR', $e->getMessage());
            throw $e;
        }
    }
}