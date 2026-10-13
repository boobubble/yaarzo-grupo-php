<?php

declare(strict_types = 1);

class Registry
{
    private static ?self $instance = null;
    private array $store = [];

    private function __construct() {}
    private function __clone() {}

    public static function __init() {
        if (self::$instance == null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public static function add(string $key, mixed $value): self
    {
        $instance = self::__init();
        $instance->store[$key] = $value;
        return $instance;
    }

    public static function load(string $key): mixed
    {
        $instance = self::__init();
        if (!\array_key_exists($key, $instance->store)) {
            throw new \RuntimeException("Key '{$key}' does not exist.");
        }
        return $instance->store[$key];
    }

   public static function stored(string $key): bool
    {
        return \array_key_exists($key, self::__init()->store);
    }

    public static function has(string $key): bool
    {
        return self::stored($key);
    }
    
   public static function remove(string $key): self
    {
        $instance = self::__init();
        unset($instance->store[$key]);
        return $instance;
    }

  public static function output(): array
    {
        return self::__init()->store;
    }

   public function __sleep(): array { return []; }
    public function __wakeup(): void {
        throw new \LogicException("Cannot unserialize a singleton.");
    }
}