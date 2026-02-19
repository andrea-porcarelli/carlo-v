#!/bin/sh
# Fix .git ownership so www-data (php-fpm) can run git pull
if [ -d /var/www/html/.git ]; then
    chown -R www-data:www-data /var/www/html/.git
fi

exec "$@"
