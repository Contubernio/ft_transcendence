// websocket_server.js
const WebSocket = require('ws');

const PORT = 8080;

// Crear servidor WebSocket escuchando en todas las interfaces
const wss = new WebSocket.Server({ port: PORT, host: '0.0.0.0' });

console.log(`Servidor WebSocket funcionando en ws://localhost:${PORT}`);

// Función de keepalive para evitar desconexiones por timeout
function heartbeat() {
  this.isAlive = true;
}

wss.on('connection', function connection(ws) {
  console.log('✅ Cliente conectado');
  ws.isAlive = true;

  // Escuchar pong (respuesta al ping)
  ws.on('pong', heartbeat);

  // Enviar mensaje de bienvenida
  ws.send('¡Hola! Estás conectado al servidor de WebSocket.');

  // Escuchar mensajes del cliente
  ws.on('message', function incoming(message) {
    console.log('📩 Mensaje recibido del cliente:', message.toString());
    ws.send(`Servidor dice: Recibí tu mensaje: "${message}"`);
  });

  // Manejar desconexión
  ws.on('close', function close() {
    console.log('❌ Cliente desconectado');
  });
});

// Intervalo para enviar ping a cada cliente
const interval = setInterval(function ping() {
  wss.clients.forEach(function each(ws) {
    if (ws.isAlive === false) {
      console.log('⚠️ Cliente sin respuesta, cerrando conexión');
      return ws.terminate();
    }

    ws.isAlive = false;
    ws.ping();
  });
}, 30000); // cada 30s

wss.on('close', function close() {
  clearInterval(interval);
});
