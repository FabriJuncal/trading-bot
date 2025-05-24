<?php
namespace TradingBot\Exceptions;

class ExchangeConnectionException extends TradingBotException {
    public function __construct($message = "", $code = 0, \Throwable $previous = null, array $context = []) {
        parent::__construct(
            "Error de conexión con el exchange: $message",
            $code,
            $previous,
            $context
        );
    }
}
?>