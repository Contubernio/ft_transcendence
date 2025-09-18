#frontend
# Imagen base ligera
FROM node:20-slim

# Instalar http-server
RUN npm install -g http-server

# Crear directorio de trabajo
WORKDIR /usr/src/app

# Copiar archivos estáticos del frontend
COPY . .

# Exponer el puerto
EXPOSE 8081

# Servir el frontend
CMD ["http-server", ".", "-p", "8081"]
