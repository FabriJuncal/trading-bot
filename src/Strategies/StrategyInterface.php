<?php
namespace TradingBot\Strategies;

interface StrategyInterface {
    /**
     * Ejecuta la lógica principal de la estrategia
     * 
     * @param array $marketData Datos del mercado en formato OHLCV
     * @param array $params Parámetros específicos de la estrategia
     * @return array Resultado de la ejecución con recomendación de orden
     */
    public function execute(array $marketData, array $params = []): array;

    /**
     * Determina si se debe ejecutar una orden basada en las condiciones
     */
    public function shouldExecute(array $marketData): bool;

    /**
     * Backtesting de la estrategia con datos históricos
     */
    public function backtest(array $historicalData): array;

    /**
     * Configuración de parámetros dinámicos
     */
    public function setParameters(array $params): void;

    /**
     * Obtiene los parámetros actuales de la estrategia
     */
    public function getParameters(): array;

    /**
     * Prepara datos para notificaciones/alertas
     */
    public function prepareNotificationData(array $executionResult): array;
}