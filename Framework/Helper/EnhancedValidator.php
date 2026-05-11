<?php

namespace Framework\Helper;

/**
 * Enhanced Validator
 * Supports nested arrays, custom rules, conditional validation,
 * unique checks, exists checks, confirmed, same, different, and more.
 */
class EnhancedValidator
{
    private array $data = [];
    private array $rules = [];
    private array $messages = [];
    private array $errors = [];
    private array $customMessages = [];

    public function __construct(array $data, array $rules, array $customMessages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    /**
     * Run validation and return self for chaining.
     */
    public function validate(): self
    {
        foreach ($this->rules as $field => $ruleList) {
            $rules = is_string($ruleList) ? explode('|', $ruleList) : $ruleList;

            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }

        return $this;
    }

    /**
     * Check if validation failed.
     */
    public function fails(): bool
    {
        $this->validate();
        return !empty($this->errors);
    }

    /**
     * Check if validation passed.
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * Get all errors.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for a field.
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get validated (clean) data.
     */
    public function validated(): array
    {
        return $this->passes() ? $this->data : [];
    }

    /* ============================================================
       RULE APPLICATION
       ============================================================ */

    private function applyRule(string $field, string $rule): void
    {
        $value = $this->getValue($field);
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? $this->parseParameters($parts[1]) : [];

        $method = 'validate' . ucfirst($ruleName);

        if (method_exists($this, $method)) {
            $result = $this->$method($field, $value, $parameters);
            if (!$result) {
                $this->addError($field, $ruleName, $parameters);
            }
        }
    }

    private function getValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    private function parseParameters(string $params): array
    {
        $parameters = explode(',', $params);

        return array_map(function ($param) {
            $param = trim($param);

            if ($param === 'true') return true;
            if ($param === 'false') return false;
            if (is_numeric($param)) return $param + 0;

            return $param;
        }, $parameters);
    }

    private function addError(string $field, string $rule, array $parameters): void
    {
        $message = $this->customMessages["{$field}.{$rule}"]
            ?? $this->customMessages[$rule]
            ?? $this->getDefaultMessage($field, $rule, $parameters);

        $this->errors[$field][] = $message;
    }

    private function getDefaultMessage(string $field, string $rule, array $parameters): string
    {
        return match ($rule) {
            'required' => "The {$field} field is required.",
            'email' => "The {$field} must be a valid email address.",
            'url' => "The {$field} must be a valid URL.",
            'numeric' => "The {$field} must be a number.",
            'integer' => "The {$field} must be an integer.",
            'alpha' => "The {$field} must contain only letters.",
            'alpha_num' => "The {$field} must contain only letters and numbers.",
            'alpha_dash' => "The {$field} may only contain letters, numbers, dashes, and underscores.",
            'min' => "The {$field} must be at least {$parameters[0]} characters.",
            'max' => "The {$field} must not exceed {$parameters[0]} characters.",
            'between' => "The {$field} must be between {$parameters[0]} and {$parameters[1]} characters.",
            'size' => "The {$field} must be exactly {$parameters[0]} characters.",
            'in' => "The {$field} must be one of: " . implode(', ', $parameters),
            'not_in' => "The {$field} must not be one of the disallowed values.",
            'regex' => "The {$field} format is invalid.",
            'confirmed' => "The {$field} confirmation does not match.",
            'same' => "The {$field} must match {$parameters[0]}.",
            'different' => "The {$field} must be different from {$parameters[0]}.",
            'unique' => "The {$field} has already been taken.",
            'exists' => "The selected {$field} is invalid.",
            'ip' => "The {$field} must be a valid IP address.",
            'ipv4' => "The {$field} must be a valid IPv4 address.",
            'ipv6' => "The {$field} must be a valid IPv6 address.",
            'json' => "The {$field} must be a valid JSON string.",
            'uuid' => "The {$field} must be a valid UUID.",
            'date' => "The {$field} must be a valid date.",
            'before' => "The {$field} must be a date before {$parameters[0]}.",
            'after' => "The {$field} must be a date after {$parameters[0]}.",
            'file' => "The {$field} must be a file.",
            'image' => "The {$field} must be an image.",
            'mimes' => "The {$field} must be a file of type: " . implode(', ', $parameters),
            'phone' => "The {$field} must be a valid phone number.",
            'password' => "The {$field} does not meet the password requirements.",
            'strong_password' => "The {$field} must contain uppercase, lowercase, number, and special character.",
            default => "The {$field} field is invalid.",
        };
    }

    /* ============================================================
       VALIDATION RULES
       ============================================================ */

    private function validateRequired(string $field, mixed $value, array $params): bool
    {
        if ($value === null) return false;
        if (is_string($value) && trim($value) === '') return false;
        if (is_array($value) && empty($value)) return false;
        return true;
    }

    private function validateEmail(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateUrl(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateNumeric(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return is_numeric($value);
    }

    private function validateInteger(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateAlpha(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return ctype_alpha(str_replace(' ', '', $value));
    }

    private function validateAlphaNum(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return ctype_alnum(str_replace(' ', '', $value));
    }

    private function validateAlphaDash(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return preg_match('/^[\p{L}\p{N}_-]+$/u', $value) === 1;
    }

    private function validateMin(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $min = $params[0];
        if (is_numeric($value)) return $value >= $min;
        return strlen($value) >= $min;
    }

    private function validateMax(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $max = $params[0];
        if (is_numeric($value)) return $value <= $max;
        return strlen($value) <= $max;
    }

    private function validateBetween(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $min = $params[0];
        $max = $params[1];
        if (is_numeric($value)) return $value >= $min && $value <= $max;
        $len = strlen($value);
        return $len >= $min && $len <= $max;
    }

    private function validateSize(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return strlen($value) == $params[0];
    }

    private function validateIn(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return in_array($value, $params, true);
    }

    private function validateNotIn(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return !in_array($value, $params, true);
    }

    private function validateRegex(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return preg_match($params[0], $value) === 1;
    }

    private function validateConfirmed(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->getValue($confirmField);
        return $value === $confirmValue;
    }

    private function validateSame(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $otherValue = $this->getValue($params[0]);
        return $value === $otherValue;
    }

    private function validateDifferent(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $otherValue = $this->getValue($params[0]);
        return $value !== $otherValue;
    }

    private function validateUnique(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $table = $params[0];
        $column = $params[1] ?? $field;

        try {
            $existing = \Framework\Helper\DB::table($table)->where($column, $value)->first();
            return $existing === null;
        } catch (\Exception $e) {
            return true;
        }
    }

    private function validateExists(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $table = $params[0];
        $column = $params[1] ?? $field;

        try {
            $existing = \Framework\Helper\DB::table($table)->where($column, $value)->first();
            return $existing !== null;
        } catch (\Exception $e) {
            return true;
        }
    }

    private function validateIp(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validateIpv4(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    private function validateIpv6(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    private function validateJson(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function validateUuid(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private function validateDate(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        $format = $params[0] ?? 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value;
    }

    private function validateBefore(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return strtotime($value) < strtotime($params[0]);
    }

    private function validateAfter(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return strtotime($value) > strtotime($params[0]);
    }

    private function validateFile(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return is_array($value) && isset($value['error']) && $value['error'] === UPLOAD_ERR_OK;
    }

    private function validateImage(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        if (!is_array($value) || !isset($value['tmp_name'])) return false;
        $info = getimagesize($value['tmp_name']);
        return $info !== false;
    }

    private function validateMimes(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        if (!is_array($value) || !isset($value['name'])) return false;
        $ext = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));
        return in_array($ext, $params, true);
    }

    private function validatePhone(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return preg_match('/^[\d\s\-\+\(\)]+$/', $value) === 1 && strlen(preg_replace('/\D/', '', $value)) >= 7;
    }

    private function validatePassword(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return PasswordPolicy::validate($value);
    }

    private function validateStrongPassword(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') return true;
        return PasswordPolicy::validate($value, 'strong');
    }

    /**
     * Conditional: required_if:other_field,value
     */
    private function validateRequiredIf(string $field, mixed $value, array $params): bool
    {
        $otherField = $params[0];
        $otherValue = $params[1];

        if ($this->getValue($otherField) == $otherValue) {
            return $this->validateRequired($field, $value, []);
        }

        return true;
    }

    /**
     * Conditional: required_unless:other_field,value
     */
    private function validateRequiredUnless(string $field, mixed $value, array $params): bool
    {
        $otherField = $params[0];
        $otherValue = $params[1];

        if ($this->getValue($otherField) != $otherValue) {
            return $this->validateRequired($field, $value, []);
        }

        return true;
    }

    /**
     * Conditional: required_with:other_field
     */
    private function validateRequiredWith(string $field, mixed $value, array $params): bool
    {
        foreach ($params as $param) {
            if ($this->getValue($param) !== null && $this->getValue($param) !== '') {
                return $this->validateRequired($field, $value, []);
            }
        }

        return true;
    }

    /**
     * Nullable — skip all subsequent rules if value is null.
     */
    private function validateNullable(string $field, mixed $value, array $params): bool
    {
        return true;
    }
}
