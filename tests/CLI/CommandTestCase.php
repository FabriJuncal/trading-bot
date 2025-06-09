<?php

namespace Tests\CLI;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TradingBot\Utilities\Config;

abstract class CommandTestCase extends TestCase
{
    protected Application $application;
    protected CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Inicializar configuración
        Config::initialize();
        
        // Crear aplicación CLI
        $this->application = new Application('Trading Bot', '1.0.0');
        
        // Registrar comandos
        $this->registerCommands();
    }

    abstract protected function registerCommands(): void;

    protected function executeCommand(string $commandName, array $input = []): int
    {
        $command = $this->application->find($commandName);
        $this->commandTester = new CommandTester($command);
        return $this->commandTester->execute($input);
    }

    protected function getCommandOutput(): string
    {
        return $this->commandTester->getDisplay();
    }

    protected function assertCommandSuccess(): void
    {
        $this->assertEquals(0, $this->commandTester->getStatusCode(), 'El comando falló con salida: ' . $this->getCommandOutput());
    }

    protected function assertCommandFailure(): void
    {
        $this->assertNotEquals(0, $this->commandTester->getStatusCode(), 'El comando no falló como se esperaba. Salida: ' . $this->getCommandOutput());
    }

    protected function assertOutputContains(string $expected): void
    {
        $this->assertStringContainsString($expected, $this->getCommandOutput());
    }

    protected function assertOutputNotContains(string $expected): void
    {
        $this->assertStringNotContainsString($expected, $this->getCommandOutput());
    }
} 