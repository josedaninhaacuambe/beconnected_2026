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

# Instalar Node.js e npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Instalar Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Copiar ficheiros da aplicação (antes de instalar dependências)
COPY . /var/www

# Instalar dependências npm como root (antes de mudar para utilizador www)
WORKDIR /var/www
RUN npm install --legacy-peer-deps

# Criar utilizador não-root (mesmo UID que o host para evitar problemas de permissão)
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

# Ajustar permissões para o utilizador www
RUN chown -R www:www /var/www

# Script de arranque: usa o start.sh do repositório (sem Vite, apenas Octane)
COPY docker/php/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
