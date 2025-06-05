<?php
namespace TradingBot\Notifications;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Exceptions\NotificationException;

class TelegramNotifier implements NotificationChannelInterface {
    private $client;
    private $botToken;
    private $chatId;
    private $apiEndpoint;

    public function __construct() {
        $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $this->chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? null;
        
        $this->validateConfig();
        
        $this->client = new Client([
            'base_uri' => 'https://api.telegram.org',
            'timeout'  => 10.0
        ]);
        
        $this->apiEndpoint = "/bot{$this->botToken}/sendMessage";
    }

    public function send(string $message, bool $isSuccess = true, array $context = []): void {
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

    private function escapeMarkdown(string $text): string {
        // Ya no necesitamos escapar caracteres Markdown
        return $text;
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
                "La API de Telegram respondió con un error: " . ($responseBody['description'] ?? 'Unknown error')
            );
        }

        TradingLogger::info("Notificación de Telegram enviada exitosamente", [
            'message_length' => strlen($message),
            'recipient' => $this->chatId
        ]);
    }

    private function validateConfig(): void {
        if (empty($this->botToken) || empty($this->chatId)) {
            throw new NotificationException(
                "Configuración de Telegram incompleta. Revisar BOT_TOKEN y CHAT_ID en .env."
            );
        }
    }
}