<?php

namespace Framework\Helper;

use Framework\Helper\DB;

/**
 * Universal Authentication Helper
 * 
 * Works with any database table (users, admins, members, etc.)
 * 
 * Usage:
 *   Auth::table('users')->login($email, $password);
 *   Auth::table('admins')->login($email, $password);
 *   Auth::table('members')->login($email, $password, 'email', 'password');
 * 
 *   Auth::table('users')->authenticate($email, $password);
 *   Auth::table('users')->check();
 *   Auth::table('users')->user();
 *   Auth::table('users')->logout();
 */
class Auth
{
    /**
     * Table to authenticate against
     * @var string
     */
    protected static $table = 'users';

    /**
     * Email column name
     * @var string
     */
    protected static $emailColumn = 'email';

    /**
     * Password column name
     * @var string
     */
    protected static $passwordColumn = 'password';

    /**
     * Session key for storing authenticated user
     * @var string
     */
    protected static $sessionKey = 'auth_user';

    /**
     * Remember me cookie name
     * @var string
     */
    protected static $cookieName = 'remember_me';

    /**
     * Set the table to authenticate against
     * @param string $table
     * @return Auth
     */
    public static function table(string $table)
    {
        static::$table = $table;
        return new static();
    }

    /**
     * Set custom column names
     * @param string $emailColumn
     * @param string $passwordColumn
     * @return Auth
     */
    public function columns(string $emailColumn = 'email', string $passwordColumn = 'password')
    {
        static::$emailColumn = $emailColumn;
        static::$passwordColumn = $passwordColumn;
        return $this;
    }

    /**
     * Set session key
     * @param string $key
     * @return Auth
     */
    public function sessionKey(string $key)
    {
        static::$sessionKey = $key;
        return $this;
    }

    /**
     * Set cookie name for remember me
     * @param string $name
     * @return Auth
     */
    public function cookieName(string $name)
    {
        static::$cookieName = $name;
        return $this;
    }

    /**
     * Authenticate user with email and password
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return object|null Returns user object on success, null on failure
     */
    public function login(string $email, string $password, bool $remember = false)
    {
        // Find user by email
        $user = DB::table(static::$table)
            ->where(static::$emailColumn, $email)
            ->first();

        if (!$user) {
            return null;
        }

        // Verify password
        if (!password_verify($password, $user[static::$passwordColumn] ?? '')) {
            return null;
        }

        // Store user in session
        $this->setSession($user);

        // Set remember me cookie if requested
        if ($remember) {
            $this->setRememberToken($user);
        }

        return (object) $user;
    }

    /**
     * Register a new user
     * @param array $data
     * @param bool $autoLogin
     * @return object|null
     */
    public function register(array $data, bool $autoLogin = true)
    {
        // Hash password if present
        if (isset($data[static::$passwordColumn])) {
            $data[static::$passwordColumn] = password_hash($data[static::$passwordColumn], PASSWORD_DEFAULT);
        }

        // Insert user
        $id = DB::table(static::$table)->insert($data);

        if (!$id) {
            return null;
        }

        $data['id'] = $id;

        // Auto login if requested
        if ($autoLogin) {
            $this->setSession($data);
        }

        return (object) $data;
    }

    /**
     * Check if user is authenticated
     * @return bool
     */
    public function check()
    {
        return $this->user() !== null;
    }

    /**
     * Get authenticated user
     * @return object|null
     */
    public function user()
    {
        // Check session first
        if (isset($_SESSION[static::$sessionKey])) {
            return (object) $_SESSION[static::$sessionKey];
        }

        // Check remember me cookie
        $token = $_COOKIE[static::$cookieName] ?? null;
        if ($token) {
            $user = $this->validateRememberToken($token);
            if ($user) {
                $this->setSession($user);
                return (object) $user;
            }
        }

        return null;
    }

    /**
     * Get authenticated user (alias for user())
     * @return object|null
     */
    public function authenticate()
    {
        return $this->user();
    }

    /**
     * Logout user
     * @return void
     */
    public function logout()
    {
        // Clear session
        unset($_SESSION[static::$sessionKey]);

        // Clear remember me cookie
        if (isset($_COOKIE[static::$cookieName])) {
            $this->clearRememberToken();
        }
    }

    /**
     * Get user ID
     * @return int|null
     */
    public function id()
    {
        $user = $this->user();
        return $user->id ?? null;
    }

    /**
     * Set user in session
     * @param array $user
     * @return void
     */
    protected function setSession(array $user)
    {
        // Remove password from session
        unset($user[static::$passwordColumn]);
        $_SESSION[static::$sessionKey] = $user;
    }

    /**
     * Set remember me token
     * @param array $user
     * @return void
     */
    protected function setRememberToken(array $user)
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Store token in database
        $this->createRememberToken($user['id'] ?? null, $token, $expiresAt);

        // Set cookie
        setcookie(static::$cookieName, $token, strtotime('+30 days'), '/');
    }

    /**
     * Create remember token in database
     * @param int|null $userId
     * @param string $token
     * @param string $expiresAt
     * @return void
     */
    protected function createRememberToken($userId, string $token, string $expiresAt)
    {
        // Try to insert into remember_me_tokens table if it exists
        try {
            DB::table('remember_me_tokens')->insert([
                'user_id' => $userId,
                'token' => $token,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Table doesn't exist, skip
        }
    }

    /**
     * Validate remember token
     * @param string $token
     * @return array|null
     */
    protected function validateRememberToken(string $token)
    {
        try {
            $rememberToken = DB::table('remember_me_tokens')
                ->where('token', $token)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->first();

            if ($rememberToken) {
                $user = DB::table(static::$table)->find($rememberToken['user_id']);
                
                // Delete used token
                DB::table('remember_me_tokens')
                    ->where('token', $token)
                    ->delete([]);
                
                return $user;
            }
        } catch (\Exception $e) {
            // Table doesn't exist, skip
        }

        return null;
    }

    /**
     * Clear remember token
     * @return void
     */
    protected function clearRememberToken()
    {
        setcookie(static::$cookieName, '', time() - 3600, '/');
    }

    /**
     * Get the current table
     * @return string
     */
    public static function getTable()
    {
        return static::$table;
    }

    /**
     * Reset to default settings
     * @return void
     */
    public static function reset()
    {
        static::$table = 'users';
        static::$emailColumn = 'email';
        static::$passwordColumn = 'password';
        static::$sessionKey = 'auth_user';
        static::$cookieName = 'remember_me';
    }
}
