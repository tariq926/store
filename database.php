<?php
// Ensure PDO is available
if (!extension_loaded('pdo')) {
    die('PDO extension is not loaded');
}

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $charset;
    private $pdo;
    private $options;

    public function __construct(
        $host = 'localhost', 
        $username = 'root', 
        $password = '', 
        $database = 'online_store', 
        $charset = 'utf8mb4'
    ) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->charset = $charset;

        $this->options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
        ];

        $this->connect();
    }

    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";

        try {
            $this->pdo = new \PDO($dsn, $this->username, $this->password, $this->options);
        } catch (\PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new \Exception("Database connection failed. Please try again later.");
        }
    }

    // Add prepare method to resolve the undefined method error
    public function prepare($query) {
        return $this->pdo->prepare($query);
    }

    // Get PDO connection
    public function getPdo() {
        return $this->pdo;
    }

    // Existing methods remain the same (select, insert, update, delete, etc.)
    // ... (keep all the previous methods)

    // Additional method to execute a direct query
    public function query($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            error_log("Query Execution Error: " . $e->getMessage());
            throw new \Exception("Query execution failed.");
        }
    }
}

// Example usage with more robust error handling
try {
    // Check for PDO MySQL support
    if (!in_array('mysql', \PDO::getAvailableDrivers())) {
        throw new \Exception("MySQL driver for PDO is not available");
    }

    $db = new Database();
} catch (\Exception $e) {
    error_log("Database Initialization Error: " . $e->getMessage());
    die("Sorry, there was a problem connecting to the database. Please try again later.");
}
?>