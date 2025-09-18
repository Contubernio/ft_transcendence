#backend
# Imagen base oficial de Node.js
FROM node:20-slim

# Instalar dependencias necesarias para better-sqlite3
RUN apt-get update && apt-get install -y \
    sqlite3 \
    python3 \
    make \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# Crear directorio de trabajo
WORKDIR /usr/src/app

# Copiar backend
COPY . .

# Instalar dependencias
RUN npm install && npm rebuild better-sqlite3

# Exponer el puerto
EXPOSE 3000

# Arrancar el servidor
CMD ["node", "server.js"]
