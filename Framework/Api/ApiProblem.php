<?php

namespace Framework\Api;

class ApiProblem
{
    private string $type = 'about:blank';
    private string $title = '';
    private int $status;
    private string $detail = '';
    private string $instance = '';
    private array $extensions = [];

    public function __construct(int $status)
    {
        $this->status = $status;
    }

    public static function make(int $status): self
    {
        return new self($status);
    }

    public static function badRequest(string $detail = 'Bad request'): self
    {
        return (new self(400))->title('Bad Request')->detail($detail);
    }

    public static function unauthorized(string $detail = 'Authentication required'): self
    {
        return (new self(401))->title('Unauthorized')->detail($detail);
    }

    public static function forbidden(string $detail = 'Access denied'): self
    {
        return (new self(403))->title('Forbidden')->detail($detail);
    }

    public static function notFound(string $detail = 'Resource not found'): self
    {
        return (new self(404))->title('Not Found')->detail($detail);
    }

    public static function methodNotAllowed(string $detail = 'Method not allowed'): self
    {
        return (new self(405))->title('Method Not Allowed')->detail($detail);
    }

    public static function conflict(string $detail = 'Conflict'): self
    {
        return (new self(409))->title('Conflict')->detail($detail);
    }

    public static function unprocessableEntity(string $detail = 'Validation failed', array $validationErrors = []): self
    {
        return (new self(422))
            ->title('Unprocessable Entity')
            ->detail($detail)
            ->extension('validation_errors', $validationErrors);
    }

    public static function tooManyRequests(string $detail = 'Rate limit exceeded', int $retryAfter = 60): self
    {
        return (new self(429))
            ->title('Too Many Requests')
            ->detail($detail)
            ->extension('retry_after', $retryAfter);
    }

    public static function internalError(string $detail = 'Internal server error'): self
    {
        return (new self(500))->title('Internal Server Error')->detail($detail);
    }

    public static function notImplemented(string $detail = 'Not implemented'): self
    {
        return (new self(501))->title('Not Implemented')->detail($detail);
    }

    public static function serviceUnavailable(string $detail = 'Service unavailable'): self
    {
        return (new self(503))->title('Service Unavailable')->detail($detail);
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function detail(string $detail): self
    {
        $this->detail = $detail;
        return $this;
    }

    public function instance(string $instance): self
    {
        $this->instance = $instance;
        return $this;
    }

    public function extension(string $key, mixed $value): self
    {
        $this->extensions[$key] = $value;
        return $this;
    }

    public function extensions(array $extensions): self
    {
        $this->extensions = array_merge($this->extensions, $extensions);
        return $this;
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/problem+json');

        $response = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ];

        if (!empty($this->instance)) {
            $response['instance'] = $this->instance;
        }

        foreach ($this->extensions as $key => $value) {
            $response[$key] = $value;
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function toJson(): string
    {
        $response = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ];

        if (!empty($this->instance)) {
            $response['instance'] = $this->instance;
        }

        foreach ($this->extensions as $key => $value) {
            $response[$key] = $value;
        }

        return json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
