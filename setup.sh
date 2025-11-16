#!/bin/bash

echo "LatinPay - Setup Automático"
echo "================================"

# 1. Copiar archivo .env
echo "[1/6] Configurando archivo .env..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "    Archivo .env creado correctamente"
else
    echo "    El archivo .env ya existe, omitiendo..."
fi

# 2. Levantar contenedores
echo "[2/6] Levantando contenedores Docker..."
docker-compose up -d --build

# Esperar a que MySQL esté listo
echo "[3/6] Esperando a MySQL..."
sleep 10

# 3. Instalar dependencias
echo "[4/6] Instalando dependencias..."
docker-compose exec -T app composer install

# 4. Generar clave
echo "[5/6] Generando clave de aplicación..."
docker-compose exec -T app php artisan key:generate

# 5. Ejecutar migraciones
echo "[5/6] Ejecutando migraciones..."
docker-compose exec -T app php artisan migrate --force

# 6. Cargar seeders
echo "[6/6] Cargando datos de prueba..."
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
