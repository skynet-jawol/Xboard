<?php
namespace App\Services;

use App\Models\CommissionLog;
use App\Models\Order;
use App\Models\Server;
use App\Models\Stat;
use App\Models\StatServer;
use App\Models\StatUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class StatisticalService
{
    private const CACHE_TTL = 3600; // 1小时
    private const BATCH_SIZE = 1000; // 批量处理大小
    
    protected $userStats;
    protected $startAt;
    protected $endAt;
    protected $serverStats;
    protected $statServerKey;
    protected $statUserKey;
    protected $redis;

    public function __construct()
    {
        ini_set('memory_limit', -1);
        $this->redis = Redis::connection();
    }

    public function setStartAt($timestamp)
    {
        $this->startAt = $timestamp;
        $this->statServerKey = "stat_server_{$this->startAt}";
        $this->statUserKey = "stat_user_{$this->startAt}";
    }

    public function setEndAt($timestamp)
    {
        $this->endAt = $timestamp;
    }

    /**
     * 生成统计报表，使用缓存优化
     */
    public function generateStatData(): array
    {
        $startAt = $this->startAt;
        $endAt = $this->endAt;
        if (!$startAt || !$endAt) {
            $startAt = strtotime(date('Y-m-d'));
            $endAt = strtotime('+1 day', $startAt);
        }

        $cacheKey = "stat_data:{$startAt}:{$endAt}";
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            return $cachedData;
        }

        $data = [];
        
        // 批量查询订单数据
        $orderQuery = Order::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt);
        $data['order_count'] = $orderQuery->count();
        $data['order_total'] = $orderQuery->sum('total_amount');

        // 批量查询已支付订单数据
        $paidOrderQuery = Order::where('paid_at', '>=', $startAt)
            ->where('paid_at', '<', $endAt)
            ->whereNotIn('status', [0, 2]);
        $data['paid_count'] = $paidOrderQuery->count();
        $data['paid_total'] = $paidOrderQuery->sum('total_amount');

        // 批量查询佣金数据
        $commissionLogBuilder = CommissionLog::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt);
        $data['commission_count'] = $commissionLogBuilder->count();
        $data['commission_total'] = $commissionLogBuilder->sum('get_amount');

        // 批量查询用户数据
        $userQuery = User::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt);
        $data['register_count'] = $userQuery->count();
        $data['invite_count'] = $userQuery->whereNotNull('invite_user_id')->count();

        // 批量查询流量数据
        $data['transfer_used_total'] = StatServer::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt)
            ->select(DB::raw('SUM(u) + SUM(d) as total'))
            ->value('total') ?? 0;

        // 缓存统计数据
        Cache::put($cacheKey, $data, now()->addHours(1));

        return $data;
    }

    /**
     * 批量处理服务器流量统计
     */
    public function batchStatServer(array $serverStats)
    {
        $pipeline = $this->redis->pipeline();
        foreach ($serverStats as $stat) {
            $u_member = "{$stat['type']}_{$stat['server_id']}_u";
            $d_member = "{$stat['type']}_{$stat['server_id']}_d";
            $pipeline->zincrby($this->statServerKey, $stat['u'], $u_member);
            $pipeline->zincrby($this->statServerKey, $stat['d'], $d_member);
        }
        $pipeline->execute();
    }

    /**
     * 往服务器报表缓存追加流量使用数据
     */
    public function statServer($serverId, $serverType, $u, $d)
    {
        $this->batchStatServer([[
            'server_id' => $serverId,
            'type' => $serverType,
            'u' => $u,
            'd' => $d
        ]]);
    }

    /**
     * 批量处理用户流量统计
     */
    public function batchStatUser(array $userStats)
    {
        $pipeline = $this->redis->pipeline();
        foreach ($userStats as $stat) {
            $u_member = "user_{$stat['user_id']}_u";
            $d_member = "user_{$stat['user_id']}_d";
            $pipeline->zincrby($this->statUserKey, $stat['u'], $u_member);
            $pipeline->zincrby($this->statUserKey, $stat['d'], $d_member);
        }
        $pipeline->execute();
    }

    /**
     * 追加用户使用流量
     */
    public function statUser($userId, $u, $d)
    {
        $this->batchStatUser([[
            'user_id' => $userId,
            'u' => $u,
            'd' => $d
        ]]);
    }
}
