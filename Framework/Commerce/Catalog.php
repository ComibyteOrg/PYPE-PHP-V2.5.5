<?php

namespace Framework\Commerce;

use Framework\Model\Model;

class ProductVariant extends Model
{
    protected static $table = 'product_variants';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'product_id' => 'integer',
        'sku' => 'string',
        'name' => 'string',
        'price' => 'double',
        'compare_price' => 'double',
        'cost_price' => 'double',
        'weight' => 'double',
        'attributes' => 'json',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('product_id');
        $table->string('sku', 100)->nullable();
        $table->string('name', 255)->nullable();
        $table->double('price')->default(0);
        $table->double('compare_price')->nullable();
        $table->double('cost_price')->nullable();
        $table->double('weight')->default(0);
        $table->json('attributes')->nullable();
        $table->integer('stock')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public function getAttributes(): array
    {
        if ($this->attributes) {
            return json_decode($this->attributes, true) ?? [];
        }
        return [];
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function getEffectivePrice(): float
    {
        return $this->price ?: ($this->product->price ?? 0);
    }

    public function hasDiscount(): bool
    {
        return $this->compare_price && $this->compare_price > $this->price;
    }

    public function getDiscountPercentage(): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }
        return round((($this->compare_price - $this->price) / $this->compare_price) * 100, 1);
    }
}

class ProductCategory extends Model
{
    protected static $table = 'product_categories';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'description' => 'text',
        'parent_id' => 'integer',
        'image' => 'string',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('name', 255);
        $table->string('slug', 255);
        $table->text('description')->nullable();
        $table->integer('parent_id')->nullable();
        $table->string('image', 500)->nullable();
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    protected static $pivotTable = 'product_category_pivot';

    public static function getCategoriesForProduct(int $productId): array
    {
        $result = static::rawQuery(
            "SELECT c.* FROM " . static::$table . " c
             JOIN " . static::$pivotTable . " pcp ON c.id = pcp.category_id
             WHERE pcp.product_id = ?
             ORDER BY c.sort_order ASC",
            [$productId]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }
        return [];
    }

    public static function getProductIdsByCategory(int $categoryId): array
    {
        $result = static::rawQuery(
            "SELECT product_id FROM " . static::$pivotTable . " WHERE category_id = ?",
            [$categoryId]
        );

        if ($result) {
            return array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'product_id');
        }
        return [];
    }

    public static function addProductToCategory(int $productId, int $categoryId): bool
    {
        $existing = static::rawQuery(
            "SELECT id FROM " . static::$pivotTable . " WHERE product_id = ? AND category_id = ?",
            [$productId, $categoryId]
        );

        if ($existing && $existing->fetch(\PDO::FETCH_ASSOC)) {
            return false;
        }

        return (bool) static::rawQuery(
            "INSERT INTO " . static::$pivotTable . " (product_id, category_id) VALUES (?, ?)",
            [$productId, $categoryId]
        );
    }

    public static function removeProductFromCategory(int $productId, int $categoryId): bool
    {
        return (bool) static::rawQuery(
            "DELETE FROM " . static::$pivotTable . " WHERE product_id = ? AND category_id = ?",
            [$productId, $categoryId]
        );
    }

    public static function active()
    {
        return static::where('is_active', true)->orderBy('sort_order', 'ASC');
    }

    public static function tree(): array
    {
        $categories = static::active()->get();
        return self::buildTree($categories);
    }

    protected static function buildTree(array $categories, int $parentId = 0): array
    {
        $tree = [];
        foreach ($categories as $category) {
            if ((int) $category['parent_id'] === $parentId) {
                $children = self::buildTree($categories, (int) $category['id']);
                if (!empty($children)) {
                    $category['children'] = $children;
                }
                $tree[] = $category;
            }
        }
        return $tree;
    }
}

class ProductTag extends Model
{
    protected static $table = 'product_tags';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('name', 100)->unique();
        $table->string('slug', 100)->unique();
    }

    protected static $pivotTable = 'product_tag_pivot';

    public static function getTagsForProduct(int $productId): array
    {
        $result = static::rawQuery(
            "SELECT t.* FROM " . static::$table . " t
             JOIN " . static::$pivotTable . " ptp ON t.id = ptp.tag_id
             WHERE ptp.product_id = ?",
            [$productId]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }
        return [];
    }

    public static function addTagToProduct(int $productId, string $tagName): bool
    {
        $slug = strtolower(trim($tagName));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        $existingTag = static::where('slug', $slug)->getFirst();
        if (!$existingTag) {
            $existingTag = static::create([
                'name' => $tagName,
                'slug' => $slug,
            ]);
        }

        if (!$existingTag) {
            return false;
        }

        $tagId = is_array($existingTag) ? $existingTag['id'] : $existingTag->id;

        $existing = static::rawQuery(
            "SELECT id FROM " . static::$pivotTable . " WHERE product_id = ? AND tag_id = ?",
            [$productId, $tagId]
        );

        if ($existing && $existing->fetch(\PDO::FETCH_ASSOC)) {
            return false;
        }

        return (bool) static::rawQuery(
            "INSERT INTO " . static::$pivotTable . " (product_id, tag_id) VALUES (?, ?)",
            [$productId, $tagId]
        );
    }
}
