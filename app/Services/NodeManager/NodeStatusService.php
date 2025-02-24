<?php

namespace App\Services\NodeManager;

use App\Models\Server;
use App\Services\NodeGrpcClient;
use App\Utils\CacheKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NodeStatusService
{
    private NodeGrpcClient $client;
    
    public function __construct(NodeGrpcClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * 获取节点系统状态
     *
     * @param Server $server 服务器节点
     * @return array|null
     */
    public function getSystemStats(Server $server): ?array
    {
        try {
            $response = $this->client->getSystemStats($server->id);
            if (!$response->success) {
                throw new \Exception($response->message);
            }
            
            $stats = [
                'cpu_usage' => $response->system_load->cpu_usage,
                'memory_usage' => $response->system_load->memory_usage,
                'disk_usage' => $response->system_load->disk_usage,
                'load_averages' => $response->system_load->load_averages,
                'timestamp' => Carbon::now()->timestamp
            ];
            
            // 更新缓存
            $cacheKey = sprintf(CacheKey::NODE_SYSTEM_STATS, $server->id);
            Cache::put($cacheKey, $stats, now()->addMinutes(5));
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('获取节点系统状态失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 获取节点Xray状态
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
            
            $status = [
                'xray_version' => $response->xray_version,
                'xray_status' => $response->xray_status,
                'timestamp' => Carbon::now()->timestamp
            ];
            
            // 更新缓存
            $cacheKey = sprintf(CacheKey::NODE_STATUS, $server->id);
            Cache::put($cacheKey, $status, now()->addMinutes(5));
            
            return $status;
        } catch (\Exception $e) {
            Log::error('获取节点状态失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 检查节点告警阈值
     *
     * @param Server $server 服务器节点
     * @param array $stats 系统状态数据
     * @return array 告警信息
     */
    public function checkAlerts(Server $server, array $stats): array
    {
        $alerts = [];
        
        // CPU使用率告警
        if ($stats['cpu_usage'] > 90) {
            $alerts[] = [
                'type' => 'HIGH_CPU_USAGE',
                'message' => "CPU使用率超过90%: {$stats['cpu_usage']}%",
                'level' => 'warning'
            ];
        }
        
        // 内存使用率告警
        if ($stats['memory_usage'] > 90) {
            $alerts[] = [
                'type' => 'HIGH_MEMORY_USAGE',
                'message' => "内存使用率超过90%: {$stats['memory_usage']}%",
                'level' => 'warning'
            ];
        }
        
        // 磁盘使用率告警
        if ($stats['disk_usage'] > 90) {
            $alerts[] = [
                'type' => 'HIGH_DISK_USAGE',
                'message' => "磁盘使用率超过90%: {$stats['disk_usage']}%",
                'level' => 'warning'
            ];
        }
        
        return $alerts;
    }
}