<?php
// Parámetros de estrategias de trading

return [
    'exchanges' => [
        'binance' => [
            'api_key' => $_ENV['BINANCE_API_KEY'] ?? null,
            'api_secret' => $_ENV['BINANCE_API_SECRET'] ?? null
        ]
    ],
    'notifications' => [
        'telegram' => [
            'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? null,
            'chat_id' => $_ENV['TELEGRAM_CHAT_ID'] ?? null
        ]
    ],
    'rsi' => [
        'period' => 14,          // Período para cálculo del RSI
        'oversold_threshold' => 40, // Nivel de sobreventa
        'symbol' => 'BTC/USDT',   // Par a operar
        'timeframe' => '1h'       // Intervalo temporal (1h, 4h, etc.)
    ],
    
    'moving_average' => [
        'fast_period' => 50,     // Media móvil rápida (50 períodos)
        'slow_period' => 200,     // Media móvil lenta (200 períodos)
        'crossover_confirmation' => 2 // Velas de confirmación para el cruce
    ],
    
    'global' => [
        'max_retries' => 3,      // Reintentos fallidos de órdenes
        'order_amount' => 0.01,   // Cantidad base a operar (BTC)
        'risk_per_trade' => 0.02 // 2% de riesgo por operación
    ]
];

// Cómo acceder a la configuración desde otros archivos:
// $config = require __DIR__.'/../config/strategies.php';
// $rsiPeriod = $config['rsi']['period'];