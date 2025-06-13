<?php

namespace TradingBot\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use TradingBot\Utilities\TradingLogger;

class TestReportCommand extends Command {
    private const TEST_DIRECTORY = __DIR__ . '/../../tests/CLI';

    public function __construct() {
        parent::__construct('test:report');
    }

    protected function configure(): void {
        $this
            ->setDescription('Genera un reporte de las pruebas CLI (rápidas) en menos de 1 minuto')
            ->setHelp('Este comando ejecuta solo los tests del directorio tests/CLI/ en paralelo y limita el tiempo de cada test.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $output->writeln('🔍 Ejecutando solo los tests CLI rápidos...');

            // Buscar todos los archivos de test en tests/CLI/
            $testFiles = glob(self::TEST_DIRECTORY . '/*Test.php');
            $totalTests = count($testFiles);
            $output->writeln("📊 Total de archivos de test CLI: {$totalTests}");

            if ($totalTests === 0) {
                $output->writeln('⚠️  No se encontraron tests en tests/CLI/');
                return Command::SUCCESS;
            }

            $results = [
                'success' => 0,
                'failed' => 0,
                'details' => []
            ];

            // Ejecutar los tests en paralelo (si hay más de 1)
            $processes = [];
            foreach ($testFiles as $testFile) {
                $processes[$testFile] = new Process([
                    'php', './vendor/bin/phpunit', '--testdox', '--stop-on-failure', $testFile
                ], __DIR__ . '/../../', null, null, 20); // 20 segundos de timeout por test
            }

            // Lanzar todos los procesos
            foreach ($processes as $testFile => $process) {
                $process->start();
            }

            // Esperar y recolectar resultados
            foreach ($processes as $testFile => $process) {
                $commandName = basename($testFile, 'Test.php');
                $output->writeln("\n🔍 Test: {$commandName}");
                $process->wait();
                $testOutput = $process->getOutput();
                $testError = $process->getErrorOutput();
                $output->writeln($testOutput);
                if ($process->isSuccessful()) {
                    $output->writeln("✅ Test exitoso para {$commandName}");
                    $results['success']++;
                    $results['details'][$commandName] = [
                        'status' => 'success',
                        'output' => $testOutput
                    ];
                } else {
                    $output->writeln("❌ Test fallido para {$commandName}");
                    $results['failed']++;
                    $results['details'][$commandName] = [
                        'status' => 'failed',
                        'output' => $testOutput,
                        'error' => $testError
                    ];
                }
            }

            // Mostrar resumen
            $this->showSummary($output, $results);
            TradingLogger::info('Reporte de pruebas CLI rápidas completado', $results);

            return $results['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln("\n❌ Error al ejecutar las pruebas: " . $e->getMessage());
            TradingLogger::error('Error en reporte de pruebas CLI rápidas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function showSummary(OutputInterface $output, array $results): void
    {
        $output->writeln("\n📊 Resumen de Pruebas CLI:");
        $output->writeln("-------------------");
        $output->writeln("✅ Exitosas: {$results['success']}");
        $output->writeln("❌ Fallidas: {$results['failed']}");
    }
} 