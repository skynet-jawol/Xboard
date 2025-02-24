<?php

namespace App\Services\NodeManager;

use App\Models\Server;
use App\Models\User;
use App\Utils\CacheKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoadBalancer
{
    private const CACHE_TTL = 300; // 5分钟
    private const METHODS = ['round-robin', 'least-connection', 'weighted-random'];

    /**
     * 根据负载均衡策略选择节点
     *
     * @param Collection $servers 可用节点列表
     * @param User $user 用户信息
     * @return Server|null
     */
    public function selectNode(Collection $servers, User $user): ?Server
    {
        if ($servers->isEmpty()) {
            return null;
        }

        // 获取第一个节点的负载均衡配置
        $settings = $servers->first()->protocol_settings;
        if (!$settings['load_balance']['enabled']) {
            return $servers->first();
        }

        $method = $settings['load_balance']['method'] ?? 'round-robin';
        return match ($method) {
            'round-robin' => $this->roundRobin($servers),
            'least-connection' => $this->leastConnection($servers),
            'weighted-random' => $this->weightedRandom($servers),
            default => $servers->first()
        };
    }

    /**
     * 轮询算法
     *
     * @param Collection $servers 节点列表
     * @return Server
     */
    private function roundRobin(Collection $servers): Server
    {
        $key = CacheKey::get('LOAD_BALANCE_ROUND_ROBIN');
        $current = Cache::get($key, 0);
        $next = ($current + 1) % $servers->count();
        
        Cache::put($key, $next, self::CACHE_TTL);
        return $servers->get($current);
    }

    /**
     * 最小连接数算法
     *
     * @param Collection $servers 节点列表
     * @return Server
     */
    private function leastConnection(Collection $servers): Server
    {
        return $servers->sortBy(function ($server) {
            return Cache::get(
                CacheKey::get('SERVER_3X_UI_ONLINE_USER', $server->id),
                0
            );
        })->first();
    }

    /**
     * 加权随机算法
     *
     * @param Collection $servers 节点列表
     * @return Server
     */
    private function weightedRandom(Collection $servers): Server
    {
        $totalWeight = $servers->sum(function ($server) {
            return $server->protocol_settings['load_balance']['weight'] ?? 1;
        });

        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($servers as $server) {
            $currentWeight += $server->protocol_settings['load_balance']['weight'] ?? 1;
            if ($random <= $currentWeight) {
                return $server;
            }
        }

        return $servers->first();
    }

    /**
     * 检查节点是否可用
     *
     * @param Server $server 节点服务器
     * @return bool
     */
    public function isAvailable(Server $server): bool
    {
        $settings = $server->protocol_settings;
        if (!$settings['health_check']['enabled']) {
            return true;
        }

        $key = CacheKey::get('NODE_HEALTH_CHECK', $server->id);
        return Cache::get($key, false);
    }

    /**
     * 获取所有可用节点
     *
     * @param Collection $servers 节点列表
     * @return Collection
     */
    public function getAvailableNodes(Collection $servers): Collection
    {
        return $servers->filter(function ($server) {
            return $this->isAvailable($server);
        });
    }
}