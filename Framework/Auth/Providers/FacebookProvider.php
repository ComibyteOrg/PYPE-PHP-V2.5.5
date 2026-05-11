<?php
namespace Framework\Auth\Providers;

use Framework\Helper\Logger;

class FacebookProvider
{
    private $appId;
    private $appSecret;
    private $redirectUri;

    public function __construct($appId, $appSecret, $redirectUri)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->redirectUri = $redirectUri;
    }

    public function redirectToAuthUrl()
    {
        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'email,public_profile'
        ];

        $authUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
        Logger::info("Redirecting to Facebook OAuth", ['url' => $authUrl]);
        header("Location: $authUrl");
        exit;
    }

    public function getUser($code)
    {
        try {
            // Exchange code for access token
            $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token';
            $tokenData = [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'redirect_uri' => $this->redirectUri,
                'code' => $code
            ];

            $tokenResponse = $this->makeRequest($tokenUrl, $tokenData);

            if (isset($tokenResponse['error'])) {
                Logger::error("Facebook OAuth token exchange failed", $tokenResponse);
                throw new \Exception("Failed to get access token: " . $tokenResponse['error_description']);
            }

            $accessToken = $tokenResponse['access_token'];

            // Get user info
            $fields = 'id,name,email,picture.width(500).height(500)';
            $userUrl = "https://graph.facebook.com/v18.0/me?fields={$fields}&access_token={$accessToken}";
            $userResponse = $this->makeApiRequest($userUrl);

            if (isset($userResponse['error'])) {
                Logger::error("Facebook OAuth user info failed", $userResponse);
                throw new \Exception("Failed to get user info: " . $userResponse['error']['message']);
            }

            return [
                'id' => $userResponse['id'],
                'name' => $userResponse['name'],
                'email' => $userResponse['email'] ?? null,
                'avatar' => $userResponse['picture']['data']['url'] ?? null,
                'provider' => 'facebook'
            ];
        } catch (\Exception $e) {
            Logger::error("Facebook OAuth failed", ['error' => $e->getMessage()]);
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
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
            throw new \Exception("HTTP Error: " . $httpCode . " - " . ($decodedResponse['error_description'] ?? $response));
        }

        return $decodedResponse;
    }

    private function makeApiRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
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