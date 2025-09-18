// backend/server.js
import Fastify from "fastify";
import websocketPlugin from "@fastify/websocket";
import path from "path";
import { fileURLToPath } from "url";
import fs from "fs/promises";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const fastify = Fastify({ logger: true });
await fastify.register(websocketPlugin);

let clients = [];
let nextPlayer = 1; // asignar jugador 1 y 2
let gameStarted = false; // Variable para controlar el estado del juego

// Estado inicial del juego
let gameState = {
  ball: { x: 300, y: 200, dx: 4, dy: 4, r: 10 }, // más rápido
  paddles: {
    left: { y: 150 },
    right: { y: 150 },
  },
  score: { left: 0, right: 0 },
};

// ----------------------
// Sirve el cliente HTML
// ----------------------
fastify.get("/", async (req, reply) => {
  const htmlPath = path.join(__dirname, "client.html");
  const htmlContent = await fs.readFile(htmlPath, "utf8");
  reply.type("text/html").send(htmlContent);
});

// ----------------------
// WebSocket en /ws
// ----------------------
fastify.get("/ws", { websocket: true }, (socket, req) => {
  const playerId = nextPlayer <= 2 ? nextPlayer++ : null;
  fastify.log.info(`🎮 Nuevo cliente conectado como jugador ${playerId ?? "espectador"}`);

  const client = { socket, playerId };
  clients.push(client);

  // Enviar rol al cliente
  socket.send(JSON.stringify({ type: "role", player: playerId }));

  // Notificar al cliente si está esperando
  if (clients.length < 2) {
    socket.send(JSON.stringify({ type: "info", message: "Esperando a otro jugador..." }));
  }

  socket.on("message", (msg) => {
    // Solo procesa los movimientos si el juego ha comenzado
    if (gameStarted) {
      try {
        const parsed = JSON.parse(msg.toString());
        if (parsed.type === "move") {
          if (client.playerId === 1) {
            // Jugador 1 controla izquierda
            gameState.paddles.left.y = Math.max(
              0,
              Math.min(300, gameState.paddles.left.y + parsed.dy)
            );
          }
          if (client.playerId === 2) {
            // Jugador 2 controla derecha
            gameState.paddles.right.y = Math.max(
              0,
              Math.min(300, gameState.paddles.right.y + parsed.dy)
            );
          }
        }
      } catch (e) {
        fastify.log.error("❌ Error parsing message", e);
      }
    }
  });

  socket.on("close", () => {
    fastify.log.info(`👋 Cliente ${playerId ?? "espectador"} desconectado`);
    clients = clients.filter((c) => c.socket !== socket);
    if (playerId) nextPlayer = playerId; // liberar el slot si se desconecta
    
    // Si un jugador se desconecta, el juego se reinicia
    if (clients.length < 2) {
      gameStarted = false;
      fastify.log.info("⚠️ Jugador desconectado. Esperando a nuevos jugadores...");
    }
  });
});

// ----------------------
// Bucle del juego
// ----------------------
setInterval(() => {
  // Solo ejecuta el bucle si hay 2 clientes conectados
  if (clients.length >= 2) {
    if (!gameStarted) {
      gameStarted = true;
      fastify.log.info("🎮 ¡Juego iniciado! Hay dos jugadores conectados.");
    }

    // Mueve la pelota
    gameState.ball.x += gameState.ball.dx;
    gameState.ball.y += gameState.ball.dy;

    // Rebote vertical
    if (
      gameState.ball.y - gameState.ball.r < 0 ||
      gameState.ball.y + gameState.ball.r > 400
    ) {
      gameState.ball.dy *= -1;
    }

    // Rebote en paletas
    if (
      gameState.ball.x - gameState.ball.r < 20 &&
      gameState.ball.y > gameState.paddles.left.y &&
      gameState.ball.y < gameState.paddles.left.y + 100
    ) {
      gameState.ball.dx *= -1.05; // aumenta la velocidad
    }
    if (
      gameState.ball.x + gameState.ball.r > 580 &&
      gameState.ball.y > gameState.paddles.right.y &&
      gameState.ball.y < gameState.paddles.right.y + 100
    ) {
      gameState.ball.dx *= -1.05; // aumenta la velocidad
    }

    // Reinicio y puntuación
    if (gameState.ball.x < 0) {
      gameState.score.right++;
      gameState.ball = { x: 300, y: 200, dx: 4, dy: 4, r: 10 };
    }
    if (gameState.ball.x > 600) {
      gameState.score.left++;
      gameState.ball = { x: 300, y: 200, dx: -4, dy: 4, r: 10 };
    }

    // Broadcast
    const state = JSON.stringify({ type: "state", data: gameState });
    clients = clients.filter((c) => {
      try {
        c.socket.send(state);
        return true;
      } catch (e) {
        return false;
      }
    });
  } else {
    // Si no hay suficientes jugadores, el juego se detiene
    gameStarted = false;
  }
}, 50);

// ----------------------
// Arranca el servidor
// ----------------------
const start = async () => {
  try {
    await fastify.listen({ port: 8080, host: "0.0.0.0" });
    fastify.log.info("🚀 Pong server corriendo en http://localhost:8080");
  } catch (err) {
    fastify.log.error(err);
    process.exit(1);
  }
};
start();