<?php

namespace App\Services;

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use NodeManager\NodeManagerClient;
use NodeManager\NodeRequest;
use NodeManager\UserInfo;
use NodeManager\QuotaInfo;
use NodeManager\TrafficRequest;
use NodeManager\MetricsRequest;

class NodeGrpcClient
{
    private NodeManagerClient $client;
    
    /**
     * 初始化gRPC客户端
     *
     * @param string $host 节点主机地址
     * @param int $port gRPC端口
     */
    public function __construct(string $host, int $port)
    {
        $this->client = new NodeManagerClient("$host:$port", [
            'credentials' => \Grpc\ChannelCredentials::createInsecure(),
            'timeout' => 5000
        ]);
    }
    
    /**
     * 获取节点状态
     *
     * @param Server $server
     * @return array
     */
    public function getNodeStatus(Server $server): array
    {
        try {
            $request = new NodeRequest();
            $request->setNodeId((string)$server->id);
            
            [$response, $status] = $this->client->GetNodeStatus($request)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC调用失败: {$status->details}");
            }
            
            return [
                'status' => $response->getStatus(),
                'cpu_usage' => $response->getCpuUsage(),
                'memory_usage' => $response->getMemoryUsage(),
                'network_in' => $response->getNetworkIn(),
                'network_out' => $response->getNetworkOut(),
                'online_users' => $response->getOnlineUsers(),
                'version' => $response->getVersion()
            ];
        } catch (\Exception $e) {
            Log::error('gRPC获取节点状态失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 同步用户配置
     *
     * @param Server $server
     * @param User $user
     * @return bool
     */
    public function syncUser(Server $server, User $user): bool
    {
        try {
            $userInfo = new UserInfo();
            $userInfo->setUserId($user->id)
                ->setEmail($user->email)
                ->setUuid($user->uuid);
                
            $quotaInfo = new QuotaInfo();
            $quotaInfo->setUserId($user->id)
                ->setSpeedLimit($user->speed_limit)
                ->setDeviceLimit($user->device_limit);
            
            // 添加用户
            [$response1, $status1] = $this->client->AddUser($userInfo)->wait();
            if ($status1->code !== \Grpc\STATUS_OK) {
                throw new \Exception("添加用户失败: {$status1->details}");
            }
            
            // 更新配额
            [$response2, $status2] = $this->client->UpdateUserQuota($quotaInfo)->wait();
            if ($status2->code !== \Grpc\STATUS_OK) {
                throw new \Exception("更新用户配额失败: {$status2->details}");
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('gRPC同步用户配置失败', [
                'server_id' => $server->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取流量统计
     *
     * @param Server $server
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    public function getTrafficStats(Server $server, int $startTime, int $endTime): array
    {
        try {
            $request = new TrafficRequest();
            $request->setNodeId((string)$server->id)
                ->setStartTime($startTime)
                ->setEndTime($endTime);
            
            [$response, $status] = $this->client->GetTrafficStats($request)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC调用失败: {$status->details}");
            }
            
            $stats = [];
            foreach ($response->getUsers() as $userTraffic) {
                $stats[$userTraffic->getUserId()] = [
                    'upload' => $userTraffic->getUpload(),
                    'download' => $userTraffic->getDownload(),
                    'connections' => array_map(function($conn) {
                        return [
                            'source_ip' => $conn->getSourceIp(),
                            'source_port' => $conn->getSourcePort(),
                            'dest_ip' => $conn->getDestIp(),
                            'dest_port' => $conn->getDestPort(),
                            'protocol' => $conn->getProtocol()
                        ];
                    }, $userTraffic->getConnections())
                ];
            }
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('gRPC获取流量统计失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取系统监控指标
     *
     * @param Server $server
     * @return array
     */
    public function getSystemMetrics(Server $server): array
    {
        try {
            $request = new MetricsRequest();
            $request->setNodeId((string)$server->id);
            
            [$response, $status] = $this->client->GetSystemMetrics($request)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC调用失败: {$status->details}");
            }
            
            return [
                'cpu' => [
                    'usage' => $response->getCpu()->getUsage(),
                    'cores' => $response->getCpu()->getCores()
                ],
                'memory' => [
                    'total' => $response->getMemory()->getTotal(),
                    'used' => $response->getMemory()->getUsed(),
                    'free' => $response->getMemory()->getFree()
                ],
                'disk' => [
                    'total' => $response->getDisk()->getTotal(),
                    'used' => $response->getDisk()->getUsed(),
                    'free' => $response->getDisk()->getFree()
                ],
                'network' => [
                    'in_speed' => $response->getNetwork()->getInSpeed(),
                    'out_speed' => $response->getNetwork()->getOutSpeed(),
                    'connections' => $response->getNetwork()->getConnections()
                ],
                'load' => $response->getLoad(),
                'uptime' => $response->getUptime()
            ];
        } catch (\Exception $e) {
            Log::error('gRPC获取系统监控指标失败', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}