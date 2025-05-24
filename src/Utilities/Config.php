<?php
// src/Utilities/Config.php
namespace TradingBot\Utilities;

use Symfony\Component\Dotenv\Dotenv;
use TradingBot\Exceptions\ConfigException;

class Config {
    private static $loaded = false;
    private static $configs = [];

    public static function initialize(): void {
        if (!self::$loaded) {
            self::loadEnv();
            self::loadConfigFiles();
            self::$loaded = true;
            self::validateEssentialConfig();
        }
    }

    public static function get(string $key, $default = null) {
        self::ensureInitialized();
        
        $keys = explode('.', $key);
        $value = self::$configs;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function getRequired(string $key) {
        $value = self::get($key);
        
        if ($value === null) {
            throw new ConfigException("Configuración requerida faltante: $key");
        }

        return $value;
    }

    private static function loadEnv(): void {
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__.'/../../.env');
    }

    private static function loadConfigFiles(): void {
        $configPath = __DIR__.'/../../config/';
        foreach (glob($configPath.'*.php') as $file) {
            self::$configs['config'] = require $file;
        }
    }

    private static function validateEssentialConfig(): void {
        $required = [
            'exchanges.binance.api_key',
            'exchanges.binance.api_secret',
            'notifications.telegram.bot_token',
            'notifications.telegram.chat_id'
        ];

        foreach ($required as $key) {
            if (self::get('config.'.$key) === null) {
                throw new ConfigException("Configuración esencial faltante: $key");
            }
        }
    }

    private static function ensureInitialized(): void {
        if (!self::$loaded) {
            self::initialize();
        }
    }

    public static function getAll(): array {
        self::ensureInitialized();
        return self::$configs;
    }
}