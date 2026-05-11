<?php

namespace Framework\Storage;

use Framework\Logging\Logger;

class UploadQueue
{
    private string $queueDir;
    private string $logFile;

    public function __construct(?string $queueDir = null)
    {
        $this->queueDir = $queueDir ?: storage_path('queue/uploads');
        $this->logFile = $this->queueDir . '/queue.log';

        if (!is_dir($this->queueDir)) {
            mkdir($this->queueDir, 0755, true);
        }
    }

    public function enqueue(string $disk, string $path, string $localPath, array $options = []): string
    {
        $jobId = uniqid('upload_', true);
        $jobFile = $this->queueDir . '/' . $jobId . '.json';

        $job = [
            'id' => $jobId,
            'disk' => $disk,
            'path' => $path,
            'local_path' => $localPath,
            'options' => $options,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'started_at' => null,
            'completed_at' => null,
            'error' => null,
            'attempts' => 0,
        ];

        file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT));

        Logger::info('Upload queued', [
            'job_id' => $jobId,
            'disk' => $disk,
            'path' => $path,
            'local_path' => $localPath,
        ]);

        return $jobId;
    }

    public function enqueueMultiple(array $jobs): array
    {
        $jobIds = [];

        foreach ($jobs as $job) {
            $jobIds[] = $this->enqueue(
                $job['disk'],
                $job['path'],
                $job['local_path'],
                $job['options'] ?? []
            );
        }

        return $jobIds;
    }

    public function process(?string $jobId = null): array
    {
        $jobs = $this->getPendingJobs($jobId);
        $results = [];

        foreach ($jobs as $jobFile) {
            $job = json_decode(file_get_contents($jobFile), true);
            $result = $this->processJob($job, $jobFile);
            $results[] = $result;
        }

        return $results;
    }

    public function status(string $jobId): array|false
    {
        $jobFile = $this->queueDir . '/' . $jobId . '.json';

        if (!file_exists($jobFile)) {
            return false;
        }

        return json_decode(file_get_contents($jobFile), true);
    }

    public function cancel(string $jobId): bool
    {
        $jobFile = $this->queueDir . '/' . $jobId . '.json';

        if (!file_exists($jobFile)) {
            return false;
        }

        $job = json_decode(file_get_contents($jobFile), true);

        if (in_array($job['status'], ['processing', 'completed'])) {
            return false;
        }

        $job['status'] = 'cancelled';
        $job['completed_at'] = date('Y-m-d H:i:s');

        file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT));

        return true;
    }

    public function retry(string $jobId): bool
    {
        $jobFile = $this->queueDir . '/' . $jobId . '.json';

        if (!file_exists($jobFile)) {
            return false;
        }

        $job = json_decode(file_get_contents($jobFile), true);
        $job['status'] = 'pending';
        $job['error'] = null;
        $job['attempts'] = 0;
        $job['started_at'] = null;
        $job['completed_at'] = null;

        file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT));

        return true;
    }

    public function pendingCount(): int
    {
        return count(glob($this->queueDir . '/*.json'));
    }

    public function clearCompleted(int $olderThanDays = 7): int
    {
        $count = 0;
        $cutoff = time() - ($olderThanDays * 86400);

        foreach (glob($this->queueDir . '/*.json') as $jobFile) {
            $job = json_decode(file_get_contents($jobFile), true);

            if ($job['status'] === 'completed' && filemtime($jobFile) < $cutoff) {
                unlink($jobFile);
                $count++;
            }
        }

        return $count;
    }

    public function list(?string $status = null, int $limit = 50): array
    {
        $jobs = [];
        $files = glob($this->queueDir . '/*.json');

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        foreach (array_slice($files, 0, $limit) as $file) {
            $job = json_decode(file_get_contents($file), true);

            if ($status === null || $job['status'] === $status) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    private function getPendingJobs(?string $jobId = null): array
    {
        if ($jobId) {
            $jobFile = $this->queueDir . '/' . $jobId . '.json';
            return file_exists($jobFile) ? [$jobFile] : [];
        }

        $files = glob($this->queueDir . '/*.json');
        $pending = [];

        foreach ($files as $file) {
            $job = json_decode(file_get_contents($file), true);
            if ($job['status'] === 'pending') {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    private function processJob(array $job, string $jobFile): array
    {
        $job['status'] = 'processing';
        $job['started_at'] = date('Y-m-d H:i:s');
        $job['attempts'] = ($job['attempts'] ?? 0) + 1;

        file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT));

        try {
            $disk = Disk::make($job['disk']);
            $success = $disk->putFile($job['path'], $job['local_path'], $job['options'] ?? []);

            $job['status'] = $success ? 'completed' : 'failed';
            $job['completed_at'] = date('Y-m-d H:i:s');

            if (!$success) {
                $job['error'] = 'Upload failed';
            }

            file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT));

            if ($success) {
                @unlink($job['local_path']);
            }

            Logger::info('Upload job processed', [
                'job_id' => $job['id'],
                'status' => $job['status'],
                'attempts' => $job['attempts'],
            ]);

            return $job;
        } catch (\Exception $e) {
            $job['status'] = 'failed';
            $job['error'] = $e->getMessage();
            $job['completed_at'] = date('Y-m-d H:i:s');

            file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT));

            Logger::error('Upload job failed', [
                'job_id' => $job['id'],
                'error' => $e->getMessage(),
            ]);

            return $job;
        }
    }
}
