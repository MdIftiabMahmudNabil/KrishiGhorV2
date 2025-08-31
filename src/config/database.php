<?php
/**
 * Database Configuration
 * PostgreSQL connection using PDO for Supabase
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    
    private function __construct() {
        // Load environment variables
        $this->loadEnvironmentVariables();
        
        // Supabase PostgreSQL Configuration
        $this->host = $_ENV['DB_HOST'] ?? 'db.moozvhfbkhbepmjadijj.supabase.co';
        $this->port = $_ENV['DB_PORT'] ?? '5432';
        $this->dbname = $_ENV['DB_NAME'] ?? 'postgres';
        $this->username = $_ENV['DB_USER'] ?? 'postgres';
        $this->password = $_ENV['DB_PASSWORD'] ?? 'system307projectG7';
        
        $this->connect();
    }
    
    private function loadEnvironmentVariables() {
        // Load .env file if it exists
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, '"\'');
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            // Build DSN with SSL requirement for Supabase
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname};sslmode=require";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                // PostgreSQL specific options
                PDO::ATTR_CASE => PDO::CASE_NATURAL,
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            // Set timezone for Bangladesh
            $this->connection->exec("SET timezone = 'Asia/Dhaka'");
            
            // Set application name for monitoring
            $this->connection->exec("SET application_name = 'KrishiGhor'");
            
            // Enable JSONB support
            $this->connection->exec("SET search_path = public");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        // Check if connection is still alive
        if (!$this->connection) {
            $this->connect();
        }
        
        // Test connection
        try {
            $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            // Reconnect if connection is dead
            $this->connect();
        }
        
        return $this->connection;
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function lastInsertId($name = null) {
        // PostgreSQL uses sequences, so we need to get the last inserted ID differently
        if ($name) {
            $stmt = $this->connection->query("SELECT currval('{$name}')");
            return $stmt->fetchColumn();
        }
        return $this->connection->lastInsertId();
    }
    
    public function isConnected() {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getConnectionInfo() {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->dbname,
            'username' => $this->username,
            'connected' => $this->isConnected(),
            'server_version' => $this->connection ? $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION) : null
        ];
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
