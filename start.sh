#!/bin/bash
PORT=${PORT:-8080}
sed "s/\${PORT}/$PORT/g" /app/nginx.conf > /tmp/nginx.conf
mv /tmp/nginx.conf /app/nginx.conf
php-fpm -y /app/php-fpm.conf &
sleep 2
nginx -c /app/nginx.conf -g 'daemon off;'

