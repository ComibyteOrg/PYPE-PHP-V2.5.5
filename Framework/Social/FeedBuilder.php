<?php

namespace Framework\Social;

/**
 * FeedBuilder Service
 * Generates personalized activity feeds/timelines with algorithmic sorting.
 * Supports chronological, weighted, and custom algorithm feeds.
 *
 * Usage:
 * $feed = FeedBuilder::forUser($userId)
 *     ->chronological()
 *     ->limit(20)
 *     ->get();
 *
 * $feed = FeedBuilder::forUser($userId)
 *     ->algorithmic()
 *     ->weightLikes(1)
 *     ->weightComments(2)
 *     ->weightRecency(3)
 *     ->get();
 *
 * $feed = FeedBuilder::forUser($userId)
 *     ->followingOnly()
 *     ->cursor($cursorToken)
 *     ->get();
 */
class FeedBuilder
{
    private int $userId;
    private string $strategy = 'chronological';
    private int $limit = 20;
    private ?int $beforeId = null;
    private ?int $afterId = null;
    private bool $followingOnly = false;
    private array $weights = [
        'likes' => 1,
        'comments' => 2,
        'shares' => 3,
        'recency' => 2,
        'follows' => 1,
    ];
    private array $types = ['text', 'image', 'video', 'link', 'poll'];
    private array $excludeTypes = [];
    private array $excludeUsers = [];

    public static function forUser(int $userId): self
    {
        $instance = new self();
        $instance->userId = $userId;
        return $instance;
    }

    public function chronological(): self
    {
        $this->strategy = 'chronological';
        return $this;
    }

    public function algorithmic(): self
    {
        $this->strategy = 'algorithmic';
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function cursor(?string $cursor): self
    {
        if ($cursor) {
            $decoded = json_decode(base64_decode($cursor), true);
            if ($decoded && isset($decoded['id'])) {
                $this->beforeId = (int) $decoded['id'];
            }
        }
        return $this;
    }

    public function cursorAfter(?string $cursor): self
    {
        if ($cursor) {
            $decoded = json_decode(base64_decode($cursor), true);
            if ($decoded && isset($decoded['id'])) {
                $this->afterId = (int) $decoded['id'];
            }
        }
        return $this;
    }

    public function followingOnly(): self
    {
        $this->followingOnly = true;
        return $this;
    }

    public function includePublic(): self
    {
        $this->followingOnly = false;
        return $this;
    }

    public function weightLikes(int $weight): self
    {
        $this->weights['likes'] = $weight;
        return $this;
    }

    public function weightComments(int $weight): self
    {
        $this->weights['comments'] = $weight;
        return $this;
    }

    public function weightShares(int $weight): self
    {
        $this->weights['shares'] = $weight;
        return $this;
    }

    public function weightRecency(int $weight): self
    {
        $this->weights['recency'] = $weight;
        return $this;
    }

    public function onlyTypes(array $types): self
    {
        $this->types = $types;
        return $this;
    }

    public function excludeTypes(array $types): self
    {
        $this->excludeTypes = array_merge($this->excludeTypes, $types);
        return $this;
    }

    public function excludeUsers(array $userIds): self
    {
        $this->excludeUsers = array_merge($this->excludeUsers, $userIds);
        return $this;
    }

    public function get(): array
    {
        $posts = $this->fetchPosts();

        if ($this->strategy === 'algorithmic') {
            $posts = $this->scorePosts($posts);
            usort($posts, function ($a, $b) {
                return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
            });
        }

        $posts = array_slice($posts, 0, $this->limit);

        foreach ($posts as &$post) {
            $post['next_cursor'] = $this->generateCursor($post['id']);
        }

        return $posts;
    }

    public function getNextCursor(array $posts): ?string
    {
        if (empty($posts)) {
            return null;
        }

        $lastPost = end($posts);
        return $this->generateCursor($lastPost['id']);
    }

    protected function fetchPosts(): array
    {
        $postModel = new Post();
        $table = $postModel->getTable();

        $sql = "SELECT p.*, u.name as user_name, u.username as user_username, u.avatar as user_avatar
                FROM {$table} p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($this->followingOnly) {
            $followTable = (new \Framework\Social\Post())->rawQuery(
                "SELECT followable_id FROM follows WHERE follower_type = ? AND follower_id = ?",
                ['App\Models\User', $this->userId]
            );

            $followingIds = [$this->userId];
            if ($followTable) {
                while ($row = $followTable->fetch(\PDO::FETCH_ASSOC)) {
                    $followingIds[] = (int) $row['followable_id'];
                }
            }

            $placeholders = implode(',', array_fill(0, count($followingIds), '?'));
            $sql .= " AND p.user_id IN ($placeholders)";
            $params = array_merge($params, $followingIds);
        }

        if (!empty($this->types)) {
            $typePlaceholders = implode(',', array_fill(0, count($this->types), '?'));
            $sql .= " AND p.type IN ($typePlaceholders)";
            $params = array_merge($params, $this->types);
        }

        if (!empty($this->excludeTypes)) {
            $excludePlaceholders = implode(',', array_fill(0, count($this->excludeTypes), '?'));
            $sql .= " AND p.type NOT IN ($excludePlaceholders)";
            $params = array_merge($params, $this->excludeTypes);
        }

        if (!empty($this->excludeUsers)) {
            $userPlaceholders = implode(',', array_fill(0, count($this->excludeUsers), '?'));
            $sql .= " AND p.user_id NOT IN ($userPlaceholders)";
            $params = array_merge($params, $this->excludeUsers);
        }

        if ($this->beforeId) {
            $sql .= " AND p.id < ?";
            $params[] = $this->beforeId;
        }

        if ($this->afterId) {
            $sql .= " AND p.id > ?";
            $params[] = $this->afterId;
        }

        $sql .= " AND p.parent_id IS NULL";
        $sql .= " ORDER BY p.created_at DESC";
        $sql .= " LIMIT " . ($this->limit * 2);

        $result = $postModel->rawQuery($sql, $params);

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    protected function scorePosts(array $posts): array
    {
        $postModel = new Post();
        $likesTable = 'likes';
        $commentsTable = 'comments';
        $sharesTable = 'shares';
        $postType = 'Framework\Social\Post';

        foreach ($posts as &$post) {
            $postId = (int) $post['id'];

            $likeCount = $postModel->rawQuery(
                "SELECT COUNT(*) as count FROM {$likesTable} WHERE post_type = ? AND post_id = ?",
                [$postType, $postId]
            );
            $likes = $likeCount ? (int) $likeCount->fetch(\PDO::FETCH_ASSOC)['count'] : 0;

            $commentCount = $postModel->rawQuery(
                "SELECT COUNT(*) as count FROM {$commentsTable} WHERE post_type = ? AND post_id = ?",
                [$postType, $postId]
            );
            $comments = $commentCount ? (int) $commentCount->fetch(\PDO::FETCH_ASSOC)['count'] : 0;

            $shareCount = $postModel->rawQuery(
                "SELECT COUNT(*) as count FROM {$sharesTable} WHERE post_type = ? AND post_id = ?",
                [$postType, $postId]
            );
            $shares = $shareCount ? (int) $shareCount->fetch(\PDO::FETCH_ASSOC)['count'] : 0;

            $hoursAgo = max(1, (time() - strtotime($post['created_at'])) / 3600);
            $recencyScore = 1 / log($hoursAgo + 2);

            $score = (
                ($likes * $this->weights['likes']) +
                ($comments * $this->weights['comments']) +
                ($shares * $this->weights['shares']) +
                ($recencyScore * $this->weights['recency'] * 10)
            );

            $post['score'] = $score;
            $post['engagement'] = [
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
            ];
        }

        return $posts;
    }

    protected function generateCursor(int $postId): string
    {
        return base64_encode(json_encode(['id' => $postId]));
    }
}
