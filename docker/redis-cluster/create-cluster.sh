#!/bin/sh
# Bootstrap Redis Cluster — compatível com Alpine sh (sem bash arrays)
set -e

wait_for_node() {
  host=$1
  port=$2
  max=60
  count=0
  echo "[Redis Cluster] Waiting for $host:$port..."
  until redis-cli -h "$host" -p "$port" PING 2>/dev/null | grep -q PONG; do
    count=$((count + 1))
    if [ "$count" -ge "$max" ]; then
      echo "[Redis Cluster] ERROR: $host:$port did not come up."
      exit 1
    fi
    sleep 2
  done
  echo "[Redis Cluster] $host:$port ready."
}

# Aguarda todos os 6 nós
wait_for_node redis-1 6379
wait_for_node redis-2 6379
wait_for_node redis-3 6379
wait_for_node redis-4 6379
wait_for_node redis-5 6379
wait_for_node redis-6 6379

# Verifica se o cluster já está criado
state=$(redis-cli -h redis-1 -p 6379 CLUSTER INFO 2>/dev/null | grep cluster_state | cut -d: -f2 | tr -d '[:space:]')
if [ "$state" = "ok" ]; then
  echo "[Redis Cluster] Cluster already running — skipping init."
  redis-cli -h redis-1 -p 6379 CLUSTER INFO | grep -E "cluster_state|cluster_slots_ok"
  exit 0
fi

echo "[Redis Cluster] Creating cluster: 3 masters + 3 replicas..."

redis-cli --cluster create \
  redis-1:6379 \
  redis-2:6379 \
  redis-3:6379 \
  redis-4:6379 \
  redis-5:6379 \
  redis-6:6379 \
  --cluster-replicas 1 \
  --cluster-yes

echo "[Redis Cluster] Cluster created."
redis-cli -h redis-1 -p 6379 CLUSTER INFO | grep -E "cluster_state|cluster_slots_ok|cluster_known_nodes"
