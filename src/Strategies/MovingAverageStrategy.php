<?php
namespace TradingBot\Strategies;

use TradingBot\Exceptions\StrategyExecutionException;

class MovingAverageStrategy implements StrategyInterface {
    private $parameters = [
        'fast_period' => 50,
        'slow_period' => 200,
        'confirmation_candles' => 2,
        'min_crossover_distance' => 0.5, // % para confirmar tendencia
        'timeframe' => '1d'
    ];

    public function execute(array $marketData, array $params = []): array {
        $this->validateMarketData($marketData);
        $this->setParameters(array_merge($this->parameters, $params));

        $closes = array_column($marketData, 'close');
        
        $fastMA = $this->calculateMA($closes, $this->parameters['fast_period']);
        $slowMA = $this->calculateMA($closes, $this->parameters['slow_period']);
        
        $currentFast = end($fastMA);
        $currentSlow = end($slowMA);
        $previousFast = $fastMA[count($fastMA)-2] ?? $currentFast;
        $previousSlow = $slowMA[count($slowMA)-2] ?? $currentSlow;

        return [
            'indicator' => 'MA',
            'action' => $this->determineCrossover($previousFast, $previousSlow, $currentFast, $currentSlow),
            'values' => [
                'fast_ma' => round($currentFast, 2),
                'slow_ma' => round($currentSlow, 2)
            ],
            'confidence' => $this->calculateConfidence($currentFast, $currentSlow),
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
        
        $fastMA = $this->calculateMA($closes, $this->parameters['fast_period']);
        $slowMA = $this->calculateMA($closes, $this->parameters['slow_period']);
        
        for ($i = max($this->parameters['fast_period'], $this->parameters['slow_period']); $i < count($closes); $i++) {
            $currentFast = $fastMA[$i];
            $currentSlow = $slowMA[$i];
            $previousFast = $fastMA[$i-1];
            $previousSlow = $slowMA[$i-1];
            
            $signal = $this->determineCrossover($previousFast, $previousSlow, $currentFast, $currentSlow);
            
            $results[] = [
                'timestamp' => $historicalData[$i]['timestamp'],
                'price' => $closes[$i],
                'fast_ma' => round($currentFast, 2),
                'slow_ma' => round($currentSlow, 2),
                'signal' => $signal
            ];
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
            'message' => "Señal MA: {$executionResult['action']}",
            'data' => [
                'MA Rápida' => $executionResult['values']['fast_ma'],
                'MA Lenta' => $executionResult['values']['slow_ma'],
                'Distancia' => round(abs($executionResult['values']['fast_ma'] - $executionResult['values']['slow_ma']), 2),
                'Confianza' => $executionResult['confidence'] * 100 . '%'
            ]
        ];
    }

    private function calculateMA(array $closes, int $period): array {
        $ma = [];
        for ($i = $period-1; $i < count($closes); $i++) {
            $sum = 0;
            for ($j = 0; $j < $period; $j++) {
                $sum += $closes[$i - $j];
            }
            $ma[] = $sum / $period;
        }
        return $ma;
    }

    private function determineCrossover(float $prevFast, float $prevSlow, float $currFast, float $currSlow): string {
        // Detectar cruce alcista
        if ($prevFast <= $prevSlow && $currFast > $currSlow) {
            return 'BUY';
        }
        
        // Detectar cruce bajista
        if ($prevFast >= $prevSlow && $currFast < $currSlow) {
            return 'SELL';
        }
        
        return 'HOLD';
    }

    private function calculateConfidence(float $fastMA, float $slowMA): float {
        $spread = abs($fastMA - $slowMA);
        $priceReference = max($fastMA, $slowMA);
        return min($spread / ($priceReference * 0.01), 1.0); // 1% de referencia
    }

    private function validateMarketData(array $marketData): void {
        $requiredData = max($this->parameters['fast_period'], $this->parameters['slow_period']);
        
        if (count($marketData) < $requiredData) {
            throw new StrategyExecutionException(
                "Datos insuficientes para calcular MA. " .
                "Se necesitan al menos $requiredData puntos de datos."
            );
        }
    }

    private function validateParameters(): void {
        if ($this->parameters['fast_period'] >= $this->parameters['slow_period']) {
            throw new \InvalidArgumentException(
                "El período rápido debe ser menor que el período lento"
            );
        }
    }

    private function analyzeBacktestResults(array $results): array {
        $analysis = [
            'total_trades' => 0,
            'successful_trades' => 0,
            'total_return' => 0.0,
            'max_drawdown' => 0.0,
            'current_streak' => 0,
            'longest_winning_streak' => 0,
            'longest_losing_streak' => 0
        ];

        $positionOpen = false;
        $entryPrice = 0.0;
        $currentStreak = 0;
        $lastTradeResult = null;

        foreach ($results as $point) {
            if ($point['signal'] !== 'HOLD' && !$positionOpen) {
                // Abrir posición
                $positionOpen = true;
                $entryPrice = $point['price'];
                $analysis['total_trades']++;
            } elseif ($positionOpen && $point['signal'] !== 'HOLD') {
                // Cerrar posición
                $positionOpen = false;
                $return = ($point['price'] - $entryPrice) / $entryPrice;
                $analysis['total_return'] += $return;
                
                if ($return > 0) {
                    $analysis['successful_trades']++;
                    $currentStreak = $lastTradeResult === 'win' ? $currentStreak + 1 : 1;
                    $analysis['longest_winning_streak'] = max($analysis['longest_winning_streak'], $currentStreak);
                    $lastTradeResult = 'win';
                } else {
                    $currentStreak = $lastTradeResult === 'loss' ? $currentStreak + 1 : 1;
                    $analysis['longest_losing_streak'] = max($analysis['longest_losing_streak'], $currentStreak);
                    $lastTradeResult = 'loss';
                }
                
                $analysis['max_drawdown'] = min($analysis['max_drawdown'], $return);
            }
        }

        return array_merge($results, ['analysis' => $analysis]);
    }
}