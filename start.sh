#!/bin/bash
php-fpm -y /app/php-fpm.conf &
nginx -c /app/nginx.conf -g 'daemon off;'

