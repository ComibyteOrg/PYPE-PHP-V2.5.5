<?php

namespace Framework\Helper;

use Framework\Helper\Logger;

class FileUploader
{
    /**
     * Upload a file to a specific directory.
     *
     * @param array $file The $_FILES element
     * @param string $directory Path relative to project root
     * @param array $allowedExtensions Array of allowed extensions
     * @return string|bool The filename on success, false on failure
     */
    public static function upload($file, $directory, $allowedExtensions = [])
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            Logger::error("File upload error", ['error_code' => $file['error'] ?? 'No file']);
            return false;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions)) {
            Logger::error("File extension not allowed", ['extension' => $extension, 'allowed' => $allowedExtensions]);
            return false;
        }

        $basePath = Helper::base_path($directory);
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $filename = uniqid() . '.' . $extension;
        $targetPath = $basePath . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            Logger::info("File uploaded successfully", ['path' => $targetPath]);
            return $filename;
        }

        Logger::error("Failed to move uploaded file");
        return false;
    }

    /**
     * Check if a directory is empty.
     */
    public static function isEmpty($directory)
    {
        $path = Helper::base_path($directory);
        if (!is_dir($path))
            return true;

        $files = array_diff(scandir($path), ['.', '..']);
        return empty($files);
    }

    /**
     * Check if a file exists.
     */
    public static function exists($path)
    {
        return file_exists(Helper::base_path($path));
    }
}
