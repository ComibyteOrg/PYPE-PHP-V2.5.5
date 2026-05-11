<?php
namespace Framework\Auth\Providers;

use Framework\Helper\Logger;

class GithubProvider
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
            'scope' => 'user:email'
        ];

        $authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query($params);
        Logger::info("Redirecting to GitHub OAuth", ['url' => $authUrl]);
        header("Location: $authUrl");
        exit;
    }

    public function getUser($code)
    {
        try {
            // Exchange code for access token
            $tokenUrl = 'https://github.com/login/oauth/access_token';
            $tokenData = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'redirect_uri' => $this->redirectUri
            ];

            $tokenResponse = $this->makeRequest($tokenUrl, $tokenData);

            if (isset($tokenResponse['error'])) {
                Logger::error("GitHub OAuth token exchange failed", $tokenResponse);
                throw new \Exception("Failed to get access token: " . $tokenResponse['error_description']);
            }

            $accessToken = $tokenResponse['access_token'];

            // Get user info
            $userUrl = 'https://api.github.com/user';
            $emailsUrl = 'https://api.github.com/user/emails';

            $userResponse = $this->makeApiRequest($userUrl, $accessToken);
            $emailsResponse = $this->makeApiRequest($emailsUrl, $accessToken);

            if (isset($userResponse['error'])) {
                Logger::error("GitHub OAuth user info failed", $userResponse);
                throw new \Exception("Failed to get user info: " . $userResponse['message']);
            }

            // Get primary email
            $email = null;
            foreach ($emailsResponse as $emailData) {
                if ($emailData['primary']) {
                    $email = $emailData['email'];
                    break;
                }
            }

            return [
                'id' => $userResponse['id'],
                'name' => $userResponse['name'] ?? $userResponse['login'],
                'email' => $email,
                'avatar' => $userResponse['avatar_url'] ?? null,
                'provider' => 'github'
            ];
        } catch (\Exception $e) {
            Logger::error("GitHub OAuth failed", ['error' => $e->getMessage()]);
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'Pype-Framework');

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
            'Authorization: token ' . $accessToken,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: Pype-Framework'
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
            throw new \Exception("HTTP Error: " . $httpCode . " - " . ($decodedResponse['message'] ?? $response));
        }

        return $decodedResponse;
    }
}