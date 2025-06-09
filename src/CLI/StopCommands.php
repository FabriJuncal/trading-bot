<?php

namespace App\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StopCommands extends Command
{
    protected function configure()
    {
        $this
            ->setName('stop')
            ->setDescription('Detiene un proceso en ejecución')
            ->addOption('exchange', null, InputOption::VALUE_REQUIRED, 'Exchange a detener')
            ->addOption('symbol', null, InputOption::VALUE_REQUIRED, 'Par a detener');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $exchange = $input->getOption('exchange');
        $symbol = $input->getOption('symbol');
        $pidFile = $this->getPidFile($exchange, $symbol);

        if (!file_exists($pidFile)) {
            $output->writeln('<error>No hay instancias en ejecución para este par</error>');
            return Command::FAILURE;
        }

        $pid = (int)file_get_contents($pidFile);
        if (!$this->isProcessRunning($pid)) {
            unlink($pidFile);
            $output->writeln('<error>El proceso ya no está en ejecución</error>');
            return Command::FAILURE;
        }

        posix_kill($pid, SIGTERM);
        unlink($pidFile);
        $output->writeln('<info>Proceso detenido correctamente</info>');
        return Command::SUCCESS;
    }

    protected function getPidFile(string $exchange, string $symbol): string
    {
        return sys_get_temp_dir() . "/bot_{$exchange}_{$symbol}.pid";
    }

    protected function isProcessRunning(int $pid): bool
    {
        return posix_kill($pid, 0);
    }
} 