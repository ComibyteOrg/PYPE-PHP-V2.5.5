# E-Commerce System - Pype PHP v2.5.5

## Overview

The Pype PHP E-Commerce system provides a complete foundation for building online stores. It includes shopping carts, product catalogs, order management, payment gateways, coupons, inventory tracking, tax calculation, shipping, invoices, and subscriptions.

## Table of Contents

- [Setup](#setup)
- [Shopping Cart](#shopping-cart)
- [Product Catalog](#product-catalog)
- [Order Management](#order-management)
- [Payment Gateways](#payment-gateways)
- [Coupons & Discounts](#coupons--discounts)
- [Inventory Tracking](#inventory-tracking)
- [Tax Engine](#tax-engine)
- [Shipping Calculator](#shipping-calculator)
- [Invoice Generation](#invoice-generation)
- [Subscriptions](#subscriptions)
- [Helper Functions](#helper-functions)

## Setup

### Run Commerce Migrations

```php
use Framework\Commerce\CommerceMigrations;

CommerceMigrations::create();
```

This creates 19 tables including products, orders, cart, payments, coupons, inventory, tax rates, shipping rates, plans, subscriptions, and invoices.

## Shopping Cart

Session-based for guests, database-backed for authenticated users. Automatic merge on login.

### Basic Usage

```php
use Framework\Commerce\Cart;

// Initialize (auto-called on first use)
Cart::init($userId); // For authenticated users
Cart::init();         // For guests (session-based)

// Add to cart
Cart::add($productId, 2);
Cart::add($productId, 1, ['color' => 'red', 'size' => 'XL']);

// Update quantity
Cart::update($cartItemId, 5);

// Remove item
Cart::remove($cartItemId);

// Clear cart
Cart::clear();
```

### Cart Methods

```php
$items = Cart::getItems();      // All items
$total = Cart::total();         // Subtotal
$count = Cart::count();         // Total quantity
$unique = Cart::uniqueItems();  // Unique products
$empty = Cart::isEmpty();       // Check empty
$weight = Cart::weight();       // Total weight
$hasProduct = Cart::hasProduct($productId);
```

## Product Catalog

Full product management with variants, categories, tags, and images.

### Creating Products

```php
use Framework\Commerce\Product;

$product = Product::createProduct([
    'name' => 'Wireless Headphones',
    'description' => 'Premium wireless headphones...',
    'short_description' => 'High-quality wireless audio',
    'price' => 99.99,
    'compare_price' => 149.99,
    'cost_price' => 45.00,
    'sku' => 'WH-001',
    'type' => 'simple',
    'status' => 'active',
    'weight' => 0.5,
    'attributes' => json_encode(['color' => 'Black', 'brand' => 'AudioTech']),
]);

// Add images
$product->attachFile($_FILES['image1'], 'images');
$product->attachFile($_FILES['image2'], 'images');

// Add categories and tags
$product->addCategory($categoryId);
$product->addTag('electronics');
$product->addTag('audio');
```

### Product Variants

```php
use Framework\Commerce\ProductVariant;

ProductVariant::create([
    'product_id' => $product->id,
    'sku' => 'WH-001-RED-L',
    'name' => 'Red / Large',
    'price' => 109.99,
    'stock' => 50,
    'attributes' => json_encode(['color' => 'Red', 'size' => 'L']),
]);
```

### Product Variants

```php
use Framework\Commerce\ProductVariant;

// Create variant
$variant = ProductVariant::create([
    'product_id' => $product->id,
    'sku' => 'WH-001-BLK',
    'price' => 109.99,
    'stock' => 25,
    'attributes' => json_encode(['color' => 'Black']),
]);

// Check variant
$variant->isInStock();
$variant->getEffectivePrice();
$variant->hasDiscount();
```

### Querying Products

```php
// Active products
$products = Product::active()->get();

// By category
$products = Product::byCategory($categoryId)->get();

// Search
$products = Product::search('headphones')->get();

// Popular products
$popular = Product::popular(limit: 10);

// On sale
$sale = Product::onSale(limit: 10);

// Product methods
$product->getImages();
$product->getMainImage();
$product->getCategories();
$product->getTags();
$product->getVariants();
$product->hasDiscount();
$product->getDiscountPercentage();
$product->getStockQuantity();
$product->isInStock();
```

## Order Management

Full order lifecycle from creation to delivery with status tracking and refunds.

### Creating Orders

```php
use Framework\Commerce\Order;

$order = Order::createFromCart(
    $userId,
    Cart::getItems(),
    [
        'name' => 'John Doe',
        'line1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'zip' => '10001',
        'country' => 'US',
        'phone' => '+1234567890',
    ],
    'stripe',
    [
        'tax_total' => 8.50,
        'shipping_total' => 9.99,
        'discount_total' => 20.00,
        'shipping_method' => 'standard',
    ]
);
```

### Order Status Management

```php
$order->updateStatus('processing');
$order->updateStatus('shipped');
$order->setTrackingNumber('1Z999AA10123456784');
$order->updateStatus('delivered');

// Payment status
$order->markAsPaid('pi_123456');
$order->markAsFailed();

// Notes
$order->addNote('Customer requested gift wrapping');

// Get order data
$items = $order->getItems();
$history = $order->getHistory();
$shipping = $order->getShippingAddress();
$billing = $order->getBillingAddress();
```

### Order Queries

```php
// By user
$orders = Order::forUser($userId)->get();

// By status
$pending = Order::byStatus('pending')->get();

// By order number
$order = Order::findByNumber('ORD-20260101-0001');

// Stats
$stats = Order::getStats();
$revenue = Order::getRevenue('2026-01-01', '2026-12-31');
```

### Refunds

```php
use Framework\Commerce\Refund;

// Create refund
$refund = Refund::createRefund(
    $orderId,
    $userId,
    50.00,
    Refund::TYPE_PARTIAL,
    Refund::REASON_DEFECTIVE,
    'Item arrived damaged'
);

// Process refund
$refund->process($moderatorId, 're_123456');

// Reject refund
$refund->reject($moderatorId, 'Outside return window');

// Check refundable
$order->isRefundable();
$order->getTotalRefunded();
```

## Payment Gateways

Support for Stripe, PayPal, Flutterwave, and Paystack.

### Setup

```php
use Framework\Commerce\Payment;
use Framework\Commerce\StripeGateway;
use Framework\Commerce\PayPalGateway;
use Framework\Commerce\FlutterwaveGateway;
use Framework\Commerce\PaystackGateway;

// Register gateways
$stripe = new StripeGateway();
$stripe->configure([
    'api_key' => 'pk_live_...',
    'secret_key' => 'sk_live_...',
]);
Payment::register('stripe', $stripe);

$paypal = new PayPalGateway();
$paypal->configure([
    'api_key' => 'client_id',
    'secret_key' => 'secret',
    'sandbox' => true,
]);
Payment::register('paypal', $paypal);

Payment::setDefault('stripe');
```

### Processing Payments

```php
// Charge
$result = Payment::charge(99.99, 'USD', [
    'token' => $_POST['stripe_token'],
    'description' => 'Order #ORD-001',
    'metadata' => ['order_id' => 123],
]);

if ($result['success']) {
    $order->markAsPaid($result['data']['id']);
}

// Refund
$refund = Payment::refund('ch_123', 50.00, 'USD');

// Verify
$verification = Payment::verify('pi_123');
```

### Webhooks

```php
$event = Payment::gateway('stripe')->webhook($_POST);

match ($event['event']) {
    'payment_success' => handlePaymentSuccess($event['data']),
    'payment_failed' => handlePaymentFailed($event['data']),
    'refunded' => handleRefund($event['data']),
};
```

## Coupons & Discounts

Percentage, fixed amount, BOGO, and free shipping codes.

### Creating Coupons

```php
use Framework\Commerce\Coupon;

// Percentage discount
Coupon::createCode('SAVE20', 'percentage', 20, [
    'min_purchase' => 50,
    'max_discount' => 100,
    'usage_limit' => 100,
    'usage_limit_per_user' => 1,
    'expires_at' => '2026-12-31',
]);

// Fixed discount
Coupon::createCode('FLAT10', 'fixed', 10);

// BOGO (Buy One Get One)
Coupon::createCode('BOGO', 'bogo', 0);

// Free shipping
Coupon::createCode('FREESHIP', 'free_shipping', 0);
```

### Validating & Applying

```php
$result = Coupon::validate('SAVE20', $userId, Cart::total(), Cart::getItems());

if ($result['valid']) {
    $discount = $result['discount'];
    Coupon::apply('SAVE20');
}
```

## Inventory Tracking

Stock management with low-stock alerts and backorder support.

### Basic Usage

```php
use Framework\Commerce\Inventory;

// Set stock
Inventory::setStock($productId, 100);
Inventory::setStock($productId, 50, $variantId);

// Adjust stock
Inventory::reduceStock($productId, 5);
Inventory::addStock($productId, 20);

// Check availability
Inventory::isAvailable($productId, 3);
Inventory::isInStock($productId);
Inventory::isLowStock($productId);
```

### Backorders & Thresholds

```php
Inventory::enableBackorder($productId);
Inventory::disableBackorder($productId);
Inventory::setLowStockThreshold($productId, 10);
```

### Reports

```php
$lowStock = Inventory::getLowStockProducts();
$outOfStock = Inventory::getOutOfStockProducts();
$value = Inventory::getInventoryValue();
```

## Tax Engine

Location-based tax calculation.

### Setup & Calculation

```php
use Framework\Commerce\Tax;

// Add rates
Tax::addRate('US', 'CA', 0.0725); // California 7.25%
Tax::addRate('US', 'NY', 0.08);   // New York 8%
Tax::addRate('US', '', 0.06);     // Default US rate 6%
Tax::addRate('GB', '', 0.20);     // UK VAT 20%

// Calculate tax
$tax = Tax::calculate(100, 'US', 'CA');
// ['rate' => 0.0725, 'amount' => 7.25, 'total' => 107.25]

// For cart
$tax = Tax::calculateForCart(Cart::total(), $shippingAddress);
```

## Shipping Calculator

Flat rate and weight-based shipping.

### Setup

```php
use Framework\Commerce\Shipping;

// Flat rates
Shipping::addFlatRate('standard', 9.99, ['free_threshold' => 50]);
Shipping::addFlatRate('express', 19.99);
Shipping::addFlatRate('priority', 14.99);

// Weight-based
Shipping::addWeightRate(0, 5, 4.99);
Shipping::addWeightRate(5, 20, 9.99);
Shipping::addWeightRate(20, null, 14.99);

// Zone-based
Shipping::addZone('US', ['standard', 'express']);
Shipping::addZone('GB', ['standard']);
```

### Calculation

```php
// Get rate for method
$cost = Shipping::calculate($weight, 'US', 'standard', Cart::total());

// Get all available rates
$rates = Shipping::getRates($weight, 'US', Cart::total());
// ['standard' => ['name' => 'Standard', 'price' => 9.99, 'estimated_days' => '5-7']]

// Check free shipping
Shipping::isFreeShippingEligible(Cart::total());
```

## Invoice Generation

HTML invoices with print and download support.

### Usage

```php
use Framework\Commerce\Invoice;

// Generate HTML
$html = Invoice::generate($orderId);

// Download as file
Invoice::download($orderId);

// Email to customer
Invoice::email($orderId, 'customer@example.com');
```

## Subscriptions

Recurring billing with trial periods and plan management.

### Plans

```php
use Framework\Commerce\Plan;

Plan::create([
    'name' => 'Pro Monthly',
    'slug' => 'pro-monthly',
    'price' => 29.99,
    'interval' => 'monthly',
    'trial_days' => 14,
    'features' => json_encode([
        'Unlimited projects',
        'Priority support',
        'Advanced analytics',
    ]),
]);
```

### Managing Subscriptions

```php
use Framework\Commerce\Subscription;

// Create subscription
$sub = Subscription::createSubscription($userId, $planId, 'stripe', [
    'trial_days' => 14,
    'gateway_subscription_id' => 'sub_123',
]);

// Lifecycle
$sub->upgrade($newPlanId);
$sub->downgrade($newPlanId);
$sub->cancel();
$sub->cancelImmediately();
$sub->pause();
$sub->resume();

// Status checks
$sub->isActive();
$sub->isTrialing();
$sub->isOnGracePeriod();
$sub->hasEnded();
$sub->daysUntilRenewal();
$sub->daysLeftInTrial();

// Process renewals
Subscription::processDueRenewals();
$expiring = Subscription::expiringSoon(7);

// User subscriptions
$active = Subscription::activeForUser($userId);
$all = Subscription::forUser($userId);
```

## Helper Functions

### Cart
```php
cart_add($productId, 2);
cart_update($cartItemId, 5);
cart_remove($cartItemId);
cart_items();
cart_total();
cart_count();
cart_clear();
```

### Products & Orders
```php
create_product(['name' => 'Product', 'price' => 99.99]);
create_order($userId, $cartItems, $address, 'stripe');
```

### Payments
```php
payment_charge(99.99, 'USD', ['token' => 'tok_123']);
payment_refund('ch_123', 50.00, 'USD');
```

### Coupons
```php
validate_coupon('SAVE20', $userId, $total);
create_coupon('SAVE20', 'percentage', 20);
```

### Tax & Shipping
```php
calculate_tax(100, 'US', 'CA');
calculate_shipping(5, 'US', 'standard', $total);
shipping_rates($weight, 'US', $total);
```

### Inventory
```php
stock_level($productId);
low_stock_products();
```

### Subscriptions & Invoices
```php
create_subscription($userId, $planId, 'stripe', ['trial_days' => 14]);
user_subscription($userId);
generate_invoice($orderId, true);
```

## Complete Store Example

```php
// Setup
CommerceMigrations::create();

// Create products
$product = Product::createProduct([
    'name' => 'Premium Headphones',
    'price' => 99.99,
    'sku' => 'HP-001',
    'status' => 'active',
]);
Inventory::setStock($product->id, 100);

// Shopping cart
cart_add($product->id, 2);
$total = cart_total();

// Calculate tax and shipping
$tax = calculate_tax($total, 'US', 'CA');
$rates = shipping_rates(Cart::weight(), 'US', $total);

// Validate coupon
$coupon = validate_coupon('SAVE20', $userId, $total);
$discount = $coupon['valid'] ? $coupon['discount'] : 0;

// Create order
$order = create_order($userId, cart_items(), $address, 'stripe', [
    'tax_total' => $tax['amount'],
    'shipping_total' => $rates['standard']['price'],
    'discount_total' => $discount,
]);

// Process payment
$payment = payment_charge($order->total, 'USD', ['token' => $_POST['stripe_token']]);
if ($payment['success']) {
    $order->markAsPaid($payment['data']['id']);
}

// Generate invoice
Invoice::email($order->id, $customerEmail);

// Subscription
$sub = create_subscription($userId, $planId, 'stripe', ['trial_days' => 14]);
```
