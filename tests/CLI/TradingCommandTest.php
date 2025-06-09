<?php

namespace Tests\CLI;

use TradingBot\CLI\TradingCommand;

class TradingCommandTest extends CommandTestCase
{
    protected function registerCommands(): void
    {
        $this->application->add(new TradingCommand());
    }

    /**
     * @dataProvider validCasesProvider
     */
    public function testValidCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('trade:run', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    /**
     * @dataProvider invalidCasesProvider
     */
    public function testInvalidCases(array $input, string $expectedOutput, int $expectedStatus): void
    {
        $status = $this->executeCommand('trade:run', $input);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertOutputContains($expectedOutput);
    }

    public static function validCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['trade:run']['valid_cases']
        );
    }

    public static function invalidCasesProvider(): array
    {
        $testCases = require __DIR__ . '/../data/command_test_cases.php';
        return array_map(
            fn($case) => [$case['input'], $case['expected_output'], $case['expected_status']],
            $testCases['trade:run']['invalid_cases']
        );
    }
} 