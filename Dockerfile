# Imagen base con Apache + PHP
FROM php:8.2-apache

# Instalar sqlite3 y extensiones necesarias
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Copiar código de la aplicación
COPY . /var/www/html/

# Dar permisos correctos a www-data (usuario de Apache)
RUN mkdir -p /var/www/html/data /var/log/apache2 \
    && chown -R www-data:www-data /var/www/html /var/log/apache2

# Copiar script de arranque
COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Puerto expuesto
EXPOSE 80

# Usar nuestro entrypoint
ENTRYPOINT ["entrypoint.sh"]

