<?php

namespace Framework\Http;

/**
 * Form Request — dedicated validation classes per request.
 *
 * Usage:
 * class StoreUserRequest extends FormRequest
 * {
 *     public function authorize(): bool { return can('create', User::class); }
 *     public function rules(): array { return ['name' => 'required', 'email' => 'required|email|unique:users']; }
 *     public function messages(): array { return ['email.required' => 'Please provide your email']; }
 * }
 */
abstract class FormRequest
{
    protected array $data = [];
    protected array $errors = [];

    public function __construct()
    {
        $this->data = array_merge($_GET, $_POST, $_FILES);
    }

    public static function validate(): static
    {
        $request = new static();

        if (!$request->authorize()) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $validator = \Framework\Helper\EnhancedValidator::make(
            $request->data(),
            $request->rules(),
            $request->messages()
        );

        if ($validator->fails()) {
            $request->errors = $validator->errors();
            $request->failedValidation();
        }

        return $request;
    }

    abstract public function rules(): array;

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [];
    }

    public function data(): array
    {
        return $this->data;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function only(array $keys): array
    {
        return array_filter(
            array_map(fn($k) => $this->input($k), $keys),
            fn($v) => $v !== null
        );
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function failedValidation(): void
    {
        if (empty($this->errors)) {
            return;
        }

        if (isAjax()) {
            json([
                'success' => false,
                'errors' => $this->errors,
            ], 422);
        }

        $_SESSION['errors'] = $this->errors;
        $_SESSION['old_input'] = $this->data;

        redirectBack();
        exit;
    }
}
