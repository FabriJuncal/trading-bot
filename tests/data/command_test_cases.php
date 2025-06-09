<?php

return [
    'trade:run' => [
        'valid_cases' => [
            [
                'input' => [
                    '--strategy' => 'rsi',
                    '--exchange' => 'binance',
                    '--symbol' => 'BTC/USDT',
                    '--interval' => '1h'
                ],
                'expected_output' => 'Iniciando bot de trading con configuración:',
                'expected_status' => 0
            ],
            [
                'input' => [
                    '--strategy' => 'ma',
                    '--exchange' => 'gateio',
                    '--symbol' => 'ETH/USDT',
                    '--interval' => '4h'
                ],
                'expected_output' => 'Iniciando bot de trading con configuración:',
                'expected_status' => 0
            ]
        ],
        'invalid_cases' => [
            [
                'input' => [
                    '--strategy' => 'invalid',
                    '--exchange' => 'binance',
                    '--symbol' => 'BTC/USDT',
                    '--interval' => '1h'
                ],
                'expected_output' => 'Estrategia no válida',
                'expected_status' => 1
            ],
            [
                'input' => [
                    '--strategy' => 'rsi',
                    '--exchange' => 'invalid',
                    '--symbol' => 'BTC/USDT',
                    '--interval' => '1h'
                ],
                'expected_output' => 'Exchange no válido',
                'expected_status' => 1
            ],
            [
                'input' => [
                    '--strategy' => 'rsi',
                    '--exchange' => 'binance',
                    '--symbol' => 'BTC/USDT',
                    '--interval' => 'invalid'
                ],
                'expected_output' => 'Formato de intervalo no válido',
                'expected_status' => 1
            ]
        ]
    ],
    'notify:run' => [
        'valid_cases' => [
            [
                'input' => [
                    '--strategy' => 'rsi',
                    '--exchange' => 'binance',
                    '--symbol' => 'BTC/USDT',
                    '--interval' => '1h'
                ],
                'expected_output' => 'Iniciando bot de trading para notificar con configuración:',
                'expected_status' => 0
            ],
            [
                'input' => [
                    '--strategy' => 'ma',
                    '--exchange' => 'gateio',
                    '--symbol' => 'ETH/USDT',
                    '--interval' => '4h'
                ],
                'expected_output' => 'Iniciando bot de trading para notificar con configuración:',
                'expected_status' => 0
            ]
        ],
        'invalid_cases' => [
            [
                'input' => [
                    '--strategy' => 'invalid',
                    '--exchange' => 'binance',
                    '--symbol' => 'BTC/USDT',
                    '--interval' => '1h'
                ],
                'expected_output' => 'Estrategia no válida',
                'expected_status' => 1
            ],
            [
                'input' => [
                    '--strategy' => 'rsi',
                    '--exchange' => 'invalid',
                    '--symbol' => 'BTC/USDT',
                    '--interval' => '1h'
                ],
                'expected_output' => 'Exchange no válido',
                'expected_status' => 1
            ],
            [
                'input' => [
                    '--strategy' => 'rsi',
                    '--exchange' => 'binance',
                    '--symbol' => 'BTC/USDT',
                    '--interval' => 'invalid'
                ],
                'expected_output' => 'Formato de intervalo no válido',
                'expected_status' => 1
            ]
        ]
    ],
    'trade:stop' => [
        'valid_cases' => [
            [
                'input' => [],
                'expected_output' => 'No hay instancias en ejecución',
                'expected_status' => 0
            ],
            [
                'input' => ['--symbol' => 'BTC/USDT'],
                'expected_output' => 'No hay instancias en ejecución',
                'expected_status' => 0
            ]
        ]
    ],
    'notify:stop' => [
        'valid_cases' => [
            [
                'input' => [],
                'expected_output' => 'No hay instancias de notificación en ejecución',
                'expected_status' => 0
            ],
            [
                'input' => ['--symbol' => 'BTC/USDT'],
                'expected_output' => 'No hay instancias de notificación en ejecución',
                'expected_status' => 0
            ]
        ]
    ],
    'trade:list' => [
        'valid_cases' => [
            [
                'input' => [],
                'expected_output' => 'No hay procesos de trading activos',
                'expected_status' => 0
            ],
            [
                'input' => ['--json' => true],
                'expected_output' => '"status":"success"',
                'expected_status' => 0
            ]
        ]
    ],
    'notify:list' => [
        'valid_cases' => [
            [
                'input' => [],
                'expected_output' => 'No hay procesos de notificación activos',
                'expected_status' => 0
            ],
            [
                'input' => ['--json' => true],
                'expected_output' => '"status":"success"',
                'expected_status' => 0
            ]
        ]
    ],
    'process:list' => [
        'valid_cases' => [
            [
                'input' => [],
                'expected_output' => 'No hay procesos activos',
                'expected_status' => 0
            ],
            [
                'input' => ['--json' => true],
                'expected_output' => '"status":"success"',
                'expected_status' => 0
            ]
        ]
    ]
]; 