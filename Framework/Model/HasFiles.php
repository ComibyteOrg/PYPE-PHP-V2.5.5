<?php

namespace Framework\Model;

use Framework\Storage\Disk;

/**
 * HasFiles Trait
 * Provides Laravel-style file attachment capabilities for models.
 * Automatically manages file-database associations and cascade deletes.
 *
 * Usage:
 * class User extends Model {
 *     use HasFiles;
 * }
 *
 * $user = User::find(1);
 * $user->attachFile($_FILES['avatar'], 'avatar');
 * $user->attachFile($_FILES['documents'], 'documents');
 * $avatarUrl = $user->getFileUrl('avatar');
 * $user->deleteFile('avatar');
 * $allFiles = $user->getFiles();
 */
trait HasFiles
{
    protected static $fileTable = 'model_files';

    public function attachFile($file, string $collection = 'default', string $disk = 'local', array $options = []): ?array
    {
        $modelId = $this->data[static::$primaryKey] ?? null;
        if (!$modelId) {
            throw new \RuntimeException('Cannot attach file: model has no primary key. Save the model first.');
        }

        $files = $this->normalizeUploadedFiles($file);
        $attachedFiles = [];

        foreach ($files as $uploadedFile) {
            if (!$this->isValidUpload($uploadedFile)) {
                continue;
            }

            $fileName = $this->generateFileName($uploadedFile, $collection);
            $directory = $this->getFileDirectory($collection);

            $path = rtrim($directory, '/') . '/' . $fileName;
            $storagePath = Disk::make($disk)->putFileAs($path, $uploadedFile, $fileName, $options);

            if ($storagePath === false) {
                continue;
            }

            $fileData = [
                'model_type' => static::class,
                'model_id' => $modelId,
                'collection' => $collection,
                'disk' => $disk,
                'file_path' => $storagePath,
                'original_name' => $uploadedFile['name'] ?? basename($storagePath),
                'mime_type' => $uploadedFile['type'] ?? $this->guessMimeType($storagePath, $disk),
                'file_size' => $uploadedFile['size'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $inserted = $this->insertFileRecord($fileData);
            if ($inserted) {
                $fileData['id'] = $inserted;
                $attachedFiles[] = $fileData;
            }
        }

        return !empty($attachedFiles) ? $attachedFiles : null;
    }

    public function attachFileFromPath(string $path, string $collection = 'default', string $disk = 'local', ?string $fileName = null, array $options = []): ?array
    {
        $modelId = $this->data[static::$primaryKey] ?? null;
        if (!$modelId) {
            throw new \RuntimeException('Cannot attach file: model has no primary key. Save the model first.');
        }

        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $fileName = $fileName ?: basename($path);
        $destination = rtrim($this->getFileDirectory($collection), '/') . '/' . $fileName;

        $storagePath = Disk::make($disk)->putFileAs($destination, $path, $fileName, $options);
        if ($storagePath === false) {
            return null;
        }

        $fileData = [
            'model_type' => static::class,
            'model_id' => $modelId,
            'collection' => $collection,
            'disk' => $disk,
            'file_path' => $storagePath,
            'original_name' => $fileName,
            'mime_type' => mime_content_type($path) ?: 'application/octet-stream',
            'file_size' => filesize($path),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $inserted = $this->insertFileRecord($fileData);
        if ($inserted) {
            $fileData['id'] = $inserted;
            return $fileData;
        }

        return null;
    }

    public function getFile(string $collection = 'default'): ?array
    {
        $modelId = $this->data[static::$primaryKey] ?? null;
        if (!$modelId) {
            return null;
        }

        $result = $this->rawQuery(
            "SELECT * FROM " . static::getFileTable() . " WHERE model_type = ? AND model_id = ? AND collection = ? ORDER BY created_at DESC LIMIT 1",
            [static::class, $modelId, $collection]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        return null;
    }

    public function getFiles(string $collection = null): array
    {
        $modelId = $this->data[static::$primaryKey] ?? null;
        if (!$modelId) {
            return [];
        }

        $sql = "SELECT * FROM " . static::getFileTable() . " WHERE model_type = ? AND model_id = ?";
        $params = [static::class, $modelId];

        if ($collection !== null) {
            $sql .= " AND collection = ?";
            $params[] = $collection;
        }

        $sql .= " ORDER BY created_at ASC";

        $result = $this->rawQuery($sql, $params);
        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public function getFileUrl(string $collection = 'default'): ?string
    {
        $file = $this->getFile($collection);
        if (!$file) {
            return null;
        }

        return Disk::make($file['disk'])->url($file['file_path']);
    }

    public function getFileUrls(string $collection = null): array
    {
        $files = $this->getFiles($collection);
        $urls = [];

        foreach ($files as $file) {
            $urls[] = Disk::make($file['disk'])->url($file['file_path']);
        }

        return $urls;
    }

    public function deleteFile(string $collection = 'default'): bool
    {
        $modelId = $this->data[static::$primaryKey] ?? null;
        if (!$modelId) {
            return false;
        }

        $file = $this->getFile($collection);
        if (!$file) {
            return false;
        }

        Disk::make($file['disk'])->delete($file['file_path']);
        return $this->deleteFileRecord($file['id']);
    }

    public function deleteFiles(string $collection = null): int
    {
        $modelId = $this->data[static::$primaryKey] ?? null;
        if (!$modelId) {
            return 0;
        }

        $files = $this->getFiles($collection);
        $deleted = 0;

        foreach ($files as $file) {
            Disk::make($file['disk'])->delete($file['file_path']);
            if ($this->deleteFileRecord($file['id'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function hasFile(string $collection = 'default'): bool
    {
        return $this->getFile($collection) !== null;
    }

    public static function getFileTable(): string
    {
        return static::$fileTable;
    }

    protected function normalizeUploadedFiles($file): array
    {
        if (!is_array($file)) {
            return [$file];
        }

        if (isset($file['tmp_name'])) {
            return [$file];
        }

        if (isset($file['tmp_name'][0])) {
            $files = [];
            foreach ($file['tmp_name'] as $i => $tmpName) {
                $files[] = [
                    'name' => $file['name'][$i] ?? '',
                    'type' => $file['type'][$i] ?? '',
                    'tmp_name' => $tmpName,
                    'error' => $file['error'][$i] ?? 0,
                    'size' => $file['size'][$i] ?? 0,
                ];
            }
            return $files;
        }

        return $file;
    }

    protected function isValidUpload(array $file): bool
    {
        return isset($file['tmp_name'])
            && $file['error'] === UPLOAD_ERR_OK
            && is_uploaded_file($file['tmp_name']);
    }

    protected function generateFileName(array $file, string $collection): string
    {
        $extension = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
        $extension = $extension ?: 'bin';
        return strtolower($collection) . '_' . uniqid() . '.' . $extension;
    }

    protected function getFileDirectory(string $collection): string
    {
        return strtolower(class_basename(static::class)) . '/' . $collection . '/' . date('Y/m/d');
    }

    protected function guessMimeType(string $path, string $disk): string
    {
        $localPath = sys_get_temp_dir() . '/' . basename($path);
        $diskInstance = Disk::make($disk);
        $content = $diskInstance->get($path);

        if ($content !== false) {
            file_put_contents($localPath, $content);
            $mimeType = mime_content_type($localPath) ?: 'application/octet-stream';
            @unlink($localPath);
            return $mimeType;
        }

        return 'application/octet-stream';
    }

    protected function insertFileRecord(array $data)
    {
        $table = static::getFileTable();
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $result = $this->rawQuery($sql, $values);

        if ($result) {
            return $this->connection->lastInsertId();
        }

        return false;
    }

    protected function deleteFileRecord(int $id): bool
    {
        $table = static::getFileTable();
        $result = $this->rawQuery("DELETE FROM {$table} WHERE id = ?", [$id]);
        return (bool) $result;
    }

    public function remove()
    {
        $this->deleteFiles();
        return parent::remove();
    }

    public static function destroy($id)
    {
        $instance = new static();
        $instance->data[static::$primaryKey] = $id;
        $instance->deleteFiles();
        return parent::destroy($id);
    }

    public static function deleteRows()
    {
        $modelClass = static::class;
        $table = static::getFileTable();

        $result = static::get();
        foreach ($result as $row) {
            $modelId = $row[static::$primaryKey];
            $files = static::rawQuery(
                "SELECT * FROM {$table} WHERE model_type = ? AND model_id = ?",
                [$modelClass, $modelId]
            );

            if ($files) {
                while ($file = $files->fetch(\PDO::FETCH_ASSOC)) {
                    Disk::make($file['disk'])->delete($file['file_path']);
                }
            }
        }

        static::rawQuery("DELETE FROM {$table} WHERE model_type = ?", [$modelClass]);

        return parent::deleteRows();
    }
}
