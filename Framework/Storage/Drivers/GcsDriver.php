<?php

namespace Framework\Storage\Drivers;

use Framework\Storage\StorageDriverInterface;
use Framework\Logging\Logger;

class GcsDriver implements StorageDriverInterface
{
    private string $bucket;
    private string $projectId;
    private string $serviceAccountKey;
    private ?string $url;
    private string $apiEndpoint = 'https://storage.googleapis.com';

    public function __construct(array $config)
    {
        $this->bucket = $config['bucket'] ?? '';
        $this->projectId = $config['project_id'] ?? env('GOOGLE_CLOUD_PROJECT_ID', '');
        $this->serviceAccountKey = $config['service_account_key'] ?? env('GOOGLE_CLOUD_SERVICE_ACCOUNT_KEY', '');
        $this->url = $config['url'] ?? null;
    }

    public function put(string $path, $contents, array $options = []): bool
    {
        $path = ltrim($path, '/');
        $contentType = $options['content_type'] ?? $this->guessMimeType($path);
        $acl = $options['visibility'] === 'public' ? 'publicRead' : 'private';

        $response = $this->apiRequest('POST', "/upload/storage/v1/b/{$this->bucket}/o", [
            'uploadType' => 'media',
            'name' => $path,
            'predefinedAcl' => $acl,
        ], [
            'Content-Type' => $contentType,
        ], is_resource($contents) ? stream_get_contents($contents) : $contents);

        return isset($response['name']);
    }

    public function putFile(string $path, string $localPath, array $options = []): bool
    {
        if (!file_exists($localPath)) {
            return false;
        }

        $contents = file_get_contents($localPath);
        if ($contents === false) {
            return false;
        }

        return $this->put($path, $contents, $options);
    }

    public function get(string $path): string|false
    {
        $path = ltrim($path, '/');
        $response = $this->apiRequest('GET', "/storage/v1/b/{$this->bucket}/o/{$path}", [
            'alt' => 'media',
        ]);

        return is_string($response) ? $response : false;
    }

    public function stream(string $path): mixed
    {
        $contents = $this->get($path);
        if ($contents === false) {
            return false;
        }

        $stream = fopen('php://temp', 'rb+');
        fwrite($stream, $contents);
        rewind($stream);
        return $stream;
    }

    public function exists(string $path): bool
    {
        $path = ltrim($path, '/');
        $response = $this->apiRequest('GET', "/storage/v1/b/{$this->bucket}/o/{$path}");
        return isset($response['name']);
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function size(string $path): int|false
    {
        $path = ltrim($path, '/');
        $response = $this->apiRequest('GET', "/storage/v1/b/{$this->bucket}/o/{$path}");
        return isset($response['size']) ? (int) $response['size'] : false;
    }

    public function lastModified(string $path): int|false
    {
        $path = ltrim($path, '/');
        $response = $this->apiRequest('GET', "/storage/v1/b/{$this->bucket}/o/{$path}");
        return isset($response['updated']) ? strtotime($response['updated']) : false;
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->url !== null) {
            return rtrim($this->url, '/') . '/' . $path;
        }

        return "{$this->apiEndpoint}/{$this->bucket}/{$path}";
    }

    public function download(string $path, ?string $name = null): void
    {
        $path = ltrim($path, '/');
        $name = $name ?: basename($path);

        $response = $this->apiRequest('GET', "/storage/v1/b/{$this->bucket}/o/{$path}", [
            'alt' => 'media',
        ]);

        if (!is_string($response)) {
            http_response_code(404);
            exit('File not found');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($response));

        echo $response;
        exit;
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_string($paths) ? [$paths] : $paths;

        foreach ($paths as $path) {
            $path = ltrim($path, '/');
            $this->apiRequest('DELETE', "/storage/v1/b/{$this->bucket}/o/{$path}");
        }

        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        $files = $this->files($path, true);
        return $this->delete($files);
    }

    public function copy(string $from, string $to): bool
    {
        $from = ltrim($from, '/');
        $to = ltrim($to, '/');

        $response = $this->apiRequest('POST', "/storage/v1/b/{$this->bucket}/o/{$from}/copyTo/b/{$this->bucket}/o/{$to}");
        return isset($response['name']);
    }

    public function move(string $from, string $to): bool
    {
        if ($this->copy($from, $to)) {
            return $this->delete($from);
        }
        return false;
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $directory = ltrim($directory, '/');
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';

        $files = [];
        $pageToken = null;

        do {
            $params = ['prefix' => $prefix, 'maxResults' => 1000];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->apiRequest('GET', "/storage/v1/b/{$this->bucket}/o", $params);

            if (!isset($response['items'])) {
                break;
            }

            foreach ($response['items'] as $item) {
                $files[] = $item['name'];
            }

            $pageToken = $response['nextPageToken'] ?? null;
        } while ($pageToken);

        return $files;
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->files($directory, true);
    }

    public function directories(string $directory = ''): array
    {
        $directory = ltrim($directory, '/');
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';

        $response = $this->apiRequest('GET', "/storage/v1/b/{$this->bucket}/o", [
            'prefix' => $prefix,
            'delimiter' => '/',
        ]);

        $dirs = [];
        if (isset($response['prefixes'])) {
            foreach ($response['prefixes'] as $prefixItem) {
                $dirs[] = rtrim($prefixItem, '/');
            }
        }

        return $dirs;
    }

    public function makeDirectory(string $path): bool
    {
        return true;
    }

    public function temporaryUrl(string $path, int $expiration): string
    {
        $path = ltrim($path, '/');
        $expiresAt = date('Y-m-d\TH:i:s\Z', $expiration);

        $policy = base64_encode(json_encode([
            'expiration' => $expiresAt,
            'conditions' => [
                ['bucket' => $this->bucket],
                ['starts-with', '$key', $path],
            ],
        ]));

        $signature = $this->signPolicy($policy);

        return "{$this->apiEndpoint}/{$this->bucket}/{$path}?GoogleAccessId={$this->projectId}@appspot.gserviceaccount.com&Expires={$expiration}&Signature={$signature}";
    }

    public function metadata(string $path): array
    {
        $path = ltrim($path, '/');
        $response = $this->apiRequest('GET', "/storage/v1/b/{$this->bucket}/o/{$path}");
        return is_array($response) ? $response : [];
    }

    public function driver(): string
    {
        return 'gcs';
    }

    private function apiRequest(string $method, string $path, array $queryParams = [], array $headers = [], string $body = '')
    {
        $url = $this->apiEndpoint . $path;

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $token = $this->getAccessToken();

        $chHeaders = [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ];

        foreach ($headers as $key => $value) {
            $chHeaders[] = "{$key}: {$value}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $chHeaders,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 204) {
            return true;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            if (empty($body)) {
                return $response;
            }
            return json_decode($response, true) ?: $response;
        }

        Logger::error('GCS API error', [
            'method' => $method,
            'path' => $path,
            'status' => $httpCode,
            'response' => $response,
        ]);

        return null;
    }

    private function getAccessToken(): string
    {
        $cacheFile = storage_path('cache/gcs_token.json');
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            if (isset($cache['expires_at']) && $cache['expires_at'] > time()) {
                return $cache['access_token'];
            }
        }

        $credentials = json_decode($this->serviceAccountKey, true);
        if (!$credentials) {
            throw new \RuntimeException('Invalid GCS service account key');
        }

        $now = time();
        $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtPayload = base64_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/devstorage.full_control',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $jwtPayload = str_replace(['+', '/', '='], ['-', '_', ''], $jwtPayload);

        $signingInput = "{$jwtHeader}.{$jwtPayload}";
        $signature = '';

        openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $jwt = "{$signingInput}.{$signature}";

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            throw new \RuntimeException('Failed to obtain GCS access token');
        }

        $tokenData = [
            'access_token' => $data['access_token'],
            'expires_at' => time() + ($data['expires_in'] ?? 3600) - 300,
        ];

        file_put_contents($cacheFile, json_encode($tokenData));

        return $data['access_token'];
    }

    private function signPolicy(string $policy): string
    {
        $credentials = json_decode($this->serviceAccountKey, true);
        $signature = '';
        openssl_sign($policy, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        return urlencode(base64_encode($signature));
    }

    private function guessMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
            'json' => 'application/json', 'xml' => 'application/xml',
            'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg',
        ];
        return $types[$ext] ?? 'application/octet-stream';
    }
}
