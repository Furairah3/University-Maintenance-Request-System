<?php
/**
 * Database Connection Class (PDO Singleton)
 * Smart Hostel Maintenance System
 */

require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            Logger::error('Database connection failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('Database connection failed. Please try again later.');
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    /**
     * Execute a query with prepared statements
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert and return last insert ID
     */
    public function insert(string $sql, array $params = []): int {
        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update/Delete and return affected rows
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollBack(): bool {
        return $this->pdo->rollBack();
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new RuntimeException("Cannot unserialize singleton");
    }
}
