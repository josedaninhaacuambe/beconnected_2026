#!/usr/bin/env bash
set -e

# Configuração
REMOTE_USER="seu_usuario"
REMOTE_HOST="seu_servidor"
REMOTE_DIR="~/beconnected.escaleno.co.mz"
BRANCH="robustez-do-beconnected"
DOCKER_SERVICE="beconnected_app"

echo "=== BUILD LOCAL ==="
docker exec -it "$DOCKER_SERVICE" bash -lc "npm run build"

echo "=== COMMIT LOCAL ==="
git add public/build/
git commit -m "feat: atualização do site"

echo "=== PUSH PARA ORIGEM ==="
git push origin "$BRANCH"

echo "=== ATUALIZANDO SERVIDOR ==="
ssh "$REMOTE_USER@$REMOTE_HOST" bash <<'EOF'
  set -e
  cd "$REMOTE_DIR"
  git pull origin "$BRANCH"
  /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize
  /opt/cpanel/ea-php83/root/usr/bin/php artisan view:clear
  /opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
  touch public/index.php
EOF

echo "=== DEPLOY COMPLETO ==="