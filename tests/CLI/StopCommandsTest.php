<?php

namespace Tests\CLI;

use TradingBot\CLI\TradingStopCommand;
use TradingBot\CLI\NotificationStopCommand;

class StopCommandsTest extends CommandTestCase
{
    protected function registerCommands(): void
    {
        $this->application->add(new TradingStopCommand());
        $this->application->add(new NotificationStopCommand());
    }

    /**
     * @dataProvider tradingStopCasesProvider
     */
    public function testTradingStopCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('trade:stop', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    /**
     * @dataProvider notificationStopCasesProvider
     */
    public function testNotificationStopCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('notify:stop', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    public static function tradingStopCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['trade:stop']['valid_cases']
        );
    }

    public static function notificationStopCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['notify:stop']['valid_cases']
        );
    }
} 