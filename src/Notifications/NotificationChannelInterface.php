<?php
namespace TradingBot\Notifications;

interface NotificationChannelInterface {
    /**
     * Envía una notificación a través del canal
     * 
     * @param string $message Contenido principal de la notificación
     * @param bool $isSuccess Indica si es una notificación de éxito o error
     * @param array $context Datos adicionales para contexto
     * 
     * @throws NotificationException Si ocurre un error en el envío
     */
    public function send(string $message, bool $isSuccess = true, array $context = []): void;
}