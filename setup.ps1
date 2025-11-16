#!/usr/bin/env pwsh

Write-Host "LatinPay - Setup Automatico" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

# 1. Levantar contenedores
Write-Host "[1/5] Levantando contenedores Docker..." -ForegroundColor Yellow
docker-compose up -d --build

# Esperar a que MySQL este listo
Write-Host "[2/5] Esperando a MySQL..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

# 2. Instalar dependencias
Write-Host "[3/5] Instalando dependencias..." -ForegroundColor Yellow
docker-compose exec -T app composer install

# 3. Generar clave
Write-Host "[4/5] Generando clave de aplicacion..." -ForegroundColor Yellow
docker-compose exec -T app php artisan key:generate

# 4. Ejecutar migraciones
Write-Host "[4/5] Ejecutando migraciones..." -ForegroundColor Yellow
docker-compose exec -T app php artisan migrate --force

# 5. Cargar seeders
Write-Host "[5/5] Cargando datos de prueba..." -ForegroundColor Yellow
docker-compose exec -T app php artisan db:seed

Write-Host ""
Write-Host "Setup completado exitosamente!" -ForegroundColor Green
Write-Host ""
Write-Host "API disponible en: http://localhost:8000/api" -ForegroundColor Cyan
Write-Host "Documentacion Swagger: http://localhost:8000/api/documentation" -ForegroundColor Cyan
Write-Host ""
Write-Host "Para ejecutar tests:" -ForegroundColor White
Write-Host "   docker-compose exec app php artisan test" -ForegroundColor Gray
Write-Host ""
Write-Host "Para ver logs de pagos:" -ForegroundColor White
Write-Host "   docker-compose exec app tail -f storage/logs/payments.log" -ForegroundColor Gray
Write-Host ""
