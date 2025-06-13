<?php
namespace TradingBot\CLI;

class ProcessMetadata {
    private string $type;
    private string $exchange;
    private string $symbol;
    private int $pid;
    private ?int $startTime;
    private string $status;

    public function __construct(string $type, string $exchange, string $symbol, int $pid, ?int $startTime = null, string $status = 'running') {
        $this->type = $type;
        $this->exchange = $exchange;
        $this->symbol = $symbol;
        $this->pid = $pid;
        $this->startTime = $startTime;
        $this->status = $status;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getExchange(): string {
        return $this->exchange;
    }

    public function getSymbol(): string {
        return $this->symbol;
    }

    public function getPid(): int {
        return $this->pid;
    }

    public function getStartTime(): ?int {
        return $this->startTime;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function setStatus(string $status): void {
        $this->status = $status;
    }

    public function getUptime(): string {
        if ($this->startTime === null) {
            return 'N/A';
        }
        
        $uptime = time() - $this->startTime;
        $hours = floor($uptime / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        $seconds = $uptime % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function toArray(): array {
        $dateTime = new \DateTime();
        $dateTime->setTimezone(new \DateTimeZone('America/Argentina/Buenos_Aires'));
        $dateTime->setTimestamp($this->startTime ?? 0);
        
        return [
            'Tipo' => $this->type,
            'Exchange' => $this->exchange,
            'Par' => $this->symbol,
            'PID' => $this->pid,
            'Inicio' => $this->startTime ? $dateTime->format('Y-m-d H:i:s') : 'N/A',
            'Tiempo de Ejecución' => $this->getUptime(),
            'Estado' => $this->status
        ];
    }

    public static function fromPidFile(string $pidFile, int $pid): self {
        $filename = basename($pidFile, '.pid');
        $parts = explode('_', str_replace(TradingStopCommand::PID_PREFIX, '', $filename));
        
        // Determinar el tipo de proceso basado en el nombre del archivo
        $type = 'Trading';
        if (strpos($pidFile, 'notify') !== false || 
            strpos($pidFile, 'Notification') !== false || 
            strpos($filename, 'notify') !== false) {
            $type = 'Notificaciones';
        }
        
        // El formato del nombre del archivo es: tradingbot_[notify_][exchange]_[symbol].pid
        // Por ejemplo: tradingbot_notify_binance_BTC_USDT.pid
        // O: tradingbot_binance_BTC_USDT.pid
        
        // Si el primer elemento es 'notify', el exchange está en el segundo elemento
        $exchangeIndex = $parts[0] === 'notify' ? 1 : 0;
        $exchange = $parts[$exchangeIndex] ?? 'N/A';
        // Capitalizar la primera letra del exchange
        $exchange = ucfirst(strtolower($exchange));
        
        // Reconstruir el par de trading (ignorando 'notify' si existe)
        $symbolStartIndex = $parts[0] === 'notify' ? 2 : 1;
        $symbolParts = array_slice($parts, $symbolStartIndex);
        $symbol = implode('/', $symbolParts);
        
        // Obtener el tiempo de inicio
        $startTime = file_exists($pidFile) ? filemtime($pidFile) : null;
        
        return new self($type, $exchange, $symbol, $pid, $startTime);
    }
} 