<?php

namespace Framework\Commerce;

/**
 * Refund Model
 * Manages refunds and returns for orders.
 *
 * Usage:
 * Refund::create($orderId, $userId, 50.00, 'full', 'Item was damaged');
 * Refund::process($refundId);
 * Refund::reject($refundId, 'Outside return window');
 */
class Refund extends \Framework\Model\Model
{
    protected static $table = 'refunds';
    protected static $primaryKey = 'id';

    public const TYPE_FULL = 'full';
    public const TYPE_PARTIAL = 'partial';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    public const REASON_DEFECTIVE = 'defective';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_NOT_AS_DESCRIBED = 'not_as_described';
    public const REASON_CHANGED_MIND = 'changed_mind';
    public const REASON_OTHER = 'other';

    public static $fields = [
        'id' => 'integer',
        'order_id' => 'integer',
        'user_id' => 'integer',
        'type' => 'string',
        'amount' => 'double',
        'reason' => 'string',
        'details' => 'text',
        'status' => 'string',
        'refund_id' => 'string',
        'processed_by' => 'integer',
        'notes' => 'text',
        'processed_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('order_id');
        $table->integer('user_id');
        $table->string('type', 20)->default('partial');
        $table->double('amount')->default(0);
        $table->string('reason', 50)->nullable();
        $table->text('details')->nullable();
        $table->string('status', 20)->default('pending');
        $table->string('refund_id', 255)->nullable();
        $table->integer('processed_by')->nullable();
        $table->text('notes')->nullable();
        $table->timestamp('processed_at')->nullable();
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createRefund(int $orderId, int $userId, float $amount, string $type = self::TYPE_PARTIAL, ?string $reason = null, ?string $details = null): ?Refund
    {
        $order = Order::find($orderId);
        if (!$order || !$order->isRefundable()) {
            return null;
        }

        $totalRefunded = $order->getTotalRefunded();
        if ($totalRefunded + $amount > $order->total) {
            return null;
        }

        $refund = static::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'details' => $details,
            'status' => self::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $refund;
    }

    public function process(int $processedBy, string $refundGatewayId = null): bool
    {
        $data = [
            'status' => self::STATUS_COMPLETED,
            'processed_by' => $processedBy,
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($refundGatewayId) {
            $data['refund_id'] = $refundGatewayId;
        }

        $result = static::where('id', $this->id)->updateRows($data);

        if ($result) {
            $order = Order::find($this->order_id);
            if ($order) {
                $totalRefunded = $order->getTotalRefunded();
                $newPaymentStatus = $totalRefunded >= $order->total
                    ? Order::PAYMENT_REFUNDED
                    : Order::PAYMENT_PARTIALLY_REFUNDED;

                $order->updateRows([
                    'payment_status' => $newPaymentStatus,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $result;
    }

    public function reject(int $processedBy, ?string $notes = null): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_REJECTED,
            'processed_by' => $processedBy,
            'notes' => $notes,
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forOrder(int $orderId): array
    {
        return static::where('order_id', $orderId)->orderBy('created_at', 'DESC')->get();
    }

    public static function pending(): array
    {
        return static::where('status', self::STATUS_PENDING)->orderBy('created_at', 'ASC')->get();
    }
}
