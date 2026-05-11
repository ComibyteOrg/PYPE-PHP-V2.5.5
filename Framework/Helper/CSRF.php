<?php

namespace Framework\Helper;

// File: core/security/CSRF.php

class CSRF
{
    private static $tokenName = 'csrf_token';
    private static $tokenExpiryName = 'csrf_token_expiry';
    private static $tokenLifetime = 1800; // 30 minutes

    public static function generateToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Only generate new token if one doesn't exist
        if (!isset($_SESSION[self::$tokenName])) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::$tokenName] = $token;
        }

        return $_SESSION[self::$tokenName];
    }

    public static function validateToken($submittedToken)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if token exists in session
        if (!isset($_SESSION[self::$tokenName])) {
            return false;
        }

        // Compare tokens (token is valid as long as it exists in session, no time expiry)
        if (!hash_equals($_SESSION[self::$tokenName], $submittedToken)) {
            return false;
        }

        // Token is valid - DON'T clear it yet (only clear on successful form processing)
        return true;
    }

    // Only call this when form processing is completely successful
    public static function clearToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION[self::$tokenName]);
    }

    public static function getTokenField()
    {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }

    public static function getTokenName()
    {
        return self::$tokenName;
    }

    public static function setTokenLifetime($seconds)
    {
        self::$tokenLifetime = (int) $seconds;
    }
}




/*
    Usage 

    In your login/signup form:
    use Framework\Helper\CSRF; 
    <form method="post" action="login.php">
        <!-- Your form fields -->
        <input type="text" name="username">
        <input type="password" name="password">

        <!-- Add the CSRF token field -->
        <?php echo CSRF::getTokenField(); ?>

        <button type="submit">Login</button>
    </form>


    // in your controller 
    $tokenName = CSRF::getTokenName();

    // Validate CSRF token
    if (!isset($_POST[$tokenName]) || !CSRF::validateToken($_POST[$tokenName])) {
        echo Helper::set_alert('danger', 'Something Wend wrong, Please try again');
        die;
    }

*/