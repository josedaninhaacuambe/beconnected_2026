#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# init-replication.sh
# Script de inicialização da replicação MySQL GTID.
# Corre uma única vez num container init, depois de master e réplicas estarem up.
# ─────────────────────────────────────────────────────────────────────────────
set -e

MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-beconnect_secret}"
REPL_USER="repl"
REPL_PASSWORD="${MYSQL_REPL_PASSWORD:-repl_secret_2025}"

wait_for_mysql() {
  local host=$1
  local max=60
  local count=0
  echo "[Replication Init] Waiting for $host..."
  until mysql -h "$host" -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1" &>/dev/null; do
    count=$((count + 1))
    if [ $count -ge $max ]; then
      echo "[Replication Init] ERROR: $host did not come up in time."
      exit 1
    fi
    sleep 2
  done
  echo "[Replication Init] $host is ready."
}

# ─── 1. Aguarda todos os nós ──────────────────────────────────────────────────
wait_for_mysql mysql-master
wait_for_mysql mysql-replica-1
wait_for_mysql mysql-replica-2

# ─── 2. Configura o master ────────────────────────────────────────────────────
echo "[Replication Init] Configuring master..."
mysql -h mysql-master -u root -p"$MYSQL_ROOT_PASSWORD" <<-MASTER_SQL
  -- Cria utilizador de replicação (idempotente)
  CREATE USER IF NOT EXISTS '${REPL_USER}'@'%'
    IDENTIFIED WITH mysql_native_password BY '${REPL_PASSWORD}';
  GRANT REPLICATION SLAVE ON *.* TO '${REPL_USER}'@'%';

  -- Cria utilizador ProxySQL monitor
  CREATE USER IF NOT EXISTS 'proxysql_monitor'@'%'
    IDENTIFIED WITH mysql_native_password BY 'proxysql_monitor_2025';
  GRANT USAGE, REPLICATION CLIENT ON *.* TO 'proxysql_monitor'@'%';

  FLUSH PRIVILEGES;
MASTER_SQL
echo "[Replication Init] Master configured."

# ─── 3. Configura réplicas ────────────────────────────────────────────────────
configure_replica() {
  local host=$1
  echo "[Replication Init] Configuring replica $host..."

  mysql -h "$host" -u root -p"$MYSQL_ROOT_PASSWORD" <<-REPLICA_SQL
    STOP SLAVE;
    RESET SLAVE ALL;

    CHANGE REPLICATION SOURCE TO
      SOURCE_HOST='mysql-master',
      SOURCE_PORT=3306,
      SOURCE_USER='${REPL_USER}',
      SOURCE_PASSWORD='${REPL_PASSWORD}',
      SOURCE_AUTO_POSITION=1,
      SOURCE_CONNECT_RETRY=10,
      SOURCE_RETRY_COUNT=100;

    START SLAVE;

    -- Cria também o utilizador monitor nas réplicas
    CREATE USER IF NOT EXISTS 'proxysql_monitor'@'%'
      IDENTIFIED WITH mysql_native_password BY 'proxysql_monitor_2025';
    GRANT USAGE, REPLICATION CLIENT ON *.* TO 'proxysql_monitor'@'%';
    FLUSH PRIVILEGES;
REPLICA_SQL

  # Verifica estado da replicação
  sleep 3
  local status
  status=$(mysql -h "$host" -u root -p"$MYSQL_ROOT_PASSWORD" \
    -e "SHOW SLAVE STATUS\G" 2>/dev/null | grep -E "Slave_(IO|SQL)_Running|Seconds_Behind")
  echo "[Replication Init] $host status: $status"
}

configure_replica mysql-replica-1
configure_replica mysql-replica-2

echo "[Replication Init] ✅ Replication setup complete."
echo "[Replication Init]    Master:    mysql-master:3306"
echo "[Replication Init]    Replica 1: mysql-replica-1:3306"
echo "[Replication Init]    Replica 2: mysql-replica-2:3306"
