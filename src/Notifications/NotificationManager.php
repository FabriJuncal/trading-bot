<?php
namespace TradingBot\Notifications;

use TradingBot\Utilities\Config;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Exceptions\NotificationException;

class NotificationManager {
    private $channels = [];
    private $enabledChannels = [];
    private $messageQueue = [];
    private $maxRetries = 3;

    public function __construct() {
        TradingLogger::info("Iniciando NotificationManager");
        $this->enabledChannels = Config::get('config.notifications.enabled_channels', ['telegram']);
        TradingLogger::info("Canales habilitados configurados: " . json_encode($this->enabledChannels));
        $this->initializeChannels();
    }

    public function notify(string $message, bool $isSuccess = true, array $context = []): void {
        $notificationData = [
            'message' => $message,
            'is_success' => $isSuccess,
            'timestamp' => time(),
            'context' => $context
        ];

        $this->messageQueue[] = $notificationData;
        $this->processQueue();
    }

    public function addChannel(NotificationChannelInterface $channel): self {
        $this->channels[get_class($channel)] = $channel;
        return $this;
    }

    public function setChannels(array $channels): self {
        foreach ($channels as $channel) {
            $this->addChannel($channel);
        }
        return $this;
    }

    private function initializeChannels(): void {
        try {
            $enabledChannels = Config::get('config.notifications.enabled_channels', []);
            TradingLogger::info("Canales habilitados en la configuración: " . json_encode($enabledChannels));
            
            if (empty($enabledChannels)) {
                TradingLogger::warning('No hay canales de notificación habilitados');
                return;
            }

            foreach ($enabledChannels as $channel) {
                try {
                    $channelConfig = Config::get("config.notifications.{$channel}");
                    TradingLogger::info("Configuración del canal {$channel}: " . json_encode($channelConfig));
                    
                    if (!$channelConfig) {
                        TradingLogger::warning("No se encontró configuración para el canal {$channel}");
                        continue;
                    }

                    if (!isset($channelConfig['enabled'])) {
                        TradingLogger::warning("La configuración del canal {$channel} no tiene el campo 'enabled'");
                        continue;
                    }

                    if (!$channelConfig['enabled']) {
                        TradingLogger::warning("El canal {$channel} está deshabilitado en la configuración");
                        continue;
                    }

                    $this->channels[$channel] = $this->createChannel($channel);
                    TradingLogger::info("Canal de notificación {$channel} inicializado correctamente");
                } catch (\Exception $e) {
                    TradingLogger::error("Error al inicializar el canal {$channel}: " . $e->getMessage(), [
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if (empty($this->channels)) {
                TradingLogger::warning('Ningún canal de notificación pudo ser inicializado', [
                    'enabled_channels' => $enabledChannels,
                    'config' => Config::get('config.notifications')
                ]);
            }
        } catch (\Exception $e) {
            TradingLogger::error('Error al inicializar los canales de notificación: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function createChannel(string $channel): NotificationChannelInterface {
        return match ($channel) {
            'telegram' => new TelegramNotifier(),
            'discord' => new DiscordNotifier(),
            default => throw new NotificationException("Canal de notificación no soportado: {$channel}")
        };
    }

    private function processQueue(): void {
        while (!empty($this->messageQueue)) {
            $notification = array_shift($this->messageQueue);
            $this->sendNotification($notification);
        }
    }

    private function sendNotification(array $notification): void {
        foreach ($this->channels as $channel) {
            $retryCount = 0;
            
            while ($retryCount <= $this->maxRetries) {
                try {
                    $channel->send(
                        $notification['message'],
                        $notification['is_success'],
                        $notification['context']
                    );
                    TradingLogger::info("Notificación enviada por " . get_class($channel), $notification);
                    break;
                } catch (NotificationException $e) {
                    TradingLogger::error("Error enviando notificación: " . $e->getMessage(), [
                        'channel' => get_class($channel),
                        'retry' => $retryCount
                    ]);
                    
                    if ($retryCount === $this->maxRetries) {
                        TradingLogger::critical("Fallo definitivo en notificación", [
                            'notification' => $notification,
                            'error' => $e->getMessage()
                        ]);
                        break;
                    }
                    
                    $retryCount++;
                    sleep(2 ** $retryCount); // Backoff exponencial
                }
            }
        }
    }

    public function getActiveChannels(): array {
        return array_keys($this->channels);
    }
}