<?php

namespace Framework\Social;

use Framework\Model\Model;
use Framework\Api\Sse;

/**
 * Notification Model & Service
 * Manages user notifications with support for database storage,
 * real-time delivery via SSE, and email fallback.
 *
 * Usage:
 * Notification::send($userId, 'new_follower', ['from_user_id' => 5]);
 * Notification::send($userId, 'post_liked', ['post_id' => 123, 'from_user_id' => 5]);
 *
 * $unread = Notification::unread($userId);
 * Notification::markAsRead($notificationId);
 * Notification::markAllAsRead($userId);
 *
 * Real-time:
 * Notification::sendRealtime($userId, 'new_message', ['text' => 'Hello!']);
 */
class Notification extends Model
{
    protected static $table = 'notifications';
    protected static $primaryKey = 'id';

    public const TYPE_NEW_FOLLOWER = 'new_follower';
    public const TYPE_POST_LIKED = 'post_liked';
    public const TYPE_POST_COMMENTED = 'post_commented';
    public const TYPE_POST_SHARED = 'post_shared';
    public const TYPE_MENTIONED = 'mentioned';
    public const TYPE_NEW_MESSAGE = 'new_message';
    public const TYPE_SYSTEM = 'system';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public static $fields = [
        'id' => 'integer',
        'user_id' => 'integer',
        'type' => 'string',
        'title' => 'string',
        'message' => 'text',
        'data' => 'json',
        'from_user_id' => 'integer',
        'is_read' => 'boolean',
        'priority' => 'string',
        'channel' => 'string',
        'created_at' => 'timestamp',
        'read_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('user_id');
        $table->string('type', 50);
        $table->string('title', 255);
        $table->text('message')->nullable();
        $table->json('data')->nullable();
        $table->integer('from_user_id')->nullable();
        $table->boolean('is_read')->default(false);
        $table->string('priority', 20)->default('normal');
        $table->string('channel', 20)->default('database');
        $table->timestamp('created_at');
        $table->timestamp('read_at')->nullable();
    }

    public static function send(int $userId, string $type, array $data = [], string $channel = 'database'): ?Notification
    {
        $notification = self::createNotification($userId, $type, $data, $channel);

        if ($notification && $channel === 'realtime') {
            self::sendRealtime($userId, $type, $data);
        }

        if ($notification && $channel === 'email') {
            self::sendEmail($userId, $notification);
        }

        return $notification;
    }

    public static function sendToMultiple(array $userIds, string $type, array $data = [], string $channel = 'database'): void
    {
        foreach ($userIds as $userId) {
            self::send($userId, $type, $data, $channel);
        }
    }

    public static function sendRealtime(int $userId, string $type, array $data = []): void
    {
        $channel = 'user_' . $userId;
        $payload = array_merge($data, [
            'type' => $type,
            'timestamp' => time(),
        ]);

        try {
            Sse::broadcast($channel, $payload);
        } catch (\Exception $e) {
        }
    }

    public static function unread(int $userId, int $limit = 50): array
    {
        return static::where('user_id', $userId)
            ->where('is_read', false)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public static function allForUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        return static::where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public static function markAsRead(int $notificationId): bool
    {
        return static::where('id', $notificationId)->updateRows([
            'is_read' => true,
            'read_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function markAllAsRead(int $userId): bool
    {
        return static::where('user_id', $userId)
            ->where('is_read', false)
            ->updateRows([
                'is_read' => true,
                'read_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public static function unreadCount(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('is_read', false)
            ->countRows();
    }

    public static function deleteOld(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return static::where('created_at', '<', $cutoff)
            ->deleteRows();
    }

    public function markRead(): bool
    {
        return self::markAsRead($this->id);
    }

    public function getData(): array
    {
        if ($this->data) {
            return json_decode($this->data, true) ?? [];
        }
        return [];
    }

    protected static function createNotification(int $userId, string $type, array $data, string $channel): ?Notification
    {
        $title = self::generateTitle($type, $data);
        $message = self::generateMessage($type, $data);

        return static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => !empty($data) ? json_encode($data) : null,
            'from_user_id' => $data['from_user_id'] ?? null,
            'priority' => self::getPriorityForType($type),
            'channel' => $channel,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected static function generateTitle(string $type, array $data): string
    {
        return match ($type) {
            self::TYPE_NEW_FOLLOWER => 'New Follower',
            self::TYPE_POST_LIKED => 'Liked Your Post',
            self::TYPE_POST_COMMENTED => 'Commented on Your Post',
            self::TYPE_POST_SHARED => 'Shared Your Post',
            self::TYPE_MENTIONED => 'Mentioned You',
            self::TYPE_NEW_MESSAGE => 'New Message',
            self::TYPE_SYSTEM => 'System Notification',
            default => 'Notification',
        };
    }

    protected static function generateMessage(string $type, array $data): string
    {
        $userName = $data['from_user_name'] ?? 'Someone';

        return match ($type) {
            self::TYPE_NEW_FOLLOWER => "{$userName} started following you",
            self::TYPE_POST_LIKED => "{$userName} liked your post",
            self::TYPE_POST_COMMENTED => "{$userName} commented: " . ($data['comment'] ?? ''),
            self::TYPE_POST_SHARED => "{$userName} shared your post",
            self::TYPE_MENTIONED => "{$userName} mentioned you in a post",
            self::TYPE_NEW_MESSAGE => "{$userName} sent you a message",
            self::TYPE_SYSTEM => $data['message'] ?? 'System notification',
            default => 'You have a new notification',
        };
    }

    protected static function getPriorityForType(string $type): string
    {
        return match ($type) {
            self::TYPE_NEW_MESSAGE => self::PRIORITY_HIGH,
            self::TYPE_MENTIONED => self::PRIORITY_HIGH,
            self::TYPE_POST_LIKED => self::PRIORITY_LOW,
            self::TYPE_POST_SHARED => self::PRIORITY_LOW,
            default => self::PRIORITY_NORMAL,
        };
    }

    protected static function sendEmail(int $userId, Notification $notification): void
    {
        try {
            $userResult = static::rawQuery(
                "SELECT email, name FROM users WHERE id = ?",
                [$userId]
            );

            if ($userResult) {
                $user = $userResult->fetch(\PDO::FETCH_ASSOC);
                if ($user) {
                    $mailer = new \Framework\Mail\Mailer();
                    $mailer->send(
                        $user['email'],
                        $notification->title,
                        $notification->message
                    );
                }
            }
        } catch (\Exception $e) {
        }
    }
}
