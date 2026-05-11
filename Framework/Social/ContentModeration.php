<?php

namespace Framework\Social;

use Framework\Model\Model;

/**
 * ContentModeration Service
 * Handles content reporting, auto-flagging, and approval queues.
 * Provides tools for moderators to review and take action on reported content.
 *
 * Usage:
 * ContentModeration::report($userId, Post::class, $postId, 'spam', 'This is spam');
 * ContentModeration::getReports();
 * ContentModeration::approveReport($reportId, $moderatorId);
 * ContentModeration::rejectReport($reportId, $moderatorId);
 * ContentModeration::autoModerate($content);
 */
class ContentModeration extends Model
{
    protected static $table = 'reports';
    protected static $primaryKey = 'id';

    public const REASON_SPAM = 'spam';
    public const REASON_HARASSMENT = 'harassment';
    public const REASON_HATE_SPEECH = 'hate_speech';
    public const REASON_VIOLENCE = 'violence';
    public const REASON_NSFW = 'nsfw';
    public const REASON_MISINFORMATION = 'misinformation';
    public const REASON_COPYRIGHT = 'copyright';
    public const REASON_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ACTIONED = 'actioned';

    public const ACTION_NONE = 'none';
    public const ACTION_HIDE = 'hide';
    public const ACTION_DELETE = 'delete';
    public const ACTION_WARN = 'warn';
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_BAN = 'ban';

    public static $fields = [
        'id' => 'integer',
        'reporter_id' => 'integer',
        'reportable_type' => 'string',
        'reportable_id' => 'integer',
        'reason' => 'string',
        'details' => 'text',
        'status' => 'string',
        'action_taken' => 'string',
        'moderator_id' => 'integer',
        'moderator_notes' => 'text',
        'created_at' => 'timestamp',
        'resolved_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('reporter_id');
        $table->string('reportable_type', 255);
        $table->integer('reportable_id');
        $table->string('reason', 50);
        $table->text('details')->nullable();
        $table->string('status', 20)->default('pending');
        $table->string('action_taken', 20)->default('none');
        $table->integer('moderator_id')->nullable();
        $table->text('moderator_notes')->nullable();
        $table->timestamp('created_at');
        $table->timestamp('resolved_at')->nullable();
    }

    public static function report(int $reporterId, string $modelType, int $modelId, string $reason, ?string $details = null): ?ContentModeration
    {
        if (!self::isValidReason($reason)) {
            return null;
        }

        if (self::alreadyReported($reporterId, $modelType, $modelId)) {
            return null;
        }

        return static::create([
            'reporter_id' => $reporterId,
            'reportable_type' => $modelType,
            'reportable_id' => $modelId,
            'reason' => $reason,
            'details' => $details,
            'status' => self::STATUS_PENDING,
            'action_taken' => self::ACTION_NONE,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function getReports(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $query = static::where('1', '=', '1');

        if ($status) {
            $query = $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public static function getPendingReports(int $limit = 50): array
    {
        return self::getReports(self::STATUS_PENDING, $limit);
    }

    public static function approveReport(int $reportId, int $moderatorId, string $action = self::ACTION_HIDE, ?string $notes = null): bool
    {
        $report = static::find($reportId);
        if (!$report) {
            return false;
        }

        $result = static::where('id', $reportId)->updateRows([
            'status' => self::STATUS_ACTIONED,
            'action_taken' => $action,
            'moderator_id' => $moderatorId,
            'moderator_notes' => $notes,
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);

        if ($result) {
            self::executeAction($report, $action);
        }

        return $result;
    }

    public static function rejectReport(int $reportId, int $moderatorId, ?string $notes = null): bool
    {
        return static::where('id', $reportId)->updateRows([
            'status' => self::STATUS_REJECTED,
            'moderator_id' => $moderatorId,
            'moderator_notes' => $notes,
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function autoModerate(string $content): array
    {
        $flags = [];

        $bannedWords = self::getBannedWords();
        foreach ($bannedWords as $word) {
            if (stripos($content, $word) !== false) {
                $flags[] = [
                    'type' => 'banned_word',
                    'word' => $word,
                    'severity' => 'high',
                ];
            }
        }

        if (self::hasExcessiveCaps($content)) {
            $flags[] = [
                'type' => 'excessive_caps',
                'severity' => 'low',
            ];
        }

        if (self::hasExcessiveLinks($content)) {
            $flags[] = [
                'type' => 'excessive_links',
                'severity' => 'medium',
            ];
        }

        if (self::hasPotentialSpam($content)) {
            $flags[] = [
                'type' => 'potential_spam',
                'severity' => 'medium',
            ];
        }

        return $flags;
    }

    public static function shouldAutoHide(string $content): bool
    {
        $flags = self::autoModerate($content);
        $highSeverity = array_filter($flags, fn($f) => $f['severity'] === 'high');
        return count($highSeverity) >= 2;
    }

    public static function getReportCount(string $modelType, int $modelId): int
    {
        return static::where('reportable_type', $modelType)
            ->where('reportable_id', $modelId)
            ->where('status', self::STATUS_PENDING)
            ->countRows();
    }

    public static function getReporterHistory(int $userId, int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return static::where('reporter_id', $userId)
            ->where('created_at', '>=', $since)
            ->get();
    }

    public static function getReportStats(): array
    {
        $table = static::getTable();
        $result = static::rawQuery(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'actioned' THEN 1 ELSE 0 END) as actioned
             FROM {$table}"
        );

        if ($result) {
            return $result->fetch(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public static function addBannedWord(string $word): bool
    {
        $table = 'banned_words';
        $existing = static::rawQuery(
            "SELECT id FROM {$table} WHERE word = ?",
            [strtolower($word)]
        );

        if ($existing && $existing->fetch(\PDO::FETCH_ASSOC)) {
            return false;
        }

        return (bool) static::rawQuery(
            "INSERT INTO {$table} (word, created_at) VALUES (?, ?)",
            [strtolower($word), date('Y-m-d H:i:s')]
        );
    }

    public static function removeBannedWord(string $word): bool
    {
        return (bool) static::rawQuery(
            "DELETE FROM banned_words WHERE word = ?",
            [strtolower($word)]
        );
    }

    protected static function isValidReason(string $reason): bool
    {
        return in_array($reason, [
            self::REASON_SPAM,
            self::REASON_HARASSMENT,
            self::REASON_HATE_SPEECH,
            self::REASON_VIOLENCE,
            self::REASON_NSFW,
            self::REASON_MISINFORMATION,
            self::REASON_COPYRIGHT,
            self::REASON_OTHER,
        ]);
    }

    protected static function alreadyReported(int $reporterId, string $modelType, int $modelId): bool
    {
        $result = static::where('reporter_id', $reporterId)
            ->where('reportable_type', $modelType)
            ->where('reportable_id', $modelId)
            ->where('status', self::STATUS_PENDING)
            ->exists();

        return $result;
    }

    protected static function executeAction(ContentModeration $report, string $action): void
    {
        $modelType = $report->reportable_type;
        $modelId = $report->reportable_id;

        switch ($action) {
            case self::ACTION_HIDE:
                if (method_exists($modelType, 'where')) {
                    $modelType::where('id', $modelId)->updateRows([
                        'visibility' => 'hidden',
                    ]);
                }
                break;

            case self::ACTION_DELETE:
                if (method_exists($modelType, 'destroy')) {
                    $modelType::destroy($modelId);
                }
                break;

            case self::ACTION_WARN:
            case self::ACTION_SUSPEND:
            case self::ACTION_BAN:
                $affectedUserId = self::getOwnerUserId($modelType, $modelId);
                if ($affectedUserId) {
                    self::applyUserAction($affectedUserId, $action);
                }
                break;
        }
    }

    protected static function getOwnerUserId(string $modelType, int $modelId): ?int
    {
        if (method_exists($modelType, 'find')) {
            $model = $modelType::find($modelId);
            if ($model && isset($model->user_id)) {
                return (int) $model->user_id;
            }
        }
        return null;
    }

    protected static function applyUserAction(int $userId, string $action): void
    {
        $updates = [];

        switch ($action) {
            case self::ACTION_WARN:
                $updates['warning_count'] = 'warning_count + 1';
                break;
            case self::ACTION_SUSPEND:
                $updates['is_suspended'] = true;
                $updates['suspended_until'] = date('Y-m-d H:i:s', strtotime('+7 days'));
                break;
            case self::ACTION_BAN:
                $updates['is_banned'] = true;
                break;
        }

        if (!empty($updates)) {
            $userModel = new \App\Models\User();
            $userModel->connection->exec(
                "UPDATE users SET " . implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys($updates), $updates)) . " WHERE id = ?",
                [$userId]
            );
        }
    }

    protected static function getBannedWords(): array
    {
        $result = static::rawQuery("SELECT word FROM banned_words");
        if ($result) {
            return array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'word');
        }
        return [];
    }

    protected static function hasExcessiveCaps(string $content): bool
    {
        $alpha = preg_replace('/[^a-zA-Z]/', '', $content);
        if (strlen($alpha) < 10) {
            return false;
        }
        $caps = preg_replace('/[^A-Z]/', '', $content);
        return (strlen($caps) / strlen($alpha)) > 0.7;
    }

    protected static function hasExcessiveLinks(string $content): bool
    {
        preg_match_all('/https?:\/\//', $content, $matches);
        return count($matches[0]) > 3;
    }

    protected static function hasPotentialSpam(string $content): bool
    {
        $spamPatterns = [
            '/buy\s+now/i',
            '/click\s+here/i',
            '/limited\s+time/i',
            '/act\s+fast/i',
            '/make\s+money/i',
            '/free\s+cash/i',
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}
