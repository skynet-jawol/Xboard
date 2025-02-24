<?php

namespace App\Services\NodeManager;

use App\Models\Server;
use App\Models\User;
use App\Services\NodeManagerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrafficStatsService
{
    private NodeManagerService $nodeManager;
    private array $trafficStats = [];

    public function __construct(NodeManagerService $nodeManager)
    {
        $this->nodeManager = $nodeManager;
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
            $cacheKey = sprintf('user_traffic:%d:%d:%s:%s', 
                $user->id, 
                $server->id, 
                $startTime->timestamp,
                $endTime->timestamp
            );

            // 尝试从缓存获取
            if ($stats = Cache::get($cacheKey)) {
                return $stats;
            }

            // 从节点获取流量统计
            $stats = $this->nodeManager->getUserTraffic($server, $user, $startTime, $endTime);
            if (!$stats) {
                throw new \Exception('获取用户流量统计失败');
            }

            // 更新缓存
            Cache::put($cacheKey, $stats, now()->addMinutes(5));

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
            $cacheKey = sprintf('node_traffic:%d:%s:%s',
                $server->id,
                $startTime->timestamp,
                $endTime->timestamp
            );

            // 尝试从缓存获取
            if ($stats = Cache::get($cacheKey)) {
                return $stats;
            }

            // 从节点获取流量统计
            $stats = $this->nodeManager->getTrafficStats($server, $startTime->timestamp, $endTime->timestamp);
            if (!$stats) {
                throw new \Exception('获取节点流量统计失败');
            }

            // 更新缓存
            Cache::put($cacheKey, $stats, now()->addMinutes(5));

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
     * 清理过期的流量统计数据
     *
     * @param int $days 保留天数
     * @return void
     */
    public function cleanupTrafficStats(int $days = 30): void
    {
        try {
            $expireTime = now()->subDays($days);
            
            // 清理用户流量统计缓存
            $userTrafficKeys = Cache::get('user_traffic:*');
            foreach ($userTrafficKeys as $key) {
                if (Cache::get($key)['timestamp'] < $expireTime->timestamp) {
                    Cache::forget($key);
                }
            }

            // 清理节点流量统计缓存
            $nodeTrafficKeys = Cache::get('node_traffic:*');
            foreach ($nodeTrafficKeys as $key) {
                if (Cache::get($key)['timestamp'] < $expireTime->timestamp) {
                    Cache::forget($key);
                }
            }
        } catch (\Exception $e) {
            Log::error('清理流量统计数据失败', [
                'error' => $e->getMessage()
            ]);
        }
    }
}