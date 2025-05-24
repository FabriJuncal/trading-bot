<?php
// src/Services/OrderService.php
namespace TradingBot\Services;

use TradingBot\Exchanges\ExchangeFactory;
use TradingBot\Exceptions\OrderExecutionException;
use TradingBot\Notifications\NotificationManager;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Utilities\Config;

class OrderService {
    private $exchange;
    private $symbol;
    private $notificationManager;
    private $maxRetries = 3;
    private $retryDelay = 1;
    private $lockFile;

    public function __construct(string $exchangeName, string $symbol) {
        $this->exchange = ExchangeFactory::create($exchangeName);
        $this->symbol = $symbol;
        $this->notificationManager = new NotificationManager();
        $this->lockFile = __DIR__."/../../storage/locks/".md5($exchangeName.$symbol).".lock";
    }

    public function executeOrder(string $side, float $amount, array $params = []): array {
        $this->acquireLock();
        
        try {
            $order = $this->executeWithRetry($side, $amount, $params);
            $this->handleRiskManagement($order, $params);
            
            TradingLogger::info("Orden ejecutada exitosamente", $order);
            $this->sendNotification($order, true);
            
            return $order;
        } catch (\Exception $e) {
            TradingLogger::error("Error en orden: ".$e->getMessage(), $params);
            $this->sendNotification([], false, $e->getMessage());
            throw new OrderExecutionException($e->getMessage(), 0, $e);
        } finally {
            $this->releaseLock();
        }
    }

    private function executeWithRetry(string $side, float $amount, array $params): array {
        $retryCount = 0;
        $lastError = null;
        
        while ($retryCount <= $this->maxRetries) {
            try {
                $this->validateOrder($side, $amount);
                return $this->exchange->executeOrder($this->symbol, $side, $amount, $params);
            } catch (\Exception $e) {
                $lastError = $e;
                TradingLogger::warning("Intento de orden fallido ($retryCount/".$this->maxRetries.")", [
                    'error' => $e->getMessage(),
                    'params' => $params
                ]);
                
                if ($retryCount++ < $this->maxRetries) {
                    sleep($this->retryDelay * pow(2, $retryCount));
                    continue;
                }
                
                throw $lastError;
            }
        }
        
        throw $lastError;
    }

    private function validateOrder(string $side, float $amount): void {
        $balance = $this->exchange->getBalance();
        $symbolParts = explode('/', $this->symbol);
        
        // Obtener moneda relevante (base para venta, quote para compra)
        $currency = ($side === 'buy') ? $symbolParts[1] : $symbolParts[0];
        
        $minOrderSize = $this->exchange->getMinOrderSize($this->symbol);
        $currentPrice = $this->exchange->getPrice($this->symbol);
        $orderValue = $amount * $currentPrice;
    
        // Verificar balance disponible
        $available = $balance['free'][$currency] ?? 0;
    
        if ($side === 'buy' && $available < $orderValue) {
            throw new OrderExecutionException(
                "Saldo insuficiente en $currency. Disponible: $available, Requerido: $orderValue"
            );
        }
    
        if ($side === 'sell' && $available < $amount) {
            throw new OrderExecutionException(
                "Saldo insuficiente en $currency. Disponible: $available, Requerido: $amount"
            );
        }
    
        // Verificar tamaño mínimo de orden
        if ($orderValue < $minOrderSize) {
            throw new OrderExecutionException(
                "Tamaño de orden insuficiente. Mínimo requerido: $minOrderSize, Intentado: $orderValue"
            );
        }
    }

    private function handleRiskManagement(array $order, array $params): void {
        if (isset($params['stopLoss'])) {
            $this->exchange->createStopLossOrder(
                $this->symbol,
                $order['amount'],
                $params['stopLoss'],
                $order['side'] === 'buy' ? 'sell' : 'buy'
            );
        }
        
        if (isset($params['takeProfit'])) {
            $this->exchange->createTakeProfitOrder(
                $this->symbol,
                $order['amount'],
                $params['takeProfit'],
                $order['side'] === 'buy' ? 'sell' : 'buy'
            );
        }
    }

    private function sendNotification(array $order, bool $isSuccess, string $error = ''): void {
        $message = $isSuccess 
            ? "Orden {$order['side']} de {$order['amount']} {$this->symbol} ejecutada @ {$order['price']}"
            : "Error en orden: $error";

        $this->notificationManager->notify($message, $isSuccess, [
            'exchange' => get_class($this->exchange),
            'symbol' => $this->symbol,
            'order' => $order
        ]);
    }

    private function acquireLock(): void {
        $fp = fopen($this->lockFile, 'w+');
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            throw new OrderExecutionException("Orden en proceso para {$this->symbol}. Intente nuevamente más tarde.");
        }
        register_shutdown_function([$this, 'releaseLock']);
    }

    public function releaseLock(): void {
        if (file_exists($this->lockFile)) {
            $fp = fopen($this->lockFile, 'w+');
            flock($fp, LOCK_UN);
            fclose($fp);
            unlink($this->lockFile);
        }
    }
}