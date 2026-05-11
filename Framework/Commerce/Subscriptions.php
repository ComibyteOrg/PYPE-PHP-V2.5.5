<?php

namespace Framework\Commerce;

use Framework\Model\Model;

/**
 * Subscription System
 * Manages recurring billing, trial periods, plan upgrades, and cancellations.
 *
 * Usage:
 * $subscription = Subscription::create($userId, $planId, 'stripe');
 * $subscription->upgrade($newPlanId);
 * $subscription->cancel();
 * $subscription->pause();
 * $subscription->resume();
 * Subscription::processDueRenewals();
 */
class Subscription extends Model
{
    protected static $table = 'subscriptions';
    protected static $primaryKey = 'id';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PAST_DUE = 'past_due';

    public const INTERVAL_DAILY = 'daily';
    public const INTERVAL_WEEKLY = 'weekly';
    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_YEARLY = 'yearly';

    public static $fields = [
        'id' => 'integer',
        'user_id' => 'integer',
        'plan_id' => 'integer',
        'status' => 'string',
        'gateway' => 'string',
        'gateway_subscription_id' => 'string',
        'price' => 'double',
        'currency' => 'string',
        'interval' => 'string',
        'interval_count' => 'integer',
        'trial_ends_at' => 'timestamp',
        'current_period_start' => 'timestamp',
        'current_period_end' => 'timestamp',
        'cancelled_at' => 'timestamp',
        'ends_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('user_id');
        $table->integer('plan_id');
        $table->string('status', 20)->default('active');
        $table->string('gateway', 50);
        $table->string('gateway_subscription_id', 255)->nullable();
        $table->double('price')->default(0);
        $table->string('currency', 3)->default('USD');
        $table->string('interval', 20)->default('monthly');
        $table->integer('interval_count')->default(1);
        $table->timestamp('trial_ends_at')->nullable();
        $table->timestamp('current_period_start');
        $table->timestamp('current_period_end');
        $table->timestamp('cancelled_at')->nullable();
        $table->timestamp('ends_at')->nullable();
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createSubscription(int $userId, int $planId, string $gateway, array $options = []): ?Subscription
    {
        $plan = Plan::find($planId);
        if (!$plan) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $periodEnd = self::calculatePeriodEnd($now, $plan->interval, $plan->interval_count);

        $trialEndsAt = null;
        $status = self::STATUS_ACTIVE;

        if (isset($options['trial_days']) && $options['trial_days'] > 0) {
            $trialEndsAt = date('Y-m-d H:i:s', strtotime("+{$options['trial_days']} days"));
            $status = self::STATUS_TRIALING;
        }

        $data = [
            'user_id' => $userId,
            'plan_id' => $planId,
            'status' => $status,
            'gateway' => $gateway,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'interval_count' => $plan->interval_count,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (isset($options['gateway_subscription_id'])) {
            $data['gateway_subscription_id'] = $options['gateway_subscription_id'];
        }

        return static::create($data);
    }

    public function cancel(?string $reason = null): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'ends_at' => $this->current_period_end,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function cancelImmediately(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'ends_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function upgrade(int $newPlanId): bool
    {
        $newPlan = Plan::find($newPlanId);
        if (!$newPlan) {
            return false;
        }

        return static::where('id', $this->id)->updateRows([
            'plan_id' => $newPlanId,
            'price' => $newPlan->price,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function downgrade(int $newPlanId): bool
    {
        return $this->upgrade($newPlanId);
    }

    public function pause(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_PAUSED,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function resume(): bool
    {
        $now = date('Y-m-d H:i:s');
        $newPeriodEnd = self::calculatePeriodEnd($now, $this->interval, $this->interval_count);

        return static::where('id', $this->id)->updateRows([
            'status' => self::STATUS_ACTIVE,
            'current_period_start' => $now,
            'current_period_end' => $newPeriodEnd,
            'ends_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function renew(): bool
    {
        $now = date('Y-m-d H:i:s');
        $newPeriodEnd = self::calculatePeriodEnd($now, $this->interval, $this->interval_count);

        return static::where('id', $this->id)->updateRows([
            'current_period_start' => $this->current_period_end,
            'current_period_end' => $newPeriodEnd,
            'updated_at' => $now,
        ]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING]);
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIALING;
    }

    public function isOnGracePeriod(): bool
    {
        return $this->status === self::STATUS_CANCELLED
            && $this->ends_at
            && strtotime($this->ends_at) > time();
    }

    public function hasEnded(): bool
    {
        return in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_CANCELLED])
            && $this->ends_at
            && strtotime($this->ends_at) < time();
    }

    public function daysUntilRenewal(): int
    {
        if (!$this->current_period_end) {
            return 0;
        }
        return max(0, ceil((strtotime($this->current_period_end) - time()) / 86400));
    }

    public function daysLeftInTrial(): int
    {
        if (!$this->trial_ends_at) {
            return 0;
        }
        return max(0, ceil((strtotime($this->trial_ends_at) - time()) / 86400));
    }

    public function getPlan(): ?Plan
    {
        return Plan::find($this->plan_id);
    }

    public static function forUser(int $userId): array
    {
        return static::where('user_id', $userId)->orderBy('created_at', 'DESC')->get();
    }

    public static function activeForUser(int $userId): ?Subscription
    {
        return static::where('user_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->orWhere('status', self::STATUS_TRIALING)
            ->getFirst();
    }

    public static function processDueRenewals(): int
    {
        $now = date('Y-m-d H:i:s');
        $dueSubscriptions = static::rawQuery(
            "SELECT * FROM " . static::$table . " WHERE status IN (?, ?) AND current_period_end <= ?",
            [self::STATUS_ACTIVE, self::STATUS_TRIALING, $now]
        );

        if (!$dueSubscriptions) {
            return 0;
        }

        $renewed = 0;
        while ($sub = $dueSubscriptions->fetch(\PDO::FETCH_ASSOC)) {
            $subscription = static::find($sub['id']);
            if ($subscription) {
                if ($subscription->renew()) {
                    $renewed++;
                }
            }
        }

        return $renewed;
    }

    public static function expiringSoon(int $days = 7): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        return static::rawQuery(
            "SELECT * FROM " . static::$table . " WHERE status IN (?, ?) AND current_period_end <= ?",
            [self::STATUS_ACTIVE, self::STATUS_TRIALING, $cutoff]
        )->fetchAll(\PDO::FETCH_ASSOC) ?? [];
    }

    protected static function calculatePeriodEnd(string $startDate, string $interval, int $count = 1): string
    {
        return match ($interval) {
            self::INTERVAL_DAILY => date('Y-m-d H:i:s', strtotime("+{$count} days", strtotime($startDate))),
            self::INTERVAL_WEEKLY => date('Y-m-d H:i:s', strtotime("+{$count} weeks", strtotime($startDate))),
            self::INTERVAL_MONTHLY => date('Y-m-d H:i:s', strtotime("+{$count} months", strtotime($startDate))),
            self::INTERVAL_YEARLY => date('Y-m-d H:i:s', strtotime("+{$count} years", strtotime($startDate))),
            default => date('Y-m-d H:i:s', strtotime("+1 month", strtotime($startDate))),
        };
    }
}

class Plan extends Model
{
    protected static $table = 'plans';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'description' => 'text',
        'price' => 'double',
        'currency' => 'string',
        'interval' => 'string',
        'interval_count' => 'integer',
        'trial_days' => 'integer',
        'features' => 'json',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->string('name', 100);
        $table->string('slug', 100)->unique();
        $table->text('description')->nullable();
        $table->double('price')->default(0);
        $table->string('currency', 3)->default('USD');
        $table->string('interval', 20)->default('monthly');
        $table->integer('interval_count')->default(1);
        $table->integer('trial_days')->default(0);
        $table->json('features')->nullable();
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public function getFeatures(): array
    {
        if ($this->features) {
            return json_decode($this->features, true) ?? [];
        }
        return [];
    }

    public function active()
    {
        return static::where('is_active', true)->orderBy('sort_order', 'ASC');
    }
}
