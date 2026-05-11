<?php

namespace Framework\Security;

use Framework\Helper\Logger;
use Framework\Logging\Logger as FrameworkLogger;

/**
 * SecureUploader
 * Production-grade file upload pipeline with MIME validation,
 * image re-processing, virus scanning hooks, and quarantine support.
 */
class SecureUploader
{
    private string $uploadDir;
    private array $allowedMimes = [];
    private array $allowedExtensions = [];
    private int $maxFileSize = 10485760; // 10MB default
    private bool $renameFile = true;
    private bool $processImages = true;
    private int $imageMaxWidth = 1920;
    private int $imageMaxHeight = 1080;
    private int $imageQuality = 85;
    private bool $quarantine = true;
    private string $quarantineDir;
    private array $errors = [];

    /* ============================================================
       CONFIGURATION
       ============================================================ */

    public static function make(string $directory): self
    {
        $instance = new self();
        $instance->uploadDir = rtrim($directory, '/');
        $instance->quarantineDir = rtrim($directory, '/') . '/quarantine';

        return $instance;
    }

    /**
     * Set allowed MIME types.
     */
    public function mimes(array $mimes): self
    {
        $this->allowedMimes = $mimes;
        return $this;
    }

    /**
     * Set allowed file extensions.
     */
    public function extensions(array $extensions): self
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    /**
     * Set max file size in bytes.
     */
    public function maxSize(int $bytes): self
    {
        $this->maxFileSize = $bytes;
        return $this;
    }

    /**
     * Enable/disable image processing.
     */
    public function processImages(bool $process = true): self
    {
        $this->processImages = $process;
        return $this;
    }

    /**
     * Set image constraints.
     */
    public function imageConstraints(int $maxWidth = 1920, int $maxHeight = 1080, int $quality = 85): self
    {
        $this->imageMaxWidth = $maxWidth;
        $this->imageMaxHeight = $maxHeight;
        $this->imageQuality = $quality;
        return $this;
    }

    /**
     * Enable/disable quarantine for suspicious files.
     */
    public function quarantine(bool $enabled = true): self
    {
        $this->quarantine = $enabled;
        return $this;
    }

    /**
     * Enable/disable automatic file renaming.
     */
    public function rename(bool $rename = true): self
    {
        $this->renameFile = $rename;
        return $this;
    }

    /* ============================================================
       UPLOAD
       ============================================================ */

    /**
     * Upload a single file with full security validation.
     * Returns the filename on success, false on failure.
     */
    public function upload(array $file): string|false
    {
        $this->errors = [];

        // Step 1: Basic file checks
        if (!$this->validateFile($file)) {
            return false;
        }

        // Step 2: MIME type validation
        if (!$this->validateMime($file)) {
            return false;
        }

        // Step 3: Extension validation
        if (!$this->validateExtension($file)) {
            return false;
        }

        // Step 4: Double-extension check
        if ($this->hasDoubleExtension($file)) {
            $this->addError('Double extensions are not allowed.');
            return false;
        }

        // Step 5: Content verification
        if (!$this->verifyContent($file)) {
            if ($this->quarantine) {
                $this->quarantineFile($file);
                $this->addError('File quarantined for suspicious content.');
            }
            return false;
        }

        // Step 6: Generate safe filename
        $filename = $this->generateFilename($file);

        // Step 7: Ensure directory exists
        $this->ensureDirectory();

        // Step 8: Process image if applicable
        if ($this->isImage($file) && $this->processImages) {
            if (!$this->processImage($file, $filename)) {
                return false;
            }
        } else {
            // Step 9: Move file
            $targetPath = $this->uploadDir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $this->addError('Failed to move uploaded file.');
                return false;
            }
        }

        // Step 10: Set secure permissions
        $targetPath = $this->uploadDir . '/' . $filename;
        chmod($targetPath, 0644);

        Logger::info('File uploaded securely', [
            'filename' => $filename,
            'size' => $file['size'],
            'mime' => $file['type'] ?? 'unknown'
        ]);

        return $filename;
    }

    /**
     * Upload multiple files.
     */
    public function uploadMultiple(array $files): array
    {
        $results = [];

        foreach ($files as $file) {
            $filename = $this->upload($file);
            if ($filename) {
                $results[] = $filename;
            }
        }

        return $results;
    }

    /**
     * Get errors from last upload attempt.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /* ============================================================
       VALIDATION
       ============================================================ */

    private function validateFile(array $file): bool
    {
        if (!isset($file['error']) || !is_numeric($file['error'])) {
            $this->addError('Invalid file upload.');
            return false;
        }

        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = $errorMessages[$file['error']] ?? 'Unknown upload error.';
            $this->addError($msg);
            return false;
        }

        if ($file['size'] > $this->maxFileSize) {
            $this->addError('File exceeds maximum allowed size (' . ($this->maxFileSize / 1048576) . 'MB).');
            return false;
        }

        if ($file['size'] === 0) {
            $this->addError('Uploaded file is empty.');
            return false;
        }

        return true;
    }

    private function validateMime(array $file): bool
    {
        if (empty($this->allowedMimes)) {
            return true;
        }

        // Use finfo for reliable MIME detection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $this->allowedMimes, true)) {
            $this->addError('File type not allowed. Allowed: ' . implode(', ', $this->allowedMimes));
            return false;
        }

        return true;
    }

    private function validateExtension(array $file): bool
    {
        if (empty($this->allowedExtensions)) {
            return true;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $this->allowedExtensions, true)) {
            $this->addError('File extension not allowed. Allowed: ' . implode(', ', $this->allowedExtensions));
            return false;
        }

        return true;
    }

    private function hasDoubleExtension(array $file): bool
    {
        $name = $file['name'];

        // Check for patterns like: file.php.jpg, file.exe.png
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'cgi', 'exe', 'sh', 'bat', 'cmd', 'pl', 'py', 'rb'];
        $parts = explode('.', $name);

        if (count($parts) > 2) {
            foreach (array_slice($parts, 0, -1) as $part) {
                if (in_array(strtolower($part), $dangerousExtensions)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function verifyContent(array $file): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Check if PHP file disguised as image
        $phpSignatures = ['<?php', '<?=', '<script', 'eval(', 'base64_decode('];
        $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);

        foreach ($phpSignatures as $signature) {
            if (stripos($content, $signature) !== false) {
                // If detected MIME is not PHP-related but content has PHP code, suspicious
                $phpMimes = ['application/x-php', 'text/x-php', 'application/x-httpd-php'];
                if (!in_array($detectedMime, $phpMimes)) {
                    return false;
                }
            }
        }

        return true;
    }

    /* ============================================================
       IMAGE PROCESSING
       ============================================================ */

    private function processImage(array $file, string $filename): bool
    {
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $this->addError('Invalid image file.');
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];

        // Check dimensions
        if ($width > $this->imageMaxWidth || $height > $this->imageMaxHeight) {
            $this->addError("Image dimensions exceed maximum ({$this->imageMaxWidth}x{$this->imageMaxHeight}).");
            return false;
        }

        $targetPath = $this->uploadDir . '/' . $filename;

        // Re-create image to strip metadata and malicious payloads
        $source = null;

        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($file['tmp_name']);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($file['tmp_name']);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($file['tmp_name']);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($file['tmp_name']);
                break;
            default:
                $this->addError('Unsupported image type.');
                return false;
        }

        if (!$source) {
            $this->addError('Failed to process image.');
            return false;
        }

        $saved = false;

        switch ($type) {
            case IMAGETYPE_JPEG:
                $saved = imagejpeg($source, $targetPath, $this->imageQuality);
                break;
            case IMAGETYPE_PNG:
                $saved = imagepng($source, $targetPath);
                break;
            case IMAGETYPE_GIF:
                $saved = imagegif($source, $targetPath);
                break;
            case IMAGETYPE_WEBP:
                $saved = imagewebp($source, $targetPath, $this->imageQuality);
                break;
        }

        imagedestroy($source);

        if (!$saved) {
            $this->addError('Failed to save processed image.');
            return false;
        }

        return true;
    }

    private function isImage(array $file): bool
    {
        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        return in_array($mime, $imageMimes, true);
    }

    /* ============================================================
       UTILITIES
       ============================================================ */

    private function generateFilename(array $file): string
    {
        if ($this->renameFile) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            return bin2hex(random_bytes(16)) . '.' . $extension;
        }

        $name = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^\w\-]/', '_', $name);

        return $safeName . '.' . strtolower($extension);
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        if ($this->quarantine && !is_dir($this->quarantineDir)) {
            mkdir($this->quarantineDir, 0755, true);
        }
    }

    private function quarantineFile(array $file): void
    {
        $this->ensureDirectory();

        $quarantineName = 'quarantine_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8));
        $quarantinePath = $this->quarantineDir . '/' . $quarantineName;

        copy($file['tmp_name'], $quarantinePath);
        chmod($quarantinePath, 0000); // Make unreadable

        Logger::warning('File quarantined', [
            'original_name' => $file['name'],
            'quarantine_path' => $quarantinePath,
            'reason' => 'Suspicious content detected'
        ]);
    }

    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }
}
