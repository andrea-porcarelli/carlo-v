#!/bin/bash
# Setup SEMPLICE Carlov (esegui da cartella docker/)

echo "🚀 Setup Carlov SEMPLICE..."

# Stop tutto
docker compose down 2>/dev/null || true

# Copia .env se non esiste
if [ ! -f "../.env" ]; then
    cp .env.simple ../.env
    echo "✅ .env creato"
else
    echo "✅ .env già presente"
fi

echo "✅ File configurati"

# Genera APP_KEY se manca
if ! grep -q "APP_KEY=base64:" ../.env; then
    docker compose run --rm app php artisan key:generate
    echo "✅ APP_KEY generata"
fi

# Start
docker compose up -d

echo "✅ Container avviati"

# Aspetta 30 secondi
echo "⏳ Aspetto 30 secondi..."
sleep 30

# Setup Laravel se necessario
if [ ! -d "../vendor" ]; then
    echo "📦 Composer install..."
    docker compose exec app composer install --no-dev --optimize-autoloader
fi

# Migrazioni
#echo "🗄️ Migrazioni database..."
#docker compose exec app php artisan migrate --force

#echo ""
echo "🎉 FATTO!"
#echo "App: http://localhost"
#echo "PhpMyAdmin: http://localhost:8080"
#echo "Backup: cd docker && ./backup.sh"
