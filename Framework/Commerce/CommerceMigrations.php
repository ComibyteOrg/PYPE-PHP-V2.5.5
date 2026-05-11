<?php

namespace Framework\Commerce;

use Framework\Database\Migration;
use Framework\Database\Schema;

/**
 * CommerceMigrations
 * Creates all tables required for e-commerce features.
 * Run this migration once to enable the commerce system.
 *
 * Usage:
 * (new \Framework\Commerce\CommerceMigrations())->up();
 * CommerceMigrations::create();
 */
class CommerceMigrations extends Migration
{
    public function up()
    {
        $this->createProductsTable();
        $this->createProductVariantsTable();
        $this->createProductCategoriesTable();
        $this->createProductTagsTable();
        $this->createProductCategoryPivotTable();
        $this->createProductTagPivotTable();
        $this->createInventoryTable();
        $this->createCartItemsTable();
        $this->createOrdersTable();
        $this->createOrderItemsTable();
        $this->createOrderHistoryTable();
        $this->createRefundsTable();
        $this->createCouponsTable();
        $this->createCouponUsageTable();
        $this->createTaxRatesTable();
        $this->createShippingRatesTable();
        $this->createPlansTable();
        $this->createSubscriptionsTable();
        $this->createInvoicesTable();
    }

    public function down()
    {
        $this->dropTable('invoices');
        $this->dropTable('subscriptions');
        $this->dropTable('plans');
        $this->dropTable('shipping_rates');
        $this->dropTable('tax_rates');
        $this->dropTable('coupon_usage');
        $this->dropTable('coupons');
        $this->dropTable('refunds');
        $this->dropTable('order_history');
        $this->dropTable('order_items');
        $this->dropTable('orders');
        $this->dropTable('cart_items');
        $this->dropTable('inventory');
        $this->dropTable('product_tag_pivot');
        $this->dropTable('product_category_pivot');
        $this->dropTable('product_tags');
        $this->dropTable('product_categories');
        $this->dropTable('product_variants');
        $this->dropTable('products');
    }

    public static function create()
    {
        (new self())->up();
    }

    public static function drop()
    {
        (new self())->down();
    }

    protected function createProductsTable()
    {
        $this->createTable('products', function (Schema $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('type', 20)->default('simple');
            $table->string('status', 20)->default('draft');
            $table->string('sku', 100)->nullable()->unique();
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

            $table->index('status');
            $table->index('type');
        });
    }

    protected function createProductVariantsTable()
    {
        $this->createTable('product_variants', function (Schema $table) {
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

            $table->index('product_id');
        });
    }

    protected function createProductCategoriesTable()
    {
        $this->createTable('product_categories', function (Schema $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->integer('parent_id')->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index('parent_id');
        });
    }

    protected function createProductTagsTable()
    {
        $this->createTable('product_tags', function (Schema $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
        });
    }

    protected function createProductCategoryPivotTable()
    {
        $this->createTable('product_category_pivot', function (Schema $table) {
            $table->id();
            $table->integer('product_id');
            $table->integer('category_id');

            $table->unique(['product_id', 'category_id']);
            $table->index('category_id');
        });
    }

    protected function createProductTagPivotTable()
    {
        $this->createTable('product_tag_pivot', function (Schema $table) {
            $table->id();
            $table->integer('product_id');
            $table->integer('tag_id');

            $table->unique(['product_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    protected function createInventoryTable()
    {
        $this->createTable('inventory', function (Schema $table) {
            $table->id();
            $table->integer('product_id');
            $table->integer('variant_id')->nullable();
            $table->integer('stock')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('allow_backorder')->default(false);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['product_id', 'variant_id']);
        });
    }

    protected function createCartItemsTable()
    {
        $this->createTable('cart_items', function (Schema $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->integer('product_id');
            $table->integer('variant_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->double('price')->default(0);
            $table->text('options')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['user_id']);
            $table->index(['session_id']);
            $table->index(['product_id']);
        });
    }

    protected function createOrdersTable()
    {
        $this->createTable('orders', function (Schema $table) {
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

            $table->index('user_id');
            $table->index('status');
            $table->index('payment_status');
        });
    }

    protected function createOrderItemsTable()
    {
        $this->createTable('order_items', function (Schema $table) {
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

            $table->index('order_id');
            $table->index('product_id');
        });
    }

    protected function createOrderHistoryTable()
    {
        $this->createTable('order_history', function (Schema $table) {
            $table->id();
            $table->integer('order_id');
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->text('note')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at');

            $table->index('order_id');
        });
    }

    protected function createRefundsTable()
    {
        $this->createTable('refunds', function (Schema $table) {
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

            $table->index('order_id');
            $table->index('status');
        });
    }

    protected function createCouponsTable()
    {
        $this->createTable('coupons', function (Schema $table) {
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
        });
    }

    protected function createCouponUsageTable()
    {
        $this->createTable('coupon_usage', function (Schema $table) {
            $table->id();
            $table->integer('coupon_id');
            $table->integer('user_id');
            $table->integer('order_id')->nullable();
            $table->timestamp('created_at');

            $table->index('coupon_id');
            $table->index('user_id');
        });
    }

    protected function createTaxRatesTable()
    {
        $this->createTable('tax_rates', function (Schema $table) {
            $table->id();
            $table->string('country', 10);
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->double('rate')->default(0);
            $table->string('name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['country', 'region']);
        });
    }

    protected function createShippingRatesTable()
    {
        $this->createTable('shipping_rates', function (Schema $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('method', 50);
            $table->string('type', 20)->default('flat');
            $table->double('price')->default(0);
            $table->double('free_threshold')->nullable();
            $table->double('min_weight')->default(0);
            $table->double('max_weight')->nullable();
            $table->text('countries')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }

    protected function createPlansTable()
    {
        $this->createTable('plans', function (Schema $table) {
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
        });
    }

    protected function createSubscriptionsTable()
    {
        $this->createTable('subscriptions', function (Schema $table) {
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

            $table->index('user_id');
            $table->index('status');
        });
    }

    protected function createInvoicesTable()
    {
        $this->createTable('invoices', function (Schema $table) {
            $table->id();
            $table->integer('order_id');
            $table->integer('user_id');
            $table->string('invoice_number', 50)->unique();
            $table->string('status', 20)->default('pending');
            $table->double('subtotal')->default(0);
            $table->double('tax_total')->default(0);
            $table->double('total')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at');

            $table->index('order_id');
            $table->index('user_id');
        });
    }
}
