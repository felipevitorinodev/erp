#!/bin/bash

echo "Rodando migrations..."
php artisan migrate --force

echo "Rodando seeders..."
php artisan db:seed --force

echo "Limpando cache..."
php artisan config:clear
php artisan cache:clear

echo "Iniciando Apache..."
apache2-foreground