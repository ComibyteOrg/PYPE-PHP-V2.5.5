<?php

namespace Framework\Blog;

use Framework\Model\Model;

/**
 * Article Model
 * Supports drafts, scheduling, revisions, and versioning.
 *
 * Usage:
 * $article = Article::createArticle([
 *     'title' => 'My Post',
 *     'slug' => 'my-post',
 *     'content' => '# Hello World',
 *     'author_id' => 1,
 *     'status' => 'draft',
 * ]);
 * $article->schedulePublish('2026-06-01 09:00:00');
 * $article->publish();
 * $article->saveRevision('Updated content');
 * $article->getRevisions();
 */
class Article extends Model
{
    use \Framework\Model\HasFiles;

    protected static $table = 'articles';
    protected static $primaryKey = 'id';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_TRASH = 'trash';

    public static $fields = [
        'id' => 'integer',
        'title' => 'string',
        'slug' => 'string',
        'subtitle' => 'string',
        'content' => 'text',
        'content_html' => 'text',
        'excerpt' => 'text',
        'cover_image' => 'string',
        'author_id' => 'integer',
        'guest_author_name' => 'string',
        'guest_author_email' => 'string',
        'status' => 'string',
        'visibility' => 'string',
        'is_featured' => 'boolean',
        'is_pinned' => 'boolean',
        'password' => 'string',
        'published_at' => 'timestamp',
        'scheduled_at' => 'timestamp',
        'seo_title' => 'string',
        'seo_description' => 'text',
        'seo_keywords' => 'string',
        'og_title' => 'string',
        'og_description' => 'text',
        'og_image' => 'string',
        'canonical_url' => 'string',
        'reading_time' => 'integer',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('title', 500);
        $table->string('slug', 500)->unique();
        $table->string('subtitle', 500)->nullable();
        $table->text('content');
        $table->text('content_html')->nullable();
        $table->text('excerpt')->nullable();
        $table->string('cover_image', 500)->nullable();
        $table->integer('author_id');
        $table->string('guest_author_name', 255)->nullable();
        $table->string('guest_author_email', 255)->nullable();
        $table->string('status', 20)->default('draft');
        $table->string('visibility', 20)->default('public');
        $table->boolean('is_featured')->default(false);
        $table->boolean('is_pinned')->default(false);
        $table->string('password', 255)->nullable();
        $table->timestamp('published_at')->nullable();
        $table->timestamp('scheduled_at')->nullable();
        $table->string('seo_title', 255)->nullable();
        $table->text('seo_description')->nullable();
        $table->string('seo_keywords', 500)->nullable();
        $table->string('og_title', 255)->nullable();
        $table->text('og_description')->nullable();
        $table->string('og_image', 500)->nullable();
        $table->string('canonical_url', 500)->nullable();
        $table->integer('reading_time')->default(0);
        $table->integer('view_count')->default(0);
        $table->integer('like_count')->default(0);
        $table->integer('comment_count')->default(0);
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createArticle(array $data): ?Article
    {
        if (!isset($data['slug']) && isset($data['title'])) {
            $data['slug'] = self::generateSlug($data['title']);
        }

        if (!isset($data['excerpt']) && isset($data['content'])) {
            $data['excerpt'] = self::generateExcerpt($data['content']);
        }

        if (!isset($data['reading_time']) && isset($data['content'])) {
            $data['reading_time'] = self::calculateReadingTime($data['content']);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_DRAFT;
        }

        if ($data['status'] === self::STATUS_PUBLISHED && !isset($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        return static::create($data);
    }

    public function schedulePublish(string $datetime): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_SCHEDULED,
            'scheduled_at' => $datetime,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function publish(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function unpublish(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_DRAFT,
            'published_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function archive(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_ARCHIVED,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function trash(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_TRASH,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function restore(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_DRAFT,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function saveRevision(?string $content = null, ?string $note = null): bool
    {
        $content = $content ?? $this->content;

        return ArticleRevision::create([
            'article_id' => $this->id,
            'content' => $content,
            'content_html' => $this->content_html,
            'title' => $this->title,
            'author_id' => $this->author_id,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getRevisions(int $limit = 20): array
    {
        return ArticleRevision::where('article_id', $this->id)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function restoreRevision(int $revisionId): bool
    {
        $revision = ArticleRevision::find($revisionId);
        if (!$revision || $revision->article_id != $this->id) {
            return false;
        }

        $revisionData = is_array($revision) ? $revision : $revision->toArray();

        return static::where('id', $this->id)->updateRows([
            'content' => $revisionData['content'],
            'content_html' => $revisionData['content_html'],
            'title' => $revisionData['title'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function setCoverImage($file): ?array
    {
        return $this->attachFile($file, 'cover');
    }

    public function getCoverImageUrl(): ?string
    {
        return $this->getFileUrl('cover');
    }

    public function incrementViews(): bool
    {
        return static::rawQuery(
            "UPDATE " . static::$table . " SET view_count = view_count + 1, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $this->id]
        );
    }

    public function incrementComments(): bool
    {
        return static::rawQuery(
            "UPDATE " . static::$table . " SET comment_count = comment_count + 1, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $this->id]
        );
    }

    public function decrementComments(): bool
    {
        return static::rawQuery(
            "UPDATE " . static::$table . " SET comment_count = GREATEST(0, comment_count - 1), updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $this->id]
        );
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function shouldPublishNow(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->scheduled_at
            && strtotime($this->scheduled_at) <= time();
    }

    public function getSeoTitle(): string
    {
        return $this->seo_title ?: $this->title;
    }

    public function getSeoDescription(): string
    {
        return $this->seo_description ?: $this->excerpt ?: self::generateExcerpt($this->content);
    }

    public function getOgTitle(): string
    {
        return $this->og_title ?: $this->getSeoTitle();
    }

    public function getOgDescription(): string
    {
        return $this->og_description ?: $this->getSeoDescription();
    }

    public function getOgImageUrl(): ?string
    {
        return $this->og_image ?: $this->getCoverImageUrl();
    }

    public function getAuthorName(): string
    {
        if ($this->guest_author_name) {
            return $this->guest_author_name;
        }

        $result = static::rawQuery(
            "SELECT name FROM users WHERE id = ?",
            [$this->author_id]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return $row['name'];
            }
        }

        return 'Unknown Author';
    }

    public static function published()
    {
        return static::where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '<=', date('Y-m-d H:i:s'));
    }

    public static function drafts()
    {
        return static::where('status', self::STATUS_DRAFT);
    }

    public static function scheduled()
    {
        return static::where('status', self::STATUS_SCHEDULED);
    }

    public static function featured(int $limit = 5): array
    {
        return static::where('status', self::STATUS_PUBLISHED)
            ->where('is_featured', true)
            ->orderBy('published_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public static function popular(int $limit = 10, int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return static::where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '>=', $since)
            ->orderBy('view_count', 'DESC')
            ->limit($limit)
            ->get();
    }

    public static function recent(int $limit = 10): array
    {
        return static::where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('published_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public static function publishScheduled(): int
    {
        $now = date('Y-m-d H:i:s');
        $result = static::rawQuery(
            "UPDATE " . static::$table . " SET status = ?, published_at = ?, updated_at = ? WHERE status = ? AND scheduled_at <= ?",
            [self::STATUS_PUBLISHED, $now, $now, self::STATUS_SCHEDULED, $now]
        );

        return $result ? (int) $result->rowCount() : 0;
    }

    public static function byAuthor(int $authorId)
    {
        return static::where('author_id', $authorId)
            ->where('status', self::STATUS_PUBLISHED)
            ->orderBy('published_at', 'DESC');
    }

    public static function getArchive(?int $year = null, ?int $month = null): array
    {
        $sql = "SELECT YEAR(published_at) as year, MONTH(published_at) as month, COUNT(*) as count
                FROM " . static::$table . " WHERE status = ? GROUP BY YEAR(published_at), MONTH(published_at)
                ORDER BY year DESC, month DESC";

        $result = static::rawQuery($sql, [self::STATUS_PUBLISHED]);
        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }
        return [];
    }

    protected static function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'article-' . time();
    }

    protected static function generateExcerpt(string $content, int $length = 160): string
    {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    protected static function calculateReadingTime(string $content, int $wpm = 200): int
    {
        $wordCount = str_word_count(strip_tags($content));
        return max(1, ceil($wordCount / $wpm));
    }
}

class ArticleRevision extends Model
{
    protected static $table = 'article_revisions';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'article_id' => 'integer',
        'content' => 'text',
        'content_html' => 'text',
        'title' => 'string',
        'author_id' => 'integer',
        'note' => 'text',
        'created_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('article_id');
        $table->text('content');
        $table->text('content_html')->nullable();
        $table->string('title', 500);
        $table->integer('author_id');
        $table->text('note')->nullable();
        $table->timestamp('created_at');
    }
}
