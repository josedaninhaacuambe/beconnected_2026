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

# Script de arranque: inicia Octane e Vite, aquece workers, mantém em foreground
# Escrito como root antes de mudar para utilizador www
RUN echo '#!/bin/sh' > /usr/local/bin/start.sh && \
    echo 'cd /var/www' >> /usr/local/bin/start.sh && \
    echo 'echo "[start] Starting Vite dev server as root..."' >> /usr/local/bin/start.sh && \
    echo 'npm run dev -- --host 0.0.0.0 --port 5173 &' >> /usr/local/bin/start.sh && \
    echo 'VITE_PID=$!' >> /usr/local/bin/start.sh && \
    echo 'echo "[start] Waiting for Vite..."' >> /usr/local/bin/start.sh && \
    echo 'sleep 5' >> /usr/local/bin/start.sh && \
    echo 'su -c "php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8080 --workers=auto --max-requests=1000" www &' >> /usr/local/bin/start.sh && \
    echo 'OCTANE_PID=$!' >> /usr/local/bin/start.sh && \
    echo 'echo "[start] Waiting for Octane..."' >> /usr/local/bin/start.sh && \
    echo 'for s in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22 23 24 25 26 27 28 29 30; do' >> /usr/local/bin/start.sh && \
    echo '  curl -sf http://127.0.0.1:8080/ >/dev/null 2>&1 && echo "[start] Octane ready (${s}s)" && break' >> /usr/local/bin/start.sh && \
    echo '  sleep 1' >> /usr/local/bin/start.sh && \
    echo 'done' >> /usr/local/bin/start.sh && \
    echo 'W=$(nproc 2>/dev/null || echo 4)' >> /usr/local/bin/start.sh && \
    echo '# Warmup sequencial: espera resposta de cada request antes de enviar a próxima.' >> /usr/local/bin/start.sh && \
    echo '# Garante que cada worker PHP processa pelo menos 1 request (cold start individual ~7s).' >> /usr/local/bin/start.sh && \
    echo '# Total de tempo: W × ~8s = ~112s para 14 workers (feito em background durante startup).' >> /usr/local/bin/start.sh && \
    echo 'TOTAL=$((W * 2))' >> /usr/local/bin/start.sh && \
    echo 'echo "[start] Warming $W workers ($TOTAL sequential requests)..."' >> /usr/local/bin/start.sh && \
    echo 'i=0; while [ $i -lt $TOTAL ]; do curl -sf http://127.0.0.1:8080/ >/dev/null 2>&1; i=$((i+1)); done' >> /usr/local/bin/start.sh && \
    echo 'echo "[start] All workers warmed. Production ready."' >> /usr/local/bin/start.sh && \
    echo 'wait $OCTANE_PID' >> /usr/local/bin/start.sh && \
    chmod +x /usr/local/bin/start.sh

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
