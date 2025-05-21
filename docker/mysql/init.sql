-- Creación de la base de datos
CREATE DATABASE IF NOT EXISTS trading_bot;
USE trading_bot;

-- Tabla para almacenar las operaciones
CREATE TABLE IF NOT EXISTS trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset VARCHAR(50) NOT NULL,
    amount DECIMAL(18, 8) NOT NULL,
    price DECIMAL(18, 8) NOT NULL,
    exchange VARCHAR(50) NOT NULL,
    allocationPercentage  DECIMAL(18, 8) NOT NULL,
    date DATETIME NOT NULL,
    INDEX idx_date (date),
    INDEX idx_asset (asset)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario dedicado para la aplicación
CREATE USER IF NOT EXISTS 'trader'@'%' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON trading_bot.* TO 'trader'@'%';
FLUSH PRIVILEGES;