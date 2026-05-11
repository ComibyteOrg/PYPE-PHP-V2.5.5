<?php

namespace Framework\Auth;

use Framework\Auth\SocialLoginManager;
use Framework\Auth\Providers\GoogleProvider;
use Framework\Auth\Providers\GithubProvider;
use Framework\Auth\Providers\FacebookProvider;
use Framework\Helper\Helper;
use Framework\Helper\Logger;
use Framework\Helper\DB;

class SocialAuthController
{
    private $manager;

    public function __construct()
    {
        $this->manager = new SocialLoginManager();
        $this->manager->autoRegisterProviders();
    }

    public function redirectToProvider($provider)
    {
        try {
            return $this->manager->redirectToProvider($provider);
        } catch (\Exception $e) {
            Logger::error("Social Auth Redirect Error", ['error' => $e->getMessage()]);
            Helper::redirect('/?error=auth_failed');
        }
    }

    public function handleProviderCallback($provider)
    {
        try {
            $code = $_GET['code'] ?? null;
            if (!$code) {
                throw new \Exception("No code provided from $provider");
            }

            $userData = $this->manager->handleCallback($provider, $code);

            // Handle user login/registration logic
            $user = DB::table('users')->where('email', $userData['email'])->first();

            if (!$user) {
                // Register new user
                $userId = DB::table('users')->insert([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'avatar' => $userData['avatar'],
                    'provider' => $userData['provider'],
                    'provider_id' => $userData['id'],
                    'email_verified_at' => date('Y-m-d H:i:s')
                ]);
                $user = DB::table('users')->find($userId);
            }

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['auth_user'] = $user;

            Logger::info("User logged in via social auth", ['email' => $user['email'], 'provider' => $provider]);
            Helper::redirect('/');

        } catch (\Exception $e) {
            Logger::error("Social Auth Callback Error", ['error' => $e->getMessage()]);
            Helper::redirect('/?error=callback_failed');
        }
    }
}
