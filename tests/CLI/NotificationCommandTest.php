<?php

namespace Tests\CLI;

use TradingBot\CLI\NotificationCommand;

class NotificationCommandTest extends CommandTestCase
{
    protected function registerCommands(): void
    {
        $this->application->add(new NotificationCommand());
    }

    /**
     * @dataProvider validCasesProvider
     */
    public function testValidCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('notify:run', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    /**
     * @dataProvider invalidCasesProvider
     */
    public function testInvalidCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('notify:run', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    public static function validCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['notify:run']['valid_cases']
        );
    }

    public static function invalidCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['notify:run']['invalid_cases']
        );
    }
} 