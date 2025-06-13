<?php
namespace TradingBot\Notifications;

interface NotificationChannelInterface {
    /**
     * Envía una notificación a través del canal
     * 
     * @param string $message El mensaje a enviar
     * @param bool $isSuccess Indica si la notificación es de éxito o error
     * @param array $context Contexto adicional para la notificación
     * 
     * @throws NotificationException Si hay un error al enviar la notificación
     */
    public function send(string $message, bool $isSuccess = true, array $context = []): void;
}