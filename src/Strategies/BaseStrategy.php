<?php

namespace TradingBot\Strategies;

use TradingBot\Utilities\Config;

abstract class BaseStrategy {
    protected $parameters = [];

    public function setParameters(array $parameters): void {
        $this->parameters = array_merge($this->parameters, $parameters);
    }

    public function getParameters(): array {
        return $this->parameters;
    }

    abstract public function shouldExecute(array $data): bool;
    abstract public function execute(array $data): array;

    public function prepareNotificationData(array $result): array {
        return [
            'message' => $this->formatNotificationMessage($result),
            'data' => $this->formatNotificationData($result)
        ];
    }

    public function prepareOrderData(array $result): array {
        return [
            'symbol' => $this->parameters['symbol'],
            'side' => strtolower($result['action']),
            'amount' => $result['amount'] ?? Config::get('global.order_amount', 0.01),
            'price' => $result['price'] ?? null,
            'type' => $result['type'] ?? 'market',
            'timestamp' => time()
        ];
    }

    protected function formatNotificationMessage(array $result): string {
        return sprintf(
            "🔔 Señal de Trading\n\n" .
            "📊 Detalles:\n" .
            "• Acción: %s\n" .
            "• Par: %s\n" .
            "• Temporalidad: %s\n" .
            "• Confianza: %s%%",
            $result['action'],
            $this->parameters['symbol'],
            $this->parameters['timeframe'],
            $result['confidence'] ?? 'N/A'
        );
    }

    protected function formatNotificationData(array $result): array {
        return [
            'action' => $result['action'],
            'symbol' => $this->parameters['symbol'],
            'timeframe' => $this->parameters['timeframe'],
            'confidence' => $result['confidence'] ?? null,
            'indicators' => $result['indicators'] ?? [],
            'timestamp' => time()
        ];
    }
} 