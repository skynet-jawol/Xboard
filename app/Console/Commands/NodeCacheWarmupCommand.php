<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\NodeCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NodeCacheWarmupCommand extends Command
{
    protected $signature = 'node:cache-warmup {--server= : 指定服务器ID}'
                          . ' {--all : 预热所有服务器缓存}';

    protected $description = '预热节点配置缓存';

    private NodeCacheService $cacheService;

    public function __construct(NodeCacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    public function handle()
    {
        try {
            if ($this->option('all')) {
                $this->warmupAllServers();
            } else if ($serverId = $this->option('server')) {
                $this->warmupServer($serverId);
            } else {
                $this->error('请指定 --server=<id> 或使用 --all 参数');
                return 1;
            }

            $this->info('节点缓存预热完成');
            return 0;
        } catch (\Exception $e) {
            Log::error('节点缓存预热失败', [
                'error' => $e->getMessage()
            ]);
            $this->error('节点缓存预热失败: ' . $e->getMessage());
            return 1;
        }
    }

    private function warmupAllServers()
    {
        $servers = Server::where('status', 1)->get();
        $this->info(sprintf('开始预热 %d 个节点的缓存...', $servers->count()));

        $bar = $this->output->createProgressBar($servers->count());
        $bar->start();

        foreach ($servers as $server) {
            $this->cacheService->warmupHotData($server);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function warmupServer($serverId)
    {
        $server = Server::findOrFail($serverId);
        if (!$server->status) {
            throw new \Exception('指定的服务器未启用');
        }

        $this->info(sprintf('开始预热节点 [%d] %s 的缓存...', $server->id, $server->name));
        $this->cacheService->warmupHotData($server);
    }
}