<?php

namespace App\Services\NodeManager;

use App\Services\NodeGrpcClient;
use App\Models\Server;
use App\Events\NodeStatusChanged;
use App\Events\NodeAlertEvent;

class NodeManagerService
{
    private NodeGrpcClient $grpcClient;
    private array $metrics = [];
    private array $alerts = [];

    public function __construct(NodeGrpcClient $grpcClient)
    {
        $this->grpcClient = $grpcClient;
    }

    /**
     * 获取节点状态
     */
    public function getNodeStatus(string $nodeId): array
    {
        try {
            $status = $this->grpcClient->getNodeStatus($nodeId);
            $this->updateNodeMetrics($nodeId, $status);
            return $status;
        } catch (\Exception $e) {
            $this->createAlert($nodeId, 'NODE_ERROR', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 更新节点配置
     */
    public function updateNodeConfig(string $nodeId, array $config): bool
    {
        try {
            $server = Server::findOrFail($nodeId);
            $nodeConfig = new NodeConfig(
                node_id: $nodeId,
                name: $server->name,
                address: $server->host,
                port: $server->port,
                transport_type: $server->network ?? 'tcp',
                settings: $config['settings'] ?? [],
                tags: $server->tags ?? [],
                rate_limit: [
                    'enabled' => $server->rate_limit_enabled ?? false,
                    'upload' => $server->rate_limit_upload ?? 0,
                    'download' => $server->rate_limit_download ?? 0
                ],
                security: [
                    'tls_enabled' => $server->tls_enabled ?? true,
                    'allow_insecure' => $server->allow_insecure ?? false,
                    'cipher_suites' => $server->cipher_suites ?? []
                ]
            );

            $nodeConfig->validate();
            $result = $this->grpcClient->updateNodeConfig($nodeConfig);

            if ($result) {
                event(new NodeStatusChanged($nodeId, 'config_updated'));
            }

            return $result;
        } catch (\Exception $e) {
            $this->createAlert($nodeId, 'CONFIG_ERROR', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 更新节点监控指标
     */
    private function updateNodeMetrics(string $nodeId, array $status): void
    {
        $this->metrics[$nodeId] = [
            'timestamp' => time(),
            'cpu_usage' => $status['cpu_usage'] ?? 0,
            'memory_usage' => $status['memory_usage'] ?? 0,
            'network_rx' => $status['network_rx'] ?? 0,
            'network_tx' => $status['network_tx'] ?? 0,
            'connections' => $status['connections'] ?? 0
        ];

        $this->checkThresholds($nodeId);
    }

    /**
     * 检查监控指标阈值
     */
    private function checkThresholds(string $nodeId): void
    {
        $metrics = $this->metrics[$nodeId];
        
        if ($metrics['cpu_usage'] > 90) {
            $this->createAlert($nodeId, 'HIGH_CPU_USAGE', 'CPU使用率超过90%');
        }
        
        if ($metrics['memory_usage'] > 90) {
            $this->createAlert($nodeId, 'HIGH_MEMORY_USAGE', '内存使用率超过90%');
        }

        if ($metrics['connections'] > 10000) {
            $this->createAlert($nodeId, 'HIGH_CONNECTION_COUNT', '连接数超过10000');
        }
    }

    /**
     * 创建节点告警
     */
    private function createAlert(string $nodeId, string $type, string $message): void
    {
        $alert = [
            'node_id' => $nodeId,
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];

        $this->alerts[] = $alert;
        event(new NodeAlertEvent($nodeId, $type, $message));
    }

    /**
     * 获取节点告警历史
     */
    public function getNodeAlerts(string $nodeId): array
    {
        return array_filter($this->alerts, fn($alert) => $alert['node_id'] === $nodeId);
    }
}