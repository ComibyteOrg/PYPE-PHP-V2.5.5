<?php

namespace Framework\Commerce;

use Framework\Model\Model;

/**
 * Shopping Cart Service
 * Supports session-based carts for guests and database carts for authenticated users.
 * Automatically merges session cart into database cart on login.
 *
 * Usage:
 * Cart::add($productId, 2);
 * Cart::add($productId, 1, ['color' => 'red', 'size' => 'XL']);
 * Cart::update($cartItemId, 5);
 * Cart::remove($cartItemId);
 * Cart::clear();
 * Cart::getItems();
 * Cart::total();
 * Cart::count();
 */
class Cart
{
    protected static $table = 'cart_items';
    protected static $sessionKey = 'pype_cart';
    protected static $cartItems = [];
    protected static $initialized = false;
    protected static $userId = null;
    protected static $sessionId = null;

    public static function init(?int $userId = null, ?string $sessionId = null): void
    {
        self::$userId = $userId;
        self::$sessionId = $sessionId ?: session_id();
        self::$cartItems = [];

        if ($userId) {
            self::loadDatabaseCart();
            self::mergeSessionCart();
        } else {
            self::loadSessionCart();
        }

        self::$initialized = true;
    }

    public static function add(int $productId, int $quantity = 1, array $options = []): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        $product = Product::find($productId);
        if (!$product) {
            return false;
        }

        if (!Inventory::isAvailable($productId, $quantity)) {
            return false;
        }

        $existing = self::findByProduct($productId, $options);
        if ($existing) {
            $newQuantity = $existing['quantity'] + $quantity;
            return self::update($existing['id'], $newQuantity);
        }

        $price = self::getProductPrice($product, $options);

        if (self::$userId) {
            return self::addToDatabase($productId, $quantity, $price, $options);
        }

        return self::addToSession($productId, $quantity, $price, $options);
    }

    public static function update(int $cartItemId, int $quantity): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        if ($quantity <= 0) {
            return self::remove($cartItemId);
        }

        $item = self::findById($cartItemId);
        if (!$item) {
            return false;
        }

        if (!Inventory::isAvailable($item['product_id'], $quantity)) {
            return false;
        }

        if (self::$userId) {
            return self::updateDatabaseItem($cartItemId, $quantity);
        }

        return self::updateSessionItem($cartItemId, $quantity);
    }

    public static function remove(int $cartItemId): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        if (self::$userId) {
            return self::removeDatabaseItem($cartItemId);
        }

        return self::removeSessionItem($cartItemId);
    }

    public static function clear(): void
    {
        if (!self::$initialized) {
            self::init();
        }

        if (self::$userId) {
            self::clearDatabaseCart();
        }

        self::clearSessionCart();
        self::$cartItems = [];
    }

    public static function getItems(): array
    {
        if (!self::$initialized) {
            self::init();
        }

        return self::$cartItems;
    }

    public static function getItem(int $cartItemId): ?array
    {
        if (!self::$initialized) {
            self::init();
        }

        foreach (self::$cartItems as $item) {
            if ($item['id'] == $cartItemId) {
                return $item;
            }
        }
        return null;
    }

    public static function total(): float
    {
        if (!self::$initialized) {
            self::init();
        }

        $subtotal = 0;
        foreach (self::$cartItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        return round($subtotal, 2);
    }

    public static function subtotal(): float
    {
        return self::total();
    }

    public static function count(): int
    {
        if (!self::$initialized) {
            self::init();
        }

        $count = 0;
        foreach (self::$cartItems as $item) {
            $count += $item['quantity'];
        }

        return $count;
    }

    public static function uniqueItems(): int
    {
        if (!self::$initialized) {
            self::init();
        }

        return count(self::$cartItems);
    }

    public static function isEmpty(): bool
    {
        return self::count() === 0;
    }

    public static function isNotEmpty(): bool
    {
        return !self::isEmpty();
    }

    public static function hasProduct(int $productId): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        foreach (self::$cartItems as $item) {
            if ($item['product_id'] == $productId) {
                return true;
            }
        }
        return false;
    }

    public static function weight(): float
    {
        if (!self::$initialized) {
            self::init();
        }

        $totalWeight = 0;
        foreach (self::$cartItems as $item) {
            $product = Product::find($item['product_id']);
            if ($product && $product->weight) {
                $totalWeight += $product->weight * $item['quantity'];
            }
        }

        return round($totalWeight, 2);
    }

    protected static function findById(int $cartItemId): ?array
    {
        foreach (self::$cartItems as $item) {
            if ($item['id'] == $cartItemId) {
                return $item;
            }
        }
        return null;
    }

    protected static function findByProduct(int $productId, array $options = []): ?array
    {
        foreach (self::$cartItems as $item) {
            if ($item['product_id'] == $productId) {
                $itemOptions = $item['options'] ?? [];
                if (json_encode($itemOptions) === json_encode($options)) {
                    return $item;
                }
            }
        }
        return null;
    }

    protected static function getProductPrice($product, array $options = []): float
    {
        $price = $product->price;

        if (isset($options['variant_id'])) {
            $variant = ProductVariant::find($options['variant_id']);
            if ($variant) {
                $price = $variant->price ?: $product->price;
            }
        }

        return (float) $price;
    }

    protected static function loadDatabaseCart(): void
    {
        $model = new Model();
        $result = $model->rawQuery(
            "SELECT * FROM " . self::$table . " WHERE user_id = ? ORDER BY created_at ASC",
            [self::$userId]
        );

        if ($result) {
            while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                $row['options'] = $row['options'] ? json_decode($row['options'], true) : [];
                self::$cartItems[] = $row;
            }
        }
    }

    protected static function loadSessionCart(): void
    {
        if (isset($_SESSION[self::$sessionKey])) {
            self::$cartItems = $_SESSION[self::$sessionKey];
        }
    }

    protected static function mergeSessionCart(): void
    {
        if (!isset($_SESSION[self::$sessionKey])) {
            return;
        }

        foreach ($_SESSION[self::$sessionKey] as $sessionItem) {
            $existing = self::findByProduct($sessionItem['product_id'], $sessionItem['options'] ?? []);
            if ($existing) {
                self::updateDatabaseItem($existing['id'], $existing['quantity'] + $sessionItem['quantity']);
            } else {
                self::addToDatabase(
                    $sessionItem['product_id'],
                    $sessionItem['quantity'],
                    $sessionItem['price'],
                    $sessionItem['options'] ?? []
                );
            }
        }

        self::clearSessionCart();
    }

    protected static function addToDatabase(int $productId, int $quantity, float $price, array $options): bool
    {
        $model = new Model();
        $result = $model->rawQuery(
            "INSERT INTO " . self::$table . " (user_id, session_id, product_id, quantity, price, options, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [self::$userId, self::$sessionId, $productId, $quantity, $price, json_encode($options), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        if ($result) {
            $lastId = (int) $model->connection->lastInsertId();
            self::$cartItems[] = [
                'id' => $lastId,
                'user_id' => self::$userId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'options' => $options,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            return true;
        }

        return false;
    }

    protected static function addToSession(int $productId, int $quantity, float $price, array $options): bool
    {
        $cartItemId = 'session_' . uniqid();
        self::$cartItems[] = [
            'id' => $cartItemId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price,
            'options' => $options,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $_SESSION[self::$sessionKey] = self::$cartItems;
        return true;
    }

    protected static function updateDatabaseItem(int $cartItemId, int $quantity): bool
    {
        $model = new Model();
        $result = $model->rawQuery(
            "UPDATE " . self::$table . " SET quantity = ?, updated_at = ? WHERE id = ? AND user_id = ?",
            [$quantity, date('Y-m-d H:i:s'), $cartItemId, self::$userId]
        );

        if ($result) {
            foreach (self::$cartItems as &$item) {
                if ($item['id'] == $cartItemId) {
                    $item['quantity'] = $quantity;
                    break;
                }
            }
            return true;
        }

        return false;
    }

    protected static function updateSessionItem(int $cartItemId, int $quantity): bool
    {
        foreach (self::$cartItems as &$item) {
            if ($item['id'] == $cartItemId) {
                $item['quantity'] = $quantity;
                $item['updated_at'] = date('Y-m-d H:i:s');
                $_SESSION[self::$sessionKey] = self::$cartItems;
                return true;
            }
        }
        return false;
    }

    protected static function removeDatabaseItem(int $cartItemId): bool
    {
        $model = new Model();
        $result = $model->rawQuery(
            "DELETE FROM " . self::$table . " WHERE id = ? AND user_id = ?",
            [$cartItemId, self::$userId]
        );

        if ($result) {
            self::$cartItems = array_filter(self::$cartItems, fn($i) => $i['id'] != $cartItemId);
            return true;
        }

        return false;
    }

    protected static function removeSessionItem(int $cartItemId): bool
    {
        $before = count(self::$cartItems);
        self::$cartItems = array_filter(self::$cartItems, fn($i) => $i['id'] != $cartItemId);

        if (count(self::$cartItems) < $before) {
            $_SESSION[self::$sessionKey] = self::$cartItems;
            return true;
        }

        return false;
    }

    protected static function clearDatabaseCart(): void
    {
        $model = new Model();
        $model->rawQuery(
            "DELETE FROM " . self::$table . " WHERE user_id = ?",
            [self::$userId]
        );
    }

    protected static function clearSessionCart(): void
    {
        unset($_SESSION[self::$sessionKey]);
    }
}
