<?php

namespace Framework\Api;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $statusCode = 200, array $meta = []): never
    {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => empty($meta) ? null : $meta,
        ], $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, mixed $data = null, array $errors = []): never
    {
        self::send([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'errors' => empty($errors) ? null : $errors,
        ], $statusCode);
    }

    public static function created(mixed $data = null, string $message = 'Resource created', ?string $location = null): never
    {
        if ($location) {
            header("Location: {$location}");
        }
        self::success($data, $message, 201);
    }

    public static function noContent(string $message = 'No content'): never
    {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => null,
        ], 204);
    }

    public static function notFound(string $message = 'Resource not found'): never
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }

    public static function validationError(array $errors, string $message = 'Validation failed'): never
    {
        self::error($message, 422, null, $errors);
    }

    public static function tooManyRequests(int $retryAfter = 60, string $message = 'Too many requests'): never
    {
        header("Retry-After: {$retryAfter}");
        self::error($message, 429);
    }

    public static function paginated(array $items, int $page, int $perPage, int $total, string $message = 'OK', array $extraMeta = []): never
    {
        $totalPages = (int) ceil($total / $perPage);

        self::success($items, $message, 200, array_merge([
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ],
        ], $extraMeta));
    }

    public static function send(array $response, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        if ($statusCode === 204) {
            exit;
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
