<?php

namespace Framework\Storage\Drivers;

use Framework\Storage\StorageDriverInterface;
use Framework\Logging\Logger;

class AzureDriver implements StorageDriverInterface
{
    private string $accountName;
    private string $accountKey;
    private string $container;
    private string $baseUrl;

    public function __construct(array $config)
    {
        $this->accountName = $config['account_name'] ?? env('AZURE_STORAGE_ACCOUNT_NAME', '');
        $this->accountKey = $config['account_key'] ?? env('AZURE_STORAGE_ACCOUNT_KEY', '');
        $this->container = $config['container'] ?? 'uploads';
        $this->baseUrl = "https://{$this->accountName}.blob.core.windows.net";
    }

    public function put(string $path, $contents, array $options = []): bool
    {
        $path = ltrim($path, '/');
        $contentType = $options['content_type'] ?? $this->guessMimeType($path);
        $visibility = $options['visibility'] ?? 'private';

        $headers = [
            'x-ms-blob-type' => 'BlockBlob',
            'x-ms-blob-content-type' => $contentType,
            'x-ms-blob-content-disposition' => $options['disposition'] ?? null,
        ];

        if ($visibility === 'public') {
            $headers['x-ms-blob-access-tier'] = 'Hot';
        }

        $headers = array_filter($headers);
        $resource = "/{$this->accountName}/{$this->container}/{$path}";

        $response = $this->sendRequest('PUT', $resource, $headers, is_resource($contents) ? stream_get_contents($contents) : $contents);
        return $response['success'];
    }

    public function putFile(string $path, string $localPath, array $options = []): bool
    {
        if (!file_exists($localPath)) {
            return false;
        }

        $contents = file_get_contents($localPath);
        return $contents !== false && $this->put($path, $contents, $options);
    }

    public function get(string $path): string|false
    {
        $path = ltrim($path, '/');
        $resource = "/{$this->accountName}/{$this->container}/{$path}";
        $response = $this->sendRequest('GET', $resource);
        return $response['success'] ? $response['body'] : false;
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
        $resource = "/{$this->accountName}/{$this->container}/{$path}";
        $response = $this->sendRequest('HEAD', $resource);
        return $response['success'];
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function size(string $path): int|false
    {
        $path = ltrim($path, '/');
        $resource = "/{$this->accountName}/{$this->container}/{$path}";
        $response = $this->sendRequest('HEAD', $resource);

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
        $resource = "/{$this->accountName}/{$this->container}/{$path}";
        $response = $this->sendRequest('HEAD', $resource);

        if (!$response['success']) {
            return false;
        }

        foreach ($response['headers'] as $header) {
            if (stripos($header, 'last-modified:') !== false) {
                return strtotime(trim(substr($header, strlen('last-modified:'))));
            }
        }

        return false;
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');
        return "{$this->baseUrl}/{$this->container}/{$path}";
    }

    public function download(string $path, ?string $name = null): void
    {
        $contents = $this->get($path);

        if ($contents === false) {
            http_response_code(404);
            exit('File not found');
        }

        $name = $name ?: basename($path);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($contents));

        echo $contents;
        exit;
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_string($paths) ? [$paths] : $paths;

        foreach ($paths as $path) {
            $path = ltrim($path, '/');
            $resource = "/{$this->accountName}/{$this->container}/{$path}";
            $this->sendRequest('DELETE', $resource);
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

        $sourceUrl = "{$this->baseUrl}/{$this->container}/{$from}";
        $resource = "/{$this->accountName}/{$this->container}/{$to}";

        $response = $this->sendRequest('PUT', $resource, [
            'x-ms-copy-source' => $sourceUrl,
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
        return $this->listBlobs($directory);
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->files($directory, true);
    }

    public function directories(string $directory = ''): array
    {
        return [];
    }

    public function makeDirectory(string $path): bool
    {
        return true;
    }

    public function temporaryUrl(string $path, int $expiration): string
    {
        $path = ltrim($path, '/');
        $signedIdentifier = '';
        $start = date('Y-m-d\TH:i:s\Z', time() - 300);
        $expiry = date('Y-m-d\TH:i:s\Z', $expiration);

        $stringToSign = "r\n" .
            "{$start}\n" .
            "{$expiry}\n" .
            "/blob/{$this->accountName}/{$this->container}/{$path}\n" .
            "{$signedIdentifier}\n\n\n2020-02-10\nb\n";

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));
        $signature = urlencode($signature);

        return "{$this->baseUrl}/{$this->container}/{$path}?" . http_build_query([
            'sv' => '2020-02-10',
            'sr' => 'b',
            'sig' => $signature,
            'sp' => 'r',
            'st' => $start,
            'se' => $expiry,
        ]);
    }

    public function metadata(string $path): array
    {
        $path = ltrim($path, '/');
        $resource = "/{$this->accountName}/{$this->container}/{$path}";
        $response = $this->sendRequest('HEAD', $resource);

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
        return 'azure';
    }

    private function listBlobs(string $directory = ''): array
    {
        $directory = ltrim($directory, '/');
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';

        $files = [];
        $marker = null;

        do {
            $params = ['comp' => 'list', 'prefix' => $prefix];
            if ($marker) {
                $params['marker'] = $marker;
            }

            $resource = "/{$this->accountName}/{$this->container}?" . http_build_query($params);
            $response = $this->sendRequest('GET', $resource);

            if (!$response['success']) {
                break;
            }

            $xml = simplexml_load_string($response['body']);
            if ($xml === false) {
                break;
            }

            if (isset($xml->Blobs->Blob)) {
                foreach ($xml->Blobs->Blob as $blob) {
                    $name = (string) $blob->Name;
                    if (!str_ends_with($name, '/')) {
                        $files[] = $name;
                    }
                }
            }

            $marker = isset($xml->NextMarker) ? (string) $xml->NextMarker : null;
        } while ($marker);

        return $files;
    }

    private function sendRequest(string $method, string $resource, array $headers = [], string $body = ''): array
    {
        $date = gmdate('D, d M Y H:i:s T');
        $url = $this->baseUrl . $resource;

        $canonicalHeaders = '';
        foreach ($headers as $key => $value) {
            if ($value !== null) {
                $lowerKey = strtolower($key);
                $canonicalHeaders .= "{$lowerKey}:{$value}\n";
            }
        }

        $contentLength = strlen($body);
        $contentMd5 = !empty($body) ? base64_encode(md5($body, true)) : '';

        $stringToSign = "{$method}\n" .
            "{$contentMd5}\n\n" .
            "{$contentLength}\n\n\n\n\n\n\n" .
            $canonicalHeaders .
            "{$resource}";

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));

        $ch = curl_init($url);

        $chHeaders = [
            "x-ms-date: {$date}",
            "x-ms-version: 2020-02-10",
            "Authorization: SharedKey {$this->accountName}:{$signature}",
        ];

        foreach ($headers as $key => $value) {
            if ($value !== null) {
                $chHeaders[] = "{$key}: {$value}";
            }
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $chHeaders,
            CURLOPT_TIMEOUT => 60,
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
            'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
            'json' => 'application/json', 'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg',
        ];
        return $types[$ext] ?? 'application/octet-stream';
    }
}
