<?php
namespace TradingBot\Exchanges;

use ccxt\binance;
use ccxt\NetworkError;
use TradingBot\Exceptions\ExchangeConnectionException;
use TradingBot\Exceptions\OrderExecutionException;

class BinanceConnector {
    private $client;
    private $sandbox;

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
            'verbose' => $this->sandbox,
        ]);

        if ($this->sandbox) {
            $this->client->set_sandbox_mode(true);
        }
    }

    public function getMarketData(string $symbol, string $timeframe = '1h', int $limit = 100): array {
        try {
            $ohlcv = $this->client->fetch_ohlcv($symbol, $timeframe, null, $limit);
            return $this->formatMarketData($ohlcv);
        } catch (NetworkError $e) {
            throw new ExchangeConnectionException(
                $e->getMessage(),
                $e->getCode(),
                $e,
                ['symbol' => 'Binance - ' . $symbol]
            );
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

    private function formatMarketData(array $ohlcv): array {
        return array_map(function($item) {
            return [
                'timestamp' => $item[0],
                'open' => $item[1],
                'high' => $item[2],
                'low' => $item[3],
                'close' => $item[4],
                'volume' => $item[5]
            ];
        }, $ohlcv);
    }

    private function isMarketAvailable(string $symbol): bool {
        $markets = $this->client->load_markets();
        return isset($markets[$symbol]) && $markets[$symbol]['active'];
    }

    public function getBalance(): array {
        try {
            $balance = $this->client->fetchBalance();
            
            return [
                'free' => $balance['free'] ?? [],
                'used' => $balance['used'] ?? [],
                'total' => $balance['total'] ?? []
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
}