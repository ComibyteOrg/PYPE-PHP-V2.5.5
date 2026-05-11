<?php

namespace Framework\Commerce;

use Framework\Model\Model;

/**
 * Inventory Service
 * Manages stock levels, low-stock alerts, and backorder support.
 *
 * Usage:
 * Inventory::setStock($productId, 100);
 * Inventory::reduceStock($productId, 5);
 * Inventory::addStock($productId, 20);
 * Inventory::isAvailable($productId, 3);
 * Inventory::getLowStockProducts();
 * Inventory::enableBackorder($productId);
 */
class Inventory
{
    protected static $table = 'inventory';

    public static function setStock(int $productId, int $quantity, ?int $variantId = null): bool
    {
        $existing = self::getRecord($productId, $variantId);

        if ($existing) {
            return self::updateStock($existing['id'], $quantity);
        }

        return self::createStock($productId, $quantity, $variantId);
    }

    public static function reduceStock(int $productId, int $quantity, ?int $variantId = null): bool
    {
        $record = self::getRecord($productId, $variantId);
        if (!$record) {
            return false;
        }

        $newStock = max(0, $record['stock'] - $quantity);
        return self::updateStock($record['id'], $newStock);
    }

    public static function addStock(int $productId, int $quantity, ?int $variantId = null): bool
    {
        $record = self::getRecord($productId, $variantId);
        if (!$record) {
            return self::createStock($productId, $quantity, $variantId);
        }

        return self::updateStock($record['id'], $record['stock'] + $quantity);
    }

    public static function getStock(int $productId, ?int $variantId = null): int
    {
        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            return $variant ? (int) $variant->stock : 0;
        }

        $record = self::getRecord($productId);
        return $record ? (int) $record['stock'] : 0;
    }

    public static function isInStock(int $productId, ?int $variantId = null): bool
    {
        $record = self::getRecord($productId, $variantId);
        if (!$record) {
            return false;
        }

        if ($record['allow_backorder']) {
            return true;
        }

        return $record['stock'] > 0;
    }

    public static function isAvailable(int $productId, int $quantity, ?int $variantId = null): bool
    {
        $record = self::getRecord($productId, $variantId);
        if (!$record) {
            return false;
        }

        if ($record['allow_backorder']) {
            return true;
        }

        return $record['stock'] >= $quantity;
    }

    public static function isLowStock(int $productId, ?int $variantId = null): bool
    {
        $record = self::getRecord($productId, $variantId);
        if (!$record) {
            return false;
        }

        return $record['stock'] <= $record['low_stock_threshold'];
    }

    public static function enableBackorder(int $productId, ?int $variantId = null): bool
    {
        $record = self::getRecord($productId, $variantId);
        if (!$record) {
            return self::createStock($productId, 0, $variantId, true);
        }

        $model = new Model();
        return (bool) $model->rawQuery(
            "UPDATE " . static::$table . " SET allow_backorder = 1, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $record['id']]
        );
    }

    public static function disableBackorder(int $productId, ?int $variantId = null): bool
    {
        $record = self::getRecord($productId, $variantId);
        if (!$record) {
            return false;
        }

        $model = new Model();
        return (bool) $model->rawQuery(
            "UPDATE " . static::$table . " SET allow_backorder = 0, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $record['id']]
        );
    }

    public static function setLowStockThreshold(int $productId, int $threshold, ?int $variantId = null): bool
    {
        $record = self::getRecord($productId, $variantId);
        if (!$record) {
            return self::createStock($productId, 0, $variantId, false, $threshold);
        }

        $model = new Model();
        return (bool) $model->rawQuery(
            "UPDATE " . static::$table . " SET low_stock_threshold = ?, updated_at = ? WHERE id = ?",
            [$threshold, date('Y-m-d H:i:s'), $record['id']]
        );
    }

    public static function getLowStockProducts(int $threshold = null): array
    {
        $model = new Model();
        $sql = "SELECT i.*, p.name as product_name, p.sku as product_sku
                FROM " . static::$table . " i
                JOIN products p ON i.product_id = p.id
                WHERE i.stock <= i.low_stock_threshold";

        if ($threshold !== null) {
            $sql .= " AND i.stock <= ?";
            $result = $model->rawQuery($sql . " ORDER BY i.stock ASC", [$threshold]);
        } else {
            $sql .= " ORDER BY i.stock ASC";
            $result = $model->rawQuery($sql);
        }

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public static function getOutOfStockProducts(): array
    {
        $model = new Model();
        $result = $model->rawQuery(
            "SELECT i.*, p.name as product_name, p.sku as product_sku
             FROM " . static::$table . " i
             JOIN products p ON i.product_id = p.id
             WHERE i.stock = 0 AND i.allow_backorder = 0
             ORDER BY p.name ASC"
        );

        if ($result) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public static function getInventoryValue(): float
    {
        $model = new Model();
        $result = $model->rawQuery(
            "SELECT SUM(i.stock * p.cost_price) as value
             FROM " . static::$table . " i
             JOIN products p ON i.product_id = p.id"
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (float) ($row['value'] ?? 0);
        }

        return 0;
    }

    protected static function getRecord(int $productId, ?int $variantId = null): ?array
    {
        $model = new Model();
        $result = $model->rawQuery(
            "SELECT * FROM " . static::$table . " WHERE product_id = ? AND variant_id " . ($variantId ? '= ?' : 'IS NULL'),
            $variantId ? [$productId, $variantId] : [$productId]
        );

        if ($result) {
            return $result->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        return null;
    }

    protected static function createStock(int $productId, int $quantity, ?int $variantId = null, bool $allowBackorder = false, int $lowStockThreshold = 5): bool
    {
        $model = new Model();
        return (bool) $model->rawQuery(
            "INSERT INTO " . static::$table . " (product_id, variant_id, stock, low_stock_threshold, allow_backorder, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$productId, $variantId, $quantity, $lowStockThreshold, $allowBackorder ? 1 : 0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
    }

    protected static function updateStock(int $recordId, int $quantity): bool
    {
        $model = new Model();
        return (bool) $model->rawQuery(
            "UPDATE " . static::$table . " SET stock = ?, updated_at = ? WHERE id = ?",
            [$quantity, date('Y-m-d H:i:s'), $recordId]
        );
    }
}
