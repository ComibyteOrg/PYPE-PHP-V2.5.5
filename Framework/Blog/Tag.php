<?php

namespace Framework\Blog;

use Framework\Model\Model;

/**
 * Blog Tag Model
 * Flat tagging system for articles.
 *
 * Usage:
 * $tag = Tag::createTag(['name' => 'PHP']);
 * $articles = $tag->getArticles();
 * $cloud = Tag::cloud();
 */
class Tag extends Model
{
    protected static $table = 'blog_tags';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'article_count' => 'integer',
        'created_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('name', 100)->unique();
        $table->string('slug', 100)->unique();
        $table->integer('article_count')->default(0);
        $table->timestamp('created_at');
    }

    public static function createTag(array $data): ?Tag
    {
        if (!isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = self::generateSlug($data['name']);
        }
        $data['article_count'] = 0;
        $data['created_at'] = date('Y-m-d H:i:s');

        $existing = static::where('slug', $data['slug'])->first();
        if ($existing) {
            return is_object($existing) ? $existing : new static($existing);
        }

        return static::create($data);
    }

    public static function firstOrCreateByName(string $name): Tag
    {
        $tag = static::where('name', $name)->first();
        if ($tag) {
            return is_object($tag) ? $tag : new static($tag);
        }
        return static::createTag(['name' => $name]);
    }

    public function getArticles(int $limit = 10): array
    {
        $sql = "SELECT a.* FROM articles a
                JOIN article_tags at ON a.id = at.article_id
                WHERE at.tag_id = ? AND a.status = ?
                ORDER BY a.published_at DESC LIMIT ?";
        $result = static::rawQuery($sql, [$this->id, Article::STATUS_PUBLISHED, $limit]);
        return $result ? $result->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    public function incrementArticles(): bool
    {
        return static::rawQuery(
            "UPDATE " . static::$table . " SET article_count = article_count + 1 WHERE id = ?",
            [$this->id]
        );
    }

    public static function cloud(int $limit = 50): array
    {
        return static::orderBy('article_count', 'DESC')
            ->limit($limit)
            ->get();
    }

    public static function popular(int $limit = 10): array
    {
        return static::orderBy('article_count', 'DESC')
            ->limit($limit)
            ->get();
    }

    protected static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
