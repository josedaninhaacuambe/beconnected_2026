#!/bin/bash
# Script de arranque automático do Beconnect via Docker
# Executar: bash docker/start.sh

set -e

echo "🚀 Beconnect — Arranque Docker"
echo "================================"

# 1. Copiar .env.docker para .env se não existir ou se pedido
if [ ! -f .env ] || [ "$1" == "--reset-env" ]; then
    echo "📋 A copiar .env.docker → .env ..."
    cp .env.docker .env
fi

# 2. Subir os contentores em background
echo "🐳 A iniciar contentores..."
docker compose up -d --build

# 3. Aguardar MySQL estar pronto
echo "⏳ A aguardar MySQL ficar disponível..."
until docker compose exec mysql mysqladmin ping -h localhost -u root -pbeconnect_secret --silent 2>/dev/null; do
    echo "   MySQL ainda não está pronto, aguardar..."
    sleep 3
done
echo "✅ MySQL pronto!"

# 4. Instalar dependências PHP (caso vendor/ não exista)
if [ ! -d vendor ]; then
    echo "📦 A instalar dependências PHP (composer install)..."
    docker compose exec app composer install --no-interaction --prefer-dist
fi

# 5. Gerar APP_KEY se estiver em branco
APP_KEY=$(grep "^APP_KEY=" .env | cut -d '=' -f2)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" == "base64:" ]; then
    echo "🔑 A gerar APP_KEY..."
    docker compose exec app php artisan key:generate
fi

# 6. Executar migrações e seeders
echo "🗄️  A executar migrações e seeders..."
docker compose exec app php artisan migrate --seed --force

# 7. Criar link de storage
echo "📁 A criar link de storage..."
docker compose exec app php artisan storage:link

# 8. Limpar e optimizar caches
echo "⚡ A limpar caches..."
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear

# 9. Build do frontend (Node)
echo "🎨 A compilar frontend (npm run build)..."
docker compose --profile build run --rm node

echo ""
echo "======================================"
echo "✅ Beconnect está pronto!"
echo "======================================"
echo ""
echo "  🌐 Aplicação:   http://localhost:8000"
echo "  🗄️  phpMyAdmin:  http://localhost:8081"
echo "  📧 Admin:       admin@beconnect.co.mz"
echo "  🔑 Password:    Beconnect@2025"
echo ""
echo "  MySQL host (externo): 127.0.0.1:3307"
echo "  MySQL user: beconnect / beconnect_secret"
echo ""
