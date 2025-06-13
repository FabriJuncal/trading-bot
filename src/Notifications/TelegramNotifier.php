<?php
namespace TradingBot\Notifications;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Exceptions\NotificationException;
use TradingBot\Utilities\Config;

class TelegramNotifier implements NotificationChannelInterface {
    private $client;
    private $botToken;
    private $chatId;
    private $apiEndpoint;

    public function __construct() {
        TradingLogger::info("Iniciando TelegramNotifier");
        $this->validateConfig();
        
        $this->botToken = Config::get('config.notifications.telegram.bot_token');
        $this->chatId = Config::get('config.notifications.telegram.chat_id');
        
        TradingLogger::info("Configuración de Telegram cargada", [
            'bot_token_length' => strlen($this->botToken ?? ''),
            'chat_id' => $this->chatId
        ]);
        
        $this->client = new Client([
            'base_uri' => 'https://api.telegram.org',
            'timeout'  => 10.0
        ]);
        
        $this->apiEndpoint = "/bot{$this->botToken}/sendMessage";
        TradingLogger::info("TelegramNotifier inicializado correctamente");
    }

    public function send(string $message, bool $isSuccess = true, array $context = []): void {
        if (empty($this->botToken) || empty($this->chatId)) {
            throw new NotificationException("Configuración de Telegram incompleta");
        }

        try {
            $formattedMessage = $this->formatMessage($message, $isSuccess, $context);
            
            $response = $this->client->post($this->apiEndpoint, [
                'form_params' => [
                    'chat_id' => $this->chatId,
                    'text' => $formattedMessage,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true
                ]
            ]);

            $this->handleResponse($response, $formattedMessage);
        } catch (GuzzleException $e) {
            TradingLogger::error("Telegram API Error: " . $e->getMessage(), $context);
            throw new NotificationException(
                "Failed to send Telegram notification: " . $e->getMessage(),
                $context,
                0,
                $e
            );
        }
    }

    private function formatMessage(string $message, bool $isSuccess, array $context): string {
        $emoji = $isSuccess ? '✅' : '❌';
        $header = "{$emoji} Trading Bot Notification {$emoji}";
        
        $formattedMessage = "{$header}\n\n";
        
        // Formatear el mensaje principal
        $formattedMessage .= "📝 Mensaje:\n";
        $formattedMessage .= "{$message}\n\n";
        
        if (!empty($context)) {
            $formattedMessage .= "📊 Detalles:\n";
            
            // Formatear cada campo del contexto de manera más legible
            foreach ($context as $key => $value) {
                $key = ucfirst(str_replace('_', ' ', $key));
                $formattedMessage .= "• {$key}: {$value}\n";
            }
        }
        
        return $formattedMessage;
    }

    private function handleResponse($response, string $message): void {
        $statusCode = $response->getStatusCode();
        $responseBody = json_decode($response->getBody(), true);

        if ($statusCode !== 200 || !$responseBody['ok']) {
            TradingLogger::error("Respuesta de error de la API de Telegram", [
                'status' => $statusCode,
                'response' => $responseBody
            ]);
            
            throw new NotificationException(
                "La API de Telegram respondió con un error: " . ($responseBody['description'] ?? 'Unknown error'),
                ['status' => $statusCode, 'response' => $responseBody]
            );
        }

        TradingLogger::info("Notificación de Telegram enviada exitosamente", [
            'message_length' => strlen($message),
            'recipient' => $this->chatId
        ]);
    }

    private function validateConfig(): void {
        TradingLogger::info("Validando configuración de Telegram");
        $config = Config::get('config.notifications.telegram');
        
        if (!$config) {
            TradingLogger::error("Configuración de Telegram no encontrada");
            throw new NotificationException(
                'Configuración de Telegram no encontrada',
                ['config' => $config]
            );
        }

        TradingLogger::info("Configuración de Telegram encontrada", ['config' => $config]);

        if (!isset($config['enabled']) || !$config['enabled']) {
            TradingLogger::error("Canal de Telegram deshabilitado");
            throw new NotificationException(
                'Canal de Telegram deshabilitado',
                ['config' => $config]
            );
        }

        if (empty($config['bot_token']) || empty($config['chat_id'])) {
            TradingLogger::error("Configuración de Telegram incompleta", [
                'bot_token_present' => !empty($config['bot_token']),
                'chat_id_present' => !empty($config['chat_id'])
            ]);
            throw new NotificationException(
                'Configuración de Telegram incompleta. Revisar BOT_TOKEN y CHAT_ID en .env',
                [
                    'bot_token' => $config['bot_token'] ?? null,
                    'chat_id' => $config['chat_id'] ?? null
                ]
            );
        }

        TradingLogger::info("Configuración de Telegram validada correctamente");
    }
}