<?php
namespace TradingBot\Exceptions;

class ConfigException extends \RuntimeException {
    protected $context = [];

    public function __construct($message = "", $code = 0, \Throwable $previous = null, array $context = []) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array {
        return $this->context;
    }
}