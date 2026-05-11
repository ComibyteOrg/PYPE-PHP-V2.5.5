<?php

namespace Framework\Commerce;

use Framework\Model\Model;

/**
 * Order Model
 * Manages orders, order items, status tracking, refunds, and returns.
 *
 * Usage:
 * $order = Order::createFromCart($userId, $cartItems, $shippingAddress, $paymentMethod);
 * $order->updateStatus('processing');
 * $order->addNote('Customer requested gift wrapping');
 * $order->getHistory();
 */
class Order extends Model
{
    protected static $table = 'orders';
    protected static $primaryKey = 'id';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_FAILED = 'failed';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';
    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    public static $fields = [
        'id' => 'integer',
        'order_number' => 'string',
        'user_id' => 'integer',
        'status' => 'string',
        'subtotal' => 'double',
        'tax_total' => 'double',
        'shipping_total' => 'double',
        'discount_total' => 'double',
        'total' => 'double',
        'currency' => 'string',
        'payment_method' => 'string',
        'payment_status' => 'string',
        'payment_id' => 'string',
        'shipping_method' => 'string',
        'shipping_address' => 'json',
        'billing_address' => 'json',
        'notes' => 'text',
        'tracking_number' => 'string',
        'shipped_at' => 'timestamp',
        'delivered_at' => 'timestamp',
        'cancelled_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('order_number', 50)->unique();
        $table->integer('user_id');
        $table->string('status', 20)->default('pending');
        $table->double('subtotal')->default(0);
        $table->double('tax_total')->default(0);
        $table->double('shipping_total')->default(0);
        $table->double('discount_total')->default(0);
        $table->double('total')->default(0);
        $table->string('currency', 3)->default('USD');
        $table->string('payment_method', 50)->nullable();
        $table->string('payment_status', 20)->default('pending');
        $table->string('payment_id', 255)->nullable();
        $table->string('shipping_method', 100)->nullable();
        $table->json('shipping_address')->nullable();
        $table->json('billing_address')->nullable();
        $table->text('notes')->nullable();
        $table->string('tracking_number', 100)->nullable();
        $table->timestamp('shipped_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createFromCart(int $userId, array $cartItems, array $shippingAddress, string $paymentMethod, array $options = []): ?Order
    {
        if (empty($cartItems)) {
            return null;
        }

        $orderNumber = self::generateOrderNumber();
        $billingAddress = $options['billing_address'] ?? $shippingAddress;
        $currency = $options['currency'] ?? 'USD';
        $notes = $options['notes'] ?? '';

        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $taxTotal = $options['tax_total'] ?? 0;
        $shippingTotal = $options['shipping_total'] ?? 0;
        $discountTotal = $options['discount_total'] ?? 0;
        $total = $subtotal + $taxTotal + $shippingTotal - $discountTotal;

        $shippingMethod = $options['shipping_method'] ?? null;

        $order = static::create([
            'order_number' => $orderNumber,
            'user_id' => $userId,
            'status' => self::STATUS_PENDING,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'shipping_total' => $shippingTotal,
            'discount_total' => $discountTotal,
            'total' => $total,
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'payment_status' => self::PAYMENT_PENDING,
            'shipping_method' => $shippingMethod,
            'shipping_address' => json_encode($shippingAddress),
            'billing_address' => json_encode($billingAddress),
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$order) {
            return null;
        }

        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'name' => $item['name'] ?? '',
                'sku' => $item['sku'] ?? '',
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'total' => $item['price'] * $item['quantity'],
                'options' => isset($item['options']) ? json_encode($item['options']) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Inventory::reduceStock($item['product_id'], $item['quantity']);
        }

        Cart::clear();

        OrderHistory::add($order->id, self::STATUS_PENDING, 'Order created');

        return $order;
    }

    public function updateStatus(string $status, ?string $note = null): bool
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === self::STATUS_SHIPPED) {
            $data['shipped_at'] = date('Y-m-d H:i:s');
        } elseif ($status === self::STATUS_DELIVERED) {
            $data['delivered_at'] = date('Y-m-d H:i:s');
        } elseif ($status === self::STATUS_CANCELLED) {
            $data['cancelled_at'] = date('Y-m-d H:i:s');
        }

        $result = static::where('id', $this->id)->updateRows($data);

        if ($result) {
            OrderHistory::add($this->id, $status, $note ?: "Status changed to {$status}");
        }

        return $result;
    }

    public function markAsPaid(string $paymentId = null): bool
    {
        $data = [
            'payment_status' => self::PAYMENT_PAID,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($paymentId) {
            $data['payment_id'] = $paymentId;
        }

        return static::where('id', $this->id)->updateRows($data);
    }

    public function markAsFailed(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'payment_status' => self::PAYMENT_FAILED,
            'status' => self::STATUS_FAILED,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function addNote(string $note): bool
    {
        return static::where('id', $this->id)->updateRows([
            'notes' => ($this->notes ? $this->notes . "\n" : '') . '[' . date('Y-m-d H:i') . '] ' . $note,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function setTrackingNumber(string $trackingNumber): bool
    {
        return static::where('id', $this->id)->updateRows([
            'tracking_number' => $trackingNumber,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getItems(): array
    {
        return OrderItem::where('order_id', $this->id)->get();
    }

    public function getHistory(): array
    {
        return OrderHistory::where('order_id', $this->id)->orderBy('created_at', 'ASC')->get();
    }

    public function getShippingAddress(): array
    {
        if ($this->shipping_address) {
            return json_decode($this->shipping_address, true) ?? [];
        }
        return [];
    }

    public function getBillingAddress(): array
    {
        if ($this->billing_address) {
            return json_decode($this->billing_address, true) ?? [];
        }
        return [];
    }

    public function getRefunds(): array
    {
        return Refund::where('order_id', $this->id)->get();
    }

    public function getTotalRefunded(): float
    {
        $refunds = $this->getRefunds();
        $total = 0;
        foreach ($refunds as $refund) {
            $total += (float) $refund['amount'];
        }
        return round($total, 2);
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_SHIPPED])
            && $this->payment_status === self::PAYMENT_PAID
            && $this->getTotalRefunded() < $this->total;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public static function findByNumber(string $orderNumber): ?Order
    {
        return static::where('order_number', $orderNumber)->getFirst();
    }

    public static function forUser(int $userId)
    {
        return static::where('user_id', $userId)->orderBy('created_at', 'DESC');
    }

    public static function byStatus(string $status)
    {
        return static::where('status', $status);
    }

    public static function getRevenue(?string $startDate = null, ?string $endDate = null): float
    {
        $sql = "SELECT SUM(total) as revenue FROM " . static::$table . " WHERE payment_status = ?";
        $params = [self::PAYMENT_PAID];

        if ($startDate) {
            $sql .= " AND created_at >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND created_at <= ?";
            $params[] = $endDate;
        }

        $result = static::rawQuery($sql, $params);
        if ($result) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return (float) ($row['revenue'] ?? 0);
        }

        return 0;
    }

    public static function getStats(): array
    {
        $table = static::$table;
        $result = static::rawQuery(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue
             FROM {$table}"
        );

        if ($result) {
            return $result->fetch(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    protected static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = date('Ymd');
        $lastOrder = static::where('order_number', 'LIKE', "{$prefix}-{$date}-%")
            ->orderBy('id', 'DESC')
            ->getFirst();

        $sequence = 1;
        if ($lastOrder) {
            $lastNumber = $lastOrder->order_number ?? (is_array($lastOrder) ? $lastOrder['order_number'] : '');
            $parts = explode('-', $lastNumber);
            $sequence = (int) end($parts) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }
}

class OrderItem extends Model
{
    protected static $table = 'order_items';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'order_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'name' => 'string',
        'sku' => 'string',
        'price' => 'double',
        'quantity' => 'integer',
        'total' => 'double',
        'options' => 'text',
        'created_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('order_id');
        $table->integer('product_id');
        $table->integer('variant_id')->nullable();
        $table->string('name', 255);
        $table->string('sku', 100)->nullable();
        $table->double('price')->default(0);
        $table->integer('quantity')->default(1);
        $table->double('total')->default(0);
        $table->text('options')->nullable();
        $table->timestamp('created_at');
    }

    public function getOptions(): array
    {
        if ($this->options) {
            return json_decode($this->options, true) ?? [];
        }
        return [];
    }

    public function getProduct(): ?Product
    {
        return Product::find($this->product_id);
    }
}

class OrderHistory extends Model
{
    protected static $table = 'order_history';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'order_id' => 'integer',
        'old_status' => 'string',
        'new_status' => 'string',
        'note' => 'text',
        'created_by' => 'integer',
        'created_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('order_id');
        $table->string('old_status', 20)->nullable();
        $table->string('new_status', 20);
        $table->text('note')->nullable();
        $table->integer('created_by')->nullable();
        $table->timestamp('created_at');
    }

    public static function add(int $orderId, string $newStatus, ?string $note = null, ?int $createdBy = null): bool
    {
        $order = Order::find($orderId);
        $oldStatus = $order ? ($order->status ?? null) : null;

        return (bool) static::rawQuery(
            "INSERT INTO " . static::$table . " (order_id, old_status, new_status, note, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$orderId, $oldStatus, $newStatus, $note, $createdBy, date('Y-m-d H:i:s')]
        );
    }
}
