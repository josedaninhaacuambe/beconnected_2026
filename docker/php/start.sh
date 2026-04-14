#!/bin/sh
# ─── Beconnect Octane startup script ─────────────────────────────────────────
# 1. Inicia RoadRunner Octane em background
# 2. Aquece todos os workers com uma request cada (elimina cold start de 12s)
# 3. Passa para foreground para que o Docker não considere o container terminado

cd /var/www

# Remover ficheiro hot do Vite para forçar modo produção (usa public/build/)
rm -f public/hot

# Guardar contra recriação do ficheiro hot (ex: npm run dev na máquina host).
# Um loop em background elimina-o a cada 30 segundos enquanto o container correr.
(while true; do rm -f public/hot; sleep 30; done) &

# Iniciar Octane em background
php artisan octane:start \
  --server=roadrunner \
  --host=0.0.0.0 \
  --port=8080 \
  --workers=auto \
  --max-requests=1000 &

OCTANE_PID=$!

# Aguardar que o servidor esteja a ouvir (máx. 30s)
echo "[start.sh] Waiting for Octane to be ready..."
for i in $(seq 1 30); do
  if curl -sf http://127.0.0.1:8080/ > /dev/null 2>&1; then
    echo "[start.sh] Octane ready after ${i}s"
    break
  fi
  sleep 1
done

# Aquecer todos os workers sequencialmente com uma rota de API real.
# Rota /api/products/all toca DB (ProxySQL→MySQL) + Redis — aquece tudo.
# Sequencial garante que cada worker processa pelo menos 1 request.
WORKERS=$(nproc)
TOTAL=$((WORKERS * 2))
echo "[start.sh] Warming ${WORKERS} workers (${TOTAL} sequential requests to /api/products/all)..."
i=0
while [ $i -lt $TOTAL ]; do
  curl -sf http://127.0.0.1:8080/api/products/all > /dev/null 2>&1
  i=$((i + 1))
done
echo "[start.sh] All workers warmed. Ready for production."

# Manter o processo Octane em foreground
wait $OCTANE_PID
