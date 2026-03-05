#!/bin/bash
set -e

MYSQL_HOST="${MYSQL_HOST:-mysql-primary}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"

# Wait for MySQL to be ready
echo "Waiting for MySQL at ${MYSQL_HOST}:${MYSQL_PORT}..."
for i in $(seq 1 60); do
    if mysqladmin ping -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --silent 2>/dev/null; then
        echo "MySQL is ready."
        break
    fi
    echo "Attempt $i: MySQL not ready yet, waiting..."
    sleep 2
done

# Generate MySQL Router configuration
cat > /tmp/mysqlrouter.conf << EOF
[DEFAULT]
logging_folder =
runtime_folder = /tmp/mysqlrouter
plugin_folder = /usr/lib/mysqlrouter

[logger]
level = INFO

[routing:primary]
bind_address = 0.0.0.0
bind_port = 6446
destinations = ${MYSQL_HOST}:${MYSQL_PORT}
routing_strategy = first-available
protocol = classic

[routing:secondary]
bind_address = 0.0.0.0
bind_port = 6447
destinations = ${MYSQL_HOST}:${MYSQL_PORT}
routing_strategy = round-robin-with-fallback
protocol = classic
EOF

mkdir -p /tmp/mysqlrouter

echo "Starting MySQL Router..."
exec mysqlrouter --config /tmp/mysqlrouter.conf
