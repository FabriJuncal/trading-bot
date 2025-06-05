<?php
namespace TradingBot\Exchanges;

use ccxt\binance;
use ccxt\NetworkError;
use TradingBot\Exceptions\ExchangeConnectionException;
use TradingBot\Exceptions\OrderExecutionException;
use TradingBot\Utilities\TradingLogger;

class BinanceConnector {
    private $client;
    private $sandbox;
    private $currentTimeframe = '1h';

    public function __construct(string $apiKey, string $apiSecret) {
        $this->sandbox = $_ENV['APP_ENV'] === 'dev';
        
        $this->client = new binance([
            'apiKey' => $apiKey,
            'secret' => $apiSecret,
            'enableRateLimit' => true,
            'options' => [
                'defaultType' => 'spot', // spot, margin, future
                'adjustForTimeDifference' => true
            ],
            'enableRateLimit' => true, // Evitar bloqueos por límite de solicitudes
            'verbose' => $this->sandbox,
        ]);

        if ($this->sandbox) {
            $this->client->set_sandbox_mode(true);
        }
    }

    public function getMarketData(string $symbol, string $timeframe = '1h', int $limit = 100): array {
        try {
            // Sincronizar tiempo con el servidor de Binance
            $serverTime = $this->client->fetch_time();
            $localTime = (int)(microtime(true) * 1000);
            $timeDiff = $serverTime - $localTime;
            
            if (abs($timeDiff) > 5000) { // Si la diferencia es mayor a 5 segundos
                TradingLogger::warning("Gran diferencia de tiempo detectada", [
                    'server_time' => date('Y-m-d H:i:s', $serverTime/1000),
                    'local_time' => date('Y-m-d H:i:s', $localTime/1000),
                    'difference_ms' => $timeDiff
                ]);
            }

            // Validar que el símbolo existe
            $markets = $this->client->fetch_markets();
            $symbolExists = false;
            foreach ($markets as $market) {
                if ($market['symbol'] === $symbol) {
                    $symbolExists = true;
                    break;
                }
            }
            
            if (!$symbolExists) {
                throw new \Exception("El símbolo $symbol no está disponible en el exchange");
            }

            // Obtener datos OHLCV
            $ohlcv = $this->client->fetch_ohlcv($symbol, $timeframe, null, $limit);
            
            if (empty($ohlcv)) {
                throw new \Exception("No se pudieron obtener datos para $symbol");
            }

            // Ordenar por timestamp
            usort($ohlcv, function($a, $b) {
                return $a[0] <=> $b[0];
            });

            // Validar que el último dato no sea muy antiguo o futuro
            $lastTimestamp = (int)$ohlcv[count($ohlcv) - 1][0];
            $currentTime = $serverTime; // Usar el tiempo del servidor
            $maxAge = $this->getMaxAgeForTimeframe($timeframe);

            // Validar que el timestamp no sea futuro
            if ($lastTimestamp > ($currentTime + 60000)) { // No más de 1 minuto en el futuro
                throw new \Exception(
                    "Datos con timestamp futuro detectado. Última actualización: " . 
                    date('Y-m-d H:i:s', $lastTimestamp/1000)
                );
            }

            // Validar que los datos no sean muy antiguos
            if (($currentTime - $lastTimestamp) > $maxAge) {
                throw new \Exception(
                    "Datos desactualizados. Última actualización: " . 
                    date('Y-m-d H:i:s', $lastTimestamp/1000)
                );
            }

            return $this->formatMarketData($ohlcv);
        } catch (\ccxt\NetworkError $e) {
            TradingLogger::error("Error de red al obtener datos", [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Error de red al obtener datos: " . $e->getMessage());
        } catch (\ccxt\ExchangeError $e) {
            TradingLogger::error("Error del exchange al obtener datos", [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Error del exchange al obtener datos: " . $e->getMessage());
        } catch (\Exception $e) {
            TradingLogger::error("Error al obtener datos", [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function executeOrder(string $symbol, string $side, float $amount): array {
        try {
            if (!$this->isMarketAvailable($symbol)) {
                throw new OrderExecutionException("Market not available: $symbol");
            }

            return $this->client->create_market_order($symbol, $side, $amount);
        } catch (\ccxt\ExchangeError $e) {
            throw new OrderExecutionException(
                $e->getMessage(),
                $e->getCode(),
                $e,
                [
                    'exchange' => 'Binance',
                    'symbol' => $symbol,
                    'side' => $side,
                    'amount' => $amount
                ]
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

    private function formatMarketData(array $ohlcv): array {
        return array_map(function($candle) {
            return [
                'timestamp' => (int)$candle[0],
                'open' => (float)$candle[1],
                'high' => (float)$candle[2],
                'low' => (float)$candle[3],
                'close' => (float)$candle[4],
                'volume' => (float)$candle[5]
            ];
        }, $ohlcv);
    }

    private function isMarketAvailable(string $symbol): bool {
        $markets = $this->client->load_markets();
        return isset($markets[$symbol]) && $markets[$symbol]['active'];
    }

    public function getBalance(): array {
        try {
            // Obtener balance de la wallet
            $balance = $this->client->fetchBalance();

            // Mostrar solo activos con saldo > 0
            foreach ($balance['free'] as $asset => $amount) {
                if ($amount > 0) {
                    // Create a new array with the asset and amount
                    $free[$asset] = $amount;
                }
            }
            // Mostrar solo activos con saldo > 0
            foreach ($balance['used'] as $asset => $amount) {
                if ($amount > 0) {
                    // echo "Activo: $asset | Saldo: $amount\n";
                    $used[$asset] = $amount;
                }
            }
            // Mostrar solo activos con saldo > 0
            foreach ($balance['total'] as $asset => $amount) {
                if ($amount > 0) {
                    // echo "Activo: $asset | Saldo: $amount\n";
                    $total[$asset] = $amount;
                }
            }
            
            return [
                'free' => $free ?? [],
                'used' => $used ?? [],
                'total' => $total ?? []
            ];
            
        } catch (NetworkError $e) {
            throw new ExchangeConnectionException(
                "Error de conexión al obtener balance: " . $e->getMessage(),
                $e->getCode(),
                $e,
                ['endpoint' => 'fetchBalance']
            );
        } catch (\ccxt\ExchangeError $e) {
            throw new OrderExecutionException(
                "Error del exchange al obtener balance: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function getServerTime(): int {
        return $this->client->fetch_time();
    }

    public function getCurrentTimeframe(): string {
        return $this->currentTimeframe ?? '1h';
    }

    public function setCurrentTimeframe(string $timeframe): void {
        $this->currentTimeframe = $timeframe;
    }
}