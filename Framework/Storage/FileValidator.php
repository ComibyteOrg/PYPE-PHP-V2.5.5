<?php

namespace Framework\Storage;

class FileValidator
{
    private array $errors = [];
    private array $allowedExtensions = [];
    private array $allowedMimes = [];
    private int $maxSize = 10485760;
    private int $minSize = 0;
    private bool $checkContent = true;
    private bool $checkDoubleExtensions = true;
    private array $forbiddenPatterns = ['<?php', '<?=', '<script', 'eval(', 'base64_decode'];
    private array $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp'];

    public static function make(): self
    {
        return new self();
    }

    public function allowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    public function allowedMimes(array $mimes): self
    {
        $this->allowedMimes = $mimes;
        return $this;
    }

    public function maxSize(int $bytes): self
    {
        $this->maxSize = $bytes;
        return $this;
    }

    public function minSize(int $bytes): self
    {
        $this->minSize = $bytes;
        return $this;
    }

    public function checkContent(bool $check = true): self
    {
        $this->checkContent = $check;
        return $this;
    }

    public function checkDoubleExtensions(bool $check = true): self
    {
        $this->checkDoubleExtensions = $check;
        return $this;
    }

    public function forbiddenPatterns(array $patterns): self
    {
        $this->forbiddenPatterns = $patterns;
        return $this;
    }

    public function imageOnly(): self
    {
        $this->allowedMimes = $this->imageMimes;
        return $this;
    }

    public function validate(array $file): bool
    {
        $this->errors = [];

        if (empty($file) || !isset($file['name']) || !isset($file['tmp_name'])) {
            $this->errors[] = 'No file provided';
            return false;
        }

        $this->validateSize($file);
        $this->validateExtension($file);
        $this->validateMime($file);

        if ($this->checkDoubleExtensions) {
            $this->validateNoDoubleExtension($file);
        }

        if ($this->checkContent && isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
            $this->validateContent($file['tmp_name']);
            $this->validateMimeContent($file['tmp_name'], $file['name']);
        }

        return empty($this->errors);
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function getLastError(): string
    {
        return end($this->errors) ?: '';
    }

    public static function validateUpload(array $file, array $rules = []): bool
    {
        $validator = self::make();

        if (isset($rules['extensions'])) {
            $validator->allowedExtensions($rules['extensions']);
        }

        if (isset($rules['mimes'])) {
            $validator->allowedMimes($rules['mimes']);
        }

        if (isset($rules['max_size'])) {
            $validator->maxSize($rules['max_size']);
        }

        if (isset($rules['min_size'])) {
            $validator->minSize($rules['min_size']);
        }

        if (isset($rules['image']) && $rules['image']) {
            $validator->imageOnly();
        }

        return $validator->validate($file);
    }

    private function validateSize(array $file): void
    {
        $size = $file['size'] ?? 0;

        if ($size === 0) {
            $this->errors[] = 'File is empty';
            return;
        }

        if ($size > $this->maxSize) {
            $this->errors[] = 'File too large. Maximum: ' . $this->formatBytes($this->maxSize);
        }

        if ($this->minSize > 0 && $size < $this->minSize) {
            $this->errors[] = 'File too small. Minimum: ' . $this->formatBytes($this->minSize);
        }
    }

    private function validateExtension(array $file): void
    {
        if (empty($this->allowedExtensions)) {
            return;
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if (!in_array($extension, $this->allowedExtensions)) {
            $this->errors[] = 'File type not allowed. Allowed: ' . implode(', ', $this->allowedExtensions);
        }
    }

    private function validateMime(array $file): void
    {
        if (empty($this->allowedMimes)) {
            return;
        }

        $mime = $file['type'] ?? '';

        if (!empty($mime) && !in_array($mime, $this->allowedMimes)) {
            $this->errors[] = 'MIME type not allowed: ' . $mime;
        }
    }

    private function validateNoDoubleExtension(array $file): void
    {
        $name = $file['name'] ?? '';
        $nameWithoutExt = pathinfo($name, PATHINFO_FILENAME);

        $dangerousExts = ['php', 'php3', 'php4', 'php5', 'phtml', 'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'sh', 'exe', 'bat', 'cmd'];

        foreach ($dangerousExts as $ext) {
            if (str_contains(strtolower($nameWithoutExt), ".{$ext}")) {
                $this->errors[] = 'Dangerous double extension detected';
                break;
            }
        }
    }

    private function validateContent(string $tmpPath): void
    {
        $content = file_get_contents($tmpPath);
        if ($content === false) {
            return;
        }

        foreach ($this->forbiddenPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $this->errors[] = 'File contains forbidden content';
                break;
            }
        }
    }

    private function validateMimeContent(string $tmpPath, string $filename): void
    {
        $detectedMime = mime_content_type($tmpPath);
        if ($detectedMime === false) {
            return;
        }

        if (!empty($this->allowedMimes) && !in_array($detectedMime, $this->allowedMimes)) {
            $this->errors[] = 'Content MIME type not allowed: ' . $detectedMime;
        }

        if (in_array($detectedMime, $this->imageMimes)) {
            $info = getimagesize($tmpPath);
            if ($info === false) {
                $this->errors[] = 'File claims to be an image but is not a valid image';
            }
        }
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
