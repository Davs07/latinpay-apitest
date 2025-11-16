.PHONY: help setup up down logs test migrate seed fresh clean

help:
	@echo "LatinPay - Comandos disponibles"
	@echo "================================"
	@echo "make setup    - Setup inicial del proyecto (instala todo)"
	@echo "make up       - Levantar contenedores"
	@echo "make down     - Detener contenedores"
	@echo "make logs     - Ver logs en tiempo real"
	@echo "make test     - Ejecutar tests"
	@echo "make migrate  - Ejecutar migraciones"
	@echo "make seed     - Cargar datos de prueba"
	@echo "make fresh    - Limpiar y recrear BD"
	@echo "make clean    - Limpiar todo"

setup:
	@echo "Setup inicial..."
	docker-compose up -d --build
	@sleep 10
	docker-compose exec -T app composer install
	docker-compose exec -T app php artisan key:generate
	docker-compose exec -T app php artisan migrate --force
	docker-compose exec -T app php artisan db:seed
	@echo "Setup completado!"
	@echo ""
	@echo "API: http://localhost:8000/api"

up:
	docker-compose up -d
	@echo "Contenedores levantados"

down:
	docker-compose down
	@echo "Contenedores detenidos"

logs:
	docker-compose exec app tail -f storage/logs/payments.log

test:
	docker-compose exec app php artisan test

migrate:
	docker-compose exec app php artisan migrate

seed:
	docker-compose exec app php artisan db:seed

fresh:
	docker-compose exec app php artisan migrate:fresh --seed
	@echo "Base de datos recreada"

clean:
	docker-compose down -v
	@echo "Todo limpio"
