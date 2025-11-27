#!/bin/bash
export PORT=${PORT:-8080}
sed -i "s/listen 8080;/listen $PORT;/" /app/nginx.conf
php-fpm -y /app/php-fpm.conf &
nginx -c /app/nginx.conf -g 'daemon off;'

