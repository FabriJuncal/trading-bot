<?php
namespace TradingBot\Exceptions;

class StrategyExecutionException extends \RuntimeException {
    private array $context;

    public function __construct(
        string $message = "", 
        int $code = 0, 
        \Throwable $previous = null,
        array $context = []
    ) {
        $dateTime = new \DateTime();
        $dateTime->setTimezone(new \DateTimeZone('America/Argentina/Buenos_Aires'));
        
        $context['timestamp'] = $dateTime->format('Y-m-d H:i:s');
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array {
        return $this->context;
    }

    public function getStrategyDetails(): string {
        return sprintf(
            "[%s] Estrategia fallida - %s | Contexto: %s",
            date('Y-m-d H:i:s'),
            $this->getMessage(),
            json_encode($this->context, JSON_PRETTY_PRINT)
        );
    }
}