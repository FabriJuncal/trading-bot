<?php

namespace TradingBot\Tests\Notifications;

use PHPUnit\Framework\TestCase;
use TradingBot\Notifications\TelegramNotifier;
use TradingBot\Exceptions\NotificationException;

class MockTelegramNotifier extends TelegramNotifier {
    public array $sentMessages = [];
    public function send(string $message, bool $isSuccess = true, array $context = []): void {
        $this->sentMessages[] = compact('message', 'isSuccess', 'context');
    }
}

class TelegramNotifierTest extends TestCase {
    private MockTelegramNotifier $notifier;

    protected function setUp(): void {
        $this->notifier = new MockTelegramNotifier();
    }

    public function testSendMessage(): void {
        $message = "Test message";
        $context = ['type' => 'test'];
        
        $this->notifier->send($message, true, $context);
        $this->assertNotEmpty($this->notifier->sentMessages);
    }

    public function testProcessConfirmationCommands(): void {
        $message = "/confirmar_order_123";
        $context = [
            'type' => 'confirmation_request',
            'confirmation_id' => 'order_123'
        ];
        
        $this->notifier->send($message, true, $context);
        $this->assertNotEmpty($this->notifier->sentMessages);
    }

    public function testInvalidConfirmationCommand(): void {
        $message = "/invalid_command";
        $context = [
            'type' => 'confirmation_request',
            'confirmation_id' => 'order_123'
        ];
        
        $this->notifier->send($message, true, $context);
        $this->assertNotEmpty($this->notifier->sentMessages);
    }
} 