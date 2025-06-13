<?php

return [
    'notifications' => [
        'enabled_channels' => ['telegram'],
        'telegram' => [
            'enabled' => true,
            'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? null,
            'chat_id' => $_ENV['TELEGRAM_CHAT_ID'] ?? null
        ],
        'discord' => [
            'enabled' => false,
            'bot_token' => $_ENV['DISCORD_BOT_TOKEN'] ?? null,
            'channel_id' => $_ENV['DISCORD_CHANNEL_ID'] ?? null
        ]
    ],
    'trading' => [
        'confirmation_timeout' => 300, // 5 minutos
        'auto_confirm' => false
    ],
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'file' => __DIR__ . '/../logs/trading.log'
    ]
]; 