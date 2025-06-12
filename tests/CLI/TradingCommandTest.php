<?php

namespace Tests\CLI;

use PHPUnit\Framework\TestCase;
use TradingBot\CLI\TradingCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use TradingBot\Notifications\NotificationManager;
use Symfony\Component\Console\Tester\CommandTester;

class MockNotificationManager extends NotificationManager
{
    public array $sentMessages = [];

    public function notify(string $message, bool $isSuccess = true, array $context = []): void
    {
        $this->sentMessages[] = [
            'message' => $message,
            'is_success' => $isSuccess,
            'context' => $context
        ];
    }
}

class TradingCommandTest extends TestCase
{
    private TradingCommand $command;
    private MockNotificationManager $mockNotificationManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        putenv('APP_ENV=test');
        $this->mockNotificationManager = new MockNotificationManager();
        $this->command = new TradingCommand($this->mockNotificationManager);
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV=');
    }

    public function testValidInput(): void
    {
        $this->commandTester->execute([
            '--strategy' => 'rsi',
            '--exchange' => 'binance',
            '--symbol' => 'BTC/USDT',
            '--interval' => '1h'
        ]);
        $result = $this->commandTester->getStatusCode();
        $this->assertEquals(0, $result);
        
        // Verificar que se envió la notificación
        $this->assertNotEmpty($this->mockNotificationManager->sentMessages);
        $lastMessage = end($this->mockNotificationManager->sentMessages);
        $this->assertEquals("🤖 Bot de Trading Iniciado", $lastMessage['message']);
        $this->assertTrue($lastMessage['is_success']);
        $this->assertEquals('rsi', $lastMessage['context']['Estrategia']);
        $this->assertEquals('binance', strtolower($lastMessage['context']['Exchange']));
        $this->assertEquals('BTC/USDT', $lastMessage['context']['Par']);
        $this->assertEquals('1h', $lastMessage['context']['Intervalo']);
    }

    public function testInvalidStrategy(): void
    {
        $this->commandTester->execute([
            '--strategy' => 'invalid',
            '--exchange' => 'binance',
            '--symbol' => 'BTC/USDT',
            '--interval' => '1h'
        ]);
        $result = $this->commandTester->getStatusCode();
        $this->assertEquals(1, $result);
        $this->assertEmpty($this->mockNotificationManager->sentMessages);
    }

    public function testInvalidExchange(): void
    {
        $this->commandTester->execute([
            '--strategy' => 'rsi',
            '--exchange' => 'invalid',
            '--symbol' => 'BTC/USDT',
            '--interval' => '1h'
        ]);
        $result = $this->commandTester->getStatusCode();
        $this->assertEquals(1, $result);
        $this->assertEmpty($this->mockNotificationManager->sentMessages);
    }

    public function testInvalidInterval(): void
    {
        $this->commandTester->execute([
            '--strategy' => 'rsi',
            '--exchange' => 'binance',
            '--symbol' => 'BTC/USDT',
            '--interval' => 'invalid'
        ]);
        $result = $this->commandTester->getStatusCode();
        $this->assertEquals(1, $result);
        $this->assertEmpty($this->mockNotificationManager->sentMessages);
    }
} 