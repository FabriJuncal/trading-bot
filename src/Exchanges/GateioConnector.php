<?php
namespace TradingBot\Exchanges;

use ccxt\gateio;
use TradingBot\Exceptions\ExchangeConnectionException;
use TradingBot\Exceptions\OrderExecutionException;

class GateioConnector {
    private $client;
    private $sandbox;

    public function __construct(string $apiKey, string $apiSecret) {
        $this->sandbox = $_ENV['APP_ENV'] === 'dev';
        
        $this->client = new gateio([
            'apiKey' => $apiKey,
            'secret' => $apiSecret,
            'enableRateLimit' => true,
            'options' => [
                'createMarketBuyOrderRequiresPrice' => false,
                'adjustForTimeDifference' => true
            ],
            'verbose' => $this->sandbox,
        ]);
    }

    public function getMarketData(string $symbol, string $timeframe = '1h', int $limit = 100): array {
        try {
            $ohlcv = $this->client->fetch_ohlcv($symbol, $timeframe, null, $limit);
            return $this->formatMarketData($ohlcv);
        } catch (\ccxt\NetworkError $e) {
            throw new ExchangeConnectionException(
                "Error fetching Gate.io data: " . $e->getMessage(),
                0,
                $e,
                ['symbol' => 'Gate.io - ' . $symbol]
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
                "Gate.io order failed: " . $e->getMessage(),
                0,
                $e,
                [
                    'exchange' => 'Gate.io',
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
}