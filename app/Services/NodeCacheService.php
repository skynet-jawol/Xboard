<?php

namespace App\Services;
use App\Models\Server;
use App\Models\User;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class NodeCacheService
{
    private const CACHE_TTL = 3600; // 1小时
    private const REDIS_PREFIX = 'node_cache:';
    private const BATCH_SIZE = 100; // 批量处理大小
    
    /**
     * 实现多级缓存策略
     * L1: Redis (热点数据)
     * L2: Laravel Cache (次热点数据)
     */
    public function getNodeConfig(Server $server, User $user)
    {
        $cacheKey = sprintf(CacheKey::USER_NODE_CONFIG, $user->id, $server->id);
        $redisKey = self::REDIS_PREFIX . $cacheKey;
        
        // 尝试从Redis获取
        $config = Redis::get($redisKey);
        if ($config) {
            return json_decode($config, true);
        }
        
        // 尝试从Laravel Cache获取
        $config = Cache::get($cacheKey);
        if ($config) {
            // 提升到Redis缓存
            Redis::setex($redisKey, self::CACHE_TTL, json_encode($config));
            return $config;
        }
        
        return null;
    }
    
    /**
     * 批量获取节点配置
     */
    public function batchGetNodeConfigs(Server $server, Collection $users)
    {
        $configs = [];
        $missingKeys = [];
        
        foreach ($users as $user) {
            $cacheKey = sprintf(CacheKey::USER_NODE_CONFIG, $user->id, $server->id);
            $redisKey = self::REDIS_PREFIX . $cacheKey;
            $configs[$user->id] = null;
            $missingKeys[] = $redisKey;
        }
        
        // 批量从Redis获取
        $redisResults = Redis::mget($missingKeys);
        foreach ($redisResults as $index => $result) {
            if ($result) {
                $userId = $users[$index]->id;
                $configs[$userId] = json_decode($result, true);
            }
        }
        
        return $configs;
    }
    
    /**
     * 更新节点配置缓存
     */
    public function updateNodeConfig(Server $server, User $user, array $config)
    {
        try {
            $cacheKey = sprintf(CacheKey::USER_NODE_CONFIG, $user->id, $server->id);
            $redisKey = self::REDIS_PREFIX . $cacheKey;
            
            // 同时更新两级缓存
            Redis::setex($redisKey, self::CACHE_TTL, json_encode($config));
            Cache::put($cacheKey, $config, now()->addHours(1));
            
            return true;
        } catch (\Exception $e) {
            Log::error('更新节点配置缓存失败', [
                'user_id' => $user->id,
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 批量更新节点配置缓存
     */
    public function batchUpdateNodeConfigs(Server $server, array $userConfigs)
    {
        try {
            $pipeline = Redis::pipeline();
            foreach ($userConfigs as $userId => $config) {
                $cacheKey = sprintf(CacheKey::USER_NODE_CONFIG, $userId, $server->id);
                $redisKey = self::REDIS_PREFIX . $cacheKey;
                $pipeline->setex($redisKey, self::CACHE_TTL, json_encode($config));
                Cache::put($cacheKey, $config, now()->addHours(1));
            }
            $pipeline->execute();
            return true;
        } catch (\Exception $e) {
            Log::error('批量更新节点配置缓存失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 清除节点配置缓存
     */
    public function clearNodeConfig(Server $server, User $user)
    {
        try {
            $cacheKey = sprintf(CacheKey::USER_NODE_CONFIG, $user->id, $server->id);
            $redisKey = self::REDIS_PREFIX . $cacheKey;
            
            Redis::del($redisKey);
            Cache::forget($cacheKey);
            
            return true;
        } catch (\Exception $e) {
            Log::error('清除节点配置缓存失败', [
                'user_id' => $user->id,
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 批量清除节点配置缓存
     */
    public function batchClearNodeConfigs(Server $server, Collection $users)
    {
        try {
            $pipeline = Redis::pipeline();
            foreach ($users as $user) {
                $cacheKey = sprintf(CacheKey::USER_NODE_CONFIG, $user->id, $server->id);
                $redisKey = self::REDIS_PREFIX . $cacheKey;
                $pipeline->del($redisKey);
                Cache::forget($cacheKey);
            }
            $pipeline->execute();
            return true;
        } catch (\Exception $e) {
            Log::error('批量清除节点配置缓存失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 预热热点数据
     */
    public function warmupHotData(Server $server)
    {
        try {
            // 分批获取活跃用户
            User::where('enable', 1)
                ->where('expired_at', '>', now())
                ->chunk(self::BATCH_SIZE, function ($users) use ($server) {
                    $configs = [];
                    foreach ($users as $user) {
                        // 这里需要实现获取用户配置的逻辑
                        $config = $this->generateUserConfig($server, $user);
                        if ($config) {
                            $configs[$user->id] = $config;
                        }
                    }
                    $this->batchUpdateNodeConfigs($server, $configs);
                });
            return true;
        } catch (\Exception $e) {
            Log::error('预热节点配置缓存失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 生成用户配置
     */
    private function generateUserConfig(Server $server, User $user): ?array
    {
        // 实现具体的配置生成逻辑
        return [
            'user_id' => $user->id,
            'server_id' => $server->id,
            // 其他配置项...
        ];
    }
}