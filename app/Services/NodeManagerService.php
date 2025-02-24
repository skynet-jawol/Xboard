<?php

namespace App\Services;

use App\Models\Server;
use App\Models\User;
use App\Utils\CacheKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NodeManagerService
{
    private NodeGrpcClient $client;
    
    public function __construct(NodeGrpcClient $client)
    {
        $this->client = $client;
    }

    /**
     * 获取节点状态
     *
     * @param Server $server 服务器节点
     * @return array|null
     */
    public function getNodeStatus(Server $server): ?array
    {
        try {
            $response = $this->client->getNodeStatus($server->id);
            if (!$response->success) {
                throw new \Exception($response->message);
            }
            
            return [
                'cpu_usage' => $response->stats->cpu_usage,
                'memory_usage' => $response->stats->memory_usage,
                'disk_usage' => $response->stats->disk_usage,
                'uptime' => $response->stats->uptime,
                'load' => $response->stats->load,
                'network' => [
                    'in' => $response->stats->network_in,
                    'out' => $response->stats->network_out
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取节点状态失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 获取节点在线用户数
     *
     * @param Server $server 服务器节点
     * @return int
     */
    public function getOnlineUsers(Server $server): int
    {
        try {
            $response = $this->client->getOnlineUsers($server->id);
            if (!$response->success) {
                throw new \Exception($response->message);
            }
            
            return $response->count;
        } catch (\Exception $e) {
            Log::error('获取在线用户数失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 同步用户配置到节点
     *
     * @param Server $server 服务器节点
     * @param User $user 用户信息
     * @return bool
     */
    public function syncUserToNode(Server $server, User $user): bool
    {
        try {
            // 构建用户配置
            $userConfig = [
                'id' => $user->id,
                'email' => $user->email,
                'uuid' => $user->uuid,
                'speed_limit' => $user->speed_limit,
                'device_limit' => $user->device_limit,
                'enable' => $user->enable
            ];
            
            // 调用gRPC服务同步用户配置
            $response = $this->client->syncUsers($server->id, [$userConfig]);
            if (!$response->success) {
                throw new \Exception($response->message);
            }
            
            // 更新缓存
            $cacheKey = sprintf(CacheKey::USER_NODE_CONFIG, $user->id, $server->id);
            Cache::put($cacheKey, $userConfig, now()->addHours(1));
            
            return true;
        } catch (\Exception $e) {
            Log::error('同步用户配置到节点失败', [
                'user_id' => $user->id,
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 获取用户流量统计
     *
     * @param Server $server 服务器节点
     * @param User $user 用户信息
     * @param Carbon $startTime 开始时间
     * @param Carbon $endTime 结束时间
     * @return array|null
     */
    public function getUserTraffic(Server $server, User $user, Carbon $startTime, Carbon $endTime): ?array
    {
        try {
            $response = $this->client->getUserTraffic(
                $server->id,
                $user->id,
                $startTime->timestamp,
                $endTime->timestamp
            );
            
            if (!$response->success) {
                throw new \Exception($response->message);
            }
            
            return [
                'upload' => $response->stats->upload,
                'download' => $response->stats->download,
                'connections' => $response->stats->connections
            ];
        } catch (\Exception $e) {
            Log::error('获取用户流量统计失败', [
                'user_id' => $user->id,
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 获取节点状态
     *
     * @param Server $server 服务器节点
     * @return array|null
     */
    public function getNodeStatus(Server $server): ?array
    {
        try {
            $response = $this->client->getNodeStatus($server->id);
            
            if (!$response->success) {
                throw new \Exception($response->message);
            }
            
            return [
                'system_load' => [
                    'cpu_usage' => $response->system_load->cpu_usage,
                    'memory_usage' => $response->system_load->memory_usage,
                    'disk_usage' => $response->system_load->disk_usage,
                    'load_averages' => $response->system_load->load_averages
                ],
                'xray_version' => $response->xray_version,
                'xray_status' => $response->xray_status
            ];
        } catch (\Exception $e) {
            Log::error('获取节点状态失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 获取节点状态
     *
     * @param Server $server 服务器节点
     * @return array
     */
    public function getNodeStatus(Server $server): array
    {
        try {
            // 初始化gRPC客户端
            $this->initClient($server);
            
            // 获取节点状态
            $status = $this->client->getNodeStatus($server);
            if (empty($status)) {
                throw new \Exception('获取节点状态失败');
            }
            
            return $status;
        } catch (\Exception $e) {
            Log::error('获取节点状态失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 获取节点流量统计
     *
     * @param Server $server 服务器节点
     * @param int $startTime 开始时间戳
     * @param int $endTime 结束时间戳
     * @return array
     */
    public function getTrafficStats(Server $server, int $startTime, int $endTime): array
    {
        try {
            $this->initClient($server);
            
            $stats = $this->client->getTrafficStats($server, $startTime, $endTime);
            if (empty($stats)) {
                throw new \Exception('获取流量统计失败');
            }
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('获取节点流量统计失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取系统监控指标
     *
     * @param Server $server 服务器节点
     * @return array
     */
    public function getSystemMetrics(Server $server): array
    {
        try {
            $this->initClient($server);
            
            $metrics = $this->client->getSystemMetrics($server);
            if (empty($metrics)) {
                throw new \Exception('获取系统监控指标失败');
            }
            
            return $metrics;
        } catch (\Exception $e) {
            Log::error('获取系统监控指标失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 初始化gRPC客户端
     *
     * @param Server $server
     * @return void
     */
    private function initClient(Server $server): void
    {
        if (!isset($this->client)) {
            $host = parse_url($server->host, PHP_URL_HOST) ?: $server->host;
            $port = $server->grpc_port ?: 8100; // 默认gRPC端口
            $this->client = new NodeGrpcClient($host, $port);
        }
    }
}