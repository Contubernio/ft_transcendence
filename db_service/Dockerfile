# Usa una imagen base oficial de Node.js
FROM node:20-slim

# Instalar dependencias del sistema necesarias para better-sqlite3
RUN apt-get update && apt-get install -y \
    sqlite3 \
    python3 \
    make \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# Establecer el directorio de trabajo
WORKDIR /usr/src/app

# Copiar solo package.json primero (para cachear la instalación de dependencias)
COPY backend/package*.json ./backend/

# Instalar dependencias
RUN cd backend && npm install && npm rebuild better-sqlite3

# Copiar el resto del backend y los datos
COPY backend ./backend
COPY data ./data
COPY schema.sql ./

# Exponer el puerto de la API
EXPOSE 3000

# Ejecutar el servidor DB
CMD ["node", "backend/db_server.js"]
