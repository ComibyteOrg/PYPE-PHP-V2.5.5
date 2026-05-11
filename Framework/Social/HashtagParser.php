<?php

namespace Framework\Social;

/**
 * HashtagParser Service
 * Extracts and manages hashtags (#) and mentions (@) from content.
 * Provides helpers for building tag indexes, mention notifications, and search.
 *
 * Usage:
 * $parsed = HashtagParser::parse('Hello @john! Check out #php #webdev');
 * $parsed['hashtags']; // ['php', 'webdev']
 * $parsed['mentions']; // ['john']
 *
 * HashtagParser::store('post', $postId, 'Love #coding and #php!');
 * HashtagParser::getPostsByTag('php');
 * HashtagParser::getMentionedUsers('Check out @john');
 */
class HashtagParser
{
    protected static $tagsTable = 'hashtags';
    protected static $taggablesTable = 'taggables';
    protected static $mentionsTable = 'mentions';

    public static function parse(string $content): array
    {
        $hashtags = [];
        $mentions = [];

        preg_match_all('/#([a-zA-Z0-9_]+)/', $content, $hashtagMatches);
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $mentionMatches);

        $hashtags = array_map('strtolower', array_unique($hashtagMatches[1]));
        $mentions = array_map('strtolower', array_unique($mentionMatches[1]));

        return [
            'hashtags' => $hashtags,
            'mentions' => $mentions,
            'content' => $content,
        ];
    }

    public static function formatContent(string $content, string $tagUrl = '/tag/{tag}', string $mentionUrl = '/user/{mention}'): string
    {
        $content = preg_replace(
            '/#([a-zA-Z0-9_]+)/',
            '<a href="' . str_replace('{tag}', '$1', $tagUrl) . '">#$1</a>',
            $content
        );

        $content = preg_replace(
            '/@([a-zA-Z0-9_]+)/',
            '<a href="' . str_replace('{mention}', '$1', $mentionUrl) . '">@$1</a>',
            $content
        );

        return $content;
    }

    public static function store(string $modelType, int $modelId, string $content): array
    {
        $parsed = self::parse($content);

        self::storeHashtags($modelType, $modelId, $parsed['hashtags']);
        self::storeMentions($modelType, $modelId, $parsed['mentions']);

        return $parsed;
    }

    public static function getPostsByTag(string $tag, int $limit = 20, int $offset = 0): array
    {
        $tag = strtolower($tag);
        $postsTable = (new \Framework\Social\Post())->getTable();

        $result = (new \Framework\Social\Post())->rawQuery(
            "SELECT p.* FROM " . static::$tagsTable . " t
             JOIN " . static::$taggablesTable . " tg ON t.id = tg.tag_id
             JOIN {$postsTable} p ON tg.taggable_type = ? AND tg.taggable_id = p.id
             WHERE t.name = ? AND p.visibility = 'public'
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            ['Framework\Social\Post', $tag, $limit, $offset]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public static function getTrendingTags(int $limit = 10, int $hours = 24): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        $result = (new \Framework\Social\Post())->rawQuery(
            "SELECT t.name, COUNT(tg.id) as usage_count
             FROM " . static::$tagsTable . " t
             JOIN " . static::$taggablesTable . " tg ON t.id = tg.tag_id
             JOIN " . (new \Framework\Social\Post())->getTable() . " p ON tg.taggable_type = ? AND tg.taggable_id = p.id
             WHERE p.created_at >= ?
             GROUP BY t.id, t.name
             ORDER BY usage_count DESC
             LIMIT ?",
            ['Framework\Social\Post', $since, $limit]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public static function getMentionedUsers(string $content): array
    {
        $parsed = self::parse($content);
        $mentions = $parsed['mentions'];

        if (empty($mentions)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($mentions), '?'));
        $result = (new \Framework\Social\Post())->rawQuery(
            "SELECT id, name, email, username FROM users WHERE username IN ($placeholders)",
            $mentions
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public static function getTagsForModel(string $modelType, int $modelId): array
    {
        $result = (new \Framework\Social\Post())->rawQuery(
            "SELECT t.name FROM " . static::$tagsTable . " t
             JOIN " . static::$taggablesTable . " tg ON t.id = tg.tag_id
             WHERE tg.taggable_type = ? AND tg.taggable_id = ?",
            [$modelType, $modelId]
        );

        if ($result) {
            return array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'name');
        }

        return [];
    }

    public static function searchTags(string $query, int $limit = 10): array
    {
        $result = (new \Framework\Social\Post())->rawQuery(
            "SELECT name FROM " . static::$tagsTable . " WHERE name LIKE ? ORDER BY name ASC LIMIT ?",
            ["%{$query}%", $limit]
        );

        if ($result) {
            return array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'name');
        }

        return [];
    }

    public static function clearTagsForModel(string $modelType, int $modelId): void
    {
        $result = (new \Framework\Social\Post())->rawQuery(
            "SELECT tag_id FROM " . static::$taggablesTable . " WHERE taggable_type = ? AND taggable_id = ?",
            [$modelType, $modelId]
        );

        if ($result) {
            $tagIds = array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'tag_id');

            (new \Framework\Social\Post())->rawQuery(
                "DELETE FROM " . static::$taggablesTable . " WHERE taggable_type = ? AND taggable_id = ?",
                [$modelType, $modelId]
            );

            foreach ($tagIds as $tagId) {
                $countResult = (new \Framework\Social\Post())->rawQuery(
                    "SELECT COUNT(*) as count FROM " . static::$taggablesTable . " WHERE tag_id = ?",
                    [$tagId]
                );

                if ($countResult) {
                    $row = $countResult->fetch(\PDO::FETCH_ASSOC);
                    if ((int) $row['count'] === 0) {
                        (new \Framework\Social\Post())->rawQuery(
                            "DELETE FROM " . static::$tagsTable . " WHERE id = ?",
                            [$tagId]
                        );
                    }
                }
            }
        }
    }

    protected static function storeHashtags(string $modelType, int $modelId, array $hashtags): void
    {
        foreach ($hashtags as $tag) {
            $tagId = self::getOrCreateTag($tag);

            $existing = (new \Framework\Social\Post())->rawQuery(
                "SELECT id FROM " . static::$taggablesTable . " WHERE tag_id = ? AND taggable_type = ? AND taggable_id = ?",
                [$tagId, $modelType, $modelId]
            );

            if (!$existing || !$existing->fetch(\PDO::FETCH_ASSOC)) {
                (new \Framework\Social\Post())->rawQuery(
                    "INSERT INTO " . static::$taggablesTable . " (tag_id, taggable_type, taggable_id, created_at) VALUES (?, ?, ?, ?)",
                    [$tagId, $modelType, $modelId, date('Y-m-d H:i:s')]
                );
            }
        }
    }

    protected static function storeMentions(string $modelType, int $modelId, array $mentions): array
    {
        $mentionedUsers = [];

        foreach ($mentions as $username) {
            $userResult = (new \Framework\Social\Post())->rawQuery(
                "SELECT id, name, username FROM users WHERE username = ?",
                [$username]
            );

            if ($userResult) {
                $user = $userResult->fetch(\PDO::FETCH_ASSOC);
                if ($user) {
                    (new \Framework\Social\Post())->rawQuery(
                        "INSERT INTO " . static::$mentionsTable . " (mentionable_type, mentionable_id, user_id, created_at) VALUES (?, ?, ?, ?)",
                        [$modelType, $modelId, (int) $user['id'], date('Y-m-d H:i:s')]
                    );
                    $mentionedUsers[] = $user;
                }
            }
        }

        return $mentionedUsers;
    }

    protected static function getOrCreateTag(string $name): int
    {
        $name = strtolower($name);

        $result = (new \Framework\Social\Post())->rawQuery(
            "SELECT id FROM " . static::$tagsTable . " WHERE name = ?",
            [$name]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return (int) $row['id'];
            }
        }

        (new \Framework\Social\Post())->rawQuery(
            "INSERT INTO " . static::$tagsTable . " (name, created_at) VALUES (?, ?)",
            [$name, date('Y-m-d H:i:s')]
        );

        return (int) (new \Framework\Social\Post())->connection->lastInsertId();
    }
}
