#!/usr/bin/env pwsh

Write-Host "LatinPay - Setup Automatico" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

# 1. Copiar archivo .env
Write-Host "[1/6] Configurando archivo .env..." -ForegroundColor Yellow
if (-Not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "    Archivo .env creado correctamente" -ForegroundColor Green
} else {
    Write-Host "    El archivo .env ya existe, omitiendo..." -ForegroundColor Gray
}

# 2. Levantar contenedores
Write-Host "[2/6] Levantando contenedores Docker..." -ForegroundColor Yellow
docker-compose up -d --build

# Esperar a que MySQL este listo
Write-Host "[3/6] Esperando a MySQL..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

# 3. Instalar dependencias
Write-Host "[4/6] Instalando dependencias..." -ForegroundColor Yellow
docker-compose exec -T app composer install

# 4. Generar clave
Write-Host "[5/6] Generando clave de aplicacion..." -ForegroundColor Yellow
docker-compose exec -T app php artisan key:generate

# 5. Ejecutar migraciones
Write-Host "[5/6] Ejecutando migraciones..." -ForegroundColor Yellow
docker-compose exec -T app php artisan migrate --force

# 6. Cargar seeders
Write-Host "[6/6] Cargando datos de prueba..." -ForegroundColor Yellow
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
