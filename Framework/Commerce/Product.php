<?php

namespace Framework\Commerce;

use Framework\Model\Model;

/**
 * Product Model
 * Supports variants, SKUs, attributes, categories, and tags.
 * Includes HasFiles for product images.
 *
 * Usage:
 * $product = Product::create([...]);
 * $product->attachFile($_FILES['image'], 'images');
 * Product::active()->get();
 * Product::byCategory($categoryId)->get();
 * Product::search('keyword')->get();
 */
class Product extends Model
{
    use \Framework\Model\HasFiles;

    protected static $table = 'products';
    protected static $primaryKey = 'id';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_OUT_OF_STOCK = 'out_of_stock';

    public const TYPE_SIMPLE = 'simple';
    public const TYPE_VARIABLE = 'variable';
    public const TYPE_DIGITAL = 'digital';
    public const TYPE_SERVICE = 'service';

    public static $fields = [
        'id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'description' => 'text',
        'short_description' => 'text',
        'type' => 'string',
        'status' => 'string',
        'sku' => 'string',
        'price' => 'double',
        'compare_price' => 'double',
        'cost_price' => 'double',
        'weight' => 'double',
        'dimensions' => 'json',
        'attributes' => 'json',
        'meta_title' => 'string',
        'meta_description' => 'text',
        'meta_keywords' => 'string',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('name', 255);
        $table->string('slug', 255);
        $table->text('description')->nullable();
        $table->text('short_description')->nullable();
        $table->string('type', 20)->default('simple');
        $table->string('status', 20)->default('draft');
        $table->string('sku', 100)->nullable();
        $table->double('price')->default(0);
        $table->double('compare_price')->nullable();
        $table->double('cost_price')->nullable();
        $table->double('weight')->default(0);
        $table->json('dimensions')->nullable();
        $table->json('attributes')->nullable();
        $table->string('meta_title', 255)->nullable();
        $table->text('meta_description')->nullable();
        $table->string('meta_keywords', 500)->nullable();
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createProduct(array $data): ?Product
    {
        if (!isset($data['slug'])) {
            $data['slug'] = self::generateSlug($data['name'] ?? '');
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return static::create($data);
    }

    public function getImages(): array
    {
        return $this->getFileUrls('images');
    }

    public function getMainImage(): ?string
    {
        $images = $this->getImages();
        return $images[0] ?? null;
    }

    public function getCategories(): array
    {
        return ProductCategory::getCategoriesForProduct($this->id);
    }

    public function getTags(): array
    {
        return ProductTag::getTagsForProduct($this->id);
    }

    public function getVariants(): array
    {
        return ProductVariant::where('product_id', $this->id)->get();
    }

    public function getAttributes(): array
    {
        if ($this->attributes) {
            return json_decode($this->attributes, true) ?? [];
        }
        return [];
    }

    public function getDimensions(): array
    {
        if ($this->dimensions) {
            return json_decode($this->dimensions, true) ?? [];
        }
        return ['length' => 0, 'width' => 0, 'height' => 0];
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

    public function getStockQuantity(): int
    {
        return Inventory::getStock($this->id);
    }

    public function isInStock(): bool
    {
        return Inventory::isInStock($this->id);
    }

    public function isLowStock(): bool
    {
        return Inventory::isLowStock($this->id);
    }

    public function getEffectivePrice(): float
    {
        return (float) $this->price;
    }

    public function addCategory(int $categoryId): bool
    {
        return ProductCategory::addProductToCategory($this->id, $categoryId);
    }

    public function removeCategory(int $categoryId): bool
    {
        return ProductCategory::removeProductFromCategory($this->id, $categoryId);
    }

    public function addTag(string $tagName): bool
    {
        return ProductTag::addTagToProduct($this->id, $tagName);
    }

    public static function active()
    {
        return static::where('status', self::STATUS_ACTIVE);
    }

    public static function byCategory(int $categoryId)
    {
        $productIds = ProductCategory::getProductIdsByCategory($categoryId);
        if (empty($productIds)) {
            return static::where('id', 0);
        }
        return static::whereIn('id', $productIds);
    }

    public static function search(string $query)
    {
        return static::where('name', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->orWhere('sku', 'LIKE', "%{$query}%");
    }

    public static function popular(int $limit = 10): array
    {
        $orderItems = 'order_items';
        $result = static::rawQuery(
            "SELECT p.*, COUNT(oi.id) as sales_count
             FROM " . static::$table . " p
             JOIN {$orderItems} oi ON p.id = oi.product_id
             WHERE p.status = ?
             GROUP BY p.id
             ORDER BY sales_count DESC
             LIMIT ?",
            [self::STATUS_ACTIVE, $limit]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public static function onSale(int $limit = 10): array
    {
        $result = static::rawQuery(
            "SELECT * FROM " . static::$table . " WHERE status = ? AND compare_price > price AND compare_price > 0 ORDER BY created_at DESC LIMIT ?",
            [self::STATUS_ACTIVE, $limit]
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    protected static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'product-' . time();
    }
}
