<?php

namespace Tests\CLI;

use TradingBot\CLI\ListCommand;
use TradingBot\CLI\NotificationListCommand;
use TradingBot\CLI\ProcessListCommand;

class ListCommandsTest extends CommandTestCase
{
    protected function registerCommands(): void
    {
        $this->application->add(new ListCommand());
        $this->application->add(new NotificationListCommand());
        $this->application->add(new ProcessListCommand());
    }

    /**
     * @dataProvider tradingListCasesProvider
     */
    public function testTradingListCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('trade:list', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    /**
     * @dataProvider notificationListCasesProvider
     */
    public function testNotificationListCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('notify:list', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    /**
     * @dataProvider processListCasesProvider
     */
    public function testProcessListCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('process:list', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    public static function tradingListCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['trade:list']['valid_cases']
        );
    }

    public static function notificationListCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['notify:list']['valid_cases']
        );
    }

    public static function processListCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['process:list']['valid_cases']
        );
    }
} 