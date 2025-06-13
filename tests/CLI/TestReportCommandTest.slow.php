<?php

namespace Tests\CLI;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TradingBot\CLI\TestReportCommand;

class TestReportCommandTest extends TestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new TestReportCommand());
        
        $command = $application->find('test:report');
        $this->commandTester = new CommandTester($command);
    }

    public function testCommandExists(): void
    {
        $command = new TestReportCommand();
        $this->assertInstanceOf(TestReportCommand::class, $command);
    }

    public function testCommandDescription(): void
    {
        $command = new TestReportCommand();
        $expectedDescription = 'Genera un reporte de las pruebas CLI (rápidas) en menos de 1 minuto';
        $actualDescription = $command->getDescription();
        
        $this->assertEquals($expectedDescription, $actualDescription);
    }

    public function testCommandExecution(): void
    {
        // Ejecutar el comando
        $this->commandTester->execute([], ['capture_stderr_separately' => true]);
        
        // Obtener la salida
        $output = $this->commandTester->getDisplay();
        
        // Verificar mensaje inicial
        $this->assertStringContainsString('Ejecutando solo los tests CLI rápidos...', $output);
        
        // Verificar código de estado
        $statusCode = $this->commandTester->getStatusCode();
        $this->assertIsInt($statusCode);
    }
} 