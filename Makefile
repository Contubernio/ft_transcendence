CONTAINER_NAME=transcendence
IMAGE_NAME=transcendence:latest
HOST_UID := $(shell id -u)
NODE_UID := 1000

# Servicios docker-compose
COMPOSE_BACKEND=transcendence_backend
COMPOSE_FRONTEND=transcendence_frontend

# Construir la imagen
build:
	docker build -f Dockerfile.node -t $(IMAGE_NAME) .

# Detectar si ./data tiene permisos correctos
check-perms:
	@if [ -d ./data ]; then \
		OWNER=$$(stat -c "%u" ./data); \
		if [ "$$OWNER" -eq $(NODE_UID) ]; then \
			echo "✅ ./data tiene UID $(NODE_UID), se usará bind mount (up-host)"; \
		else \
			echo "⚠️ ./data pertenece a UID $$OWNER (no es $(NODE_UID)), se usará volumen de Docker (up-volume)"; \
			exit 1; \
		fi \
	else \
		echo "ℹ️ ./data no existe, se usará volumen de Docker (up-volume)"; \
		exit 1; \
	fi

# Crear carpeta ./data con permisos correctos
# No borra la carpeta si ya existe
prepare-host:
	mkdir -p ./data
	sudo chown -R $(NODE_UID):$(NODE_UID) ./data

# Arranque usando carpeta del host (solo se prepara si la carpeta no existe)
up-host: build
	@if [ ! -d ./data ]; then \
		echo "ℹ️ Carpeta ./data no encontrada, ejecutando prepare-host..."; \
		$(MAKE) prepare-host; \
	fi
	docker run -d \
		--name $(CONTAINER_NAME) \
		-p 8080:3000 \
		-v $(PWD)/data:/usr/src/app/data \
		$(IMAGE_NAME)

# Arranque usando volumen gestionado por Docker
volume:
	docker volume create transcendence_data || true

up-volume: build volume
	docker run -d \
		--name $(CONTAINER_NAME) \
		-p 8080:3000 \
		-v transcendence_data:/usr/src/app/data \
		$(IMAGE_NAME)

# Target automático: usa host si puede, volumen si no
up: build
	@if $(MAKE) check-perms; then \
		$(MAKE) up-host; \
	else \
		$(MAKE) up-volume; \
	fi

# Logs del contenedor
logs:
	docker logs -f $(CONTAINER_NAME)

# Acceso a la shell del contenedor
shell:
	docker exec -it $(CONTAINER_NAME) /bin/sh

# Bajar contenedor
down:
	docker stop $(CONTAINER_NAME) || true
	docker rm $(CONTAINER_NAME) || true

re: down up

test: down up logs

# -------------------------
# SECCIÓN: Comandos para docker-compose
# -------------------------

compose-up:
	ddocker compose up -d --build

compose-down:
	docker compose down

compose-logs:
	docker compose logs -f

compose-shell-db:
	docker exec -it $(COMPOSE_DB_SERVER) /bin/sh

compose-shell-pong:
	docker exec -it $(COMPOSE_PONG_SERVER) /bin/sh

compose-shell-web:
	docker exec -it $(COMPOSE_WEB_SERVER) /bin/sh

compose-re: compose-down compose-up

compose-test: compose-down compose-up compose-logs

.PHONY: build check-perms prepare-host up-host volume up-volume up logs shell down re test compose-up compose-down compose-logs compose-shell-db compose-shell-pong compose-shell-web compose-re compose-test