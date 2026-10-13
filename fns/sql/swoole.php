<?php

include 'fns/sql/Medoo.php';
use Medoo\Medoo;
use Swoole\Coroutine;

class DB {
    private static $instances = [];
    private static $lastCheck = [];
    private static $connectionPool = null;
    private static $poolSize = 250;
    private static $debug = false;

    /**
    * Initialize connection pool
    */
    public static function init() {
        if (self::$connectionPool === null && extension_loaded('swoole')) {
            self::$connectionPool = new \Swoole\Coroutine\Channel(self::$poolSize);
            self::log("Connection pool initialized with size: " . self::$poolSize);
        }
    }

    /**
    * Get database connection with auto-release support
    */
    public static function connect($workerId = null) {
        self::init();

        // Use connection pool if available
        if (self::$connectionPool !== null && Coroutine::getCid() > 0) {
            return self::getFromPool($workerId);
        }

        // Fallback to old per-coroutine connection
        return self::getPersistentConnection($workerId);
    }

    /**
    * Get connection from pool
    */
    private static function getFromPool($workerId = null) {
        $startTime = microtime(true);

        // Try to get connection from pool
        $conn = self::$connectionPool->pop(0.5); // 100ms timeout

        if ($conn === false) {
            $conn = self::createNewConnection();
        }

        // Return auto-releasing wrapper
        return new class($conn) {
            private $connection;
            private $released = false;

            public function __construct($connection) {
                $this->connection = $connection;
            }

            public function __call($method, $args) {
                if ($this->released) {
                    throw new \RuntimeException("Connection already released");
                }

                try {
                    if (!method_exists($this->connection, $method)) {
                        throw new \BadMethodCallException("Method$method does not exist");
                    }

                    $result = call_user_func_array([$this->connection, $method], $args);

                    // Auto-release after method call
                    $this->release();

                    return $result;
                } catch (\Exception $e) {
                    // Release on exception too
                    $this->release();
                    throw $e;
                }
            }

            public function __destruct() {
                $this->release();
            }

            public function release() {
                if (!$this->released && $this->connection) {
                    DB::returnToPool($this->connection);
                    $this->released = true;
                }
            }

            // For transaction handling
            public function transaction(callable $callback) {
                if ($this->released) {
                    throw new \RuntimeException("Connection already released");
                }

                try {
                    $this->connection->pdo->beginTransaction();
                    $result = $callback($this->connection);
                    $this->connection->pdo->commit();
                    return $result;
                } catch (\Exception $e) {
                    if ($this->connection->pdo->inTransaction()) {
                        $this->connection->pdo->rollBack();
                    }
                    throw $e;
                } finally {
                    $this->release();
                }
            }

            // Get raw connection for manual use
            public function raw() {
                return $this->connection;
            }
        };
    }

    /**
    * Return connection to pool
    */
    public static function returnToPool($connection) {
        if (self::$connectionPool !== null) {
            // Don't push if pool is full
            if (self::$connectionPool->length() < self::$poolSize) {
                if (self::checkConnectionAlive($connection)) {
                    self::$connectionPool->push($connection);
                } else {
                    self::log("Dead connection not returned to pool");
                }
            } else {
                self::log("Pool full, closing connection");
            }
        }
    }

    /**
    * Create new connection
    */
    private static function createNewConnection() {
        $config = Registry::load('config')->database;
        $config['option'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $config['option'][PDO::ATTR_PERSISTENT] = false;
        $config['option'][PDO::ATTR_TIMEOUT] = 5;

        try {
            $medoo = new Medoo($config);
            return $medoo;
        } catch (\Exception $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
    * Check if connection is alive
    */
    private static function checkConnectionAlive($medoo) {
        return ($medoo && $medoo->pdo);
    }

    /**
    * Old method for backward compatibility
    */
    private static function getPersistentConnection($workerId = null) {
        $cid = Coroutine::getCid();
        $workerId = $workerId ?? ($GLOBALS['swoole_worker_id'] ?? 0);
        $key = $workerId . '-' . $cid;
        $now = time();

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = self::createNewConnection();
            self::$lastCheck[$key] = $now;
        } elseif (($now - (self::$lastCheck[$key] ?? 0)) > 60) {
            try {
                self::$instances[$key]->pdo->query('SELECT 1');
            } catch (\Exception $e) {
                self::log("Reconnecting due to: " . $e->getMessage());
                self::$instances[$key] = self::createNewConnection();
            }
            self::$lastCheck[$key] = $now;
        }

        return self::$instances[$key];
    }

    /**
    * Magic static calls with auto-release
    */
    public static function __callStatic($name, $arguments) {
        $wrapper = self::connect();

        try {
            return call_user_func_array([$wrapper, $name], $arguments);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
    * Static transaction helper
    */
    public static function transaction(callable $callback) {
        $wrapper = self::connect();
        return $wrapper->transaction($callback);
    }

    /**
    * Reset connection for current coroutine
    */
    public static function reset($workerId = null) {
        $cid = Coroutine::getCid();
        $workerId = $workerId ?? ($GLOBALS['swoole_worker_id'] ?? 0);
        $key = $workerId . '-' . $cid;

        if (isset(self::$instances[$key])) {
            unset(self::$instances[$key]);
            unset(self::$lastCheck[$key]);
        }
    }

    /**
    * Close connection
    */
    public static function closeConnection($workerId = null) {
        self::reset($workerId);
    }

    /**
    * Clean up pool
    */
    public static function cleanup() {
        if (self::$connectionPool !== null) {
            while (self::$connectionPool->length() > 0) {
                $conn = self::$connectionPool->pop(0.001);
                // Connection will be cleaned up by GC
            }
        }

        self::$instances = [];
        self::$lastCheck = [];
    }

    /**
    * Configure pool
    */
    public static function configure($options) {
        if (isset($options['pool_size'])) {
            self::$poolSize = max(10, min(200, (int)$options['pool_size']));
        }
        if (isset($options['debug'])) {
            self::$debug = (bool)$options['debug'];
        }
    }

    /**
    * Get pool stats
    */
    public static function stats() {
        $poolLength = self::$connectionPool ? self::$connectionPool->length() : 0;

        return [
            'pool_size' => self::$poolSize,
            'pool_available' => $poolLength,
            'persistent_connections' => count(self::$instances),
            'debug' => self::$debug
        ];
    }

    /**
    * Logging
    */
    private static function log($message) {
        if (self::$debug) {
            error_log("[DB] " . date('H:i:s') . " - " . $message);
        }
    }

    private function __clone() {}
    private function __construct() {}
}