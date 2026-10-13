<?php
include 'fns/sql/Medoo.php';
use Medoo\Medoo;

class DB {
    private static ?Medoo $instance = null;
    private static array $poolConfig = [
        'max_pool_size' => 100,
        'min_pool_size' => 20,
        'max_wait_time' => 5,
        'enable_pooling' => true,
    ];

    public static function connect(): Medoo {
        if (!self::$instance) {
            // Load configuration
            $config = Registry::load('config')->database;
            
            // Extract pool config if exists
            if (isset($config['pool'])) {
                self::$poolConfig = array_merge(self::$poolConfig, $config['pool']);
                unset($config['pool']);
            }
            
            // Configure PDO options
            $config['option'] = array_merge($config['option'] ?? [], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            // Add persistent connection for pooling if enabled
            if (self::$poolConfig['enable_pooling'] && isset($config['database_type']) && $config['database_type'] === 'mysql') {
                $config['option'][PDO::ATTR_PERSISTENT] = true;
            }
            
            // IMPORTANT: Create instance before trying to use it
            self::$instance = new Medoo($config);
            
            // Initialize connection pool if enabled
            if (self::$poolConfig['enable_pooling']) {
                self::initializeConnectionPool();
            }
        }
        return self::$instance;
    }

    private static function initializeConnectionPool(): void {
        if (self::$poolConfig['min_pool_size'] > 0 && self::$instance) {
            try {
                self::$instance->query('SELECT 1');
                
            } catch (PDOException $e) {
                error_log("Connection pool initialization failed: " . $e->getMessage());
                throw $e;
            }
        }
    }

    public static function reset(): void {
        self::$instance = null;
    }

    public static function closeConnection(): void {
        self::$instance = null;
    }

    public static function getPoolStats(): array {
        $stats = [
            'pool_config' => self::$poolConfig,
            'has_instance' => self::$instance !== null,
            'active_connections' => self::$instance ? 1 : 0,
            'persistent_enabled' => self::$poolConfig['enable_pooling'],
        ];
        
        if (self::$instance && self::$instance->pdo) {
            try {
                if (self::$instance->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    $stmt = self::$instance->pdo->query('SHOW STATUS LIKE "Threads_connected"');
                    if ($stmt) {
                        $threads = $stmt->fetch(PDO::FETCH_ASSOC);
                        $stats['threads_connected'] = $threads['Value'] ?? 'N/A';
                    }
                }
            } catch (PDOException $e) {
                $stats['stats_error'] = $e->getMessage();
            }
        }
        
        return $stats;
    }

    public static function __callStatic(string $method, array $arguments) {
        $db = self::connect();
        if (!method_exists($db, $method)) {
            throw new BadMethodCallException("Method {$method} does not exist in Medoo");
        }
        return call_user_func_array([$db, $method], $arguments);
    }

    // Helper methods for common operations
    public static function beginTransaction(): bool {
        return self::connect()->pdo->beginTransaction();
    }

    public static function commit(): bool {
        return self::connect()->pdo->commit();
    }

    public static function rollBack(): bool {
        return self::connect()->pdo->rollBack();
    }

    public static function lastInsertId(?string $name = null): string {
        return (string) self::connect()->pdo->lastInsertId($name);
    }
    
    // Helper to get the PDO instance directly if needed
    public static function pdo(): PDO {
        return self::connect()->pdo;
    }
    
    // Helper for raw queries with error handling
    public static function raw(string $sql, array $params = []): \PDOStatement {
        $pdo = self::connect()->pdo;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    // Helper for executing raw SQL (INSERT, UPDATE, DELETE)
    public static function exec(string $sql): int {
        return self::connect()->pdo->exec($sql);
    }
    
    // Helper for checking if table exists
    public static function tableExists(string $table): bool {
        try {
            $db = self::connect();
            $result = $db->query("SHOW TABLES LIKE '{$table}'")->fetchAll();
            return !empty($result);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Helper for getting table columns
    public static function getTableColumns(string $table): array {
        $db = self::connect();
        return $db->query("DESCRIBE {$table}")->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Helper for transactions with callback
    public static function transaction(callable $callback) {
        $db = self::connect();
        $pdo = $db->pdo;
        
        try {
            $pdo->beginTransaction();
            $result = $callback($db);
            $pdo->commit();
            return $result;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function __clone() {}
    private function __construct() {}
}
?>