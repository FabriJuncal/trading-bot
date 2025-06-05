# Bot de Trading Automatizado

## Descripción
Bot de trading automatizado que utiliza diferentes estrategias técnicas para operar en exchanges de criptomonedas. El bot soporta múltiples exchanges, estrategias y timeframes, con sistema de notificaciones integrado.

## Características Principales
- Soporte para múltiples exchanges (Binance, Gate.io)
- Estrategias técnicas implementadas (RSI, Media Móvil)
- Sistema de notificaciones en tiempo real
- Gestión de órdenes y manejo de riesgos
- Validación de datos en tiempo real
- Sistema de caché para optimizar rendimiento
- Logging detallado de operaciones

## Requisitos
- PHP 8.1 o superior
- Composer
- Docker (opcional)
- Cuenta en exchange(s) soportado(s)

## Instalación

### Usando Composer
```bash
composer install
```

### Usando Docker
```bash
docker-compose up -d
```

## Configuración

### Variables de Entorno
Crear un archivo `.env` en la raíz del proyecto con las siguientes variables:
```env
APP_ENV=dev
BINANCE_API_KEY=tu_api_key
BINANCE_API_SECRET=tu_api_secret
GATEIO_API_KEY=tu_api_key
GATEIO_API_SECRET=tu_api_secret
TELEGRAM_BOT_TOKEN=tu_token
TELEGRAM_CHAT_ID=tu_chat_id
```

## Uso

### Comandos Disponibles

#### Iniciar Bot de Trading
```bash
php bin/console trade:run --strategy=rsi --exchange=binance --symbol=BTC/USDT --interval=1h
```

#### Iniciar Bot de Notificaciones
```bash
php bin/console notify:run --strategy=rsi --exchange=binance --symbol=BTC/USDT --interval=1h
```

#### Detener Bot
```bash
php bin/console trade:stop --exchange=binance --symbol=BTC/USDT
```

### Parámetros de Comandos
- `--strategy`: Estrategia a utilizar (rsi, ma)
- `--exchange`: Exchange a utilizar (binance, gateio)
- `--symbol`: Par a operar (ej: BTC/USDT)
- `--interval`: Intervalo de tiempo (ej: 1h, 4h)

## Estructura del Proyecto

### Directorios Principales
- `src/`: Código fuente del proyecto
  - `CLI/`: Comandos de consola
  - `Exchanges/`: Conectores para exchanges
  - `Services/`: Servicios principales
  - `Strategies/`: Estrategias de trading
  - `Exceptions/`: Clases de excepciones personalizadas
  - `Utilities/`: Utilidades y helpers
- `config/`: Archivos de configuración
- `storage/`: Almacenamiento de datos y caché
- `bin/`: Scripts ejecutables

### Componentes Principales

#### Estrategias
- **RSI (Relative Strength Index)**
  - Período por defecto: 14
  - Niveles de sobrecompra/sobreventa: 70/40
  - Señales: BUY (sobreventa), SELL (sobrecompra)

- **Media Móvil**
  - Períodos: 50 (rápida) y 200 (lenta)
  - Señales basadas en cruces de medias
  - Confirmación de tendencia

#### Servicios
- **MarketDataService**: Gestión de datos de mercado
- **OrderService**: Ejecución de órdenes
- **AccountDataService**: Gestión de cuenta
- **NotificationManager**: Sistema de notificaciones

#### Exchanges
- **BinanceConnector**
  - Soporte para testnet
  - Validación de tiempo del servidor
  - Manejo de límites de API

- **GateioConnector**
  - Soporte para sandbox
  - Validación de mercado
  - Manejo de órdenes

## Sistema de Notificaciones

### Formato de Notificaciones
```
✅ Trading Bot Notification ✅

📝 Mensaje:
Señal [ESTRATEGIA]: [ACCIÓN]

📊 Detalles:
• Valor [INDICADOR]: [VALOR]
• Confianza: [PORCENTAJE]%
• Par: [PAR]
• Temporalidad: [TIMEFRAME]
```

### Tipos de Notificaciones
- Señales de trading
- Ejecución de órdenes
- Errores críticos
- Estado del bot

## Manejo de Errores

### Tipos de Excepciones
- `DataServiceException`: Errores en datos de mercado
- `OrderExecutionException`: Errores en ejecución de órdenes
- `ExchangeConnectionException`: Errores de conexión
- `StrategyExecutionException`: Errores en estrategias

### Sistema de Logging
- Registro detallado de operaciones
- Niveles: INFO, WARNING, ERROR, CRITICAL
- Rotación de logs
- Contexto en cada entrada

## Caché y Optimización

### Sistema de Caché
- Almacenamiento en sistema de archivos
- TTL configurable por tipo de dato
- Invalidación automática
- Limpieza periódica

### Validación de Datos
- Verificación de timestamps
- Validación de formatos
- Comprobación de límites
- Sincronización con servidor

## Contribución
1. Fork del repositorio
2. Crear rama para feature (`git checkout -b feature/AmazingFeature`)
3. Commit de cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir Pull Request

## Licencia
Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## Contacto
Para soporte o consultas, por favor abrir un issue en el repositorio. 