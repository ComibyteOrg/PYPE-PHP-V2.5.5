<?php

namespace Framework\Commerce;

/**
 * Invoice Generator
 * Generates PDF invoices from orders using pure PHP (no external dependencies).
 * Creates HTML-based invoices that can be saved as PDF or printed.
 *
 * Usage:
 * Invoice::generate($orderId);
 * Invoice::download($orderId);
 * Invoice::email($orderId, 'customer@example.com');
 */
class Invoice
{
    public static function generate(int $orderId, bool $returnHtml = false): string
    {
        $order = Order::find($orderId);
        if (!$order) {
            return '<h1>Order not found</h1>';
        }

        $orderData = is_array($order) ? $order : $order->toArray();
        $items = $order->getItems();
        $shipping = $order->getShippingAddress();
        $billing = $order->getBillingAddress();

        $html = self::buildInvoiceHtml($orderData, $items, $shipping, $billing);

        if ($returnHtml) {
            return $html;
        }

        return $html;
    }

    public static function download(int $orderId): void
    {
        $html = self::generate($orderId);
        $filename = 'invoice-' . $orderId . '.html';

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $html;
        exit;
    }

    public static function email(int $orderId, string $toEmail, ?string $subject = null): bool
    {
        $html = self::generate($orderId, true);
        $subject = $subject ?: 'Invoice for Order #' . $orderId;

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";

        return mail($toEmail, $subject, $html, $headers);
    }

    protected static function buildInvoiceHtml(array $order, array $items, array $shipping, array $billing): string
    {
        $currency = $order['currency'] ?? 'USD';
        $currencySymbol = self::getCurrencySymbol($currency);

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemData = is_array($item) ? $item : $item->toArray();
            $itemsHtml .= sprintf(
                '<tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">%s</td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">%s</td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">%s%s</td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">%s%s</td>
                </tr>',
                htmlspecialchars($itemData['name']),
                $itemData['quantity'],
                $currencySymbol,
                number_format($itemData['price'], 2),
                $currencySymbol,
                number_format($itemData['total'], 2)
            );
        }

        $shippingAddress = self::formatAddress($shipping);
        $billingAddress = self::formatAddress($billing);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{$order['order_number']}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .invoice { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #333; }
        .company h1 { margin: 0; font-size: 28px; color: #333; }
        .invoice-details { text-align: right; }
        .invoice-details h2 { margin: 0 0 10px; font-size: 24px; color: #666; }
        .addresses { display: flex; gap: 40px; margin-bottom: 30px; }
        .address { flex: 1; }
        .address h3 { margin: 0 0 10px; font-size: 14px; color: #666; text-transform: uppercase; }
        .address p { margin: 0; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { padding: 12px; background: #f8f8f8; text-align: left; font-size: 12px; text-transform: uppercase; color: #666; }
        th:last-child { text-align: right; }
        .totals { text-align: right; margin-left: auto; width: 300px; }
        .totals div { padding: 8px 0; display: flex; justify-content: space-between; }
        .totals .grand-total { font-size: 20px; font-weight: bold; border-top: 2px solid #333; padding-top: 12px; margin-top: 8px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
<div class="invoice">
    <div class="header">
        <div class="company">
            <h1>Invoice</h1>
        </div>
        <div class="invoice-details">
            <h2>#{$order['order_number']}</h2>
            <p><strong>Date:</strong> {$order['created_at']}</p>
            <p><strong>Status:</strong> {$order['status']}</p>
            <p><strong>Payment:</strong> {$order['payment_method']}</p>
        </div>
    </div>

    <div class="addresses">
        <div class="address">
            <h3>Bill To</h3>
            <p>{$billingAddress}</p>
        </div>
        <div class="address">
            <h3>Ship To</h3>
            <p>{$shippingAddress}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Price</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            {$itemsHtml}
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal:</span><span>{$currencySymbol}{$order['subtotal']}</span></div>
        <div><span>Tax:</span><span>{$currencySymbol}{$order['tax_total']}</span></div>
        <div><span>Shipping:</span><span>{$currencySymbol}{$order['shipping_total']}</span></div>
        <div><span>Discount:</span><span>-{$currencySymbol}{$order['discount_total']}</span></div>
        <div class="grand-total"><span>Total:</span><span>{$currencySymbol}{$order['total']}</span></div>
    </div>

    <div class="footer">
        <p>Thank you for your purchase!</p>
    </div>
</div>
</body>
</html>
HTML;
    }

    protected static function formatAddress(array $address): string
    {
        $parts = [];
        if (!empty($address['name'])) $parts[] = htmlspecialchars($address['name']);
        if (!empty($address['company'])) $parts[] = htmlspecialchars($address['company']);
        if (!empty($address['line1'])) $parts[] = htmlspecialchars($address['line1']);
        if (!empty($address['line2'])) $parts[] = htmlspecialchars($address['line2']);
        if (!empty($address['city'])) $parts[] = htmlspecialchars($address['city']);
        if (!empty($address['state'])) $parts[] = htmlspecialchars($address['state']);
        if (!empty($address['zip'])) $parts[] = htmlspecialchars($address['zip']);
        if (!empty($address['country'])) $parts[] = htmlspecialchars($address['country']);
        if (!empty($address['phone'])) $parts[] = htmlspecialchars($address['phone']);

        return implode('<br>', $parts);
    }

    protected static function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
            'INR' => '₹', 'NGN' => '₦', 'KES' => 'KSh', 'GHS' => 'GH₵',
            'ZAR' => 'R', 'CAD' => 'C$', 'AUD' => 'A$', 'BRL' => 'R$',
            'CNY' => '¥', 'KRW' => '₩', 'RUB' => '₽', 'SEK' => 'kr',
            'CHF' => 'Fr', 'MXN' => 'MX$', 'TRY' => '₺', 'THB' => '฿',
        ];

        return $symbols[strtoupper($currency)] ?? $currency . ' ';
    }
}
