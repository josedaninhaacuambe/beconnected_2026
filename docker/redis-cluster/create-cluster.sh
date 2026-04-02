#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# create-cluster.sh
# Bootstrap do Redis Cluster: 3 masters + 3 réplicas (1 réplica por master).
# Corre uma única vez num container init após todos os 6 nós estarem UP.
# ─────────────────────────────────────────────────────────────────────────────
set -e

NODES=(
  "redis-1:6379"
  "redis-2:6379"
  "redis-3:6379"
  "redis-4:6379"
  "redis-5:6379"
  "redis-6:6379"
)

wait_for_node() {
  local node=$1
  local host=${node%:*}
  local port=${node#*:}
  local max=60
  local count=0
  echo "[Redis Cluster] Waiting for $node..."
  until redis-cli -h "$host" -p "$port" PING 2>/dev/null | grep -q PONG; do
    count=$((count + 1))
    if [ $count -ge $max ]; then
      echo "[Redis Cluster] ERROR: $node did not come up."
      exit 1
    fi
    sleep 2
  done
  echo "[Redis Cluster] $node is ready."
}

# ─── 1. Aguarda todos os nós ──────────────────────────────────────────────────
for node in "${NODES[@]}"; do
  wait_for_node "$node"
done

# ─── 2. Verifica se o cluster já está criado ──────────────────────────────────
cluster_info=$(redis-cli -h redis-1 -p 6379 CLUSTER INFO 2>/dev/null | grep cluster_state)
if echo "$cluster_info" | grep -q "cluster_state:ok"; then
  echo "[Redis Cluster] ✅ Cluster already running — skipping init."
  exit 0
fi

# ─── 3. Cria o cluster ────────────────────────────────────────────────────────
echo "[Redis Cluster] Creating cluster with 3 masters + 3 replicas..."

redis-cli --cluster create \
  redis-1:6379 \
  redis-2:6379 \
  redis-3:6379 \
  redis-4:6379 \
  redis-5:6379 \
  redis-6:6379 \
  --cluster-replicas 1 \
  --cluster-yes

echo "[Redis Cluster] ✅ Cluster created successfully."
redis-cli -h redis-1 -p 6379 CLUSTER INFO | grep -E "cluster_state|cluster_slots_ok|connected_slaves"

echo "[Redis Cluster] Nodes:"
redis-cli -h redis-1 -p 6379 CLUSTER NODES
