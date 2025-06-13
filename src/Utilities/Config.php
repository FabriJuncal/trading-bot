<?php
// src/Utilities/Config.php
namespace TradingBot\Utilities;

use Symfony\Component\Dotenv\Dotenv;
use TradingBot\Exceptions\ConfigException;

/**
 * Clase Config
 * 
 * Gestiona la configuración de la aplicación, cargando variables de entorno y archivos de configuración.
 * Proporciona métodos para acceder y modificar la configuración de manera segura.
 */
class Config {
    private static $loaded = false;
    private static $configs = [];

    /**
     * Inicializa la configuración de la aplicación.
     * Carga las variables de entorno y los archivos de configuración.
     * Asegura que la inicialización solo ocurra una vez.
     */
    public static function initialize(): void {
        if (!self::$loaded) {
            self::loadEnv();
            self::loadConfigFiles();
            self::$loaded = true;
            self::validateEssentialConfig();
        }
    }

    /**
     * Obtiene un valor de configuración usando una notación de punto.
     * 
     * @param string $key La clave de configuración.
     * @param mixed $default Valor predeterminado si la clave no existe.
     * @return mixed El valor de la configuración o el valor predeterminado.
     */
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

    /**
     * Obtiene un valor de configuración requerido.
     * Lanza una excepción si la configuración no está presente.
     * 
     * @param string $key La clave de configuración.
     * @return mixed El valor de la configuración.
     * @throws ConfigException Si la configuración no está presente.
     */
    public static function getRequired(string $key) {
        $value = self::get($key);
        
        if ($value === null) {
            throw new ConfigException("Configuración requerida faltante: $key");
        }

        return $value;
    }

    /**
     * Carga las variables de entorno desde el archivo .env.
     */
    private static function loadEnv(): void {
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__.'/../../.env');
    }

    /**
     * Carga y fusiona los archivos de configuración desde el directorio config/.
     */
    private static function loadConfigFiles(): void {
        $configPath = __DIR__.'/../../config/';
        self::$configs = ['config' => []];
        
        // Cargar configuración base
        foreach (glob($configPath.'*.php') as $file) {
            $config = require $file;
            if (is_array($config)) {
                self::$configs['config'] = array_merge_recursive(self::$configs['config'], $config);
            }
        }

        // Mapear variables de entorno a la configuración
        self::mapEnvToConfig();
        
    }

    /**
     * Mapea las variables de entorno a la estructura de configuración.
     */
    private static function mapEnvToConfig(): void {
        // Mapear configuración de Telegram
        if (isset($_ENV['TELEGRAM_BOT_TOKEN']) && isset($_ENV['TELEGRAM_CHAT_ID'])) {
            if (!isset(self::$configs['config']['notifications']['telegram'])) {
                self::$configs['config']['notifications']['telegram'] = [];
            }
            self::$configs['config']['notifications']['telegram'] = array_merge(
                self::$configs['config']['notifications']['telegram'],
                [
                    'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'],
                    'chat_id' => $_ENV['TELEGRAM_CHAT_ID']
                ]
            );
        }

        // Mapear configuración de Binance
        if (isset($_ENV['BINANCE_API_KEY']) && isset($_ENV['BINANCE_API_SECRET'])) {
            if (!isset(self::$configs['config']['exchanges']['binance'])) {
                self::$configs['config']['exchanges']['binance'] = [];
            }
            self::$configs['config']['exchanges']['binance'] = array_merge(
                self::$configs['config']['exchanges']['binance'],
                [
                    'api_key' => $_ENV['BINANCE_API_KEY'],
                    'api_secret' => $_ENV['BINANCE_API_SECRET']
                ]
            );
        }

        // Mapear configuración de Gate.io
        if (isset($_ENV['GATEIO_API_KEY']) && isset($_ENV['GATEIO_API_SECRET'])) {
            if (!isset(self::$configs['config']['exchanges']['gateio'])) {
                self::$configs['config']['exchanges']['gateio'] = [];
            }
            self::$configs['config']['exchanges']['gateio'] = array_merge(
                self::$configs['config']['exchanges']['gateio'],
                [
                    'api_key' => $_ENV['GATEIO_API_KEY'],
                    'api_secret' => $_ENV['GATEIO_API_SECRET']
                ]
            );
        }
    }

    /**
     * Valida que las configuraciones esenciales estén presentes.
     * Lanza una excepción si alguna configuración esencial falta.
     */
    private static function validateEssentialConfig(): void {
        $required = [
            'exchanges.binance.api_key',
            'exchanges.binance.api_secret',
            'notifications.telegram.bot_token',
            'notifications.telegram.chat_id'
        ];

        foreach ($required as $key) {
            if (self::get('config.'.$key) === null) {
                echo "Configuración esencial faltante: $key\n";
                throw new ConfigException("Configuración esencial faltante: $key");
            }
        }
    }

    /**
     * Asegura que la configuración esté inicializada antes de acceder a ella.
     */
    private static function ensureInitialized(): void {
        if (!self::$loaded) {
            self::initialize();
        }
    }

    /**
     * Obtiene todas las configuraciones.
     * 
     * @return array Todas las configuraciones.
     */
    public static function getAll(): array {
        self::ensureInitialized();
        return self::$configs;
    }

    /**
     * Establece un valor de configuración usando una notación de punto.
     * 
     * @param string $key La clave de configuración.
     * @param mixed $value El valor a establecer.
     */
    public static function set(string $key, $value): void {
        $keys = explode('.', $key);
        $config = &self::$configs['config'];

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Verifica si una clave de configuración existe.
     * 
     * @param string $key La clave de configuración.
     * @return bool True si la clave existe, false en caso contrario.
     */
    public static function has(string $key): bool {
        return self::get($key) !== null;
    }

    /**
     * Obtiene todas las configuraciones.
     * 
     * @return array Todas las configuraciones.
     */
    public static function all(): array {
        return self::$configs['config'];
    }
}