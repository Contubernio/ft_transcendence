# Usa la imagen oficial de PHP para el servidor web.
FROM php:8.1-apache

# Habilita la reescritura de URLs para la compatibilidad con SPA
RUN a2enmod rewrite

# Instala las dependencias de SQLite y el programa sqlite3
RUN apt-get update && apt-get install -y libsqlite3-dev sqlite3

# Instala la extensión de SQLite para PHP
RUN docker-php-ext-install pdo pdo_sqlite

# Copia los archivos del proyecto al directorio del servidor web de Apache
COPY . /var/www/html/

# Establece el directorio de trabajo
WORKDIR /var/www/html

# Asegúrate de que el usuario de Apache (www-data) tiene permisos para escribir en el directorio
RUN chown -R www-data:www-data /var/www/html

# Cambia al usuario de Apache
USER www-data

# Elimina el archivo de la base de datos si existe, para asegurar una base de datos limpia al construir la imagen.
RUN if [ -f "transcendence.db" ]; then rm transcendence.db; fi

# Crea el archivo de la base de datos y ejecuta el script SQL con los permisos adecuados.
RUN sqlite3 transcendence.db < schema.sql

# Exponer el puerto 80 del contenedor
EXPOSE 80

# Inicia Apache en primer plano
CMD ["apache2-foreground"]
    
