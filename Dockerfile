# Imagen base de PHP con Composer
FROM php:8.2-apache

# Instalar dependencias del sistema con versión específica de libgmp-dev
RUN apt-get update && apt-get install -y \
    locales \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    default-mysql-client \
    software-properties-common && \
    # Añadir repositorio Debian Bullseye para libgmp-dev actualizado
    add-apt-repository -y 'deb http://deb.debian.org/debian bullseye main' && \
    apt-get update && \
    apt-get install -y libgmp-dev && \
    # Instalar extensiones PHP
    docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    sockets \
    zip \
    gmp && \
    # Limpiar repositorio temporal de Bullseye
    rm -f /etc/apt/sources.list.d/debian.sources && \
    # Instalar Redis
    pecl install redis && \
    docker-php-ext-enable redis && \
    # Limpieza final
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Configurar locale
RUN sed -i -e 's/# es_ES.UTF-8 UTF-8/es_ES.UTF-8 UTF-8/' /etc/locale.gen && \
    locale-gen

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia de los archivos del proyecto al contenedor
COPY . /var/www/trading-bot
WORKDIR /var/www/trading-bot

# Configurar usuario no-root
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN groupadd -g ${GROUP_ID} trader && \
    useradd -u ${USER_ID} -g trader -m trader && \
    chown -R trader:trader /var/www

# Configuración PHP personalizada
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/trading-bot
USER trader

EXPOSE 80