<?php
// src/Notifications/DiscordNotifier.php
namespace TradingBot\Notifications;

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Builders\MessageBuilder;
use Discord\Builders\EmbedBuilder;
use TradingBot\Exceptions\NotificationException;
use TradingBot\Utilities\TradingLogger;
use React\EventLoop\Loop;

class DiscordNotifier implements NotificationChannelInterface {
    private $discord;
    private $channelId;
    private $maxRetries = 3;
    private $retryDelay = 1;

    public function __construct() {
        $this->validateConfig();
        $this->channelId = $_ENV['DISCORD_CHANNEL_ID'];
        
        $this->discord = new Discord([
            'token' => $_ENV['DISCORD_BOT_TOKEN'],
            'loop' => Loop::get(),
            'intents' => Discord::getDefaultIntents()
        ]);
    }

    public function send(string $message, bool $isSuccess = true, array $context = []): void {
        $this->sendWithRetry($message, $isSuccess, $context);
    }

    private function sendWithRetry(string $message, bool $isSuccess, array $context, int $retryCount = 0): void {
        $this->discord->getChannel($this->channelId)->then(
            function ($channel) use ($message, $isSuccess, $context) {
                $messageBuilt = $this->buildMessage($message, $isSuccess, $context);
                
                $channel->sendMessage($messageBuilt)->then(
                    function (Message $message) {
                        TradingLogger::info("Mensaje enviado a Discord: {$message->id}");
                    },
                    function (\Exception $e) use ($message, $isSuccess, $context, $retryCount) {
                        $this->handleError($e, $message, $isSuccess, $context, $retryCount);
                    }
                );
            },
            function (\Exception $e) use ($message, $isSuccess, $context, $retryCount) {
                $this->handleError($e, $message, $isSuccess, $context, $retryCount);
            }
        );

        $this->discord->run();
    }

    private function buildMessage(string $message, bool $isSuccess, array $context): MessageBuilder {
        $embed = new EmbedBuilder();
        $embed
            ->setTitle($isSuccess ? '✅ Operación Exitosa' : '❌ Error en Operación')
            ->setDescription($message)
            ->setColor($isSuccess ? 0x00FF00 : 0xFF0000)
            ->setTimestamp(time());

        if (!empty($context)) {
            $embed->addField([
                'name' => 'Contexto',
                'value' => "```json\n" . json_encode($context, JSON_PRETTY_PRINT) . "\n```",
                'inline' => false
            ]);
        }

        return MessageBuilder::new()->addEmbed($embed);
    }

    private function handleError(
        \Exception $e,
        string $message,
        bool $isSuccess,
        array $context,
        int $retryCount
    ): void {
        TradingLogger::error("Error en Discord: " . $e->getMessage(), $context);

        if ($retryCount < $this->maxRetries) {
            sleep($this->retryDelay * ($retryCount + 1));
            $this->sendWithRetry($message, $isSuccess, $context, $retryCount + 1);
        } else {
            throw new NotificationException(
                "Fallo al enviar a Discord después de {$this->maxRetries} intentos: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function validateConfig(): void {
        $required = ['DISCORD_BOT_TOKEN', 'DISCORD_CHANNEL_ID'];
        
        foreach ($required as $key) {
            if (empty($_ENV[$key])) {
                throw new NotificationException(
                    "Configuración de Discord incompleta. Verifica $key en .env"
                );
            }
        }
    }
}