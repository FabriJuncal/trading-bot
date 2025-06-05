<?php
namespace TradingBot\Services;

use TradingBot\Exchanges\ExchangeFactory;

class AccountDataService {
    private $exchange;

    public function __construct(string $exchangeName) {
        $this->exchange = ExchangeFactory::create($exchangeName);
    }

    public function getBalance() {
        $accountBalance = $this->exchange->getBalance();
        return $accountBalance;
    }

}