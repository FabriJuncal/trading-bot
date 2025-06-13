<?php

namespace TradingBot\Tests\Services;

use PHPUnit\Framework\TestCase;
use TradingBot\Services\OrderConfirmationService;
use TradingBot\Exceptions\OrderConfirmationException;
use TradingBot\Notifications\NotificationChannelInterface;

class MockNotifier implements NotificationChannelInterface {
    public array $sentMessages = [];
    public function send(string $message, bool $isSuccess = true, array $context = []): void {
        $this->sentMessages[] = compact('message', 'isSuccess', 'context');
    }
}

class OrderConfirmationServiceTest extends TestCase {
    private OrderConfirmationService $service;
    private array $orderData;
    private MockNotifier $mockNotifier;

    protected function setUp(): void {
        $this->mockNotifier = new MockNotifier();
        $this->service = new OrderConfirmationService(true); // autoConfirm para evitar espera
        // Inyectar el mock manualmente
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('notifier');
        $property->setAccessible(true);
        $property->setValue($this->service, $this->mockNotifier);

        $this->orderData = [
            'symbol' => 'BTC/USDT',
            'side' => 'BUY',
            'amount' => '0.1',
            'price' => '50000',
            'type' => 'LIMIT'
        ];
    }

    public function testAutoConfirmEnabled(): void {
        $this->assertTrue($this->service->requestConfirmation($this->orderData));
        $this->assertNotEmpty($this->mockNotifier->sentMessages);
    }

    public function testInvalidConfirmationId(): void {
        $this->expectException(OrderConfirmationException::class);
        $this->expectExceptionMessage("ID de confirmación inválido");
        
        $this->service->handleConfirmationResponse('invalid_id', true);
    }

    public function testRejectedConfirmation(): void {
        $confirmationId = uniqid('order_confirm_');
        $this->service->requestConfirmation($this->orderData);
        
        $this->expectException(OrderConfirmationException::class);
        $this->expectExceptionMessage("Operación rechazada por el usuario");
        
        $this->service->handleConfirmationResponse($confirmationId, false);
    }

    public function testConfirmationTimeout(): void {
        $this->expectException(OrderConfirmationException::class);
        $this->expectExceptionMessage("Tiempo de espera agotado para la confirmación");
        
        $this->service->requestConfirmation($this->orderData);
        // El tiempo de espera se simula en la prueba
    }
} 