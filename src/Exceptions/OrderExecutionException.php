<?php
namespace TradingBot\Exceptions;

class OrderExecutionException extends TradingBotException {
    public function __construct($message = "", $code = 0, \Throwable $previous = null, array $context = []) {
        parent::__construct(
            "Error ejecutando orden: $message",
            $code,
            $previous,
            $context
        );
    }
}
?>