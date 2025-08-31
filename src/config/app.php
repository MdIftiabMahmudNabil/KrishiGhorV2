<?php
/**
 * Application Configuration
 * Global configuration and environment loader
 */

class AppConfig {
    private static $config = [];
    
    public static function init() {
        // Load environment variables
        self::loadEnv();
        
        // Set default configuration
        self::$config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'KrishiGhor',
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Dhaka',
                'locale' => $_ENV['APP_LOCALE'] ?? 'bn',
                'fallback_locale' => $_ENV['APP_FALLBACK_LOCALE'] ?? 'en',
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? '5432',
                'name' => $_ENV['DB_NAME'] ?? 'krishighor',
                'user' => $_ENV['DB_USER'] ?? 'krishighor_user',
                'pass' => $_ENV['DB_PASS'] ?? 'krishighor_pass',
            ],
            'auth' => [
                'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this',
                'jwt_expire' => $_ENV['JWT_EXPIRE'] ?? 3600, // 1 hour
                'session_expire' => $_ENV['SESSION_EXPIRE'] ?? 7200, // 2 hours
            ],
            'mail' => [
                'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
                'port' => $_ENV['MAIL_PORT'] ?? 587,
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@krishighor.com',
                'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'KrishiGhor',
            ],
            'ai' => [
                'api_endpoint' => $_ENV['AI_API_ENDPOINT'] ?? '',
                'api_key' => $_ENV['AI_API_KEY'] ?? '',
                'pricing_model' => $_ENV['AI_PRICING_MODEL'] ?? 'default',
            ],
            'storage' => [
                'uploads_path' => $_ENV['UPLOADS_PATH'] ?? 'public/uploads',
                'max_file_size' => $_ENV['MAX_FILE_SIZE'] ?? '10M',
                'allowed_types' => explode(',', $_ENV['ALLOWED_FILE_TYPES'] ?? 'jpg,jpeg,png,pdf,doc,docx'),
            ],
        ];
        
        // Set timezone
        date_default_timezone_set(self::$config['app']['timezone']);
        
        // Set error reporting based on environment
        if (self::$config['app']['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
            ini_set('display_errors', 0);
        }
    }
    
    private static function loadEnv() {
        // First, try to load from .env file
        $envFile = __DIR__ . '/../../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }
                
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        // If .env doesn't exist, load from our environment configuration file
        if (empty($_ENV)) {
            $envConfigFile = __DIR__ . '/env.php';
            if (file_exists($envConfigFile)) {
                require_once $envConfigFile;
            }
        }
    }
    
    public static function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public static function set($key, $value) {
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    public static function all() {
        return self::$config;
    }
}

// Initialize configuration
AppConfig::init();
