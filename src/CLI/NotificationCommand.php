<?php
namespace TradingBot\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TradingBot\Services\MarketDataService;
use TradingBot\Strategies\RsiStrategy;
use TradingBot\Strategies\MovingAverageStrategy;
use TradingBot\Exchanges\ExchangeFactory;
use TradingBot\Notifications\NotificationManager;
use TradingBot\Services\AccountDataService;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Utilities\Config;

class NotificationCommand extends Command {
    private $running = true;
    private string $pidFile;
    
    protected function configure(): void {
        $this->setName('notify:run')
            ->setDescription('Ejecuta el bot de trading y notifica los resultados')
            ->addOption(
                'strategy',
                's',
                InputOption::VALUE_REQUIRED,
                'Estrategia a usar (rsi, ma)',
                'rsi'
            )
            ->addOption(
                'exchange',
                'e',
                InputOption::VALUE_REQUIRED,
                'Exchange a utilizar (binance, gateio)',
                'binance'
            )
            ->addOption(
                'symbol',
                'p',
                InputOption::VALUE_REQUIRED,
                'Par a operar (ej: BTC/USDT)',
                'BTC/USDT'
            )
            ->addOption(
                'interval',
                'i',
                InputOption::VALUE_REQUIRED,
                'Intervalo de tiempo (ej: 1h, 4h)',
                '1h'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        // Validar formato del intervalo
        $interval = $input->getOption('interval');
        if (!$this->isValidIntervalFormat($interval)) {
            $output->writeln('<error>Formato de intervalo no válido</error>');
            return Command::FAILURE;
        }

        // Configurar PID
        $this->pidFile = NotificationStopCommand::getPidFile(
            $input->getOption('exchange'),
            $input->getOption('symbol')
        );

        if ($this->checkExistingInstance()) {
            $output->writeln('<error>Ya hay una instancia de notificación en ejecución para este par</error>');
            return Command::FAILURE;
        }
        
        try {
            $strategy = $this->initializeStrategy($input->getOption('strategy'));
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>Estrategia no válida</error>');
            return Command::FAILURE;
        }

        try {
            $marketDataService = new MarketDataService(
                $input->getOption('exchange'),
                $input->getOption('symbol')
            );
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>Exchange no válido</error>');
            return Command::FAILURE;
        }
        
        $this->registerPid();
        register_shutdown_function([$this, 'cleanupPid']);

        // Registrar manejador de señales
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGTERM, [$this, 'shutdown']);

        // Inicializar componentes
        $strategy->setParameters([
            'symbol' => $input->getOption('symbol'),
            'timeframe' => $input->getOption('interval')
        ]);

        $notificationManager = new NotificationManager();

        // Enviar notificación de inicio
        $notificationManager->notify(
            "🔔 Bot de Notificaciones Iniciado",
            true,
            [
                'Tipo' => 'Notificaciones',
                'Exchange' => ucfirst(strtolower($input->getOption('exchange'))),
                'Par' => $input->getOption('symbol'),
                'Estrategia' => $input->getOption('strategy'),
                'Intervalo' => $input->getOption('interval'),
                'PID' => getmypid()
            ]
        );

        $output->writeln("<info>Iniciando bot de trading para notificar con configuración:</info>");
        $output->writeln(" - Exchange: ".$input->getOption('exchange'));
        $output->writeln(" - Par: ".$input->getOption('symbol'));
        $output->writeln(" - Estrategia: ".$input->getOption('strategy'));
        $output->writeln(" - Intervalo: ".$input->getOption('interval'));
        $output->writeln("----------------------------------------");

        try {
            while ($this->running) {
                if (NotificationStopCommand::shouldStop()) {
                    $this->running = false;
                    TradingLogger::info("Deteniendo bot de notificación por solicitud del usuario");
                    $output->writeln("\n<comment>Deteniendo bot de notificación...</comment>");
                    continue;
                }

                $data = $marketDataService->getHistoricalData(
                    $input->getOption('interval'),
                    $strategy->getParameters()['period'] * 2,
                    true // Forzar actualización de datos
                );

                if ($strategy->shouldExecute($data)) {
                    $result = $strategy->execute($data);

                    $notificationData = $strategy->prepareNotificationData($result);
                    $notificationManager->notify(
                        $notificationData['message'],
                        true,
                        $notificationData['data']
                    );

                    $output->writeln("<fg=green>Notificación enviada:</> ".json_encode($notificationData));
                }

                $this->showStatus($output, $data);

                // Calcular el tiempo de espera según el intervalo
                $sleepTime = $this->calculateSleepTime($interval);
                sleep($sleepTime);
            }
        } catch (\Throwable $e) {
            TradingLogger::critical($e->getMessage(), ['trace' => $e->getTrace()]);
            $output->writeln("<error>Error crítico: ".$e->getMessage()."</error>");
            $notificationManager->notify(
                "🚨 Error crítico en el bot: ".$e->getMessage(),
                false
            );
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function initializeStrategy(string $strategyType) {
        return match(strtolower($strategyType)) {
            'rsi' => new RsiStrategy(),
            'ma' => new MovingAverageStrategy(),
            default => throw new \InvalidArgumentException("Estrategia no válida: $strategyType")
        };
    }

    private function showStatus(OutputInterface $output, array $data): void {
        $latest = end($data);
        // Dependiendo del intervalo que se use, se mostrará solo la hora (1h), hora con minutos (1m) o hora con minutos
        $output->writeln([
            "Último cierre: ".$latest['close'],
            "Hora: ".date('Y-m-d H:i:s', $latest['timestamp']/1000),
            "----------------------------------------"
        ]);
    }

    private function calculateSleepTime(string $interval): int {
        // Extraer el número y la unidad del intervalo
        preg_match('/(\d+)([mhd])/', $interval, $matches);
        if (count($matches) !== 3) {
            throw new \InvalidArgumentException(
                "Formato de intervalo no válido. Use el formato: Xm (minutos), Xh (horas) o Xd (días). Ejemplos: 15m, 1h, 4h"
            );
        }

        $number = (int)$matches[1];
        $unit = $matches[2];

        // Validar que el número sea positivo
        if ($number <= 0) {
            throw new \InvalidArgumentException(
                "El número en el intervalo debe ser positivo. Ejemplos: 15m, 1h, 4h"
            );
        }

        // Convertir a segundos según la unidad
        switch ($unit) {
            case 'm': // minutos
                return $number * 60;
            case 'h': // horas
                return $number * 3600;
            case 'd': // días
                return $number * 86400;
            default:
                throw new \InvalidArgumentException(
                    "Unidad de tiempo no válida. Use m (minutos), h (horas) o d (días). Ejemplos: 15m, 1h, 4h"
                );
        }
    }

    private function isValidIntervalFormat(string $interval): bool {
        return (bool) preg_match('/^\d+[mhd]$/', $interval);
    }

    private function checkExistingInstance(): bool {
        if (file_exists($this->pidFile)) {
            $pid = (int) file_get_contents($this->pidFile);
            return posix_kill($pid, 0); // Verifica si el proceso está activo
        }
        return false;
    }
    
    private function registerPid(): void {
        if (!is_dir(dirname($this->pidFile))) {
            mkdir(dirname($this->pidFile), 0755, true);
        }
        
        file_put_contents($this->pidFile, getmypid());
    }
    
    public function cleanupPid(): void {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
        $this->running = false;
    }
    
    public function shutdown(int $signal): void {
        $this->cleanupPid();
        $this->running = false;
    }
} 