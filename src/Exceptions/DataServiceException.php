<?php
// src/Exceptions/DataServiceException.php
namespace TradingBot\Exceptions;

class DataServiceException extends \RuntimeException {
    private array $context;

    public function __construct(
        string $message = "", 
        int $code = 0, 
        \Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array {
        return $this->context;
    }

    public function getDetails(): string {
        return sprintf(
            "[%s] %s | Context: %s",
            date('Y-m-d H:i:s'),
            $this->getMessage(),
            json_encode($this->context, JSON_PRETTY_PRINT)
        );
    }
}