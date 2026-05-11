<?php
namespace Framework\Auth;

use Framework\Helper\Logger;

class SocialLoginManager
{
    private $providers = [];

    public function registerProvider($name, $provider)
    {
        $this->providers[$name] = $provider;
    }

    public function autoRegisterProviders()
    {
        if (!empty($_ENV['GOOGLE_CLIENT_ID'])) {
            $this->registerProvider('google', new \Framework\Auth\Providers\GoogleProvider(
                $_ENV['GOOGLE_CLIENT_ID'],
                $_ENV['GOOGLE_CLIENT_SECRET'],
                $_ENV['GOOGLE_REDIRECT_URI']
            ));
        }

        if (!empty($_ENV['GITHUB_CLIENT_ID'])) {
            $this->registerProvider('github', new \Framework\Auth\Providers\GithubProvider(
                $_ENV['GITHUB_CLIENT_ID'],
                $_ENV['GITHUB_CLIENT_SECRET'],
                $_ENV['GITHUB_REDIRECT_URI']
            ));
        }

        if (!empty($_ENV['FACEBOOK_CLIENT_ID'])) {
            $this->registerProvider('facebook', new \Framework\Auth\Providers\FacebookProvider(
                $_ENV['FACEBOOK_CLIENT_ID'],
                $_ENV['FACEBOOK_CLIENT_SECRET'],
                $_ENV['FACEBOOK_REDIRECT_URI']
            ));
        }
    }

    public function redirectToProvider($provider)
    {
        if (!isset($this->providers[$provider])) {
            Logger::error("Social login provider not supported", ['provider' => $provider]);
            throw new \Exception("Provider not supported: " . $provider);
        }

        Logger::info("Redirecting to social provider", ['provider' => $provider]);
        $this->providers[$provider]->redirectToAuthUrl();
    }

    public function handleCallback($provider, $code)
    {
        if (!isset($this->providers[$provider])) {
            Logger::error("Social login provider not supported", ['provider' => $provider]);
            throw new \Exception("Provider not supported: " . $provider);
        }

        Logger::info("Handling social login callback", ['provider' => $provider]);
        return $this->providers[$provider]->getUser($code);
    }
}