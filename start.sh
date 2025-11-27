#!/bin/bash

PORT=${PORT:-8080}

echo "Starting PHP server on port $PORT..."

php -S 0.0.0.0:$PORT -t . server.php &
PHP_PID=$!

sleep 2

if ! kill -0 $PHP_PID 2>/dev/null; then
    echo "ERROR: PHP server failed to start"
    exit 1
fi

echo "PHP server started successfully on port $PORT"

wait $PHP_PID

