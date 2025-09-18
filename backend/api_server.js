// backend/server.js
import Fastify from 'fastify';
import fastifyStatic from '@fastify/static';
import path from 'path';
import { fileURLToPath } from 'url';
import Database from 'better-sqlite3';
import { attachPong } from './pong_server.js';
import websocket from '@fastify/websocket'; // Asegúrate de importar el plugin de websocket

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename); // Corregido: se usa path.dirname

const fastify = Fastify({ logger: true });

// ----------------------
// CORS
// ----------------------
fastify.addHook('onRequest', (request, reply, done) => {
  reply.header('Access-Control-Allow-Origin', '*');
  reply.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  reply.header('Access-Control-Allow-Headers', 'Content-Type');
  if (request.method === 'OPTIONS') {
    reply.status(200).send();
    return;
  }
  done();
});

// ----------------------
// Servir archivos estáticos
// ----------------------
fastify.register(fastifyStatic, {
  root: path.join(__dirname, 'public'),
  prefix: '/',
  decorateReply: false,
});
/*
fastify.register(fastifyStatic, {
  root: path.join(__dirname, '..', 'frontend'),
  prefix: '/frontend/',
  decorateReply: false,
});
*/

// ----------------------
// Conexión SQLite con better-sqlite3
// ----------------------
try {
  const db = new Database('/usr/src/app/data/transcendence.db', { verbose: console.log });
  db.pragma('journal_mode = WAL');

  // ----------------------
  // Integrar Pong (WebSocket en /pong)
  // ----------------------
  fastify.register(websocket);
  await attachPong(fastify);

  // ----------------------
  // Funciones de API
  // ----------------------
  function handlePlayersRequest(request, reply) {
    const { method } = request;
    const data = request.body;
    const playerId = request.query.id;

    try {
      switch (method) {
        case 'GET':
          if (playerId) {
            const player = db.prepare('SELECT * FROM player WHERE player_id = ?').get(playerId);
            if (player) {
              reply.send(player);
            } else {
              reply.status(404).send({ success: false, message: 'Jugador no encontrado.' });
            }
          } else {
            const players = db.prepare(
              'SELECT player_id, alias, first_name, last_name, email FROM player'
            ).all();
            reply.send(players);
          }
          break;

        case 'POST':
          if (!data.alias || !data.first_name || !data.last_name || !data.email) {
            reply.status(400).send({ success: false, message: 'Datos incompletos.' });
            return;
          }
          const password_hash = 'default_password';
          const stmt = db.prepare(
            'INSERT INTO player (alias, first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?, ?)'
          );
          const res = stmt.run(data.alias, data.first_name, data.last_name, data.email, password_hash);
          reply.send({
            success: true,
            message: 'Jugador añadido correctamente.',
            id: res.lastID,
          });
          break;

        case 'PUT':
          if (!data.player_id) {
            reply
              .status(400)
              .send({ success: false, message: 'ID del jugador es obligatorio para actualizar.' });
            return;
          }
          const updates = [];
          const params = [];
          if (data.alias) {
            updates.push('alias = ?');
            params.push(data.alias);
          }
          if (data.first_name) {
            updates.push('first_name = ?');
            params.push(data.first_name);
          }
          if (data.last_name) {
            updates.push('last_name = ?');
            params.push(data.last_name);
          }
          if (data.email) {
            updates.push('email = ?');
            params.push(data.email);
          }
          if (updates.length === 0) {
            reply
              .status(400)
              .send({ success: false, message: 'No hay datos para actualizar.' });
            return;
          }
          params.push(data.player_id);
          const updateStmt = db.prepare(
            `UPDATE player SET ${updates.join(', ')} WHERE player_id = ?`
          );
          const updateRes = updateStmt.run(...params);
          if (updateRes.changes > 0) {
            reply.send({ success: true, message: 'Jugador actualizado correctamente.' });
          } else {
            reply
              .status(404)
              .send({ success: false, message: 'Jugador no encontrado o datos no cambiados.' });
          }
          break;

        case 'DELETE':
          if (!data.player_id) {
            reply
              .status(400)
              .send({ success: false, message: 'ID del jugador es obligatorio para eliminar.' });
            return;
          }
          const deleteStmt = db.prepare('DELETE FROM player WHERE player_id = ?');
          const deleteRes = deleteStmt.run(data.player_id);
          if (deleteRes.changes > 0) {
            reply.send({ success: true, message: 'Jugador eliminado correctamente.' });
          } else {
            reply.status(404).send({ success: false, message: 'Jugador no encontrado.' });
          }
          break;

        default:
          reply
            .status(405)
            .send({ success: false, message: 'Método no permitido para jugadores.' });
          break;
      }
    } catch (err) {
      console.error('Error en handlePlayersRequest:', err);
      reply.status(500).send({ success: false, message: 'Error interno del servidor.' });
    }
  }

  // ----------------------
  // Router principal de la API
  // ----------------------
  fastify.all('/api', async (request, reply) => {
    const route = request.query.route ?? 'players';

    switch (route) {
      case 'players':
        handlePlayersRequest(request, reply);
        break;
      default:
        reply.status(404).send({ success: false, message: 'Ruta no encontrada.' });
        break;
    }
  });

  // ----------------------
  // Arranque del servidor
  // ----------------------
  const start = async () => {
    try {
      await fastify.listen({ port: 3000, host: '0.0.0.0' });
    } catch (err) {
      fastify.log.error(err);
      process.exit(1);
    }
  };
  start();

} catch (err) {
  console.error('Error al conectar con la base de datos:', err);
  process.exit(1);
}