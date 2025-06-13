<?php
// Bootstrap para PHPUnit: sobreescribe la configuración de Telegram para los tests

putenv('TELEGRAM_BOT_TOKEN=dummy_token');
putenv('TELEGRAM_CHAT_ID=123456'); 