<?php

namespace Framework\Storage\Drivers;

use Framework\Storage\StorageDriverInterface;
use Framework\Logging\Logger;

class CloudinaryDriver implements StorageDriverInterface
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private string $uploadPreset;
    private string $baseUrl = 'https://api.cloudinary.com/v1_1';

    public function __construct(array $config)
    {
        $this->cloudName = $config['cloud_name'] ?? env('CLOUDINARY_CLOUD_NAME', '');
        $this->apiKey = $config['api_key'] ?? env('CLOUDINARY_API_KEY', '');
        $this->apiSecret = $config['api_secret'] ?? env('CLOUDINARY_API_SECRET', '');
        $this->uploadPreset = $config['upload_preset'] ?? env('CLOUDINARY_UPLOAD_PRESET', '');
    }

    public function put(string $path, $contents, array $options = []): bool
    {
        $resourceType = $this->getResourceType($path);
        $tempFile = $this->createTempFile($contents);

        try {
            $result = $this->upload($tempFile, $path, $options);
            return $result !== false;
        } finally {
            @unlink($tempFile);
        }
    }

    public function putFile(string $path, string $localPath, array $options = []): bool
    {
        return $this->upload($localPath, $path, $options) !== false;
    }

    public function get(string $path): string|false
    {
        $publicId = $this->extractPublicId($path);
        $url = "{$this->baseUrl}/{$this->cloudName}/raw/download/{$publicId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => "{$this->apiKey}:{$this->apiSecret}",
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response ?: false;
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
        $publicId = $this->extractPublicId($path);
        $resourceType = $this->getResourceType($path);

        $url = "{$this->baseUrl}/{$this->cloudName}/resources/{$resourceType}/upload/{$publicId}";
        $timestamp = time();
        $signature = $this->apiSign(['public_id' => $publicId, 'timestamp' => $timestamp]);

        $ch = curl_init($url . '?' . http_build_query([
            'api_key' => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]));

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function size(string $path): int|false
    {
        $publicId = $this->extractPublicId($path);
        $resourceType = $this->getResourceType($path);

        $meta = $this->getResourceMeta($resourceType, $publicId);
        return $meta !== false ? ($meta['bytes'] ?? false) : false;
    }

    public function lastModified(string $path): int|false
    {
        $publicId = $this->extractPublicId($path);
        $resourceType = $this->getResourceType($path);

        $meta = $this->getResourceMeta($resourceType, $publicId);
        return $meta !== false ? (strtotime($meta['created_at'] ?? '') ?: false) : false;
    }

    public function url(string $path): string
    {
        $publicId = $this->extractPublicId($path);
        return "https://res.cloudinary.com/{$this->cloudName}/{$this->getResourceType($path)}/upload/{$publicId}";
    }

    public function transformUrl(string $path, array $transformations): string
    {
        $publicId = $this->extractPublicId($path);
        $resourceType = $this->getResourceType($path);

        $transformParts = [];

        foreach ($transformations as $key => $value) {
            $shortKey = $this->transformParam($key);
            $transformParts[] = "{$shortKey}_{$value}";
        }

        $transformString = implode(',', $transformParts);
        return "https://res.cloudinary.com/{$this->cloudName}/{$resourceType}/upload/{$transformString}/{$publicId}";
    }

    public function download(string $path, ?string $name = null): void
    {
        $url = $this->url($path);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . ($name ?: basename($path)) . '"');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
        exit;
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_string($paths) ? [$paths] : $paths;
        $resourceType = $this->getResourceType($paths[0] ?? '');

        $url = "{$this->baseUrl}/{$this->cloudName}/resources/{$resourceType}/destroy";

        foreach ($paths as $path) {
            $publicId = $this->extractPublicId($path);
            $timestamp = time();

            $data = [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
                'api_key' => $this->apiKey,
                'signature' => $this->apiSign(['public_id' => $publicId, 'timestamp' => $timestamp]),
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            curl_close($ch);
        }

        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        return false;
    }

    public function copy(string $from, string $to): bool
    {
        $fromPublicId = $this->extractPublicId($from);
        $resourceType = $this->getResourceType($from);

        $url = "{$this->baseUrl}/{$this->cloudName}/resources/{$resourceType}/upload";
        $timestamp = time();

        $toPublicId = $this->extractPublicId($to);
        $params = [
            'from_public_id' => $fromPublicId,
            'to_public_id' => $toPublicId,
            'timestamp' => $timestamp,
            'api_key' => $this->apiKey,
            'signature' => $this->apiSign([
                'from_public_id' => $fromPublicId,
                'to_public_id' => $toPublicId,
                'timestamp' => $timestamp,
            ]),
        ];

        $ch = curl_init($url . '/copy');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return true;
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
        $resourceType = $this->getResourceType($directory);
        $url = "{$this->baseUrl}/{$this->cloudName}/resources/{$resourceType}/upload";

        $timestamp = time();
        $params = [
            'type' => 'upload',
            'prefix' => $directory ?: '',
            'max_results' => 500,
            'api_key' => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $this->apiSign([
                'type' => 'upload',
                'prefix' => $directory ?: '',
                'max_results' => 500,
                'timestamp' => $timestamp,
            ]),
        ];

        $ch = curl_init($url . '?' . http_build_query($params));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $files = [];

        if (isset($data['resources'])) {
            foreach ($data['resources'] as $resource) {
                $files[] = $resource['public_id'] . '.' . ($resource['format'] ?? '');
            }
        }

        return $files;
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
        $publicId = $this->extractPublicId($path);
        $resourceType = $this->getResourceType($path);

        $urlBase = "https://res.cloudinary.com/{$this->cloudName}/{$resourceType}/upload";

        $timestamp = time();
        $expiresAt = $expiration;

        $transformation = [];
        $rawTransformation = implode('/', $transformation);

        $toSign = "{$publicId}{$rawTransformation}{$expiresAt}{$this->apiSecret}";
        $signature = hash('sha256', $toSign);

        return "{$urlBase}/t_{$rawTransformation}/{$publicId}?__cld_token__=st={$timestamp}~exp={$expiresAt}~hmac={$signature}";
    }

    public function metadata(string $path): array
    {
        $publicId = $this->extractPublicId($path);
        $resourceType = $this->getResourceType($path);

        return $this->getResourceMeta($resourceType, $publicId) ?: [];
    }

    public function driver(): string
    {
        return 'cloudinary';
    }

    private function upload(string $localPath, string $path, array $options = []): array|false
    {
        $resourceType = $this->getResourceType($path);
        $publicId = $this->extractPublicId($path);
        $url = "{$this->baseUrl}/{$this->cloudName}/{$resourceType}/upload";

        $timestamp = time();
        $params = [
            'file' => '@' . $localPath,
            'timestamp' => $timestamp,
            'api_key' => $this->apiKey,
            'signature' => $this->apiSign(['timestamp' => $timestamp]),
        ];

        if (!empty($this->uploadPreset)) {
            $params['upload_preset'] = $this->uploadPreset;
        }

        if (!empty($publicId)) {
            $params['public_id'] = $publicId;
        }

        if (isset($options['folder'])) {
            $params['folder'] = $options['folder'];
        }

        if (isset($options['tags'])) {
            $params['tags'] = is_array($options['tags']) ? implode(',', $options['tags']) : $options['tags'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $data;
        }

        Logger::error('Cloudinary upload failed', [
            'error' => $data['error']['message'] ?? 'Unknown error',
            'status' => $httpCode,
        ]);

        return false;
    }

    private function getResourceMeta(string $resourceType, string $publicId): array|false
    {
        $url = "{$this->baseUrl}/{$this->cloudName}/resources/{$resourceType}/upload/{$publicId}";
        $timestamp = time();

        $params = http_build_query([
            'api_key' => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $this->apiSign(['public_id' => $publicId, 'timestamp' => $timestamp]),
        ]);

        $ch = curl_init($url . '?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true) ?: [];
        }

        return false;
    }

    private function apiSign(array $params): string
    {
        ksort($params);
        $query = http_build_query($params);
        return sha1($query . $this->apiSecret);
    }

    private function extractPublicId(string $path): string
    {
        $path = ltrim($path, '/');
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $publicId = $ext ? substr($path, 0, -(strlen($ext) + 1)) : $path;
        return str_replace(['/', '\\'], '/', $publicId);
    }

    private function getResourceType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
        $videoExts = ['mp4', 'webm', 'mov', 'avi', 'flv'];
        $rawExts = ['pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];

        if (in_array($ext, $imageExts)) {
            return 'image';
        }
        if (in_array($ext, $videoExts)) {
            return 'video';
        }
        if (in_array($ext, $rawExts)) {
            return 'raw';
        }

        return 'raw';
    }

    private function createTempFile($contents): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cld_');
        file_put_contents($tempFile, $contents);
        return $tempFile;
    }

    private function transformParam(string $key): string
    {
        $map = [
            'width' => 'w', 'height' => 'h', 'quality' => 'q',
            'crop' => 'c', 'gravity' => 'g', 'effect' => 'e',
            'angle' => 'a', 'radius' => 'r', 'format' => 'f',
            'density' => 'dn', 'opacity' => 'o', 'border' => 'bo',
            'flags' => 'fl', 'dpr' => 'dpr',
        ];

        return $map[$key] ?? $key;
    }
}
