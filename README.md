# Bot de Inversión v2

Bot automatizado para trading e inversiones en criptomonedas.

## Características

- Soporte para múltiples exchanges (Binance, Bybit, etc.)
- Estrategias de trading personalizables
- Sistema de notificaciones en tiempo real
- Gestión de órdenes automatizada
- Monitoreo de rendimiento
- Pruebas automatizadas

## Requisitos

- PHP 8.1 o superior
- Composer
- Docker y Docker Compose
- Extensión PCNTL de PHP
- Extensión POSIX de PHP

## Instalación

1. Clonar el repositorio:
```bash
git clone https://github.com/tu-usuario/bot-inversion-v2.git
cd bot-inversion-v2
```

2. Instalar dependencias:
```bash
composer install
```

3. Configurar el entorno:
```bash
cp .env.example .env
# Editar .env con tus configuraciones
```

4. Iniciar los servicios:
```bash
docker compose up -d
```

## Uso

### Comandos Disponibles

#### Trading
```bash
# Iniciar bot de trading
php bin/console trade:run --exchange=binance --symbol=BTC/USDT --strategy=rsi --interval=15m

# Detener bot de trading
php bin/console trade:stop --exchange=binance --symbol=BTC/USDT

# Listar bots de trading en ejecución
php bin/console trade:list
```

#### Notificaciones
```bash
# Iniciar bot de notificaciones
php bin/console notify:run --exchange=binance --symbol=BTC/USDT --strategy=rsi --interval=15m

# Detener bot de notificaciones
php bin/console notify:stop --exchange=binance --symbol=BTC/USDT

# Listar bots de notificaciones en ejecución
php bin/console notify:list
```

#### Procesos
```bash
# Listar todos los procesos en ejecución
php bin/console process:list
```

### Estrategias Disponibles

- RSI (Relative Strength Index)
- MACD (Moving Average Convergence Divergence)
- Bollinger Bands
- EMA (Exponential Moving Average)

## Pruebas

El proyecto incluye pruebas automatizadas utilizando PHPUnit. Las pruebas están organizadas en:

- Pruebas unitarias
- Pruebas de integración
- Pruebas de comandos CLI

### Ejecutar Pruebas

```bash
# Ejecutar todas las pruebas
docker compose exec app ./vendor/bin/phpunit

# Ejecutar pruebas con reporte detallado
docker compose exec app ./vendor/bin/phpunit --testdox

# Ejecutar pruebas específicas
docker compose exec app ./vendor/bin/phpunit tests/CLI/NotificationCommandTest.php
```

### Estructura de Pruebas

```
tests/
├── CLI/                    # Pruebas de comandos CLI
│   ├── NotificationCommandTest.php
│   ├── TradingCommandTest.php
│   └── StopCommandsTest.php
├── Unit/                   # Pruebas unitarias
│   ├── Strategies/
│   └── Services/
├── Integration/            # Pruebas de integración
└── data/                   # Datos de prueba
    └── command_test_cases.php
```

## Desarrollo

### Flujo de Trabajo

El proyecto sigue la metodología GitFlow:

1. `main`: Rama principal con el código en producción
2. `develop`: Rama de desarrollo
3. `feature/*`: Ramas para nuevas características
4. `bugfix/*`: Ramas para correcciones de errores
5. `release/*`: Ramas para preparar releases
6. `hotfix/*`: Ramas para correcciones urgentes

### Convenciones de Código

- PSR-12 para estilo de código
- PHPDoc para documentación
- Tests para nueva funcionalidad
- Commits semánticos

### Ejemplo de Flujo de Trabajo

```bash
# Crear rama de feature
git checkout -b feature/nueva-estrategia develop

# Desarrollar y hacer commits
git add .
git commit -m "feat: implementa nueva estrategia de trading"

# Actualizar con develop
git checkout develop
git pull origin develop
git checkout feature/nueva-estrategia
git merge develop

# Crear pull request a develop
git push origin feature/nueva-estrategia
```

## Contribuir

1. Fork el repositorio
2. Crear rama de feature (`git checkout -b feature/AmazingFeature`)
3. Commit cambios (`git commit -m 'feat: add some amazing feature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir Pull Request

## Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## Contacto

Tu Nombre - [@tutwitter](https://twitter.com/tutwitter)

Link del Proyecto: [https://github.com/tu-usuario/bot-inversion-v2](https://github.com/tu-usuario/bot-inversion-v2) 