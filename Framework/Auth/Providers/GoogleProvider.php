<?php
namespace Framework\Auth\Providers;

use Framework\Helper\Logger;

class GoogleProvider
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct($clientId, $clientSecret, $redirectUri)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    public function redirectToAuthUrl()
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'email profile',
            'response_type' => 'code',
            'access_type' => 'offline'
        ];

        $authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
        Logger::info("Redirecting to Google OAuth", ['url' => $authUrl]);
        header("Location: $authUrl");
        exit;
    }

    public function getUser($code)
    {
        try {
            // Exchange code for access token
            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $tokenData = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
                'code' => $code
            ];

            $tokenResponse = $this->makeRequest($tokenUrl, $tokenData);

            if (isset($tokenResponse['error'])) {
                Logger::error("Google OAuth token exchange failed", $tokenResponse);
                throw new \Exception("Failed to get access token: " . $tokenResponse['error_description']);
            }

            $accessToken = $tokenResponse['access_token'];

            // Get user info
            $userUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
            $userResponse = $this->makeApiRequest($userUrl, $accessToken);

            if (isset($userResponse['error'])) {
                Logger::error("Google OAuth user info failed", $userResponse);
                throw new \Exception("Failed to get user info: " . $userResponse['error']['message']);
            }

            return [
                'id' => $userResponse['id'],
                'name' => $userResponse['name'],
                'email' => $userResponse['email'],
                'avatar' => $userResponse['picture'] ?? null,
                'provider' => 'google'
            ];
        } catch (\Exception $e) {
            Logger::error("Google OAuth failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function makeRequest($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("CURL Error: " . $error);
        }

        curl_close($ch);

        $decodedResponse = json_decode($response, true);
        if ($httpCode >= 400) {
            throw new \Exception("HTTP Error: " . $httpCode . " - " . ($decodedResponse['error_description'] ?? $response));
        }

        return $decodedResponse;
    }

    private function makeApiRequest($url, $accessToken)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("CURL Error: " . $error);
        }

        curl_close($ch);

        $decodedResponse = json_decode($response, true);
        if ($httpCode >= 400) {
            throw new \Exception("HTTP Error: " . $httpCode . " - " . ($decodedResponse['error']['message'] ?? $response));
        }

        return $decodedResponse;
    }
}