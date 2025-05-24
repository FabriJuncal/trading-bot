<?php
namespace TradingBot\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TradingBot\Services\MarketDataService;
use TradingBot\Services\OrderService;
use TradingBot\Strategies\RsiStrategy;
use TradingBot\Strategies\MovingAverageStrategy;
use TradingBot\Exchanges\ExchangeFactory;
use TradingBot\Notifications\NotificationManager;
use TradingBot\Utilities\TradingLogger;
use TradingBot\Utilities\Config;

class TradingCommand extends Command {
    private $running = true;
    
    protected function configure(): void {
        $this->setName('trade:run')
            ->setDescription('Ejecuta el bot de trading')
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
        // Registrar manejador de señales
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGTERM, [$this, 'shutdown']);

        // Inicializar componentes
        $strategy = $this->initializeStrategy($input->getOption('strategy'));
        $marketDataService = new MarketDataService(
            $input->getOption('exchange'),
            $input->getOption('symbol')
        );
        $orderService = new OrderService(
            $input->getOption('exchange'),
            $input->getOption('symbol')
        );
        $notificationManager = new NotificationManager();

        $output->writeln("<info>Iniciando bot de trading con configuración:</info>");
        $output->writeln(" - Exchange: ".$input->getOption('exchange'));
        $output->writeln(" - Par: ".$input->getOption('symbol'));
        $output->writeln(" - Estrategia: ".$input->getOption('strategy'));
        $output->writeln(" - Intervalo: ".$input->getOption('interval'));
        $output->writeln("----------------------------------------");

        try {
            while ($this->running) {
                $data = $marketDataService->getHistoricalData(
                    $input->getOption('interval'),
                    $strategy->getParameters()['period'] * 2
                );

                if ($strategy->shouldExecute($data)) {
                    $result = $strategy->execute($data);
                    $order = $orderService->executeOrder(
                        strtolower($result['action']),
                        Config::get('global.order_amount', 0.01)
                    );

                    $notificationData = $strategy->prepareNotificationData($result);
                    $notificationManager->notify(
                        $notificationData['message'],
                        true,
                        $notificationData['data']
                    );

                    $output->writeln("<fg=green>Orden ejecutada:</> ".json_encode($order));
                }

                $this->showStatus($output, $data);
                sleep(60); // Esperar 1 minuto entre iteraciones
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
        $output->writeln([
            "Último cierre: ".$latest['close'],
            "Hora: ".date('Y-m-d H:i', $latest['timestamp']/1000),
            "----------------------------------------"
        ]);
    }

    public function shutdown(int $signal): void {
        $this->running = false;
        TradingLogger::info("Bot detenido (señal: $signal)");
    }
}