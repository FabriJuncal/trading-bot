<?php
namespace TradingBot\Services;

use TradingBot\Exchanges\ExchangeFactory;
use TradingBot\Exceptions\DataServiceException;
use TradingBot\Utilities\TradingLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class MarketDataService {
    private $exchange;
    private $symbol;
    private $cache;
    private $maxRetries = 3;
    private $retryDelay = 1;

    public function __construct(string $exchangeName, string $symbol) {
        $this->exchange = ExchangeFactory::create($exchangeName);
        $this->symbol = $symbol;
        $this->cache = new FilesystemAdapter('market_data', 0, __DIR__.'/../../storage/cache');
    }

    public function getHistoricalData(string $timeframe = '1h', int $limit = 100, bool $forceRefresh = false): array {
        try {
            // Establecer el timeframe actual
            $this->exchange->setCurrentTimeframe($timeframe);

            $data = $this->fetchWithRetry(function () use ($timeframe, $limit) {
                $data = $this->exchange->getMarketData($this->symbol, $timeframe, $limit);
                $this->validateData($data, $limit);
                return $this->normalizeData($data);
            });

            // Registrar los datos obtenidos
            TradingLogger::info("Datos históricos obtenidos", [
                'symbol' => $this->symbol,
                'timeframe' => $timeframe,
                'data_points' => count($data),
                'last_timestamp' => date('Y-m-d H:i:s', end($data)['timestamp']/1000)
            ]);

            return $data;
        } catch (\Exception $e) {
            TradingLogger::error("Error al obtener datos históricos", [
                'symbol' => $this->symbol,
                'timeframe' => $timeframe,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getRealTimeData(): array {
        try {
            $data = $this->fetchWithRetry(function () {
                $data = $this->exchange->getMarketData($this->symbol, '1m', 1);
                $this->validateData($data, 1);
                return $this->normalizeData($data)[0] ?? [];
            });

            // Registrar los datos en tiempo real
            TradingLogger::info("Datos en tiempo real obtenidos", [
                'symbol' => $this->symbol,
                'timestamp' => date('Y-m-d H:i:s', $data['timestamp']/1000),
                'close' => $data['close']
            ]);

            return $data;
        } catch (\Exception $e) {
            TradingLogger::error("Error al obtener datos en tiempo real", [
                'symbol' => $this->symbol,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getMultipleTimeframeData(array $timeframes): array {
        $result = [];
        foreach ($timeframes as $tf) {
            $result[$tf] = $this->getHistoricalData($tf);
        }
        return $result;
    }

    private function fetchWithRetry(callable $fetchFunction) {
        $retryCount = 0;
        $lastError = null;
        
        while ($retryCount <= $this->maxRetries) {
            try {
                return $fetchFunction();
            } catch (\Exception $e) {
                $lastError = $e;
                TradingLogger::warning("Intento de obtención de datos fallido", [
                    'attempt' => $retryCount + 1,
                    'max_retries' => $this->maxRetries,
                    'error' => $e->getMessage()
                ]);
                
                if ($retryCount === $this->maxRetries) {
                    break;
                }
                
                $retryCount++;
                sleep($this->getRetryDelay($retryCount));
            }
        }
        
        throw $lastError;
    }

    private function getRetryDelay(int $attempt): int {
        return min(30, pow(2, $attempt)); // Máximo 30 segundos
    }

    private function validateData(array $data, int $expectedCount): void {
        if (empty($data)) {
            throw new DataServiceException("No se obtuvieron datos del mercado");
        }

        if (count($data) < $expectedCount) {
            throw new DataServiceException(
                "Datos insuficientes. Esperados: $expectedCount, Obtenidos: " . count($data)
            );
        }
        
        $requiredKeys = ['timestamp', 'open', 'high', 'low', 'close', 'volume'];
        foreach ($data as $entry) {
            if (count(array_intersect_key($entry, array_flip($requiredKeys))) !== count($requiredKeys)) {
                throw new DataServiceException("Formato de datos inválido");
            }

            // Validar que los valores numéricos son válidos
            foreach (['open', 'high', 'low', 'close', 'volume'] as $key) {
                if (!is_numeric($entry[$key]) || $entry[$key] <= 0) {
                    throw new DataServiceException("Valor inválido para $key: {$entry[$key]}");
                }
            }
        }

        // Obtener el último timestamp y validar su antigüedad
        $lastEntry = end($data);
        $lastTimestamp = $lastEntry['timestamp'];
        
        // Obtener el tiempo del servidor de Binance
        $serverTime = $this->exchange->getServerTime();
        
        // Validar que el timestamp no sea futuro
        if ($lastTimestamp > ($serverTime + 60000)) { // No más de 1 minuto en el futuro
            throw new DataServiceException(
                "Datos con timestamp futuro detectado. Última actualización: " . 
                date('Y-m-d H:i:s', $lastTimestamp/1000)
            );
        }

        // Obtener el timeframe actual
        $timeframe = $this->getCurrentTimeframe();
        $maxAge = $this->getMaxAgeForTimeframe($timeframe);

        // Validar que los datos no sean muy antiguos
        if (($serverTime - $lastTimestamp) > $maxAge) {
            throw new DataServiceException(
                "Datos desactualizados. Última actualización: " . 
                date('Y-m-d H:i:s', $lastTimestamp/1000)
            );
        }
    }

    private function getMaxAgeForTimeframe(string $timeframe): int {
        // Definir el tiempo máximo de antigüedad permitido para cada timeframe
        $maxAges = [
            '1m' => 2 * 60 * 1000,      // 2 minutos
            '5m' => 10 * 60 * 1000,     // 10 minutos
            '15m' => 30 * 60 * 1000,    // 30 minutos
            '1h' => 5 * 60 * 60 * 1000, // 5 horas
            '4h' => 20 * 60 * 60 * 1000,// 20 horas
            '1d' => 24 * 60 * 60 * 1000 // 24 horas
        ];

        return $maxAges[$timeframe] ?? 5 * 60 * 1000; // Default 5 minutos
    }

    private function getCurrentTimeframe(): string {
        // Obtener el timeframe actual del exchange
        return $this->exchange->getCurrentTimeframe() ?? '1h';
    }

    private function normalizeData(array $data): array {
        return array_map(function($item) {
            return [
                'timestamp' => $item['timestamp'],
                'open' => (float)$item['open'],
                'high' => (float)$item['high'],
                'low' => (float)$item['low'],
                'close' => (float)$item['close'],
                'volume' => (float)$item['volume']
            ];
        }, $data);
    }

    private function buildCacheKey(string $type = 'historical', string $timeframe = '', int $limit = 0): string {
        return implode('_', [
            $this->sanitizeCacheKey(strtolower(get_class($this->exchange))),
            $this->sanitizeCacheKey($this->symbol),
            $this->sanitizeCacheKey($type),
            $this->sanitizeCacheKey($timeframe),
            $limit
        ]);
    }

    private function sanitizeCacheKey(string $keyPart): string {
        $replacements = [
            '\\' => '_',  // Namespaces a guiones bajos
            '/' => '_',   // Símbolos de pares
            '{' => '',    // Elimina caracteres reservados
            '}' => '',
            '(' => '',
            ')' => '',
            '@' => '',
            ':' => ''
        ];
        
        return strtr($keyPart, $replacements);
    }

    public function setCache(CacheInterface $cache): self {
        $this->cache = $cache;
        return $this;
    }

    public function clearCache(): void {
        $this->cache->deleteItem('*');
    }
}
