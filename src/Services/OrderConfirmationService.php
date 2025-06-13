<?php

namespace TradingBot\Services;

use TradingBot\Notifications\TelegramNotifier;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Exceptions\OrderConfirmationException;

class OrderConfirmationService {
    private const CONFIRMATION_TIMEOUT = 300; // 5 minutos
    private const CONFIRMATION_PREFIX = 'order_confirm_';
    
    private TelegramNotifier $notifier;
    private array $pendingConfirmations = [];
    private bool $autoConfirm;

    public function __construct(bool $autoConfirm = false) {
        $this->notifier = new TelegramNotifier();
        $this->autoConfirm = $autoConfirm;
    }

    public function requestConfirmation(array $orderData): bool {
        $confirmationId = uniqid(self::CONFIRMATION_PREFIX);
        
        if ($this->autoConfirm) {
            return true;
        }

        $message = $this->formatConfirmationMessage($orderData, $confirmationId);
        $this->pendingConfirmations[$confirmationId] = [
            'order' => $orderData,
            'timestamp' => time()
        ];

        try {
            $this->notifier->send($message, true, [
                'type' => 'confirmation_request',
                'confirmation_id' => $confirmationId
            ]);
            
            return $this->waitForConfirmation($confirmationId);
        } catch (\Exception $e) {
            TradingLogger::error("Error al solicitar confirmación: " . $e->getMessage());
            throw new OrderConfirmationException("Error al solicitar confirmación: " . $e->getMessage());
        }
    }

    public function handleConfirmationResponse(string $confirmationId, bool $isConfirmed): void {
        if (!isset($this->pendingConfirmations[$confirmationId])) {
            throw new OrderConfirmationException("ID de confirmación inválido");
        }

        $orderData = $this->pendingConfirmations[$confirmationId]['order'];
        unset($this->pendingConfirmations[$confirmationId]);

        if (!$isConfirmed) {
            throw new OrderConfirmationException("Operación rechazada por el usuario");
        }
    }

    private function formatConfirmationMessage(array $orderData, string $confirmationId): string {
        return sprintf(
            "🔄 <b>Confirmación de Operación</b>\n\n" .
            "Par: %s\n" .
            "Tipo: %s\n" .
            "Cantidad: %s\n" .
            "Precio: %s\n\n" .
            "Para confirmar: /confirmar_%s\n" .
            "Para rechazar: /rechazar_%s",
            $orderData['symbol'],
            $orderData['side'],
            $orderData['amount'],
            $orderData['price'],
            $confirmationId,
            $confirmationId
        );
    }

    private function waitForConfirmation(string $confirmationId): bool {
        $startTime = time();
        
        while (time() - $startTime < self::CONFIRMATION_TIMEOUT) {
            if (!isset($this->pendingConfirmations[$confirmationId])) {
                return true;
            }
            sleep(1);
        }

        unset($this->pendingConfirmations[$confirmationId]);
        throw new OrderConfirmationException("Tiempo de espera agotado para la confirmación");
    }
} 