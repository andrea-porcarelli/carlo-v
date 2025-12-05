#!/bin/bash
# Backup semplice per Carlov (esegui da cartella docker/)

# Crea directory backup nella root del progetto
mkdir -p ../storage/backups
cd ../storage/backups

# Backup giornaliero
docker compose -f ../../docker/docker-compose.yml exec mysql mysqldump -u root -ppassword123 carlov > backup_daily_$(date +%Y%m%d).sql

# Cleanup backup vecchi (>30 giorni)
find . -name "backup_daily_*.sql" -mtime +30 -delete

echo "Backup completato: backup_daily_$(date +%Y%m%d).sql"

# Se è domenica, fai anche backup settimanale
if [ $(date +%u) -eq 7 ]; then
    cp backup_daily_$(date +%Y%m%d).sql backup_weekly_$(date +%Y%m%d).sql
    echo "Backup settimanale: backup_weekly_$(date +%Y%m%d).sql"
fi
