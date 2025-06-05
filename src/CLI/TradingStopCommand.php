<?php
namespace TradingBot\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Notifications\NotificationManager;
use TradingBot\CLI\ProcessMetadata;

class TradingStopCommand extends Command {
    public const PID_DIR = __DIR__.'/../../storage/pids/';
    public const PID_PREFIX = 'tradingbot_';

    private $notificationManager;

    public function __construct() {
        parent::__construct();
        $this->notificationManager = new NotificationManager();
    }

    protected function configure(): void {
        $this->setName('trading:stop')
            ->setDescription('Detiene el bot de trading usando el PID')
            ->addOption('symbol', 's', InputOption::VALUE_OPTIONAL, 'Par específico a detener');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $symbol = $input->getOption('symbol') ?? 'all';
        $pids = $this->getRunningPids($symbol);

        if (empty($pids)) {
            $output->writeln('<comment>No hay instancias en ejecución</comment>');
            return Command::SUCCESS;
        }

        foreach ($pids as $pidFile => $pid) {
            $metadata = ProcessMetadata::fromPidFile($pidFile, $pid);
            
            if ($this->sendTerminationSignal($pid)) {
                if (file_exists($pidFile)) {
                    unlink($pidFile);
                }
                $output->writeln("<info>Detenido proceso $pid (" . basename($pidFile) . ")</info>");
                TradingLogger::info("Bot detenido", ['pid' => $pid, 'file' => $pidFile]);
                
                // Enviar notificación de Telegram
                $metadata->setStatus('Detenido');
                $this->notificationManager->notify(
                    "🛑 Bot Detenido",
                    true,
                    $metadata->toArray()
                );
            } else {
                $output->writeln("<error>Error deteniendo proceso $pid</error>");
                TradingLogger::error("Fallo al detener bot", ['pid' => $pid]);
            }
        }

        return Command::SUCCESS;
    }

    private function getRunningPids(string $symbol): array {
        $pids = [];
        $files = glob(self::PID_DIR . self::PID_PREFIX . '*.pid');

        foreach ($files as $file) {
            $pid = (int) file_get_contents($file);
            
            if ($symbol === 'all' || str_contains($file, $symbol)) {
                if (posix_kill($pid, 0)) { // Verifica si el proceso existe
                    $pids[$file] = $pid;
                } else {
                    unlink($file); // Limpiar PID huérfano
                }
            }
        }

        return $pids;
    }

    private function sendTerminationSignal(int $pid): bool {
        if (posix_kill($pid, SIGTERM)) {
            // Esperar máximo 5 segundos para confirmar
            for ($i = 0; $i < 5; $i++) {
                if (!posix_kill($pid, 0)) break;
                sleep(1);
            }
            return true;
        }
        return false;
    }

    public static function getPidFile(string $exchange, string $symbol): string {
        $filename = self::PID_PREFIX . 
                    $exchange . '_' . 
                    str_replace('/', '_', $symbol) . 
                    '.pid';
        
        return self::PID_DIR . $filename;
    }

    public static function shouldStop(): bool {
        $stopFile = self::PID_DIR . 'stop_signal';
        if (file_exists($stopFile)) {
            unlink($stopFile);
            return true;
        }
        return false;
    }
}