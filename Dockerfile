# Usa una imagen base oficial de Node.js
FROM node:20-slim

# Copia los archivos del proyecto al contenedor
COPY . /usr/src/app

# Define el directorio de trabajo
WORKDIR /usr/src/app

# Instala las dependencias necesarias del proyecto
RUN npm install

# Expon los puertos necesarios (opcional, pero buena práctica)
EXPOSE 8080
EXPOSE 8081