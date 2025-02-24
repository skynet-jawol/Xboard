<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class CertificateManager
{
    private string $caKeyPath;
    private string $caCertPath;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'ca_key_path' => storage_path('certs/ca.key'),
            'ca_cert_path' => storage_path('certs/ca.crt'),
            'cert_dir' => storage_path('certs'),
            'cert_validity' => 365, // 证书有效期（天）
            'key_bits' => 2048,
            'digest_alg' => 'sha256'
        ], $config);

        $this->caKeyPath = $this->config['ca_key_path'];
        $this->caCertPath = $this->config['ca_cert_path'];

        // 确保证书目录存在
        if (!File::exists($this->config['cert_dir'])) {
            File::makeDirectory($this->config['cert_dir'], 0755, true);
        }
    }

    public function generateCACertificate(): void
    {
        if (File::exists($this->caKeyPath) && File::exists($this->caCertPath)) {
            return;
        }

        // 生成CA私钥
        $caKey = openssl_pkey_new([
            'private_key_bits' => $this->config['key_bits'],
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ]);

        // 生成CA证书
        $caDn = [
            'commonName' => 'Xboard Root CA',
            'organizationName' => 'Xboard',
            'countryName' => 'CN',
            'stateOrProvinceName' => 'Beijing',
            'localityName' => 'Beijing',
            'organizationalUnitName' => 'Security'
        ];

        $caCsr = openssl_csr_new($caDn, $caKey);
        $caCert = openssl_csr_sign($caCsr, null, $caKey, $this->config['cert_validity'], [
            'digest_alg' => $this->config['digest_alg']
        ]);

        // 导出并保存CA私钥和证书
        openssl_pkey_export_to_file($caKey, $this->caKeyPath);
        openssl_x509_export_to_file($caCert, $this->caCertPath);

        chmod($this->caKeyPath, 0600);
        chmod($this->caCertPath, 0644);
    }

    public function generateNodeCertificate(string $nodeId): array
    {
        if (!File::exists($this->caKeyPath) || !File::exists($this->caCertPath)) {
            throw new Exception('CA certificate not found');
        }

        $caKey = openssl_pkey_get_private('file://' . $this->caKeyPath);
        $caCert = openssl_x509_read('file://' . $this->caCertPath);

        // 生成节点私钥
        $nodeKey = openssl_pkey_new([
            'private_key_bits' => $this->config['key_bits'],
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ]);

        // 生成节点证书
        $nodeDn = [
            'commonName' => "Xboard Node $nodeId",
            'organizationName' => 'Xboard',
            'countryName' => 'CN',
            'stateOrProvinceName' => 'Beijing',
            'localityName' => 'Beijing',
            'organizationalUnitName' => 'Nodes'
        ];

        $nodeCsr = openssl_csr_new($nodeDn, $nodeKey);
        $nodeCert = openssl_csr_sign($nodeCsr, $caCert, $caKey, $this->config['cert_validity'], [
            'digest_alg' => $this->config['digest_alg']
        ]);

        // 导出证书和私钥
        $certPath = $this->config['cert_dir'] . "/node_$nodeId.crt";
        $keyPath = $this->config['cert_dir'] . "/node_$nodeId.key";

        openssl_x509_export_to_file($nodeCert, $certPath);
        openssl_pkey_export_to_file($nodeKey, $keyPath);

        chmod($keyPath, 0600);
        chmod($certPath, 0644);

        return [
            'cert_path' => $certPath,
            'key_path' => $keyPath
        ];
    }

    public function exportCertificateChain(string $nodeId): string
    {
        $nodeCertPath = $this->config['cert_dir'] . "/node_$nodeId.crt";
        if (!File::exists($nodeCertPath) || !File::exists($this->caCertPath)) {
            throw new Exception('Certificate files not found');
        }

        // 合并节点证书和CA证书
        $nodeCert = File::get($nodeCertPath);
        $caCert = File::get($this->caCertPath);
        $chainPath = $this->config['cert_dir'] . "/node_$nodeId.chain.crt";
        
        File::put($chainPath, $nodeCert . "\n" . $caCert);
        chmod($chainPath, 0644);

        return $chainPath;
    }

    public function validateCertificateChain(string $certPath): bool
    {
        try {
            $cert = openssl_x509_read('file://' . $certPath);
            $caCert = openssl_x509_read('file://' . $this->caCertPath);
            
            // 验证证书链
            return openssl_x509_verify($cert, $caCert) === 1;
        } catch (Exception $e) {
            Log::error('Certificate chain validation failed: ' . $e->getMessage());
            return false;
        }
    }
}