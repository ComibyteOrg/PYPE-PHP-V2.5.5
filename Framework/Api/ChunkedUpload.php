<?php

namespace Framework\Api;

use Framework\Logging\Logger;

class ChunkedUpload
{
    private string $uploadDir;
    private string $chunkDir;
    private array $allowedMimes = [];
    private array $allowedExtensions = [];
    private int $maxFileSize = 104857600;
    private int $chunkSize = 5242880;
    private int $maxChunks = 1000;
    private array $errors = [];

    public static function make(string $uploadDir): self
    {
        return new self($uploadDir);
    }

    public function __construct(string $uploadDir)
    {
        $this->uploadDir = rtrim($uploadDir, '/');
        $this->chunkDir = storage_path('uploads/chunks');

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        if (!is_dir($this->chunkDir)) {
            mkdir($this->chunkDir, 0755, true);
        }
    }

    public function allowedMimes(array $mimes): self
    {
        $this->allowedMimes = $mimes;
        return $this;
    }

    public function allowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = $extensions;
        return $this;
    }

    public function maxSize(int $bytes): self
    {
        $this->maxFileSize = $bytes;
        return $this;
    }

    public function chunkSize(int $bytes): self
    {
        $this->chunkSize = $bytes;
        return $this;
    }

    public function handleUpload(array $fileData): array|false
    {
        $uploadId = $fileData['upload_id'] ?? '';
        $chunkIndex = (int) ($fileData['chunk_index'] ?? 0);
        $totalChunks = (int) ($fileData['total_chunks'] ?? 1);
        $fileName = $fileData['filename'] ?? $fileData['name'] ?? '';
        $fileSize = (int) ($fileData['file_size'] ?? 0);
        $chunkData = $fileData['chunk'] ?? $fileData['data'] ?? null;
        $chunkFile = $fileData['chunk_file'] ?? null;

        if (empty($uploadId)) {
            $uploadId = bin2hex(random_bytes(16));
        }

        if ($fileSize > $this->maxFileSize) {
            $this->errors[] = 'File too large. Maximum: ' . $this->formatBytes($this->maxFileSize);
            return false;
        }

        if ($totalChunks > $this->maxChunks) {
            $this->errors[] = 'Too many chunks';
            return false;
        }

        if (!empty($this->allowedExtensions)) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExtensions)) {
                $this->errors[] = 'File type not allowed';
                return false;
            }
        }

        if ($totalChunks === 1) {
            return $this->handleSingleUpload($fileData, $fileName);
        }

        $chunkPath = $this->chunkDir . '/' . $uploadId;
        if (!is_dir($chunkPath)) {
            mkdir($chunkPath, 0755, true);
        }

        $chunkFilePath = $chunkPath . '/chunk_' . str_pad($chunkIndex, 6, '0', STR_PAD_LEFT);

        if ($chunkData !== null) {
            file_put_contents($chunkFilePath, $chunkData);
        } elseif ($chunkFile && isset($chunkFile['tmp_name'])) {
            move_uploaded_file($chunkFile['tmp_name'], $chunkFilePath);
        } else {
            $this->errors[] = 'No chunk data provided';
            return false;
        }

        $uploadedChunks = count(glob($chunkPath . '/chunk_*'));

        if ($uploadedChunks === $totalChunks) {
            return $this->assembleChunks($uploadId, $fileName, $totalChunks, $chunkPath);
        }

        return [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'uploaded_chunks' => $uploadedChunks,
            'total_chunks' => $totalChunks,
            'progress' => round(($uploadedChunks / $totalChunks) * 100, 2),
            'complete' => false,
        ];
    }

    public function getProgress(string $uploadId, int $totalChunks): array
    {
        $chunkPath = $this->chunkDir . '/' . $uploadId;
        $uploaded = is_dir($chunkPath) ? count(glob($chunkPath . '/chunk_*')) : 0;

        return [
            'upload_id' => $uploadId,
            'uploaded_chunks' => $uploaded,
            'total_chunks' => $totalChunks,
            'progress' => $totalChunks > 0 ? round(($uploaded / $totalChunks) * 100, 2) : 0,
            'complete' => $uploaded === $totalChunks,
        ];
    }

    public function cancelUpload(string $uploadId): bool
    {
        $chunkPath = $this->chunkDir . '/' . $uploadId;

        if (is_dir($chunkPath)) {
            array_map('unlink', glob($chunkPath . '/*'));
            return rmdir($chunkPath);
        }

        return false;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function getLastError(): string
    {
        return end($this->errors) ?: '';
    }

    private function handleSingleUpload(array $fileData, string $fileName): array|false
    {
        $finalPath = $this->uploadDir . '/' . $this->generateFilename($fileName);

        if (isset($fileData['tmp_name']) && is_uploaded_file($fileData['tmp_name'])) {
            if (!move_uploaded_file($fileData['tmp_name'], $finalPath)) {
                $this->errors[] = 'Failed to save file';
                return false;
            }
        } elseif (isset($fileData['data'])) {
            if (!file_put_contents($finalPath, $fileData['data'])) {
                $this->errors[] = 'Failed to save file';
                return false;
            }
        } else {
            $this->errors[] = 'No file data provided';
            return false;
        }

        chmod($finalPath, 0644);

        Logger::info('File uploaded', [
            'filename' => $fileName,
            'path' => $finalPath,
            'size' => filesize($finalPath),
        ]);

        return [
            'filename' => basename($finalPath),
            'path' => $finalPath,
            'size' => filesize($finalPath),
            'complete' => true,
        ];
    }

    private function assembleChunks(string $uploadId, string $fileName, int $totalChunks, string $chunkPath): array|false
    {
        $finalPath = $this->uploadDir . '/' . $this->generateFilename($fileName);
        $finalFile = fopen($finalPath, 'wb');

        if ($finalFile === false) {
            $this->errors[] = 'Failed to create final file';
            return false;
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $chunkPath . '/chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);

            if (!file_exists($chunkFile)) {
                fclose($finalFile);
                $this->errors[] = "Missing chunk {$i}";
                return false;
            }

            $chunkData = file_get_contents($chunkFile);
            fwrite($finalFile, $chunkData);
        }

        fclose($finalFile);
        chmod($finalPath, 0644);

        array_map('unlink', glob($chunkPath . '/*'));
        rmdir($chunkPath);

        Logger::info('Chunked upload assembled', [
            'upload_id' => $uploadId,
            'filename' => $fileName,
            'path' => $finalPath,
            'size' => filesize($finalPath),
            'chunks' => $totalChunks,
        ]);

        return [
            'upload_id' => $uploadId,
            'filename' => basename($finalPath),
            'path' => $finalPath,
            'size' => filesize($finalPath),
            'complete' => true,
        ];
    }

    private function generateFilename(string $originalName): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid('upload_', true) . ($ext ? '.' . $ext : '');
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
