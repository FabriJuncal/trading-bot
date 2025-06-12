<?php

namespace Tests\CLI;

use PHPUnit\Framework\TestCase;
use TradingBot\CLI\NotificationCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use TradingBot\Notifications\NotificationManager;
use TradingBot\Services\MarketDataService;
use TradingBot\Strategies\RsiStrategy;
use TradingBot\Strategies\MovingAverageStrategy;

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
    public function getHistoricalData(string $timeframe = '1h', int $limit = 100, bool $forceRefresh = false): array
    {
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

class NotificationCommandTest extends TestCase
{
    private NotificationCommand $command;
    private MockNotificationManager $mockNotificationManager;
    private MockMarketDataService $mockMarketDataService;

    protected function setUp(): void
    {
        putenv('APP_ENV=test');
        $this->mockNotificationManager = new MockNotificationManager();
        $this->mockMarketDataService = new MockMarketDataService('binance', 'BTC/USDT');
        $this->command = new NotificationCommand($this->mockNotificationManager);
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV=');
    }

    /**
     * @dataProvider validCasesProvider
     */
    public function testValidCases(string $strategy, string $exchange, string $symbol, string $interval): void
    {
        $input = new ArrayInput([
            '--strategy' => $strategy,
            '--exchange' => $exchange,
            '--symbol' => $symbol,
            '--interval' => $interval
        ]);
        $output = new NullOutput();

        // Mock de la estrategia
        $strategyMock = $this->createMock($strategy === 'rsi' ? RsiStrategy::class : MovingAverageStrategy::class);
        $strategyMock->method('shouldExecute')->willReturn(false);
        $strategyMock->method('getParameters')->willReturn(['period' => 14]);

        // Ejecutar el comando
        $result = $this->command->run($input, $output);
        
        // Verificar el resultado
        $this->assertEquals(0, $result);
        
        // Verificar que se envió la notificación de inicio
        $this->assertNotEmpty($this->mockNotificationManager->sentMessages);
        $lastMessage = end($this->mockNotificationManager->sentMessages);
        $this->assertEquals("🔔 Bot de Notificaciones Iniciado", $lastMessage['message']);
        $this->assertTrue($lastMessage['is_success']);
        $this->assertEquals($strategy, $lastMessage['context']['Estrategia']);
        $this->assertEquals($exchange, strtolower($lastMessage['context']['Exchange']));
        $this->assertEquals($symbol, $lastMessage['context']['Par']);
        $this->assertEquals($interval, $lastMessage['context']['Intervalo']);
    }

    /**
     * @dataProvider invalidCasesProvider
     */
    public function testInvalidCases(string $strategy, string $exchange, string $symbol, string $interval): void
    {
        $input = new ArrayInput([
            '--strategy' => $strategy,
            '--exchange' => $exchange,
            '--symbol' => $symbol,
            '--interval' => $interval
        ]);
        $output = new NullOutput();

        $result = $this->command->run($input, $output);
        $this->assertEquals(1, $result);
        $this->assertEmpty($this->mockNotificationManager->sentMessages);
    }

    public static function validCasesProvider(): array
    {
        return [
            'caso válido 1' => ['rsi', 'binance', 'BTC/USDT', '1h'],
            'caso válido 2' => ['ma', 'gateio', 'ETH/USDT', '4h']
        ];
    }

    public static function invalidCasesProvider(): array
    {
        return [
            'estrategia inválida' => ['invalid', 'binance', 'BTC/USDT', '1h'],
            'exchange inválido' => ['rsi', 'invalid', 'BTC/USDT', '1h']
        ];
    }
} 