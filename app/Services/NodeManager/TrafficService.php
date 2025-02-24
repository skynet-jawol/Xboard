<?php

namespace App\Services\NodeManager;

use App\Models\Server;
use App\Models\User;
use App\Services\NodeGrpcClient;
use App\Utils\CacheKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrafficService
{
    private NodeGrpcClient $client;
    
    public function __construct(NodeGrpcClient $client)
    {
        $this->client = $client;
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
            
            $stats = [
                'up_traffic' => $response->stats->up_traffic,
                'down_traffic' => $response->stats->down_traffic,
                'total_traffic' => $response->stats->up_traffic + $response->stats->down_traffic,
                'start_time' => $startTime->timestamp,
                'end_time' => $endTime->timestamp
            ];
            
            // 更新缓存
            $cacheKey = sprintf(CacheKey::USER_TRAFFIC_STATS, $user->id, $server->id);
            Cache::put($cacheKey, $stats, now()->addHours(1));
            
            return $stats;
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
     * 获取节点总流量统计
     *
     * @param Server $server 服务器节点
     * @param Carbon $startTime 开始时间
     * @param Carbon $endTime 结束时间
     * @return array|null
     */
    public function getNodeTraffic(Server $server, Carbon $startTime, Carbon $endTime): ?array
    {
        try {
            $response = $this->client->getNodeTraffic(
                $server->id,
                $startTime->timestamp,
                $endTime->timestamp
            );
            
            if (!$response->success) {
                throw new \Exception($response->message);
            }
            
            $stats = [
                'up_traffic' => $response->stats->up_traffic,
                'down_traffic' => $response->stats->down_traffic,
                'total_traffic' => $response->stats->up_traffic + $response->stats->down_traffic,
                'start_time' => $startTime->timestamp,
                'end_time' => $endTime->timestamp
            ];
            
            // 更新缓存
            $cacheKey = sprintf(CacheKey::NODE_TRAFFIC_STATS, $server->id);
            Cache::put($cacheKey, $stats, now()->addHours(1));
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('获取节点流量统计失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 检查流量使用告警
     *
     * @param Server $server 服务器节点
     * @param array $stats 流量统计数据
     * @return array 告警信息
     */
    public function checkTrafficAlerts(Server $server, array $stats): array
    {
        $alerts = [];
        
        // 检查流量使用是否超过阈值
        $trafficLimit = $server->traffic_limit ?? 0;
        if ($trafficLimit > 0 && $stats['total_traffic'] > $trafficLimit * 0.9) {
            $alerts[] = [
                'type' => 'HIGH_TRAFFIC_USAGE',
                'message' => sprintf('节点流量使用已达到限制的%.1f%%', ($stats['total_traffic'] / $trafficLimit) * 100),
                'level' => 'warning'
            ];
        }
        
        return $alerts;
    }
}