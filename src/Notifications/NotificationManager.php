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
        $this->enabledChannels = Config::get('notifications.enabled_channels', ['telegram']);
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
        foreach ($this->enabledChannels as $channelName) {
            $channelClass = __NAMESPACE__ . '\\' . ucfirst($channelName) . 'Notifier';
            if (class_exists($channelClass)) {
                $this->channels[$channelName] = new $channelClass();
            }
        }
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