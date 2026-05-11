<?php
namespace Framework\Helper;

class ApiResponse
{
    public static function success($data = null, $message = 'Success', $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    public static function error($message = 'Error', $code = 400, $errors = [])
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ]);
        exit;
    }

    public static function pagination($data, $total, $perPage, $currentPage)
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => (int) $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $currentPage,
                'last_page' => (int) ceil($total / $perPage)
            ]
        ]);
        exit;
    }
}