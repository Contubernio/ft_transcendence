#!/bin/bash

# Ruta a la base de datos
DB_PATH="/var/www/html/data/transcendence.db"

# Ruta al script SQL para crear la base de datos
SCHEMA_PATH="/var/www/html/schema.sql"

# Asegúrate de que el usuario de Apache (www-data) tenga permisos sobre el directorio de logs
mkdir -p /var/log/apache2/
chown -R www-data:www-data /var/log/apache2/

# Comprueba si el archivo de la base de datos existe
if [ ! -f "$DB_PATH" ]; then
    echo "Base de datos no encontrada. Creando y poblando..."
    
    # Crea la base de datos y la puebla
    sqlite3 "$DB_PATH" < "$SCHEMA_PATH"

    echo "Base de datos creada y populada correctamente."
fi

# Inicia Apache en primer plano
exec apache2-foreground