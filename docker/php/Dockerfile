FROM php:8.3-fpm

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libgd-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensões PHP necessárias para o Beconnect
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    xml \
    opcache

# Instalar extensão Redis
RUN pecl install redis && docker-php-ext-enable redis

# Instalar Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Criar utilizador não-root (mesmo UID que o host para evitar problemas de permissão)
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

# Copiar ficheiros da aplicação
COPY --chown=www:www . /var/www

USER www

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]
