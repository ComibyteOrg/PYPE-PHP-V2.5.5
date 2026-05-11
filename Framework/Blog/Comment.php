<?php

namespace Framework\Blog;

use Framework\Model\Model;

/**
 * Comment Model
 * Supports nested comments, moderation queue, and basic spam filtering.
 *
 * Usage:
 * $comment = Comment::createComment([
 *     'article_id' => 1,
 *     'author_name' => 'John',
 *     'author_email' => 'john@example.com',
 *     'content' => 'Great post!',
 * ]);
 * Comment::moderationQueue();
 * $comment->approve();
 * $comment->isSpam(); // checks basic heuristics
 */
class Comment extends Model
{
    protected static $table = 'blog_comments';
    protected static $primaryKey = 'id';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SPAM = 'spam';

    public static $fields = [
        'id' => 'integer',
        'article_id' => 'integer',
        'parent_id' => 'integer',
        'user_id' => 'integer',
        'author_name' => 'string',
        'author_email' => 'string',
        'author_url' => 'string',
        'content' => 'text',
        'ip_address' => 'string',
        'user_agent' => 'text',
        'status' => 'string',
        'is_pinned' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('article_id');
        $table->integer('parent_id')->default(0);
        $table->integer('user_id')->nullable();
        $table->string('author_name', 255);
        $table->string('author_email', 255);
        $table->string('author_url', 500)->nullable();
        $table->text('content');
        $table->string('ip_address', 45);
        $table->text('user_agent')->nullable();
        $table->string('status', 20)->default('pending');
        $table->boolean('is_pinned')->default(false);
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createComment(array $data): ?Comment
    {
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_PENDING;
        }
        if (!isset($data['parent_id'])) {
            $data['parent_id'] = 0;
        }
        if (!isset($data['ip_address'])) {
            $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        if (!isset($data['user_agent'])) {
            $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Auto-detect spam
        if (self::isSpamHeuristic($data)) {
            $data['status'] = self::STATUS_SPAM;
        }

        // Auto-approve if previously approved
        if ($data['status'] === self::STATUS_PENDING && !empty($data['author_email'])) {
            $approved = static::where('author_email', $data['author_email'])
                ->where('status', self::STATUS_APPROVED)
                ->first();
            if ($approved) {
                $data['status'] = self::STATUS_APPROVED;
            }
        }

        $comment = static::create($data);

        // Update article comment count if approved
        if ($comment && $data['status'] === self::STATUS_APPROVED) {
            $article = Article::find($data['article_id']);
            if ($article) {
                $article->incrementComments();
            }
        }

        return $comment;
    }

    public function approve(): bool
    {
        $wasPending = $this->status === self::STATUS_PENDING;
        $result = static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_APPROVED,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($result && $wasPending) {
            $article = Article::find($this->article_id);
            if ($article) {
                $article->incrementComments();
            }
        }
        return $result;
    }

    public function reject(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_REJECTED,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markSpam(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_SPAM,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSpam(): bool
    {
        return $this->status === self::STATUS_SPAM;
    }

    public function getReplies(int $limit = 50): array
    {
        return static::where('parent_id', $this->id)
            ->where('status', self::STATUS_APPROVED)
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    public static function forArticle(int $articleId, int $parentId = 0): array
    {
        return static::where('article_id', $articleId)
            ->where('parent_id', $parentId)
            ->where('status', self::STATUS_APPROVED)
            ->orderBy('is_pinned', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public static function treeForArticle(int $articleId): array
    {
        $roots = static::forArticle($articleId);
        $tree = [];

        foreach ($roots as $root) {
            $rootData = is_array($root) ? $root : $root->toArray();
            $rootData['replies'] = static::forArticle($articleId, $rootData['id']);
            $tree[] = $rootData;
        }

        return $tree;
    }

    public static function moderationQueue(int $limit = 20): array
    {
        return static::where('status', self::STATUS_PENDING)
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    public static function spamQueue(int $limit = 20): array
    {
        return static::where('status', self::STATUS_SPAM)
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    public static function countByStatus(string $status): int
    {
        $row = static::rawQuery(
            "SELECT COUNT(*) as count FROM " . static::$table . " WHERE status = ?",
            [$status]
        );
        return $row ? (int) $row->fetch(\PDO::FETCH_ASSOC)['count'] : 0;
    }

    protected static function isSpamHeuristic(array $data): bool
    {
        $content = $data['content'] ?? '';
        $name = $data['author_name'] ?? '';

        // Too many links
        if (substr_count(strtolower($content), 'http') > 3) {
            return true;
        }

        // Blacklisted words
        $blacklist = ['casino', 'viagra', 'lottery', 'winner', 'free money'];
        foreach ($blacklist as $word) {
            if (stripos($content, $word) !== false || stripos($name, $word) !== false) {
                return true;
            }
        }

        // Very short content
        if (strlen(trim($content)) < 5) {
            return true;
        }

        // All caps
        if (strlen($content) > 10 && strtoupper($content) === $content) {
            return true;
        }

        return false;
    }
}
