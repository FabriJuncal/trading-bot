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
        $this->cache = new FilesystemAdapter('market_data', 3600, __DIR__.'/../../storage/cache');
    }

    public function getHistoricalData(string $timeframe = '1h', int $limit = 100, bool $forceRefresh = false): array {
        $cacheKey = $this->buildCacheKey('historical', $timeframe, $limit);

        return $this->cache->get($cacheKey, function () use ($timeframe, $limit, $forceRefresh) {
            return $this->fetchWithRetry(function () use ($timeframe, $limit) {
                $data = $this->exchange->getMarketData($this->symbol, $timeframe, $limit);
                $this->validateData($data, $limit);
                return $this->normalizeData($data);
            }, $forceRefresh);
        });
    }

    public function getRealTimeData(): array {
        return $this->fetchWithRetry(function () {
            $data = $this->exchange->getMarketData($this->symbol, '1m', 1);
            $this->validateData($data, 1);
            return $this->normalizeData($data)[0] ?? [];
        });
    }

    public function getMultipleTimeframeData(array $timeframes): array {
        $result = [];
        foreach ($timeframes as $tf) {
            $result[$tf] = $this->getHistoricalData($tf);
        }
        return $result;
    }

    private function fetchWithRetry(callable $fetchFunction, bool $forceRefresh = false) {
        $retryCount = 0;
        
        if ($forceRefresh) {
            $this->cache->delete($this->buildCacheKey());
        }

        while ($retryCount <= $this->maxRetries) {
            try {
                $data = $fetchFunction();
                TradingLogger::info("Datos obtenidos exitosamente", [
                    'exchange' => get_class($this->exchange),
                    'symbol' => $this->symbol
                ]);
                return $data;
            } catch (\Exception $e) {
                TradingLogger::warning("Intento $retryCount fallido: " . $e->getMessage());
                
                if ($retryCount === $this->maxRetries) {
                    throw new DataServiceException(
                        "Fallo al obtener datos después de {$this->maxRetries} intentos: " . $e->getMessage(),
                        0,
                        $e
                    );
                }
                
                sleep($this->retryDelay * ($retryCount + 1));
                $retryCount++;
            }
        }
    }

    private function validateData(array $data, int $expectedCount): void {
        if (count($data) < $expectedCount) {
            throw new DataServiceException("Datos insuficientes. Esperados: $expectedCount, Obtenidos: " . count($data));
        }
        
        $requiredKeys = ['timestamp', 'open', 'high', 'low', 'close', 'volume'];
        foreach ($data as $entry) {
            if (count(array_intersect_key($entry, array_flip($requiredKeys))) !== count($requiredKeys)) {
                throw new DataServiceException("Formato de datos inválido");
            }
        }
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
}