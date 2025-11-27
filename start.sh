#!/bin/bash

PORT=${PORT:-8080}

echo "Starting PHP server on port $PORT..."
echo "PORT environment variable: $PORT"
echo "Listening on 0.0.0.0:$PORT"

php -S 0.0.0.0:$PORT -t . server.php

