<?php

namespace Framework\Api;

use Framework\Router\Route;

class OpenApiGenerator
{
    private array $spec;
    private array $routeAnnotations = [];

    public function __construct(array $info = [])
    {
        $this->spec = [
            'openapi' => '3.0.3',
            'info' => array_merge([
                'title' => 'Pype PHP API',
                'description' => 'API documentation auto-generated from routes',
                'version' => '1.0.0',
            ], $info),
            'servers' => [
                [
                    'url' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http' . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/api',
                    'description' => 'API Server',
                ],
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                    ],
                ],
            ],
        ];
    }

    public function addRoute(string $method, string $path, array $operation): self
    {
        $method = strtolower($method);
        $path = $this->convertPath($path);

        $this->spec['paths'][$path][$method] = array_merge([
            'summary' => ucfirst($method) . ' ' . $path,
            'operationId' => $method . '_' . str_replace(['/', '{', '}'], ['', '_', ''], $path),
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ], $operation);

        return $this;
    }

    public function addRouteFromAnnotation(string $method, string $path, string $handler, array $docBlock = []): self
    {
        $operation = $this->parseDocBlock($docBlock);
        return $this->addRoute($method, $path, $operation);
    }

    public function addSchema(string $name, array $schema): self
    {
        $this->spec['components']['schemas'][$name] = $schema;
        return $this;
    }

    public function autoScan(?callable $routeFilter = null): self
    {
        $reflection = new \ReflectionClass(Route::class);
        $property = $reflection->getProperty('routes');
        $property->setAccessible(true);
        $routes = $property->getValue();

        foreach ($routes as $route) {
            if ($routeFilter && !$routeFilter($route)) {
                continue;
            }

            $method = strtolower($route['method']);
            $path = $this->convertPath($route['path']);

            $operation = [
                'summary' => ucfirst($method) . ' ' . $path,
                'operationId' => $method . '_' . str_replace(['/', '{', '}'], ['', '_', ''], $path),
                'tags' => [$this->extractTag($route['handler'])],
            ];

            if (isset($route['middleware'])) {
                if (in_array(JwtMiddleware::class, $route['middleware']) || str_contains(implode(',', $route['middleware']), 'JwtMiddleware')) {
                    $operation['security'] = [['BearerAuth' => []]];
                }
            }

            $operation['responses'] = [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'success' => ['type' => 'boolean'],
                                    'data' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
                '400' => [
                    'description' => 'Bad request',
                    'content' => [
                        'application/problem+json' => [
                            'schema' => ['$ref' => '#/components/schemas/ProblemDetail'],
                        ],
                    ],
                ],
                '401' => [
                    'description' => 'Unauthorized',
                ],
                '404' => [
                    'description' => 'Not found',
                ],
            ];

            $this->spec['paths'][$path][$method] = $operation;
        }

        return $this;
    }

    public function getSpec(): array
    {
        $this->ensureDefaultSchemas();
        return $this->spec;
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        $this->ensureDefaultSchemas();
        return json_encode($this->spec, $flags);
    }

    public function toYaml(): string
    {
        if (function_exists('yaml_emit')) {
            return yaml_emit($this->getSpec());
        }

        return $this->simpleYamlEncode($this->getSpec());
    }

    public function serveSwaggerUI(string $title = 'API Documentation'): never
    {
        $specJson = htmlspecialchars($this->toJson(), ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        const spec = JSON.parse('{$specJson}');
        window.ui = SwaggerUIBundle({
            spec: spec,
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
            plugins: [SwaggerUIBundle.plugins.DownloadUrl],
            layout: 'StandaloneLayout',
        });
    </script>
</body>
</html>
HTML;
        exit;
    }

    public function serveScalarApi(): never
    {
        $specJson = htmlspecialchars($this->toJson(), ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Reference</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 2rem; background: #f5f5f5; }
        pre { background: #fff; padding: 1.5rem; border-radius: 8px; overflow-x: auto; }
        h1 { margin-top: 0; }
    </style>
</head>
<body>
    <h1>API Reference</h1>
    <pre id="spec"></pre>
    <script>
        const spec = JSON.parse('{$specJson}');
        document.getElementById('spec').textContent = JSON.stringify(spec, null, 2);
    </script>
</body>
</html>
HTML;
        exit;
    }

    private function convertPath(string $path): string
    {
        return preg_replace('/\{(\w+)\}/', '{$1}', $path);
    }

    private function extractTag(mixed $handler): string
    {
        if (is_array($handler) && isset($handler[0])) {
            $class = is_string($handler[0]) ? $handler[0] : get_class($handler[0]);
            $parts = explode('\\', $class);
            return str_replace('Controller', '', end($parts));
        }
        return 'Default';
    }

    private function parseDocBlock(array $docBlock): array
    {
        $operation = [];

        if (isset($docBlock['summary'])) {
            $operation['summary'] = $docBlock['summary'];
        }

        if (isset($docBlock['description'])) {
            $operation['description'] = $docBlock['description'];
        }

        if (isset($docBlock['tags'])) {
            $operation['tags'] = is_array($docBlock['tags']) ? $docBlock['tags'] : [$docBlock['tags']];
        }

        if (isset($docBlock['parameters'])) {
            $operation['parameters'] = $docBlock['parameters'];
        }

        if (isset($docBlock['requestBody'])) {
            $operation['requestBody'] = $docBlock['requestBody'];
        }

        if (isset($docBlock['responses'])) {
            $operation['responses'] = $docBlock['responses'];
        }

        if (isset($docBlock['security'])) {
            $operation['security'] = $docBlock['security'];
        }

        return $operation;
    }

    private function ensureDefaultSchemas(): void
    {
        if (!isset($this->spec['components']['schemas']['ProblemDetail'])) {
            $this->spec['components']['schemas']['ProblemDetail'] = [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'format' => 'uri', 'default' => 'about:blank'],
                    'title' => ['type' => 'string'],
                    'status' => ['type' => 'integer'],
                    'detail' => ['type' => 'string'],
                    'instance' => ['type' => 'string', 'format' => 'uri'],
                ],
                'required' => ['type', 'title', 'status'],
            ];
        }

        if (!isset($this->spec['components']['schemas']['ErrorResponse'])) {
            $this->spec['components']['schemas']['ErrorResponse'] = [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'message' => ['type' => 'string'],
                    'errors' => ['type' => 'array', 'items' => ['type' => 'object']],
                ],
            ];
        }

        if (!isset($this->spec['components']['schemas']['SuccessResponse'])) {
            $this->spec['components']['schemas']['SuccessResponse'] = [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'object'],
                    'meta' => ['type' => 'object'],
                ],
            ];
        }
    }

    private function simpleYamlEncode(array $data, int $indent = 0): string
    {
        $result = '';
        $pad = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    $result .= "{$pad}{$key}: []\n";
                } else {
                    $isList = array_keys($value) === range(0, count($value) - 1);
                    if ($isList) {
                        $result .= "{$pad}{$key}:\n";
                        foreach ($value as $item) {
                            if (is_array($item)) {
                                $itemYaml = $this->simpleYamlEncode($item, $indent + 1);
                                $firstLine = true;
                                foreach (explode("\n", $itemYaml) as $line) {
                                    $result .= $firstLine ? "{$pad}- {$line}\n" : "{$pad}  {$line}\n";
                                    $firstLine = false;
                                }
                            } else {
                                $result .= "{$pad}- " . $this->yamlValue($item) . "\n";
                            }
                        }
                    } else {
                        $result .= "{$pad}{$key}:\n" . $this->simpleYamlEncode($value, $indent + 1);
                    }
                }
            } else {
                $result .= "{$pad}{$key}: " . $this->yamlValue($value) . "\n";
            }
        }

        return $result;
    }

    private function yamlValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            if (str_contains($value, ':') || str_contains($value, '#') || str_contains($value, "\n") || empty($value)) {
                return '"' . str_replace('"', '\\"', $value) . '"';
            }
            return $value;
        }
        return (string) $value;
    }
}
