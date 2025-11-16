#!/bin/bash

echo "LatinPay - Setup Automático"
echo "================================"

# 1. Levantar contenedores
echo "Levantando contenedores Docker..."
docker-compose up -d --build

# Esperar a que MySQL esté listo
echo "Esperando a MySQL..."
sleep 10

# 2. Instalar dependencias
echo "Instalando dependencias..."
docker-compose exec -T app composer install

# 3. Generar clave
echo "Generando clave de aplicación..."
docker-compose exec -T app php artisan key:generate

# 4. Ejecutar migraciones
echo "Ejecutando migraciones..."
docker-compose exec -T app php artisan migrate --force

# 5. Cargar seeders
echo "Cargando datos de prueba..."
docker-compose exec -T app php artisan db:seed

echo ""
echo "Setup completado exitosamente!"
echo ""
echo "API disponible en: http://localhost:8000/api"
echo ""
echo "Para ejecutar tests:"
echo "   docker-compose exec app php artisan test"
echo ""
echo "Para ver logs de pagos:"
echo "   docker-compose exec app tail -f storage/logs/payments.log"
echo ""
