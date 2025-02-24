<?php

namespace App\Services\NodeManager;

use App\Services\NodeGrpcClient;
use App\Models\User;
use App\Events\UserSyncEvent;

class UserSyncService
{
    private NodeGrpcClient $grpcClient;
    private array $syncStatus = [];

    public function __construct(NodeGrpcClient $grpcClient)
    {
        $this->grpcClient = $grpcClient;
    }

    /**
     * 同步用户到节点
     */
    public function syncToNode(string $nodeId, array $users): bool
    {
        try {
            foreach ($users as $user) {
                $userInfo = new UserInfo(
                    user_id: $user->id,
                    email: $user->email,
                    quota: $user->transfer_enable,
                    settings: [
                        'speed_limit' => $user->speed_limit,
                        'device_limit' => $user->device_limit
                    ]
                );

                $result = $this->grpcClient->addUser($userInfo);
                if (!$result) {
                    throw new \Exception("Failed to sync user {$user->email}");
                }

                $this->updateSyncStatus($nodeId, $user->id, true);
            }

            event(new UserSyncEvent($nodeId, count($users)));
            return true;
        } catch (\Exception $e) {
            $this->updateSyncStatus($nodeId, $user->id ?? null, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 从节点移除用户
     */
    public function removeFromNode(string $nodeId, string $userId): bool
    {
        try {
            $result = $this->grpcClient->removeUser(['user_id' => $userId]);
            $this->updateSyncStatus($nodeId, $userId, $result);
            return $result;
        } catch (\Exception $e) {
            $this->updateSyncStatus($nodeId, $userId, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 更新用户配额
     */
    public function updateUserQuota(string $nodeId, string $userId, int $quota): bool
    {
        try {
            $quotaInfo = new QuotaInfo(
                user_id: $userId,
                quota: $quota,
                reset_used: false,
                quota_type: 'monthly'
            );

            $result = $this->grpcClient->updateUserQuota($quotaInfo);
            $this->updateSyncStatus($nodeId, $userId, $result);
            return $result;
        } catch (\Exception $e) {
            $this->updateSyncStatus($nodeId, $userId, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取用户流量统计
     */
    public function getUserTraffic(string $nodeId, string $userId): array
    {
        try {
            return $this->grpcClient->getTrafficStats([
                'node_id' => $nodeId,
                'user_id' => $userId
            ]);
        } catch (\Exception $e) {
            $this->updateSyncStatus($nodeId, $userId, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 更新同步状态
     */
    private function updateSyncStatus(string $nodeId, ?string $userId, bool $success, ?string $error = null): void
    {
        $this->syncStatus[$nodeId][$userId] = [
            'success' => $success,
            'error' => $error,
            'timestamp' => time()
        ];
    }

    /**
     * 获取同步状态
     */
    public function getSyncStatus(string $nodeId, ?string $userId = null): array
    {
        if ($userId) {
            return $this->syncStatus[$nodeId][$userId] ?? [];
        }
        return $this->syncStatus[$nodeId] ?? [];
    }
}