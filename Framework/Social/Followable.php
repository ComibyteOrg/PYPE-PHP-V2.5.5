<?php

namespace Framework\Social;

use Framework\Model\Model;

/**
 * Followable Trait
 * Provides self-referential follow/unfollow relationships with follower counts.
 * Models using this trait can follow and be followed by other models.
 *
 * Usage:
 * class User extends Model {
 *     use Followable;
 * }
 *
 * $user->follow($otherUser);
 * $user->unfollow($otherUser);
 * $user->isFollowing($otherUser);
 * $user->getFollowers();
 * $user->getFollowing();
 * $user->followerCount();
 */
trait Followable
{
    protected static $followTable = 'follows';

    public function follow($target): bool
    {
        $targetId = $this->resolveModelId($target);
        if (!$targetId || $targetId === $this->getPrimaryKeyValue()) {
            return false;
        }

        if ($this->isFollowing($target)) {
            return false;
        }

        $data = [
            'follower_type' => static::class,
            'follower_id' => $this->getPrimaryKeyValue(),
            'followable_type' => static::class,
            'followable_id' => $targetId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->insertFollowRecord($data);
    }

    public function unfollow($target): bool
    {
        $targetId = $this->resolveModelId($target);
        if (!$targetId) {
            return false;
        }

        return $this->deleteFollowRecord(
            static::class,
            $this->getPrimaryKeyValue(),
            static::class,
            $targetId
        );
    }

    public function isFollowing($target): bool
    {
        $targetId = $this->resolveModelId($target);
        if (!$targetId) {
            return false;
        }

        $result = $this->rawQuery(
            "SELECT COUNT(*) as count FROM " . static::getFollowTable() . " WHERE follower_type = ? AND follower_id = ? AND followable_type = ? AND followable_id = ?",
            [static::class, $this->getPrimaryKeyValue(), static::class, $targetId]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (int) $row['count'] > 0;
        }

        return false;
    }

    public function isFollowedBy($target): bool
    {
        $targetId = $this->resolveModelId($target);
        if (!$targetId) {
            return false;
        }

        $result = $this->rawQuery(
            "SELECT COUNT(*) as count FROM " . static::getFollowTable() . " WHERE follower_type = ? AND follower_id = ? AND followable_type = ? AND followable_id = ?",
            [static::class, $targetId, static::class, $this->getPrimaryKeyValue()]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (int) $row['count'] > 0;
        }

        return false;
    }

    public function getFollowers(int $limit = 20, int $offset = 0): array
    {
        $result = $this->rawQuery(
            "SELECT f.*, u.* FROM " . static::getFollowTable() . " f 
             LEFT JOIN " . static::getTable() . " u ON f.follower_type = ? AND f.follower_id = u." . static::$primaryKey . "
             WHERE f.followable_type = ? AND f.followable_id = ?
             ORDER BY f.created_at DESC
             LIMIT ? OFFSET ?",
            [static::class, static::class, $this->getPrimaryKeyValue(), $limit, $offset]
        );

        if ($result) {
            $rows = $result->fetchAll(\PDO::FETCH_ASSOC);
            return $this->wrapFollowers($rows);
        }

        return [];
    }

    public function getFollowing(int $limit = 20, int $offset = 0): array
    {
        $result = $this->rawQuery(
            "SELECT f.*, u.* FROM " . static::getFollowTable() . " f 
             LEFT JOIN " . static::getTable() . " u ON f.followable_type = ? AND f.followable_id = u." . static::$primaryKey . "
             WHERE f.follower_type = ? AND f.follower_id = ?
             ORDER BY f.created_at DESC
             LIMIT ? OFFSET ?",
            [static::class, static::class, $this->getPrimaryKeyValue(), $limit, $offset]
        );

        if ($result) {
            $rows = $result->fetchAll(\PDO::FETCH_ASSOC);
            return $this->wrapFollowers($rows);
        }

        return [];
    }

    public function followerCount(): int
    {
        return $this->countRelationships('follower');
    }

    public function followingCount(): int
    {
        return $this->countRelationships('followable');
    }

    public function toggleFollow($target): bool
    {
        if ($this->isFollowing($target)) {
            return $this->unfollow($target);
        }
        return $this->follow($target);
    }

    public function getMutualFollowers($otherUser): array
    {
        $otherId = $this->resolveModelId($otherUser);
        if (!$otherId) {
            return [];
        }

        $result = $this->rawQuery(
            "SELECT DISTINCT u.* FROM " . static::getFollowTable() . " f1
             JOIN " . static::getFollowTable() . " f2 ON f1.follower_type = f2.follower_type AND f1.follower_id = f2.follower_id
             LEFT JOIN " . static::getTable() . " u ON f1.follower_type = ? AND f1.follower_id = u." . static::$primaryKey . "
             WHERE f1.followable_type = ? AND f1.followable_id = ?
             AND f2.followable_type = ? AND f2.followable_id = ?",
            [static::class, static::class, $this->getPrimaryKeyValue(), static::class, $otherId]
        );

        if ($result) {
            $rows = $result->fetchAll(\PDO::FETCH_ASSOC);
            return $this->wrapFollowers($rows);
        }

        return [];
    }

    public static function getFollowTable(): string
    {
        return static::$followTable;
    }

    protected function resolveModelId($target): ?int
    {
        if (is_object($target) && isset($target->{static::$primaryKey})) {
            return (int) $target->{static::$primaryKey};
        }
        if (is_int($target) || is_string($target)) {
            return (int) $target;
        }
        return null;
    }

    protected function getPrimaryKeyValue(): int
    {
        return (int) ($this->data[static::$primaryKey] ?? 0);
    }

    protected function insertFollowRecord(array $data): bool
    {
        $table = static::getFollowTable();
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        return (bool) $this->rawQuery($sql, $values);
    }

    protected function deleteFollowRecord(string $followerType, int $followerId, string $followableType, int $followableId): bool
    {
        $table = static::getFollowTable();
        return (bool) $this->rawQuery(
            "DELETE FROM {$table} WHERE follower_type = ? AND follower_id = ? AND followable_type = ? AND followable_id = ?",
            [$followerType, $followerId, $followableType, $followableId]
        );
    }

    protected function countRelationships(string $type): int
    {
        $table = static::getFollowTable();
        $column = $type === 'follower' ? 'followable_id' : 'follower_id';
        $typeColumn = $type === 'follower' ? 'followable_type' : 'follower_type';

        $result = $this->rawQuery(
            "SELECT COUNT(*) as count FROM {$table} WHERE {$typeColumn} = ? AND {$column} = ?",
            [static::class, $this->getPrimaryKeyValue()]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (int) $row['count'];
        }

        return 0;
    }

    protected function wrapFollowers(array $rows): array
    {
        $followers = [];
        foreach ($rows as $row) {
            $follower = [];
            foreach ($row as $key => $value) {
                if (!in_array($key, ['follower_type', 'follower_id', 'followable_type', 'followable_id'])) {
                    $follower[$key] = $value;
                }
            }
            if (!empty($follower)) {
                $followers[] = $follower;
            }
        }
        return $followers;
    }
}
