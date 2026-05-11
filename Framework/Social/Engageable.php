<?php

namespace Framework\Social;

/**
 * Engageable Trait
 * Provides polymorphic engagement: likes, comments, shares, and bookmarks.
 * Works with any model that can be engaged with (posts, videos, etc.).
 *
 * Usage:
 * class Post extends Model {
 *     use Engageable;
 * }
 *
 * $post->like($userId);
 * $post->unlike($userId);
 * $post->isLikedBy($userId);
 * $post->getLikes();
 * $post->likeCount();
 *
 * $post->addComment($userId, 'Great post!');
 * $post->getComments();
 * $post->commentCount();
 *
 * $post->share($userId);
 * $post->getShares();
 *
 * $post->bookmark($userId);
 * $post->isBookmarkedBy($userId);
 */
trait Engageable
{
    protected static $likesTable = 'likes';
    protected static $commentsTable = 'comments';
    protected static $sharesTable = 'shares';
    protected static $bookmarksTable = 'bookmarks';

    public function like(int $userId): bool
    {
        if ($this->isLikedBy($userId)) {
            return false;
        }

        return $this->insertEngagement(
            static::$likesTable,
            $this->getEngagementData($userId)
        );
    }

    public function unlike(int $userId): bool
    {
        return $this->deleteEngagement(
            static::$likesTable,
            $this->getEngagementBaseData($userId)
        );
    }

    public function isLikedBy(int $userId): bool
    {
        return $this->hasEngagement(static::$likesTable, $this->getEngagementBaseData($userId));
    }

    public function getLikes(int $limit = 50): array
    {
        return $this->getEngagements(static::$likesTable, $limit);
    }

    public function likeCount(): int
    {
        return $this->countEngagements(static::$likesTable);
    }

    public function addComment(int $userId, string $content, ?int $parentId = null): ?array
    {
        $data = [
            'commentable_type' => static::class,
            'commentable_id' => $this->getEngagementId(),
            'user_id' => $userId,
            'content' => $content,
            'parent_id' => $parentId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $table = static::$commentsTable;
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $result = $this->rawQuery($sql, $values);

        if ($result) {
            $data['id'] = (int) $this->connection->lastInsertId();
            return $data;
        }

        return null;
    }

    public function deleteComment(int $commentId): bool
    {
        $this->rawQuery(
            "DELETE FROM " . static::$commentsTable . " WHERE id = ?",
            [$commentId]
        );
        return true;
    }

    public function getComments(int $limit = 50, int $parentId = null): array
    {
        $table = static::$commentsTable;
        $sql = "SELECT c.*, u.name as user_name, u.email as user_email FROM {$table} c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.commentable_type = ? AND c.commentable_id = ?";
        $params = [static::class, $this->getEngagementId()];

        if ($parentId !== null) {
            $sql .= " AND c.parent_id = ?";
            $params[] = $parentId;
        } else {
            $sql .= " AND c.parent_id IS NULL";
        }

        $sql .= " ORDER BY c.created_at ASC LIMIT ?";
        $params[] = $limit;

        $result = $this->rawQuery($sql, $params);
        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public function getCommentReplies(int $commentId, int $limit = 50): array
    {
        return $this->getComments($limit, $commentId);
    }

    public function commentCount(): int
    {
        $table = static::$commentsTable;
        return $this->countEngagements($table);
    }

    public function share(int $userId): bool
    {
        if ($this->isSharedBy($userId)) {
            return false;
        }

        return $this->insertEngagement(
            static::$sharesTable,
            $this->getEngagementData($userId)
        );
    }

    public function unshare(int $userId): bool
    {
        return $this->deleteEngagement(
            static::$sharesTable,
            $this->getEngagementBaseData($userId)
        );
    }

    public function isSharedBy(int $userId): bool
    {
        return $this->hasEngagement(static::$sharesTable, $this->getEngagementBaseData($userId));
    }

    public function getShares(int $limit = 50): array
    {
        return $this->getEngagements(static::$sharesTable, $limit);
    }

    public function shareCount(): int
    {
        return $this->countEngagements(static::$sharesTable);
    }

    public function bookmark(int $userId): bool
    {
        if ($this->isBookmarkedBy($userId)) {
            return false;
        }

        return $this->insertEngagement(
            static::$bookmarksTable,
            $this->getEngagementData($userId)
        );
    }

    public function unbookmark(int $userId): bool
    {
        return $this->deleteEngagement(
            static::$bookmarksTable,
            $this->getEngagementBaseData($userId)
        );
    }

    public function isBookmarkedBy(int $userId): bool
    {
        return $this->hasEngagement(static::$bookmarksTable, $this->getEngagementBaseData($userId));
    }

    public function getBookmarks(int $userId): array
    {
        $table = static::$bookmarksTable;
        $result = $this->rawQuery(
            "SELECT b.*, " . $this->getEngagementSelectColumns() . " FROM {$table} b
             JOIN " . $this->getEngagementTable() . " e ON b." . $this->getEngagementTypeColumn() . " = ? AND b." . $this->getEngagementIdColumn() . " = e.id
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC LIMIT ?",
            [static::class, $userId, 100]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public function toggleLike(int $userId): bool
    {
        if ($this->isLikedBy($userId)) {
            return $this->unlike($userId);
        }
        return $this->like($userId);
    }

    public function toggleBookmark(int $userId): bool
    {
        if ($this->isBookmarkedBy($userId)) {
            return $this->unbookmark($userId);
        }
        return $this->bookmark($userId);
    }

    public function getEngagementSummary(): array
    {
        return [
            'likes' => $this->likeCount(),
            'comments' => $this->commentCount(),
            'shares' => $this->shareCount(),
        ];
    }

    protected function getEngagementId(): int
    {
        return (int) ($this->data[static::$primaryKey] ?? 0);
    }

    protected function getEngagementData(int $userId): array
    {
        return [
            $this->getEngagementTypeColumn() => static::class,
            $this->getEngagementIdColumn() => $this->getEngagementId(),
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    protected function getEngagementBaseData(int $userId): array
    {
        return [
            $this->getEngagementTypeColumn() => static::class,
            $this->getEngagementIdColumn() => $this->getEngagementId(),
            'user_id' => $userId,
        ];
    }

    protected function getEngagementTypeColumn(): string
    {
        $shortName = strtolower(class_basename(static::class));
        return $shortName . '_type';
    }

    protected function getEngagementIdColumn(): string
    {
        $shortName = strtolower(class_basename(static::class));
        return $shortName . '_id';
    }

    protected function getEngagementTable(): string
    {
        return static::$table;
    }

    protected function getEngagementSelectColumns(): string
    {
        return static::$table . '.*';
    }

    protected function insertEngagement(string $table, array $data): bool
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        return (bool) $this->rawQuery($sql, $values);
    }

    protected function deleteEngagement(string $table, array $data): bool
    {
        $typeColumn = $this->getEngagementTypeColumn();
        $idColumn = $this->getEngagementIdColumn();

        return (bool) $this->rawQuery(
            "DELETE FROM {$table} WHERE {$typeColumn} = ? AND {$idColumn} = ? AND user_id = ?",
            [$data[$typeColumn], $data[$idColumn], $data['user_id']]
        );
    }

    protected function hasEngagement(string $table, array $data): bool
    {
        $typeColumn = $this->getEngagementTypeColumn();
        $idColumn = $this->getEngagementIdColumn();

        $result = $this->rawQuery(
            "SELECT COUNT(*) as count FROM {$table} WHERE {$typeColumn} = ? AND {$idColumn} = ? AND user_id = ?",
            [$data[$typeColumn], $data[$idColumn], $data['user_id']]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (int) $row['count'] > 0;
        }

        return false;
    }

    protected function getEngagements(string $table, int $limit = 50): array
    {
        $typeColumn = $this->getEngagementTypeColumn();
        $idColumn = $this->getEngagementIdColumn();

        $result = $this->rawQuery(
            "SELECT l.*, u.name as user_name, u.email as user_email FROM {$table} l
             LEFT JOIN users u ON l.user_id = u.id
             WHERE l.{$typeColumn} = ? AND l.{$idColumn} = ?
             ORDER BY l.created_at DESC LIMIT ?",
            [static::class, $this->getEngagementId(), $limit]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    protected function countEngagements(string $table): int
    {
        $typeColumn = $this->getEngagementTypeColumn();
        $idColumn = $this->getEngagementIdColumn();

        $result = $this->rawQuery(
            "SELECT COUNT(*) as count FROM {$table} WHERE {$typeColumn} = ? AND {$idColumn} = ?",
            [static::class, $this->getEngagementId()]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (int) $row['count'];
        }

        return 0;
    }
}
