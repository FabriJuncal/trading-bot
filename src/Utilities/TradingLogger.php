<?php
// src/Utilities/Logger.php
namespace TradingBot\Utilities;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\IntrospectionProcessor;
use TradingBot\Exceptions\LoggingException;

class TradingLogger {
    private static $instance;
    private static $logPath = __DIR__.'/../../storage/logs/trading.log';
    private static $logLevel = Logger::DEBUG;

    public static function initialize(): void {
        if (!self::$instance) {
            self::validateLogDirectory();
            
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s.v',
                true,
                true
            );

            $handler = new StreamHandler(self::$logPath, self::$logLevel);
            $handler->setFormatter($formatter);

            self::$instance = new Logger('trading');
            self::$instance->pushHandler($handler);
            self::$instance->pushProcessor(new IntrospectionProcessor());
            
            if ($_ENV['APP_ENV'] === 'dev') {
                self::$instance->pushProcessor(function ($record) {
                    $record['extra']['memory_usage'] = memory_get_usage(true);
                    return $record;
                });
            }
        }
    }

    public static function log(string $level, string $message, array $context = []): void {
        self::ensureInitialized();
        
        try {
            self::$instance->log($level, $message, $context);
        } catch (\Exception $e) {
            throw new LoggingException("Error writing to log: " . $e->getMessage());
        }
    }

    public static function info(string $message, array $context = []): void {
        self::log(Logger::INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::log(Logger::WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log(Logger::ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::log(Logger::CRITICAL, $message, $context);
    }

    public static function debug(string $message, array $context = []): void {
        self::log(Logger::DEBUG, $message, $context);
    }

    private static function ensureInitialized(): void {
        if (!self::$instance) {
            self::initialize();
        }
    }

    private static function validateLogDirectory(): void {
        $logDir = dirname(self::$logPath);
        
        if (!file_exists($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                throw new LoggingException("Failed to create log directory: $logDir");
            }
        }
        
        if (!is_writable($logDir)) {
            throw new LoggingException("Log directory is not writable: $logDir");
        }
    }

    public static function setLogLevel(string $level): void {
        $level = strtoupper($level);
        if (defined("Monolog\Logger::$level")) {
            self::$logLevel = constant("Monolog\Logger::$level");
        }
    }

    public static function getLogPath(): string {
        return realpath(self::$logPath);
    }
}