<?php

namespace Framework\Commerce;

/**
 * Shipping Calculator
 * Supports flat rate, weight-based, and configurable shipping zones.
 *
 * Usage:
 * Shipping::addFlatRate('standard', 9.99);
 * Shipping::addFlatRate('express', 19.99);
 * Shipping::addWeightRate(0, 5, 4.99);
 * Shipping::addWeightRate(5, 20, 9.99);
 * Shipping::addWeightRate(20, null, 14.99);
 * Shipping::calculate($cartWeight, $country, 'standard');
 * Shipping::getRates($cartWeight, $country);
 */
class Shipping
{
    protected static array $flatRates = [];
    protected static array $weightRates = [];
    protected static array $zones = [];
    protected static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::loadRatesFromDatabase();
        self::$initialized = true;
    }

    public static function addFlatRate(string $method, float $price, array $options = []): void
    {
        self::$flatRates[$method] = [
            'price' => $price,
            'min' => $options['min'] ?? 0,
            'max' => $options['max'] ?? null,
            'countries' => $options['countries'] ?? [],
            'free_threshold' => $options['free_threshold'] ?? null,
        ];
    }

    public static function addWeightRate(float $minWeight, ?float $maxWeight, float $price): void
    {
        self::$weightRates[] = [
            'min' => $minWeight,
            'max' => $maxWeight,
            'price' => $price,
        ];
    }

    public static function addZone(string $country, array $methods): void
    {
        self::$zones[strtoupper($country)] = $methods;
    }

    public static function calculate(float $weight, string $country, string $method, float $cartTotal = 0): float
    {
        self::init();

        if (isset(self::$flatRates[$method])) {
            return self::calculateFlatRate($method, $cartTotal);
        }

        if (!empty(self::$weightRates)) {
            return self::calculateWeightRate($weight);
        }

        return 0;
    }

    public static function getRates(float $weight, string $country, float $cartTotal = 0): array
    {
        self::init();
        $rates = [];

        foreach (self::$flatRates as $method => $config) {
            if (!self::isCountryEligible($config, $country)) {
                continue;
            }

            $price = $config['price'];
            if ($config['free_threshold'] && $cartTotal >= $config['free_threshold']) {
                $price = 0;
            }

            $rates[$method] = [
                'name' => ucwords(str_replace('_', ' ', $method)),
                'price' => $price,
                'estimated_days' => $method === 'express' ? '1-2' : ($method === 'priority' ? '3-5' : '5-7'),
            ];
        }

        if (!empty(self::$weightRates)) {
            $weightPrice = self::calculateWeightRate($weight);
            $rates['weight_based'] = [
                'name' => 'Weight-Based Shipping',
                'price' => $weightPrice,
                'estimated_days' => '5-7',
            ];
        }

        return $rates;
    }

    public static function getFreeShippingThreshold(): ?float
    {
        foreach (self::$flatRates as $config) {
            if (isset($config['free_threshold'])) {
                return $config['free_threshold'];
            }
        }
        return null;
    }

    public static function isFreeShippingEligible(float $cartTotal): bool
    {
        $threshold = self::getFreeShippingThreshold();
        return $threshold !== null && $cartTotal >= $threshold;
    }

    protected static function calculateFlatRate(string $method, float $cartTotal): float
    {
        $config = self::$flatRates[$method];
        $price = $config['price'];

        if ($config['free_threshold'] && $cartTotal >= $config['free_threshold']) {
            return 0;
        }

        return $price;
    }

    protected static function calculateWeightRate(float $weight): float
    {
        foreach (self::$weightRates as $rate) {
            $inMinRange = $weight >= $rate['min'];
            $inMaxRange = $rate['max'] === null || $weight <= $rate['max'];

            if ($inMinRange && $inMaxRange) {
                return $rate['price'];
            }
        }

        return end(self::$weightRates)['price'] ?? 0;
    }

    protected static function isCountryEligible(array $config, string $country): bool
    {
        if (empty($config['countries'])) {
            return true;
        }

        return in_array(strtoupper($country), array_map('strtoupper', $config['countries']));
    }

    protected static function loadRatesFromDatabase(): void
    {
        try {
            $model = new \Framework\Model\Model();
            $result = $model->rawQuery("SELECT * FROM shipping_rates WHERE is_active = 1 ORDER BY sort_order ASC");

            if ($result) {
                while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                    if ($row['type'] === 'flat') {
                        self::addFlatRate($row['method'], (float) $row['price'], [
                            'free_threshold' => $row['free_threshold'] ?: null,
                            'countries' => $row['countries'] ? explode(',', $row['countries']) : [],
                        ]);
                    } elseif ($row['type'] === 'weight') {
                        self::addWeightRate(
                            (float) $row['min_weight'],
                            $row['max_weight'] ? (float) $row['max_weight'] : null,
                            (float) $row['price']
                        );
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }
}
