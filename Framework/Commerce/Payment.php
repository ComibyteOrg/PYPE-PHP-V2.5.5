<?php

namespace Framework\Commerce;

/**
 * Payment Gateway Interface
 * All payment drivers must implement this interface.
 */
interface PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $options = []): array;
    public function refund(string $transactionId, float $amount, string $currency): array;
    public function verify(string $transactionId): array;
    public function webhook(array $payload): array;
    public function configure(array $config): void;
    public function name(): string;
}

/**
 * Payment Gateway Driver Base
 * Provides shared functionality for all payment drivers.
 */
abstract class PaymentGateway implements PaymentGatewayInterface
{
    protected array $config = [];
    protected string $baseUrl;
    protected string $apiKey;
    protected string $secretKey;

    public function configure(array $config): void
    {
        $this->config = $config;
        $this->apiKey = $config['api_key'] ?? '';
        $this->secretKey = $config['secret_key'] ?? '';
        $this->baseUrl = $config['base_url'] ?? '';
    }

    abstract public function name(): string;
    abstract public function charge(float $amount, string $currency, array $options = []): array;
    abstract public function refund(string $transactionId, float $amount, string $currency): array;
    abstract public function verify(string $transactionId): array;
    abstract public function webhook(array $payload): array;

    protected function makeRequest(string $url, string $method = 'POST', array $data = [], array $headers = []): array
    {
        $ch = curl_init();

        $defaultHeaders = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        ]);

        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT' && !empty($data)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $body = json_decode($response, true) ?? [];

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $body,
        ];
    }

    protected function formatAmount(float $amount, string $currency = 'USD'): int
    {
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'BIF', 'CLP', 'GNF', 'KMF', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return (int) $amount;
        }
        return (int) round($amount * 100);
    }
}

class StripeGateway extends PaymentGateway
{
    protected string $baseUrl = 'https://api.stripe.com/v1';

    public function name(): string
    {
        return 'stripe';
    }

    public function charge(float $amount, string $currency, array $options = []): array
    {
        $data = [
            'amount' => $this->formatAmount($amount, $currency),
            'currency' => strtolower($currency),
            'source' => $options['token'] ?? $options['payment_method'] ?? '',
            'description' => $options['description'] ?? 'Payment',
        ];

        if (isset($options['customer_id'])) {
            $data['customer'] = $options['customer_id'];
        }

        if (isset($options['metadata']) && is_array($options['metadata'])) {
            foreach ($options['metadata'] as $key => $value) {
                $data["metadata[{$key}]"] = $value;
            }
        }

        return $this->makeRequest($this->baseUrl . '/charges', 'POST', $data, [
            'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
        ]);
    }

    public function refund(string $transactionId, float $amount, string $currency): array
    {
        $data = ['charge' => $transactionId];
        if ($amount > 0) {
            $data['amount'] = $this->formatAmount($amount, $currency);
        }

        return $this->makeRequest($this->baseUrl . '/refunds', 'POST', $data, [
            'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
        ]);
    }

    public function verify(string $transactionId): array
    {
        return $this->makeRequest($this->baseUrl . '/charges/' . urlencode($transactionId), 'GET', [], [
            'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
        ]);
    }

    public function webhook(array $payload): array
    {
        $event = $payload;
        $type = $event['type'] ?? '';

        return match ($type) {
            'payment_intent.succeeded' => ['event' => 'payment_success', 'data' => $event['data']['object']],
            'payment_intent.payment_failed' => ['event' => 'payment_failed', 'data' => $event['data']['object']],
            'charge.refunded' => ['event' => 'refunded', 'data' => $event['data']['object']],
            'charge.disputed.created' => ['event' => 'disputed', 'data' => $event['data']['object']],
            default => ['event' => 'unknown', 'data' => $event],
        };
    }
}

class PayPalGateway extends PaymentGateway
{
    protected string $baseUrl;

    public function configure(array $config): void
    {
        parent::configure($config);
        $this->baseUrl = ($config['sandbox'] ?? true)
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function name(): string
    {
        return 'paypal';
    }

    public function charge(float $amount, string $currency, array $options = []): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Failed to get access token'];
        }

        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'description' => $options['description'] ?? 'Payment',
            ]],
        ];

        return $this->makeRequest($this->baseUrl . '/v2/checkout/orders', 'POST', $data, [
            'Authorization: Bearer ' . $token,
        ]);
    }

    public function refund(string $transactionId, float $amount, string $currency): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Failed to get access token'];
        }

        $data = ['amount' => ['currency_code' => strtoupper($currency), 'value' => number_format($amount, 2, '.', '')]];
        return $this->makeRequest($this->baseUrl . '/v2/payments/captures/' . urlencode($transactionId) . '/refund', 'POST', $data, [
            'Authorization: Bearer ' . $token,
        ]);
    }

    public function verify(string $transactionId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Failed to get access token'];
        }

        return $this->makeRequest($this->baseUrl . '/v2/checkout/orders/' . urlencode($transactionId), 'GET', [], [
            'Authorization: Bearer ' . $token,
        ]);
    }

    public function webhook(array $payload): array
    {
        $eventType = $payload['event_type'] ?? '';
        $resource = $payload['resource'] ?? [];

        return match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => ['event' => 'payment_success', 'data' => $resource],
            'PAYMENT.CAPTURE.DENIED' => ['event' => 'payment_failed', 'data' => $resource],
            'PAYMENT.CAPTURE.REFUNDED' => ['event' => 'refunded', 'data' => $resource],
            default => ['event' => 'unknown', 'data' => $payload],
        };
    }

    protected function getAccessToken(): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $this->apiKey . ':' . $this->secretKey,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
}

class FlutterwaveGateway extends PaymentGateway
{
    protected string $baseUrl;

    public function configure(array $config): void
    {
        parent::configure($config);
        $this->baseUrl = ($config['sandbox'] ?? true)
            ? 'https://api.flutterwave.com'
            : 'https://api.flutterwave.com';
    }

    public function name(): string
    {
        return 'flutterwave';
    }

    public function charge(float $amount, string $currency, array $options = []): array
    {
        $data = [
            'tx_ref' => $options['tx_ref'] ?? uniqid('tx_'),
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'redirect_url' => $options['redirect_url'] ?? '',
            'customer' => [
                'email' => $options['email'] ?? '',
                'name' => $options['name'] ?? '',
            ],
        ];

        return $this->makeRequest($this->baseUrl . '/v3/payments', 'POST', $data);
    }

    public function refund(string $transactionId, float $amount, string $currency): array
    {
        $data = ['transaction_id' => $transactionId];
        if ($amount > 0) {
            $data['amount'] = $amount;
        }

        return $this->makeRequest($this->baseUrl . '/v3/refunds', 'POST', $data);
    }

    public function verify(string $transactionId): array
    {
        return $this->makeRequest($this->baseUrl . '/v3/transactions/' . urlencode($transactionId) . '/verify', 'GET');
    }

    public function webhook(array $payload): array
    {
        $status = $payload['status'] ?? '';
        $data = $payload['data'] ?? [];

        return match ($status) {
            'successful' => ['event' => 'payment_success', 'data' => $data],
            'failed' => ['event' => 'payment_failed', 'data' => $data],
            default => ['event' => 'unknown', 'data' => $payload],
        };
    }
}

class PaystackGateway extends PaymentGateway
{
    protected string $baseUrl;

    public function configure(array $config): void
    {
        parent::configure($config);
        $this->baseUrl = 'https://api.paystack.co';
    }

    public function name(): string
    {
        return 'paystack';
    }

    public function charge(float $amount, string $currency, array $options = []): array
    {
        $data = [
            'email' => $options['email'] ?? '',
            'amount' => $this->formatAmount($amount, $currency),
            'currency' => strtolower($currency),
            'callback_url' => $options['callback_url'] ?? '',
            'metadata' => $options['metadata'] ?? [],
        ];

        return $this->makeRequest($this->baseUrl . '/transaction/initialize', 'POST', $data);
    }

    public function refund(string $transactionId, float $amount, string $currency): array
    {
        $data = ['transaction' => $transactionId];
        if ($amount > 0) {
            $data['amount'] = $this->formatAmount($amount, $currency);
        }

        return $this->makeRequest($this->baseUrl . '/refund', 'POST', $data);
    }

    public function verify(string $transactionId): array
    {
        return $this->makeRequest($this->baseUrl . '/transaction/verify/' . urlencode($transactionId), 'GET');
    }

    public function webhook(array $payload): array
    {
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        return match ($event) {
            'charge.success' => ['event' => 'payment_success', 'data' => $data],
            'charge.failed' => ['event' => 'payment_failed', 'data' => $data],
            'refund.processed' => ['event' => 'refunded', 'data' => $data],
            default => ['event' => 'unknown', 'data' => $payload],
        };
    }
}

/**
 * Payment Facade
 * Easy access to any configured payment gateway.
 */
class Payment
{
    private static array $gateways = [];
    private static string $defaultGateway = 'stripe';

    public static function register(string $name, PaymentGatewayInterface $gateway): void
    {
        self::$gateways[$name] = $gateway;
    }

    public static function setDefault(string $name): void
    {
        self::$defaultGateway = $name;
    }

    public static function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: self::$defaultGateway;

        if (!isset(self::$gateways[$name])) {
            throw new \RuntimeException("Payment gateway '{$name}' is not registered.");
        }

        return self::$gateways[$name];
    }

    public static function charge(float $amount, string $currency, array $options = [], ?string $gateway = null): array
    {
        return self::gateway($gateway)->charge($amount, $currency, $options);
    }

    public static function refund(string $transactionId, float $amount, string $currency, ?string $gateway = null): array
    {
        return self::gateway($gateway)->refund($transactionId, $amount, $currency);
    }

    public static function verify(string $transactionId, ?string $gateway = null): array
    {
        return self::gateway($gateway)->verify($transactionId);
    }
}
