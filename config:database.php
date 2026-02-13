<?php
/**
 * Database configuration and connection class
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'telesol crm');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // Enable mysqli exception mode
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            $this->connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
            $this->connection->set_charset(DB_CHARSET);
            
            // Set timezone
            $this->connection->query("SET time_zone = '+00:00'");
            
        } catch (mysqli_sql_exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    // Singleton pattern to prevent multiple connections
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

// Helper function for safe string escaping
function escape($str) {
    $conn = getDB();
    return $conn->real_escape_string($str);
}

// Helper function for HTML escaping
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>