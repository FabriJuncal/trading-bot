<?php

namespace Tests\CLI;

use PHPUnit\Framework\TestCase;
use TradingBot\CLI\TradingCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use TradingBot\Notifications\NotificationManager;
use Symfony\Component\Console\Tester\CommandTester;
use TradingBot\Services\MarketDataService;
use TradingBot\Exceptions\DataServiceException;

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

class MockMarketDataService extends MarketDataService
{
    private bool $shouldThrowException = false;
    private string $exceptionMessage = 'Error al obtener datos de mercado';

    public function setShouldThrowException(bool $shouldThrow, string $message = 'Error al obtener datos de mercado'): void
    {
        $this->shouldThrowException = $shouldThrow;
        $this->exceptionMessage = $message;
    }

    public function getHistoricalData(string $timeframe = '1h', int $limit = 100, bool $forceRefresh = false): array
    {
        if ($this->shouldThrowException) {
            throw new DataServiceException($this->exceptionMessage);
        }

        return [
            [
                'timestamp' => time() * 1000,
                'open' => 100.0,
                'high' => 101.0,
                'low' => 99.0,
                'close' => 100.5,
                'volume' => 1000.0
            ]
        ];
    }

    public function __construct(string $exchange, string $symbol)
    {
        parent::__construct($exchange, $symbol);
    }
}

class TradingCommandTest extends TestCase
{
    private TradingCommand $command;
    private MockNotificationManager $mockNotificationManager;
    private MockMarketDataService $mockMarketDataService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        putenv('APP_ENV=test');
        $this->mockNotificationManager = new MockNotificationManager();
        $this->mockMarketDataService = new MockMarketDataService('binance', 'BTC/USDT');
        $this->command = new TradingCommand($this->mockNotificationManager);
        $this->command->setMarketDataService($this->mockMarketDataService);
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
        
        // Verificar que se envió la notificación de inicio
        $this->assertNotEmpty($this->mockNotificationManager->sentMessages);
        $lastMessage = end($this->mockNotificationManager->sentMessages);
        $this->assertEquals("🤖 Bot de Trading Iniciado", $lastMessage['message']);
        $this->assertTrue($lastMessage['is_success']);
        $this->assertEquals('rsi', $lastMessage['context']['Estrategia']);
        $this->assertEquals('binance', strtolower($lastMessage['context']['Exchange']));
        $this->assertEquals('BTC/USDT', $lastMessage['context']['Par']);
        $this->assertEquals('1h', $lastMessage['context']['Intervalo']);
    }

    public function testMarketDataError(): void
    {
        // Configurar el mock para que lance una excepción
        $this->mockMarketDataService->setShouldThrowException(true, 'Error al obtener datos de mercado');

        // Ejecutar el comando
        $this->commandTester->execute([
            '--strategy' => 'rsi',
            '--exchange' => 'binance',
            '--symbol' => 'BTC/USDT',
            '--interval' => '1h'
        ]);

        // Verificar que el comando falló
        $this->assertEquals(1, $this->commandTester->getStatusCode());

        // Verificar que se envió la notificación de error
        $this->assertNotEmpty($this->mockNotificationManager->sentMessages);
        $lastMessage = end($this->mockNotificationManager->sentMessages);
        $this->assertStringContainsString('Error crítico', $lastMessage['message']);
        $this->assertStringContainsString('Error al obtener datos de mercado', $lastMessage['message']);
        $this->assertFalse($lastMessage['is_success']);
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
        // Crear un mock que lance una excepción al obtener datos
        $this->mockMarketDataService = new class('invalid', 'BTC/USDT') extends MockMarketDataService {
            public function getHistoricalData(string $timeframe = '1h', int $limit = 100, bool $forceRefresh = false): array
            {
                throw new \InvalidArgumentException('Exchange no válido');
            }
        };
        $this->command->setMarketDataService($this->mockMarketDataService);

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