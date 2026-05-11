<?php

namespace Framework\Blog;

use Framework\Model\Model;

/**
 * Blog Analytics
 * Tracks page views, reading time, popular posts, and author stats.
 *
 * Usage:
 * Analytics::trackView($articleId);
 * $popular = Analytics::popularPosts(7);
 * $stats = Analytics::authorStats($authorId);
 * $overview = Analytics::overview();
 */
class Analytics extends Model
{
    protected static $table = 'blog_analytics';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'article_id' => 'integer',
        'ip_address' => 'string',
        'user_agent' => 'text',
        'referer' => 'text',
        'country' => 'string',
        'device' => 'string',
        'viewed_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('article_id');
        $table->string('ip_address', 45);
        $table->text('user_agent')->nullable();
        $table->text('referer')->nullable();
        $table->string('country', 100)->nullable();
        $table->string('device', 50)->nullable();
        $table->timestamp('viewed_at');
    }

    public static function trackView(int $articleId, array $data = []): bool
    {
        $record = [
            'article_id' => $articleId,
            'ip_address' => $data['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            'user_agent' => $data['ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referer' => $data['referer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''),
            'country' => $data['country'] ?? null,
            'device' => self::detectDevice($data['ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'viewed_at' => date('Y-m-d H:i:s'),
        ];

        $created = static::create($record);

        // Increment article view count
        if ($created) {
            Article::rawQuery(
                "UPDATE articles SET view_count = view_count + 1 WHERE id = ?",
                [$articleId]
            );
        }

        return (bool) $created;
    }

    public static function popularPosts(int $days = 30, int $limit = 10): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $sql = "SELECT a.*, COUNT(v.id) as views 
                FROM " . Article::$table . " a
                LEFT JOIN " . static::$table . " v ON a.id = v.article_id AND v.viewed_at >= ?
                WHERE a.status = ?
                GROUP BY a.id 
                ORDER BY views DESC, a.view_count DESC 
                LIMIT ?";

        $result = static::rawQuery($sql, [$since, Article::STATUS_PUBLISHED, $limit]);
        return $result ? $result->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    public static function trendingPosts(int $hours = 24, int $limit = 5): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        $sql = "SELECT a.*, COUNT(v.id) as views 
                FROM " . Article::$table . " a
                JOIN " . static::$table . " v ON a.id = v.article_id
                WHERE v.viewed_at >= ? AND a.status = ?
                GROUP BY a.id 
                ORDER BY views DESC 
                LIMIT ?";

        $result = static::rawQuery($sql, [$since, Article::STATUS_PUBLISHED, $limit]);
        return $result ? $result->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    public static function authorStats(int $authorId, int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $articles = Article::rawQuery(
            "SELECT COUNT(*) as articles, SUM(view_count) as total_views, SUM(comment_count) as total_comments 
             FROM articles WHERE author_id = ? AND status = ?",
            [$authorId, Article::STATUS_PUBLISHED]
        );

        $recentViews = static::rawQuery(
            "SELECT COUNT(*) as views FROM " . static::$table . " 
             JOIN articles ON " . static::$table . ".article_id = articles.id
             WHERE articles.author_id = ? AND viewed_at >= ?",
            [$authorId, $since]
        );

        return [
            'total_articles' => $articles ? (int) $articles->fetch(\PDO::FETCH_ASSOC)['articles'] : 0,
            'total_views' => $articles ? (int) $articles->fetch(\PDO::FETCH_ASSOC)['total_views'] : 0,
            'total_comments' => $articles ? (int) $articles->fetch(\PDO::FETCH_ASSOC)['total_comments'] : 0,
            'recent_views' => $recentViews ? (int) $recentViews->fetch(\PDO::FETCH_ASSOC)['views'] : 0,
        ];
    }

    public static function overview(int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $totalArticles = Article::rawQuery("SELECT COUNT(*) as count FROM articles WHERE status = ?", [Article::STATUS_PUBLISHED]);
        $totalComments = Comment::rawQuery("SELECT COUNT(*) as count FROM blog_comments WHERE status = ?", [Comment::STATUS_APPROVED]);
        $totalViews = static::rawQuery("SELECT COUNT(*) as count FROM " . static::$table . " WHERE viewed_at >= ?", [$since]);

        $dailyViews = static::rawQuery(
            "SELECT DATE(viewed_at) as date, COUNT(*) as count FROM " . static::$table . " 
             WHERE viewed_at >= ? GROUP BY DATE(viewed_at) ORDER BY date ASC",
            [$since]
        );

        return [
            'total_articles' => $totalArticles ? (int) $totalArticles->fetch(\PDO::FETCH_ASSOC)['count'] : 0,
            'total_comments' => $totalComments ? (int) $totalComments->fetch(\PDO::FETCH_ASSOC)['count'] : 0,
            'total_views' => $totalViews ? (int) $totalViews->fetch(\PDO::FETCH_ASSOC)['count'] : 0,
            'daily_views' => $dailyViews ? $dailyViews->fetchAll(\PDO::FETCH_ASSOC) : [],
        ];
    }

    protected static function detectDevice(string $ua): string
    {
        if (preg_match('/mobile/i', $ua)) return 'mobile';
        if (preg_match('/tablet|ipad/i', $ua)) return 'tablet';
        return 'desktop';
    }
}
