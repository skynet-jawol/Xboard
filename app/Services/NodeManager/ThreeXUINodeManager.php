<?php

namespace App\Services\NodeManager;

use App\Models\Server;
use App\Models\User;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThreeXUINodeManager
{
    private const HEALTH_CHECK_CACHE_TTL = 60; // 1分钟
    private const SYNC_CACHE_TTL = 300; // 5分钟

    /**
     * 同步用户配置到3x-ui节点
     *
     * @param Server $server 节点服务器
     * @param User $user 用户信息
     * @return bool
     */
    public function syncUser(Server $server, User $user): bool
    {
        try {
            $settings = $server->protocol_settings;
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json'])
                ->post("{$settings['api_host']}/api/users/sync", [
                    'api_key' => $settings['api_key'],
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'uuid' => $user->uuid,
                        'speed_limit' => $user->speed_limit,
                        'device_limit' => $user->device_limit,
                        'enable' => $user->enable
                    ]
                ]);

            if (!$response->successful()) {
                throw new \Exception("API请求失败: {$response->body()}");
            }

            $this->updateLastPushTime($server);
            return true;
        } catch (\Exception $e) {
            Log::error('同步用户到3x-ui节点失败', [
                'server_id' => $server->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 执行节点健康检查
     *
     * @param Server $server 节点服务器
     * @return bool
     */
    public function healthCheck(Server $server): bool
    {
        try {
            $settings = $server->protocol_settings;
            if (!$settings['health_check']['enabled']) {
                return true;
            }

            $cacheKey = CacheKey::get('NODE_HEALTH_CHECK', $server->id);
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $response = Http::timeout($settings['health_check']['timeout'])
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$settings['api_host']}/api/status", [
                    'api_key' => $settings['api_key']
                ]);

            $isHealthy = $response->successful();
            Cache::put($cacheKey, $isHealthy, self::HEALTH_CHECK_CACHE_TTL);

            if ($isHealthy) {
                $this->updateLastCheckTime($server);
            }

            return $isHealthy;
        } catch (\Exception $e) {
            Log::error('3x-ui节点健康检查失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取节点状态信息
     *
     * @param Server $server 节点服务器
     * @return array|null
     */
    public function getNodeStatus(Server $server): ?array
    {
        try {
            $settings = $server->protocol_settings;
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$settings['api_host']}/api/status", [
                    'api_key' => $settings['api_key']
                ]);

            if (!$response->successful()) {
                throw new \Exception("获取节点状态失败: {$response->body()}");
            }

            $data = $response->json();
            return [
                'cpu_usage' => $data['cpu_usage'] ?? 0,
                'memory_usage' => $data['memory_usage'] ?? 0,
                'disk_usage' => $data['disk_usage'] ?? 0,
                'uptime' => $data['uptime'] ?? 0,
                'load' => $data['load'] ?? 0,
                'network' => [
                    'in' => $data['network_in'] ?? 0,
                    'out' => $data['network_out'] ?? 0
                ],
                'online_users' => $data['online_users'] ?? 0
            ];
        } catch (\Exception $e) {
            Log::error('获取3x-ui节点状态失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 更新节点最后检查时间
     *
     * @param Server $server 节点服务器
     */
    private function updateLastCheckTime(Server $server): void
    {
        $now = time();
        Cache::put(
            CacheKey::get('SERVER_3X_UI_LAST_CHECK_AT', $server->id),
            $now,
            now()->addMinutes(5)
        );
    }

    /**
     * 更新节点最后推送时间
     *
     * @param Server $server 节点服务器
     */
    private function updateLastPushTime(Server $server): void
    {
        $now = time();
        Cache::put(
            CacheKey::get('SERVER_3X_UI_LAST_PUSH_AT', $server->id),
            $now,
            now()->addMinutes(5)
        );
    }

    /**
     * 获取用户流量统计
     *
     * @param Server $server 节点服务器
     * @param User $user 用户信息
     * @return array|null
     */
    public function getUserTraffic(Server $server, User $user): ?array
    {
        try {
            $settings = $server->protocol_settings;
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$settings['api_host']}/api/users/{$user->id}/traffic", [
                    'api_key' => $settings['api_key']
                ]);

            if (!$response->successful()) {
                throw new \Exception("获取用户流量统计失败: {$response->body()}");
            }

            $data = $response->json();
            return [
                'up' => $data['up'] ?? 0,
                'down' => $data['down'] ?? 0,
                'total' => $data['total'] ?? 0
            ];
        } catch (\Exception $e) {
            Log::error('获取用户流量统计失败', [
                'server_id' => $server->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 更新节点协议配置
     *
     * @param Server $server 节点服务器
     * @param array $config 协议配置
     * @return bool
     */
    public function updateProtocolConfig(Server $server, array $config): bool
    {
        try {
            $settings = $server->protocol_settings;
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json'])
                ->put("{$settings['api_host']}/api/config", [
                    'api_key' => $settings['api_key'],
                    'config' => $config
                ]);

            if (!$response->successful()) {
                throw new \Exception("更新协议配置失败: {$response->body()}");
            }

            return true;
        } catch (\Exception $e) {
            Log::error('更新节点协议配置失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}