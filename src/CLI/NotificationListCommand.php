<?php
namespace TradingBot\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Utilities\Config;
use TradingBot\CLI\ProcessMetadata;

class NotificationListCommand extends Command {
    protected function configure(): void {
        $this->setName('notify:list')
            ->setDescription('Muestra los procesos de notificación en ejecución')
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Formato de salida JSON'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $processes = $this->getRunningProcesses();
        
        if (empty($processes)) {
            $message = 'No hay procesos de notificación activos';
            $input->getOption('json') 
                ? $output->writeln(json_encode(['status' => 'success', 'message' => $message]))
                : $output->writeln("<comment>$message</comment>");
            return Command::SUCCESS;
        }

        if ($input->getOption('json')) {
            $output->writeln(json_encode($processes, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->renderTable($output, $processes);
        return Command::SUCCESS;
    }

    private function getRunningProcesses(): array {
        $processes = [];
        $pidFiles = glob(NotificationStopCommand::PID_DIR . NotificationStopCommand::PID_PREFIX . '*.pid');

        foreach ($pidFiles as $file) {
            $pid = (int) file_get_contents($file);
            
            // Solo procesar archivos que contengan 'notify' en el nombre
            if (strpos($file, 'notify') !== false) {
                if ($this->isProcessRunning($pid)) {
                    $metadata = ProcessMetadata::fromPidFile($file, $pid);
                    $processes[] = $metadata->toArray();
                } else {
                    unlink($file); // Limpiar PID huérfano
                    TradingLogger::warning("PID huérfano eliminado", ['file' => $file]);
                }
            }
        }

        return $processes;
    }

    private function isProcessRunning(int $pid): bool {
        if (PHP_OS_FAMILY === 'Windows') {
            return shell_exec("tasklist /FI \"PID eq $pid\"") !== null;
        }
        return posix_kill($pid, 0);
    }

    private function renderTable(OutputInterface $output, array $processes): void {
        $table = new Table($output);
        $table->setHeaders(['PID', 'Tipo', 'Exchange', 'Par', 'Inicio', 'Tiempo de Ejecución', 'Estado']);
        
        foreach ($processes as $process) {
            $table->addRow([
                $process['PID'],
                $process['Tipo'],
                $process['Exchange'],
                $process['Par'],
                $process['Inicio'] ?? 'N/A',
                $process['Tiempo de Ejecución'],
                '<fg=green>'.$process['Estado'].'</>'
            ]);
        }
        
        $table->render();
        $output->writeln("<info>Total procesos de notificación: " . count($processes) . "</info>");
    }
} 