<?php

namespace Framework\Commerce;

use Framework\Model\Model;

/**
 * Coupon Model
 * Supports percentage, fixed, BOGO, and user-specific discount codes.
 *
 * Usage:
 * $coupon = Coupon::createCode('SAVE20', 'percentage', 20);
 * $coupon->setUsageLimit(100);
 * $coupon->setExpiry('2026-12-31');
 * $isValid = Coupon::validate('SAVE20', $userId, $cartTotal);
 */
class Coupon extends Model
{
    protected static $table = 'coupons';
    protected static $primaryKey = 'id';

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_BOGO = 'bogo';
    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public static $fields = [
        'id' => 'integer',
        'code' => 'string',
        'type' => 'string',
        'value' => 'double',
        'min_purchase' => 'double',
        'max_discount' => 'double',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'usage_limit_per_user' => 'integer',
        'applicable_products' => 'text',
        'excluded_products' => 'text',
        'applicable_categories' => 'text',
        'starts_at' => 'timestamp',
        'expires_at' => 'timestamp',
        'is_active' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('code', 50)->unique();
        $table->string('type', 20)->default('percentage');
        $table->double('value')->default(0);
        $table->double('min_purchase')->default(0);
        $table->double('max_discount')->nullable();
        $table->integer('usage_limit')->nullable();
        $table->integer('usage_count')->default(0);
        $table->integer('usage_limit_per_user')->default(1);
        $table->text('applicable_products')->nullable();
        $table->text('excluded_products')->nullable();
        $table->text('applicable_categories')->nullable();
        $table->timestamp('starts_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createCode(string $code, string $type, float $value, array $options = []): ?Coupon
    {
        $data = [
            'code' => strtoupper($code),
            'type' => $type,
            'value' => $value,
            'is_active' => true,
            'usage_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($options['min_purchase'])) {
            $data['min_purchase'] = $options['min_purchase'];
        }
        if (isset($options['max_discount'])) {
            $data['max_discount'] = $options['max_discount'];
        }
        if (isset($options['usage_limit'])) {
            $data['usage_limit'] = $options['usage_limit'];
        }
        if (isset($options['usage_limit_per_user'])) {
            $data['usage_limit_per_user'] = $options['usage_limit_per_user'];
        }
        if (isset($options['expires_at'])) {
            $data['expires_at'] = $options['expires_at'];
        }
        if (isset($options['starts_at'])) {
            $data['starts_at'] = $options['starts_at'];
        }
        if (isset($options['applicable_products'])) {
            $data['applicable_products'] = json_encode($options['applicable_products']);
        }
        if (isset($options['excluded_products'])) {
            $data['excluded_products'] = json_encode($options['excluded_products']);
        }
        if (isset($options['applicable_categories'])) {
            $data['applicable_categories'] = json_encode($options['applicable_categories']);
        }

        return static::create($data);
    }

    public static function validate(string $code, ?int $userId = null, float $cartTotal = 0, array $cartItems = []): array
    {
        $coupon = static::where('code', strtoupper($code))->getFirst();

        if (!$coupon) {
            return ['valid' => false, 'error' => 'Invalid coupon code'];
        }

        $coupon = is_array($coupon) ? $coupon : (array) $coupon;

        if (!$coupon['is_active']) {
            return ['valid' => false, 'error' => 'Coupon is not active'];
        }

        $now = date('Y-m-d H:i:s');
        if ($coupon['starts_at'] && $now < $coupon['starts_at']) {
            return ['valid' => false, 'error' => 'Coupon has not started yet'];
        }

        if ($coupon['expires_at'] && $now > $coupon['expires_at']) {
            return ['valid' => false, 'error' => 'Coupon has expired'];
        }

        if ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit']) {
            return ['valid' => false, 'error' => 'Coupon usage limit reached'];
        }

        if ($coupon['min_purchase'] && $cartTotal < $coupon['min_purchase']) {
            return ['valid' => false, 'error' => 'Minimum purchase not met'];
        }

        if ($userId && $coupon['usage_limit_per_user']) {
            $usageCount = self::getUserUsageCount($coupon['id'], $userId);
            if ($usageCount >= $coupon['usage_limit_per_user']) {
                return ['valid' => false, 'error' => 'Usage limit per user reached'];
            }
        }

        $discount = self::calculateDiscount($coupon, $cartTotal, $cartItems);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'type' => $coupon['type'],
        ];
    }

    public static function apply(string $code): bool
    {
        $coupon = static::where('code', strtoupper($code))->getFirst();
        if (!$coupon) {
            return false;
        }

        $couponId = is_array($coupon) ? $coupon['id'] : $coupon->id;

        return static::where('id', $couponId)->updateRows([
            'usage_count' => (is_array($coupon) ? $coupon['usage_count'] : $coupon->usage_count) + 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deactivate(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'is_active' => false,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function activate(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'is_active' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && date('Y-m-d H:i:s') > $this->expires_at;
    }

    public function isStarted(): bool
    {
        return !$this->starts_at || date('Y-m-d H:i:s') >= $this->starts_at;
    }

    protected static function calculateDiscount(array $coupon, float $cartTotal, array $cartItems = []): float
    {
        return match ($coupon['type']) {
            self::TYPE_PERCENTAGE => self::calculatePercentageDiscount($coupon, $cartTotal),
            self::TYPE_FIXED => min($coupon['value'], $cartTotal),
            self::TYPE_BOGO => self::calculateBogoDiscount($coupon, $cartItems),
            self::TYPE_FREE_SHIPPING => 0,
            default => 0,
        };
    }

    protected static function calculatePercentageDiscount(array $coupon, float $cartTotal): float
    {
        $discount = ($cartTotal * $coupon['value']) / 100;

        if ($coupon['max_discount']) {
            $discount = min($discount, $coupon['max_discount']);
        }

        return round($discount, 2);
    }

    protected static function calculateBogoDiscount(array $coupon, array $cartItems): float
    {
        if (empty($cartItems)) {
            return 0;
        }

        $sortedItems = $cartItems;
        usort($sortedItems, fn($a, $b) => ($a['price'] * $a['quantity']) <=> ($b['price'] * $b['quantity']));

        $cheapest = reset($sortedItems);
        return round($cheapest['price'] * $cheapest['quantity'], 2);
    }

    protected static function getUserUsageCount(int $couponId, int $userId): int
    {
        $result = static::rawQuery(
            "SELECT COUNT(*) as count FROM coupon_usage WHERE coupon_id = ? AND user_id = ?",
            [$couponId, $userId]
        );

        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (int) $row['count'];
        }

        return 0;
    }
}
