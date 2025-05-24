<?php
namespace TradingBot\Strategies;

use TradingBot\Exceptions\StrategyExecutionException;

class RsiStrategy implements StrategyInterface {
    private $parameters = [
        'period' => 14,
        'oversold' => 40,
        'overbought' => 70,
        'neutral' => 50,
        'timeframe' => '1h'
    ];

    public function execute(array $marketData, array $params = []): array {
        $this->validateMarketData($marketData);
        $this->setParameters(array_merge($this->parameters, $params));

        $closes = array_column($marketData, 'close');
        $rsiValues = $this->calculateRsi($closes, $this->parameters['period']);
        $currentRsi = end($rsiValues);

        return [
            'indicator' => 'RSI',
            'value' => round($currentRsi, 2),
            'action' => $this->determineAction($currentRsi),
            'confidence' => $this->calculateConfidence($currentRsi),
            'timestamp' => time()
        ];
    }

    public function shouldExecute(array $marketData): bool {
        $result = $this->execute($marketData);
        return $result['action'] !== 'HOLD';
    }

    public function backtest(array $historicalData): array {
        $results = [];
        $closes = array_column($historicalData, 'close');
        
        for ($i = $this->parameters['period']; $i < count($closes); $i++) {
            $window = array_slice($closes, 0, $i + 1);
            $rsi = $this->calculateRsi($window, $this->parameters['period']);
            
            if (!empty($rsi)) {
                $currentRsi = end($rsi);
                $results[] = [
                    'timestamp' => $historicalData[$i]['timestamp'],
                    'price' => $closes[$i],
                    'rsi' => round($currentRsi, 2),
                    'signal' => $this->determineAction($currentRsi)
                ];
            }
        }
        
        return $this->analyzeBacktestResults($results);
    }

    public function setParameters(array $params): void {
        $this->parameters = array_merge($this->parameters, $params);
        $this->validateParameters();
    }

    public function getParameters(): array {
        return $this->parameters;
    }

    public function prepareNotificationData(array $executionResult): array {
        return [
            'message' => "Señal RSI: {$executionResult['action']}",
            'data' => [
                'Valor RSI' => $executionResult['value'],
                'Confianza' => $executionResult['confidence'] * 100 . '%',
                'Par' => $this->parameters['symbol'] ?? 'N/A',
                'Timeframe' => $this->parameters['timeframe']
            ]
        ];
    }

    private function calculateRsi(array $closes, int $period): array {
        $rsi = [];
        $changes = [];
        $avgGain = 0;
        $avgLoss = 0;

        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $changes[] = $change;
            
            if ($i >= $period) {
                $gain = $loss = 0;
                $window = array_slice($changes, $i - $period, $period);
                
                foreach ($window as $change) {
                    if ($change > 0) $gain += $change;
                    else $loss += abs($change);
                }

                $avgGain = ($avgGain * ($period - 1) + $gain) / $period;
                $avgLoss = ($avgLoss * ($period - 1) + $loss) / $period;

                if ($avgLoss == 0) {
                    $rs = $avgGain == 0 ? 0 : INF;
                } else {
                    $rs = $avgGain / $avgLoss;
                }

                $rsi[] = 100 - (100 / (1 + $rs));
            }
        }

        return $rsi;
    }

    private function determineAction(float $rsi): string {
        if ($rsi <= $this->parameters['oversold']) {
            return 'BUY';
        } elseif ($rsi >= $this->parameters['overbought']) {
            return 'SELL';
        }
        return 'HOLD';
    }

    private function calculateConfidence(float $rsi): float {
        $distanceFromNeutral = abs($rsi - $this->parameters['neutral']);
        $maxDistance = $this->parameters['neutral'] - $this->parameters['oversold'];
        return min($distanceFromNeutral / $maxDistance, 1.0);
    }

    private function validateMarketData(array $marketData): void {
        if (count($marketData) < $this->parameters['period'] + 1) {
            throw new StrategyExecutionException(
                "Datos insuficientes para calcular RSI {$this->parameters['period']}. " .
                "Se necesitan al menos " . ($this->parameters['period'] + 1) . " puntos de datos."
            );
        }
    }

    private function validateParameters(): void {
        if ($this->parameters['oversold'] >= $this->parameters['overbought']) {
            throw new \InvalidArgumentException(
                "El umbral de sobreventa debe ser menor que el de sobrecompra"
            );
        }
    }

    private function analyzeBacktestResults(array $results): array {
        $analysis = [
            'total_signals' => 0,
            'buy_signals' => 0,
            'sell_signals' => 0,
            'profitable_trades' => 0,
            'total_return' => 0.0
        ];

        $positionOpen = false;
        $entryPrice = 0.0;

        foreach ($results as $point) {
            if ($point['signal'] !== 'HOLD') {
                $analysis['total_signals']++;
                
                if ($point['signal'] === 'BUY') {
                    $analysis['buy_signals']++;
                    $positionOpen = true;
                    $entryPrice = $point['price'];
                } elseif ($positionOpen) {
                    $analysis['sell_signals']++;
                    $positionOpen = false;
                    $return = ($point['price'] - $entryPrice) / $entryPrice;
                    $analysis['total_return'] += $return;
                    if ($return > 0) $analysis['profitable_trades']++;
                }
            }
        }

        return array_merge($results, ['analysis' => $analysis]);
    }
}