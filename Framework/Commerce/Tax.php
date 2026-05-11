<?php

namespace Framework\Commerce;

/**
 * Tax Calculator
 * Location-based tax calculation with support for multiple tax rates.
 *
 * Usage:
 * Tax::addRate('US', 'CA', 0.0725);
 * Tax::addRate('US', 'NY', 0.08);
 * Tax::calculate($subtotal, 'US', 'CA');
 * Tax::calculate($subtotal, 'GB');
 */
class Tax
{
    protected static array $rates = [];
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

    public static function addRate(string $country, string $region, float $rate, ?string $city = null): void
    {
        $key = self::getZoneKey($country, $region, $city);
        self::$rates[$key] = $rate;
    }

    public static function calculate(float $subtotal, string $country, string $region = '', string $city = '', ?float $taxableAmount = null): array
    {
        self::init();

        $taxableAmount = $taxableAmount ?? $subtotal;
        $rate = self::getRate($country, $region, $city);
        $tax = round($taxableAmount * $rate, 2);

        return [
            'rate' => $rate,
            'rate_percentage' => $rate * 100,
            'amount' => $tax,
            'subtotal' => $subtotal,
            'total' => round($subtotal + $tax, 2),
            'country' => $country,
            'region' => $region,
            'city' => $city,
        ];
    }

    public static function calculateForCart(float $subtotal, array $shippingAddress): array
    {
        $country = $shippingAddress['country'] ?? '';
        $region = $shippingAddress['state'] ?? $shippingAddress['region'] ?? $shippingAddress['province'] ?? '';
        $city = $shippingAddress['city'] ?? '';

        return self::calculate($subtotal, $country, $region, $city);
    }

    public static function isTaxExempt(string $country, string $region = ''): bool
    {
        $exemptCountries = ['AE', 'BH', 'KW', 'OM', 'QA', 'SA'];
        if (in_array(strtoupper($country), $exemptCountries)) {
            return true;
        }

        $key = self::getZoneKey($country, $region);
        return isset(self::$zones[$key]) && self::$zones[$key]['exempt'];
    }

    public static function getRate(string $country, string $region = '', string $city = ''): float
    {
        $cityKey = self::getZoneKey($country, $region, $city);
        if (isset(self::$rates[$cityKey])) {
            return self::$rates[$cityKey];
        }

        $regionKey = self::getZoneKey($country, $region);
        if (isset(self::$rates[$regionKey])) {
            return self::$rates[$regionKey];
        }

        $countryKey = self::getZoneKey($country);
        if (isset(self::$rates[$countryKey])) {
            return self::$rates[$countryKey];
        }

        return self::$rates['default'] ?? 0;
    }

    public static function addZone(string $country, string $region = '', array $options = []): void
    {
        $key = self::getZoneKey($country, $region);
        self::$zones[$key] = [
            'country' => $country,
            'region' => $region,
            'exempt' => $options['exempt'] ?? false,
            'tax_included' => $options['tax_included'] ?? false,
        ];
    }

    public static function isTaxIncluded(string $country, string $region = ''): bool
    {
        $key = self::getZoneKey($country, $region);
        return isset(self::$zones[$key]) && self::$zones[$key]['tax_included'];
    }

    protected static function getZoneKey(string $country, string $region = '', string $city = ''): string
    {
        return strtoupper(trim("{$country}-{$region}-{$city}", '-'));
    }

    protected static function loadRatesFromDatabase(): void
    {
        try {
            $model = new \Framework\Model\Model();
            $result = $model->rawQuery("SELECT * FROM tax_rates WHERE is_active = 1");

            if ($result) {
                while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                    $key = self::getZoneKey($row['country'], $row['region'] ?? '', $row['city'] ?? '');
                    self::$rates[$key] = (float) $row['rate'];

                    if ($row['is_default']) {
                        self::$rates['default'] = (float) $row['rate'];
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }
}
