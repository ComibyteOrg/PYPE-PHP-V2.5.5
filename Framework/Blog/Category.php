<?php

namespace Framework\Blog;

use Framework\Model\Model;

/**
 * Blog Category Model
 * Supports hierarchical categories (parent/child).
 *
 * Usage:
 * $cat = Category::createCategory(['name' => 'Tech', 'slug' => 'tech']);
 * $child = Category::createCategory(['name' => 'AI', 'slug' => 'ai', 'parent_id' => $cat->id]);
 * $tree = Category::getTree();
 */
class Category extends Model
{
    protected static $table = 'blog_categories';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'description' => 'text',
        'parent_id' => 'integer',
        'article_count' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('name', 255);
        $table->string('slug', 255)->unique();
        $table->text('description')->nullable();
        $table->integer('parent_id')->default(0);
        $table->integer('article_count')->default(0);
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createCategory(array $data): ?Category
    {
        if (!isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = self::generateSlug($data['name']);
        }
        if (!isset($data['parent_id'])) {
            $data['parent_id'] = 0;
        }
        $data['article_count'] = 0;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return static::create($data);
    }

    public function getChildren(): array
    {
        return static::where('parent_id', $this->id)->orderBy('name', 'ASC')->get();
    }

    public function getParent(): ?Category
    {
        if (!$this->parent_id) {
            return null;
        }
        return static::find($this->parent_id);
    }

    public function getArticles(int $limit = 10): array
    {
        $sql = "SELECT a.* FROM articles a
                JOIN article_categories ac ON a.id = ac.article_id
                WHERE ac.category_id = ? AND a.status = ?
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

    public static function getTree(int $parentId = 0, int $depth = 0): array
    {
        $categories = static::where('parent_id', $parentId)->orderBy('name', 'ASC')->get();
        $tree = [];

        foreach ($categories as $cat) {
            $catData = is_array($cat) ? $cat : $cat->toArray();
            $catData['depth'] = $depth;
            $catData['children'] = static::getTree($catData['id'], $depth + 1);
            $tree[] = $catData;
        }

        return $tree;
    }

    public static function allCategories(): array
    {
        return static::orderBy('name', 'ASC')->get();
    }

    protected static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
