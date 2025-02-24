<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\CertificateManager;

class NodeAuthService
{
    private array $tlsConfig;
    private int $certTTL;
    private int $renewBefore;
    private CertificateManager $certManager;

    public function __construct(array $config = [])
    {
        $this->tlsConfig = $config['tls'] ?? [
            'cert_file' => storage_path('certs/node.crt'),
            'key_file' => storage_path('certs/node.key'),
            'ca_file' => storage_path('certs/ca.crt'),
            'verify_peer' => true
        ];
        $this->certTTL = $config['cert_ttl'] ?? 86400 * 30; // 30 days
        $this->renewBefore = $config['renew_before'] ?? 86400 * 7; // 7 days
        
        $this->certManager = new CertificateManager([
            'cert_validity' => (int)($this->certTTL / 86400), // 转换为天数
            'ca_cert_path' => $this->tlsConfig['ca_file'],
            'ca_key_path' => str_replace('.crt', '.key', $this->tlsConfig['ca_file'])
        ]);
    }

    public function setupMTLS(): array
    {
        try {
            // 验证证书文件是否存在
            $this->validateCertificates();
            
            // 返回mTLS配置
            return [
                'cert_file' => $this->tlsConfig['cert_file'],
                'key_file' => $this->tlsConfig['key_file'],
                'ca_file' => $this->tlsConfig['ca_file'],
                'verify_peer' => $this->tlsConfig['verify_peer'],
                'min_version' => 'TLS1.3'
            ];
        } catch (Exception $e) {
            Log::error('mTLS setup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function rotateCertificates(): bool
    {
        try {
            $certInfo = openssl_x509_parse(file_get_contents($this->tlsConfig['cert_file']));
            $expiryTime = $certInfo['validTo_time_t'];
            
            // 检查是否需要轮换证书
            if (time() + $this->renewBefore >= $expiryTime) {
                // 生成新的证书密钥对
                $this->generateNewCertificates();
                Log::info('Certificates rotated successfully');
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            Log::error('Certificate rotation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function validateCertificates(): void
    {
        if (!file_exists($this->tlsConfig['cert_file']) ||
            !file_exists($this->tlsConfig['key_file']) ||
            !file_exists($this->tlsConfig['ca_file'])) {
            throw new Exception('Required certificate files are missing');
        }
    }

    private function generateNewCertificates(): void
    {
        try {
            // 确保CA证书存在
            $this->certManager->generateCACertificate();
            
            // 生成新的节点证书
            $nodeId = config('app.node_id', 'default');
            $certs = $this->certManager->generateNodeCertificate($nodeId);
            
            // 备份旧证书
            if (file_exists($this->tlsConfig['cert_file'])) {
                rename($this->tlsConfig['cert_file'], $this->tlsConfig['cert_file'] . '.bak');
                rename($this->tlsConfig['key_file'], $this->tlsConfig['key_file'] . '.bak');
            }
            
            // 移动新证书到指定位置
            rename($certs['cert_path'], $this->tlsConfig['cert_file']);
            rename($certs['key_path'], $this->tlsConfig['key_file']);
            
            Log::info('New certificates generated successfully');
        } catch (Exception $e) {
            Log::error('Failed to generate new certificates: ' . $e->getMessage());
            throw $e;
        }
    }
}