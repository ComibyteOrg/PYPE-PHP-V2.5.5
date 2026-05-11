<?php

namespace Framework\Storage\Drivers;

use Framework\Storage\StorageDriverInterface;
use Framework\Logging\Logger;

class S3Driver implements StorageDriverInterface
{
    private string $bucket;
    private string $region;
    private string $accessKey;
    private string $secretKey;
    private ?string $endpoint;
    private ?string $url;
    private bool $usePathStyle;

    public function __construct(array $config)
    {
        $this->bucket = $config['bucket'] ?? '';
        $this->region = $config['region'] ?? 'us-east-1';
        $this->accessKey = $config['access_key'] ?? env('AWS_ACCESS_KEY_ID', '');
        $this->secretKey = $config['secret_key'] ?? env('AWS_SECRET_ACCESS_KEY', '');
        $this->endpoint = $config['endpoint'] ?? env('AWS_ENDPOINT', null);
        $this->url = $config['url'] ?? null;
        $this->usePathStyle = $config['use_path_style'] ?? $this->endpoint !== null;
    }

    public function put(string $path, $contents, array $options = []): bool
    {
        $path = ltrim($path, '/');
        $contentType = $options['content_type'] ?? $this->guessMimeType($path);
        $visibility = $options['visibility'] ?? 'private';

        $response = $this->sendRequest('PUT', '/' . $path, [
            'Content-Type' => $contentType,
            'x-amz-acl' => $visibility === 'public' ? 'public-read' : 'private',
        ], is_resource($contents) ? stream_get_contents($contents) : $contents);

        return $response['success'];
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
        $response = $this->sendRequest('GET', '/' . $path);

        return $response['success'] ? $response['body'] : false;
    }

    public function stream(string $path): mixed
    {
        $body = $this->get($path);
        if ($body === false) {
            return false;
        }

        $stream = fopen('php://temp', 'rb+');
        fwrite($stream, $body);
        rewind($stream);
        return $stream;
    }

    public function exists(string $path): bool
    {
        $path = ltrim($path, '/');
        $response = $this->sendRequest('HEAD', '/' . $path);
        return $response['success'];
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function size(string $path): int|false
    {
        $path = ltrim($path, '/');
        $response = $this->sendRequest('HEAD', '/' . $path);

        if (!$response['success']) {
            return false;
        }

        foreach ($response['headers'] as $header) {
            if (stripos($header, 'content-length:') !== false) {
                return (int) trim(substr($header, strlen('content-length:')));
            }
        }

        return false;
    }

    public function lastModified(string $path): int|false
    {
        $path = ltrim($path, '/');
        $response = $this->sendRequest('HEAD', '/' . $path);

        if (!$response['success']) {
            return false;
        }

        foreach ($response['headers'] as $header) {
            if (stripos($header, 'last-modified:') !== false) {
                $date = trim(substr($header, strlen('last-modified:')));
                return strtotime($date);
            }
        }

        return false;
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->url !== null) {
            return rtrim($this->url, '/') . '/' . $path;
        }

        if ($this->usePathStyle) {
            $baseUrl = $this->endpoint ?: "https://s3.{$this->region}.amazonaws.com";
            return "{$baseUrl}/{$this->bucket}/{$path}";
        }

        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$path}";
    }

    public function download(string $path, ?string $name = null): void
    {
        $path = ltrim($path, '/');
        $name = $name ?: basename($path);

        $response = $this->sendRequest('GET', '/' . $path);

        if (!$response['success']) {
            http_response_code(404);
            exit('File not found');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($response['body']));

        echo $response['body'];
        exit;
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_string($paths) ? [$paths] : $paths;

        if (count($paths) === 1) {
            $path = ltrim($paths[0], '/');
            $response = $this->sendRequest('DELETE', '/' . $path);
            return $response['success'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><Delete>';
        foreach ($paths as $path) {
            $xml .= '<Object><Key>' . htmlspecialchars(ltrim($path, '/')) . '</Key></Object>';
        }
        $xml .= '</Delete>';

        $response = $this->sendRequest('POST', '/?delete', [
            'Content-MD5' => base64_encode(md5($xml, true)),
        ], $xml);

        return $response['success'];
    }

    public function deleteDirectory(string $path): bool
    {
        $files = $this->files($path, true);
        if (empty($files)) {
            return true;
        }

        return $this->delete($files);
    }

    public function copy(string $from, string $to): bool
    {
        $from = ltrim($from, '/');
        $to = ltrim($to, '/');

        $sourcePath = "/{$this->bucket}/{$from}";
        $response = $this->sendRequest('PUT', '/' . $to, [
            'x-amz-copy-source' => $sourcePath,
        ]);

        return $response['success'];
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
        return $this->listObjects($directory, $recursive);
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->listObjects($directory, true);
    }

    public function directories(string $directory = ''): array
    {
        $directory = ltrim($directory, '/');
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';

        $files = [];
        $continuationToken = null;

        do {
            $query = http_build_query(array_filter([
                'prefix' => $prefix,
                'delimiter' => '/',
                'continuation-token' => $continuationToken,
            ]));

            $response = $this->sendRequest('GET', '/?' . $query);

            if (!$response['success']) {
                break;
            }

            $xml = simplexml_load_string($response['body']);
            if ($xml === false) {
                break;
            }

            if (isset($xml->CommonPrefixes)) {
                foreach ($xml->CommonPrefixes as $prefixes) {
                    foreach ($prefixes as $prefixItem) {
                        $dirPath = (string) $prefixItem->Prefix;
                        $dirs[] = rtrim($dirPath, '/');
                    }
                }
            }

            $continuationToken = isset($xml->NextContinuationToken) ? (string) $xml->NextContinuationToken : null;
        } while ($continuationToken);

        return $dirs ?? [];
    }

    public function makeDirectory(string $path): bool
    {
        $path = ltrim($path, '/');
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }

        $response = $this->sendRequest('PUT', '/' . $path);
        return $response['success'];
    }

    public function temporaryUrl(string $path, int $expiration): string
    {
        $path = ltrim($path, '/');
        $expires = $expiration;
        $stringToSign = "GET\n\n\n{$expires}\n/{$this->bucket}/{$path}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));
        $signature = urlencode($signature);

        $baseUrl = $this->usePathStyle
            ? ($this->endpoint ?: "https://s3.{$this->region}.amazonaws.com")
            : "https://{$this->bucket}.s3.{$this->region}.amazonaws.com";

        $fullPath = $this->usePathStyle ? "/{$this->bucket}/{$path}" : "/{$path}";

        return "{$baseUrl}{$fullPath}?AWSAccessKeyId={$this->accessKey}&Expires={$expires}&Signature={$signature}";
    }

    public function metadata(string $path): array
    {
        $path = ltrim($path, '/');
        $response = $this->sendRequest('HEAD', '/' . $path);

        if (!$response['success']) {
            return [];
        }

        $meta = ['path' => $path];
        foreach ($response['headers'] as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $meta[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return $meta;
    }

    public function driver(): string
    {
        return 's3';
    }

    private function listObjects(string $directory, bool $recursive): array
    {
        $directory = ltrim($directory, '/');
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';

        $files = [];
        $continuationToken = null;

        do {
            $query = http_build_query(array_filter([
                'prefix' => $prefix,
                'continuation-token' => $continuationToken,
            ]));

            $response = $this->sendRequest('GET', '/?' . $query);

            if (!$response['success']) {
                break;
            }

            $xml = simplexml_load_string($response['body']);
            if ($xml === false) {
                break;
            }

            if (isset($xml->Contents)) {
                foreach ($xml->Contents as $content) {
                    foreach ($content as $item) {
                        $key = (string) $item->Key;
                        if ($recursive || $directory === '' || str_starts_with($key, $prefix)) {
                            $files[] = $key;
                        }
                    }
                }
            }

            $continuationToken = isset($xml->NextContinuationToken) ? (string) $xml->NextContinuationToken : null;
        } while ($continuationToken);

        return $files;
    }

    private function sendRequest(string $method, string $path, array $headers = [], string $body = ''): array
    {
        if (empty($this->accessKey) || empty($this->secretKey)) {
            Logger::error('S3 credentials not configured');
            return ['success' => false, 'error' => 'Credentials not configured'];
        }

        $host = $this->usePathStyle
            ? ($this->endpoint ? parse_url($this->endpoint, PHP_URL_HOST) : "s3.{$this->region}.amazonaws.com")
            : "{$this->bucket}.s3.{$this->region}.amazonaws.com";

        $fullPath = $this->usePathStyle ? "/{$this->bucket}{$path}" : $path;

        $date = gmdate('D, d M Y H:i:s T');
        $contentType = $headers['Content-Type'] ?? '';
        $md5 = $method !== 'GET' && $method !== 'HEAD' && !empty($body) ? base64_encode(md5($body, true)) : '';

        $stringToSign = "{$method}\n{$md5}\n{$contentType}\n{$date}\n";

        $canonicalHeaders = '';
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (str_starts_with($lowerKey, 'x-amz-')) {
                $canonicalHeaders .= "{$lowerKey}:{$value}\n";
            }
        }
        $stringToSign .= $canonicalHeaders . $fullPath;

        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));

        $url = ($this->usePathStyle && $this->endpoint
            ? rtrim($this->endpoint, '/')
            : "https://{$host}") . $fullPath;

        $ch = curl_init($url);

        $curlHeaders = [
            "Host: {$host}",
            "Date: {$date}",
            "Authorization: AWS {$this->accessKey}:{$signature}",
        ];

        if ($contentType) {
            $curlHeaders[] = "Content-Type: {$contentType}";
        }

        if ($md5) {
            $curlHeaders[] = "Content-MD5: {$md5}";
        }

        foreach ($headers as $key => $value) {
            if (str_starts_with(strtolower($key), 'x-amz-')) {
                $curlHeaders[] = "{$key}: {$value}";
            }
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADER => true,
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        $headers = explode("\r\n", trim($responseHeaders));

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'body' => $responseBody, 'headers' => $headers];
        }

        return ['success' => false, 'body' => $responseBody, 'status' => $httpCode];
    }

    private function guessMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf', 'zip' => 'application/zip',
            'json' => 'application/json', 'xml' => 'application/xml',
            'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg', 'webm' => 'video/webm',
        ];
        return $types[$ext] ?? 'application/octet-stream';
    }
}
