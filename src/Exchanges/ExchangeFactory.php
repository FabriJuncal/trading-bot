<?php
namespace TradingBot\Exchanges;

use TradingBot\Exchanges\BinanceConnector;
use TradingBot\Exchanges\GateioConnector;
use InvalidArgumentException;

class ExchangeFactory {
    public static function create(string $exchangeName): object {
        switch (strtolower($exchangeName)) {
            case 'binance':
                return new BinanceConnector(
                    $_ENV['TESTNET_BINANCE_SPOT_API_KEY'],
                    $_ENV['TESTNET_BINANCE_SPOT_API_SECRET']
                );
            
            case 'gateio':
                return new GateioConnector(
                    $_ENV['GATEIO_API_KEY'],
                    $_ENV['GATEIO_API_SECRET']
                );
            
            default:
                throw new InvalidArgumentException(
                    "Exchange no soportado: $exchangeName"
                );
        }
    }
}