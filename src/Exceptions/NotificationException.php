<?php

namespace TradingBot\Exceptions;

class NotificationException extends TradingBotException {
    public function __construct(string $message = "", array $context = [], int $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $context, $code, $previous);
    }
} 