<?php
// app/Services/StorageService.php

require_once BASE_PATH . '/config/settings.php';

class StorageService {
    private string $driver;
    private string $publicUrl;
    private string $localBase;

    public function __construct() {
        $this->driver    = Settings::storageDriver();
        $this->publicUrl = rtrim(Settings::storagePublicUrl(), '/');
        $this->localBase = BASE_PATH . '/uploads';
    }

    /**
     * 画像バイナリを保存し、公開URLを返す
     */
    public function save(string $binaryData, string $path): string {
        return match ($this->driver) {
            'r2'    => $this->saveR2($binaryData, $path),
            default => $this->saveLocal($binaryData, $path),
        };
    }

    private function saveLocal(string $data, string $path): string {
        $fullPath = $this->localBase . '/' . ltrim($path, '/');
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $data);

        $base = rtrim(Settings::storagePublicUrl(), '/') ?: '';
        if (!$base) {
            // フォールバック：APP_URLから構築
            $scheme = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base   = "{$scheme}://{$host}";
        }
        return $base . '/uploads/' . ltrim($path, '/');
    }

    private function saveR2(string $data, string $path): string {
        $accountId = Settings::r2AccountId();
        $bucket    = Settings::r2Bucket();
        $accessKey = Settings::r2AccessKey();
        $secretKey = Settings::r2SecretKey();

        $endpoint = "https://{$accountId}.r2.cloudflarestorage.com";
        $url      = "{$endpoint}/{$bucket}/{$path}";

        // AWS Signature V4 for R2
        $service   = 's3';
        $region    = 'auto';
        $datetime  = gmdate('Ymd\THis\Z');
        $date      = gmdate('Ymd');
        $host      = "{$accountId}.r2.cloudflarestorage.com";
        $payloadHash = hash('sha256', $data);

        $canonicalUri     = "/{$bucket}/{$path}";
        $canonicalHeaders = "content-type:image/png\nhost:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$datetime}\n";
        $signedHeaders    = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "PUT\n{$canonicalUri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign    = "AWS4-HMAC-SHA256\n{$datetime}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', $service,
                hash_hmac('sha256', $region,
                    hash_hmac('sha256', $date, "AWS4{$secretKey}", true)
                , true)
            , true)
        , true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_HTTPHEADER     => [
                "Authorization: {$authorization}",
                "x-amz-date: {$datetime}",
                "x-amz-content-sha256: {$payloadHash}",
                'Content-Type: image/png',
                "Content-Length: " . strlen($data),
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 && $code !== 201) {
            throw new \RuntimeException("R2 upload failed: HTTP {$code}");
        }

        return $this->publicUrl . '/' . ltrim($path, '/');
    }
}
