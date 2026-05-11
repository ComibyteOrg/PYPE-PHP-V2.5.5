<?php

namespace Framework\Api;

use Framework\Security\Jwt;

class JwtMiddleware
{
    private array $requiredScopes;
    private bool $allowRefresh;

    public function __construct(array $requiredScopes = [], bool $allowRefresh = false)
    {
        $this->requiredScopes = $requiredScopes;
        $this->allowRefresh = $allowRefresh;
    }

    public function handle(array $params, callable $next)
    {
        $token = Jwt::getBearerToken();

        if ($token === false) {
            return ApiProblem::unauthorized('Missing or invalid Authorization header. Use "Bearer <token>".')->send();
        }

        $payload = Jwt::verifyAccessToken($token);

        if ($payload === false) {
            if ($this->allowRefresh) {
                $refreshToken = $_POST['refresh_token'] ?? $_GET['refresh_token'] ?? '';
                if (!empty($refreshToken)) {
                    $newTokens = Jwt::refreshToken($refreshToken);
                    if ($newTokens !== false) {
                        header('X-New-Access-Token: ' . $newTokens['access_token']);
                        header('X-New-Refresh-Token: ' . $newTokens['refresh_token']);
                        $payload = Jwt::verifyAccessToken($newTokens['access_token']);
                    }
                }
            }

            if ($payload === false) {
                return ApiProblem::unauthorized('Token is invalid or expired.')->send();
            }
        }

        foreach ($this->requiredScopes as $scope) {
            if (!Jwt::hasScope($payload, $scope)) {
                return ApiProblem::forbidden("Missing required scope: {$scope}")->send();
            }
        }

        $_SERVER['JWT_PAYLOAD'] = $payload;
        $_SERVER['JWT_USER_ID'] = $payload['sub'] ?? $payload['user_id'] ?? null;
        $_SERVER['JWT_SCOPES'] = $payload['scopes'] ?? [];

        return $next($params);
    }

    public static function make(array $scopes = []): self
    {
        return new self($scopes);
    }

    public static function optional(array $scopes = []): self
    {
        return new self($scopes, true);
    }
}
