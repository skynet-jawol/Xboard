<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AccessDeniedException;
use Illuminate\Support\Facades\Log;

class UserAuthService
{
    private array $jwtConfig;
    private array $rbacRoles;

    public function __construct(array $config = [])
    {
        $this->jwtConfig = $config['jwt'] ?? [
            'secret' => env('JWT_SECRET'),
            'ttl' => 3600 * 24,  // 24 hours
            'issuer' => 'xboard',
            'algorithms' => ['HS256']
        ];

        $this->rbacRoles = [
            'admin' => ['all'],
            'user' => ['read:profile', 'update:profile', 'read:nodes'],
            'guest' => ['read:public']
        ];
    }

    public function createToken(array $claims): string
    {
        try {
            $token = JWT::encode([
                'iss' => $this->jwtConfig['issuer'],
                'iat' => time(),
                'exp' => time() + $this->jwtConfig['ttl'],
                ...$claims
            ], $this->jwtConfig['secret'], $this->jwtConfig['algorithms'][0]);

            return $token;
        } catch (Exception $e) {
            Log::error('Token creation failed: ' . $e->getMessage());
            throw new AuthenticationException('Failed to create token');
        }
    }

    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode(
                $token,
                $this->jwtConfig['secret'],
                $this->jwtConfig['algorithms']
            );
            return (array) $decoded;
        } catch (Exception $e) {
            Log::error('Token validation failed: ' . $e->getMessage());
            throw new AuthenticationException('Invalid token');
        }
    }

    public function hasPermission(string $userId, string $permission): bool
    {
        try {
            $userRole = $this->getUserRole($userId);
            if (!isset($this->rbacRoles[$userRole])) {
                return false;
            }

            $permissions = $this->rbacRoles[$userRole];
            return in_array('all', $permissions) || in_array($permission, $permissions);
        } catch (Exception $e) {
            Log::error('Permission check failed: ' . $e->getMessage());
            return false;
        }
    }

    public function validateAccess(string $userId, string $permission): void
    {
        if (!$this->hasPermission($userId, $permission)) {
            throw new AccessDeniedException('Access denied');
        }
    }

    private function getUserRole(string $userId): string
    {
        try {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            // 根据用户类型返回对应角色
            if ($user->is_admin) {
                return 'admin';
            }
            
            return $user->is_staff ? 'staff' : 'user';
        } catch (\Exception $e) {
            Log::error('Failed to get user role: ' . $e->getMessage());
            return 'guest';
        }
    }
}