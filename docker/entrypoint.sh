#!/bin/sh

# Fix ownership of the entire repo so www-data (php-fpm) can run git pull
if [ -d /var/www/html ]; then
    chown -R www-data:www-data /var/www/html/storage
fi

# Set up SSH deploy key for www-data
if [ -f /ssh-keys/id_ed25519 ]; then
    mkdir -p /var/www/.ssh
    cp /ssh-keys/id_ed25519 /var/www/.ssh/id_ed25519
    chown -R www-data:www-data /var/www/.ssh
    chmod 700 /var/www/.ssh
    chmod 600 /var/www/.ssh/id_ed25519

    cat > /var/www/.ssh/config <<'EOF'
Host *
    IdentityFile /var/www/.ssh/id_ed25519
    StrictHostKeyChecking no
    UserKnownHostsFile /dev/null
EOF
    chown www-data:www-data /var/www/.ssh/config
    chmod 600 /var/www/.ssh/config
fi

exec "$@"
