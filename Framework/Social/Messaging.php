<?php

namespace Framework\Social;

use Framework\Model\Model;
use Framework\Api\Sse;

/**
 * Conversation Model
 * Manages direct message conversations between users.
 *
 * Usage:
 * $conversation = Conversation::createBetween($userId1, $userId2);
 * $conversation->sendMessage($userId, 'Hello!');
 * $messages = $conversation->getMessages();
 * $conversation->markAsRead($userId);
 * $conversation->getLastMessage();
 */
class Conversation extends Model
{
    protected static $table = 'conversations';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'user_one_id' => 'integer',
        'user_two_id' => 'integer',
        'last_message' => 'text',
        'last_message_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('user_one_id');
        $table->integer('user_two_id');
        $table->text('last_message')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createBetween(int $userId1, int $userId2): Conversation
    {
        $existing = self::findByUsers($userId1, $userId2);
        if ($existing) {
            return $existing;
        }

        return self::create([
            'user_one_id' => min($userId1, $userId2),
            'user_two_id' => max($userId1, $userId2),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function findByUsers(int $userId1, int $userId2): ?Conversation
    {
        return static::where('user_one_id', min($userId1, $userId2))
            ->where('user_two_id', max($userId1, $userId2))
            ->getFirst();
    }

    public static function forUser(int $userId, int $limit = 20): array
    {
        $table = static::getTable();
        $result = static::rawQuery(
            "SELECT c.*, 
                    u1.name as user_one_name, u1.username as user_one_username,
                    u2.name as user_two_name, u2.username as user_two_username
             FROM {$table} c
             LEFT JOIN users u1 ON c.user_one_id = u1.id
             LEFT JOIN users u2 ON c.user_two_id = u2.id
             WHERE c.user_one_id = ? OR c.user_two_id = ?
             ORDER BY c.last_message_at DESC, c.updated_at DESC
             LIMIT ?",
            [$userId, $userId, $limit]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public static function unreadCount(int $userId): int
    {
        $messageTable = Message::getTable();
        $result = static::rawQuery(
            "SELECT COUNT(*) as count FROM {$messageTable} m
             JOIN " . static::getTable() . " c ON m.conversation_id = c.id
             WHERE (c.user_one_id = ? OR c.user_two_id = ?)
             AND m.sender_id != ?
             AND m.is_read = false",
            [$userId, $userId, $userId]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (int) $row['count'];
        }

        return 0;
    }

    public function sendMessage(int $senderId, string $content, ?string $attachment = null): ?Message
    {
        $message = Message::create([
            'conversation_id' => $this->id,
            'sender_id' => $senderId,
            'content' => $content,
            'attachment' => $attachment,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($message) {
            $this->update([
                'last_message' => $content,
                'last_message_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $otherUserId = $this->getOtherUserId($senderId);
            if ($otherUserId) {
                Notification::sendRealtime($otherUserId, Notification::TYPE_NEW_MESSAGE, [
                    'conversation_id' => $this->id,
                    'sender_id' => $senderId,
                    'content' => $content,
                ]);

                Notification::send($otherUserId, Notification::TYPE_NEW_MESSAGE, [
                    'from_user_id' => $senderId,
                    'conversation_id' => $this->id,
                ], 'database');
            }
        }

        return $message;
    }

    public function getMessages(int $limit = 50, ?string $before = null): array
    {
        $messageTable = Message::getTable();
        $sql = "SELECT m.*, u.name as sender_name, u.username as sender_username
                FROM {$messageTable} m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.conversation_id = ?";
        $params = [$this->id];

        if ($before) {
            $sql .= " AND m.created_at < ?";
            $params[] = $before;
        }

        $sql .= " ORDER BY m.created_at DESC LIMIT ?";
        $params[] = $limit;

        $result = static::rawQuery($sql, $params);

        if ($result) {
            $messages = $result->fetchAll(\PDO::FETCH_ASSOC);
            return array_reverse($messages);
        }

        return [];
    }

    public function getLastMessage(): ?Message
    {
        return Message::where('conversation_id', $this->id)
            ->orderBy('created_at', 'DESC')
            ->getFirst();
    }

    public function markAsRead(int $userId): bool
    {
        $messageTable = Message::getTable();
        return (bool) static::rawQuery(
            "UPDATE {$messageTable} SET is_read = true, read_at = ? 
             WHERE conversation_id = ? AND sender_id != ? AND is_read = false",
            [date('Y-m-d H:i:s'), $this->id, $userId]
        );
    }

    public function getOtherUserId(int $userId): ?int
    {
        if ($this->user_one_id == $userId) {
            return (int) $this->user_two_id;
        }
        if ($this->user_two_id == $userId) {
            return (int) $this->user_one_id;
        }
        return null;
    }

    public function isParticipant(int $userId): bool
    {
        return $this->user_one_id == $userId || $this->user_two_id == $userId;
    }

    public function update(array $data): bool
    {
        return static::where('id', $this->id)->updateRows($data);
    }
}

/**
 * Message Model
 * Represents individual messages within a conversation.
 */
class Message extends Model
{
    protected static $table = 'messages';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'conversation_id' => 'integer',
        'sender_id' => 'integer',
        'content' => 'text',
        'attachment' => 'string',
        'is_read' => 'boolean',
        'read_at' => 'timestamp',
        'created_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('conversation_id');
        $table->integer('sender_id');
        $table->text('content');
        $table->string('attachment', 500)->nullable();
        $table->boolean('is_read')->default(false);
        $table->timestamp('read_at')->nullable();
        $table->timestamp('created_at');
    }

    public function markAsRead(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'is_read' => true,
            'read_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getConversation(): ?Conversation
    {
        return Conversation::find($this->conversation_id);
    }
}
