<?php

namespace Framework\Security;

use Framework\Helper\DB;
use Framework\Logging\Logger;

/**
 * AuditLog
 * Tracks who did what, when, and from where.
 * Records all significant actions with full context.
 */
class AuditLog
{
    private static string $table = 'audit_logs';
    private static bool $enabled = true;

    /* ============================================================
       CONFIGURATION
       ============================================================ */

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    /* ============================================================
       LOGGING
       ============================================================ */

    /**
     * Log an action.
     */
    public static function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        array $data = [],
        string $severity = 'info'
    ): void {
        if (!self::$enabled) {
            return;
        }

        self::ensureTable();

        $userId = self::getCurrentUserId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        try {
            DB::table(self::$table)->insert([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entity,
                'entity_id' => $entityId,
                'old_values' => isset($data['old']) ? json_encode($data['old']) : null,
                'new_values' => isset($data['new']) ? json_encode($data['new']) : null,
                'data' => json_encode($data),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'url' => $url,
                'method' => $method,
                'severity' => $severity,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            Logger::error('Audit log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Log a creation event.
     */
    public static function created(string $entity, int $entityId, array $data = []): void
    {
        self::log('created', $entity, $entityId, ['new' => $data]);
    }

    /**
     * Log an update event with old and new values.
     */
    public static function updated(string $entity, int $entityId, array $old, array $new): void
    {
        // Only log changed fields
        $changes = [
            'old' => [],
            'new' => [],
        ];

        foreach ($new as $key => $value) {
            if (($old[$key] ?? null) != $value) {
                $changes['old'][$key] = $old[$key] ?? null;
                $changes['new'][$key] = $value;
            }
        }

        if (!empty($changes['new'])) {
            self::log('updated', $entity, $entityId, $changes);
        }
    }

    /**
     * Log a deletion event.
     */
    public static function deleted(string $entity, int $entityId, array $data = []): void
    {
        self::log('deleted', $entity, $entityId, ['old' => $data], 'warning');
    }

    /**
     * Log a login event.
     */
    public static function login(int $userId, array $data = []): void
    {
        self::log('login', 'user', $userId, $data, 'info');
    }

    /**
     * Log a logout event.
     */
    public static function logout(int $userId): void
    {
        self::log('logout', 'user', $userId);
    }

    /**
     * Log a failed login attempt.
     */
    public static function loginFailed(string $identifier, array $data = []): void
    {
        self::log('login_failed', 'user', null, [
            'identifier' => $identifier,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ] + $data, 'warning');
    }

    /**
     * Log a permission-related event.
     */
    public static function permission(string $action, string $entity, ?int $entityId, array $data = []): void
    {
        self::log($action, $entity, $entityId, $data, 'warning');
    }

    /**
     * Log a security event.
     */
    public static function security(string $action, array $data = []): void
    {
        self::log($action, 'security', null, $data, 'critical');
    }

    /* ============================================================
       QUERYING
       ============================================================ */

    /**
     * Get audit logs with filters.
     */
    public static function query(
        ?string $action = null,
        ?string $entity = null,
        ?int $userId = null,
        ?string $severity = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        self::ensureTable();

        try {
            $query = DB::table(self::$table);

            if ($action) {
                $query = $query->where('action', $action);
            }

            if ($entity) {
                $query = $query->where('entity_type', $entity);
            }

            if ($userId) {
                $query = $query->where('user_id', $userId);
            }

            if ($severity) {
                $query = $query->where('severity', $severity);
            }

            if ($startDate) {
                $query = $query->where('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query = $query->where('created_at', '<=', $endDate);
            }

            return $query->orderBy('created_at', 'DESC')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get logs for a specific entity.
     */
    public static function forEntity(string $entity, int $entityId, int $limit = 50): array
    {
        return self::query(null, $entity, null, null, null, null, $limit);
    }

    /**
     * Get logs for a specific user.
     */
    public static function forUser(int $userId, int $limit = 50): array
    {
        return self::query(null, null, $userId, null, null, null, $limit);
    }

    /**
     * Get recent security events.
     */
    public static function recentSecurity(int $limit = 20): array
    {
        return self::query(null, 'security', null, null, null, null, $limit);
    }

    /**
     * Get logs since a specific date.
     */
    public static function since(string $date, int $limit = 50): array
    {
        return self::query(null, null, null, null, $date, null, $limit);
    }

    /**
     * Count logs matching criteria.
     */
    public static function count(?string $action = null, ?string $entity = null, ?int $userId = null): int
    {
        self::ensureTable();

        try {
            $query = DB::table(self::$table);

            if ($action) {
                $query = $query->where('action', $action);
            }

            if ($entity) {
                $query = $query->where('entity_type', $entity);
            }

            if ($userId) {
                $query = $query->where('user_id', $userId);
            }

            return $query->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Clean up old audit logs.
     */
    public static function prune(int $daysOld = 365): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        try {
            DB::rawQuery(
                "DELETE FROM " . self::$table . " WHERE created_at < ?",
                [$cutoff]
            );

            return DB::rawQuery("SELECT ROW_COUNT() as deleted")->fetch()['deleted'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /* ============================================================
       INTERNAL
       ============================================================ */

    private static function getCurrentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    }

    private static function ensureTable(): void
    {
        try {
            DB::rawQuery("CREATE TABLE IF NOT EXISTS " . self::$table . " (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(100) NOT NULL,
                entity_id INT,
                old_values JSON,
                new_values JSON,
                data JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                url TEXT,
                method VARCHAR(10),
                severity VARCHAR(20) DEFAULT 'info',
                created_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_severity (severity),
                INDEX idx_created_at (created_at)
            )");
        } catch (\Exception $e) {
            // Ignore
        }
    }
}
