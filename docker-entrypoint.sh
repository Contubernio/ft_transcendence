#!/bin/sh
set -e

APP_DIR="/usr/src/app"
BACKEND_DIR="$APP_DIR/backend"
FRONTEND_DIR="$APP_DIR/frontend"
DATA_DIR="$APP_DIR/data"
DB_FILE="$DATA_DIR/transcendence.db"
SCHEMA_PATH="$APP_DIR/schema.sql"

# Crear directorio de datos si no existe
mkdir -p "$DATA_DIR"
chown -R node:node "$DATA_DIR"
chmod -R 770 "$DATA_DIR"

# Crear la base de datos si no existe
if [ ! -f "$DB_FILE" ]; then
    echo "Base de datos no encontrada. Creando y poblando..."
    gosu node sh -c "sqlite3 \"$DB_FILE\" < \"$SCHEMA_PATH\""
    echo "Base de datos creada y populada correctamente."
fi

# Asegurar permisos correctos sobre el archivo .db
chown node:node "$DB_FILE" || true
chmod 660 "$DB_FILE" || true

# Confirmar que frontend existe
if [ ! -d "$FRONTEND_DIR" ]; then
    echo "⚠️  Advertencia: no se encontró la carpeta frontend en $FRONTEND_DIR"
fi

# Ejecutar servidor como usuario node con npm start
exec gosu node npm start --prefix "$BACKEND_DIR"

