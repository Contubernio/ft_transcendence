// backend/pong_server.js
import websocket from '@fastify/websocket';

const GAME_WIDTH = 800;
const GAME_HEIGHT = 500;
const PADDLE_WIDTH = 15;
const PADDLE_HEIGHT = 100;
const BALL_RADIUS = 10;
const PADDLE_SPEED = 5;
const INITIAL_BALL_SPEED = 5;
const TICK_RATE = 60;

class Game {
  constructor(log) {
    this.log = log;

    this.state = this.initialState();
    this.inputs = {
      left: { up: false, down: false },
      right: { up: false, down: false },
    };

    this.sockets = { left: null, right: null, spectators: new Set() };

    this.loop = setInterval(() => this.step(), 1000 / TICK_RATE);
  }

  initialState() {
    return {
      width: GAME_WIDTH,
      height: GAME_HEIGHT,
      left: { x: 0, y: (GAME_HEIGHT - PADDLE_HEIGHT) / 2, dy: 0 },
      right: { x: GAME_WIDTH - PADDLE_WIDTH, y: (GAME_HEIGHT - PADDLE_HEIGHT) / 2, dy: 0 },
      ball: {
        x: GAME_WIDTH / 2,
        y: GAME_HEIGHT / 2,
        dx: INITIAL_BALL_SPEED * (Math.random() > 0.5 ? 1 : -1),
        dy: INITIAL_BALL_SPEED * (Math.random() > 0.5 ? 1 : -1),
      },
      score: { left: 0, right: 0 },
      running: true,
    };
  }

  handleConnection(ws) {
    // Asignar rol
    let role = 'spectator';
    if (!this.sockets.left) {
      this.sockets.left = ws;
      role = 'left';
    } else if (!this.sockets.right) {
      this.sockets.right = ws;
      role = 'right';
    } else {
      this.sockets.spectators.add(ws);
    }

    // Enviar rol al cliente
    ws.send(JSON.stringify({ type: 'role', role }));

    // Manejo de mensajes (inputs)
    ws.on('message', (buf) => {
      try {
        const msg = JSON.parse(buf.toString());
        if (msg.type === 'input') {
          const target = role === 'left' ? 'left' : role === 'right' ? 'right' : null;
          if (target) {
            this.inputs[target].up = !!msg.up;
            this.inputs[target].down = !!msg.down;
          }
        }
      } catch (e) {
        this.log.error(e);
      }
    });

    // Cleanup al desconectar
    ws.on('close', () => {
      if (this.sockets.left === ws) this.sockets.left = null;
      else if (this.sockets.right === ws) this.sockets.right = null;
      else this.sockets.spectators.delete(ws);

      // Si un jugador se va, resetea inputs del lado
      if (role === 'left') this.inputs.left = { up: false, down: false };
      if (role === 'right') this.inputs.right = { up: false, down: false };
    });
  }

  step() {
    const s = this.state;
    if (!s.running) return;

    // Aplicar inputs a palas
    s.left.dy = (this.inputs.left.up ? -PADDLE_SPEED : 0) + (this.inputs.left.down ? PADDLE_SPEED : 0);

    // Si no hay jugador derecho, IA simple; si hay, usar inputs
    if (this.sockets.right) {
      s.right.dy = (this.inputs.right.up ? -PADDLE_SPEED : 0) + (this.inputs.right.down ? PADDLE_SPEED : 0);
    } else {
      // IA: seguir la bola con umbral
      const center = s.right.y + PADDLE_HEIGHT / 2;
      s.right.dy = Math.abs(s.ball.y - center) < 10 ? 0 : (s.ball.y < center ? -PADDLE_SPEED : PADDLE_SPEED);
    }

    // Mover palas
    s.left.y = clamp(s.left.y + s.left.dy, 0, GAME_HEIGHT - PADDLE_HEIGHT);
    s.right.y = clamp(s.right.y + s.right.dy, 0, GAME_HEIGHT - PADDLE_HEIGHT);

    // Mover bola
    s.ball.x += s.ball.dx;
    s.ball.y += s.ball.dy;

    // Rebote arriba/abajo
    if (s.ball.y - BALL_RADIUS < 0 || s.ball.y + BALL_RADIUS > GAME_HEIGHT) {
      s.ball.dy *= -1;
      s.ball.y = clamp(s.ball.y, BALL_RADIUS, GAME_HEIGHT - BALL_RADIUS);
    }

    // Colisión con pala izquierda
    if (
      s.ball.dx < 0 &&
      s.ball.x - BALL_RADIUS <= PADDLE_WIDTH &&
      s.ball.y >= s.left.y &&
      s.ball.y <= s.left.y + PADDLE_HEIGHT
    ) {
      s.ball.dx *= -1;
      s.ball.x = PADDLE_WIDTH + BALL_RADIUS;
      // pequeño “spin” según dónde pega
      const offset = (s.ball.y - (s.left.y + PADDLE_HEIGHT / 2)) / (PADDLE_HEIGHT / 2);
      s.ball.dy = INITIAL_BALL_SPEED * offset;
    }

    // Colisión con pala derecha
    if (
      s.ball.dx > 0 &&
      s.ball.x + BALL_RADIUS >= s.right.x &&
      s.ball.y >= s.right.y &&
      s.ball.y <= s.right.y + PADDLE_HEIGHT
    ) {
      s.ball.dx *= -1;
      s.ball.x = s.right.x - BALL_RADIUS;
      const offset = (s.ball.y - (s.right.y + PADDLE_HEIGHT / 2)) / (PADDLE_HEIGHT / 2);
      s.ball.dy = INITIAL_BALL_SPEED * offset;
    }

    // Goles
    if (s.ball.x + BALL_RADIUS < 0) {
      s.score.right += 1;
      this.resetBall(-1);
    } else if (s.ball.x - BALL_RADIUS > GAME_WIDTH) {
      s.score.left += 1;
      this.resetBall(1);
    }

    // Emitir estado
    this.broadcast({
      type: 'state',
      state: {
        leftY: s.left.y,
        rightY: s.right.y,
        ballX: s.ball.x,
        ballY: s.ball.y,
        score: s.score,
        w: GAME_WIDTH,
        h: GAME_HEIGHT,
      },
    });
  }

  resetBall(dir = 1) {
    this.state.ball.x = GAME_WIDTH / 2;
    this.state.ball.y = GAME_HEIGHT / 2;
    this.state.ball.dx = INITIAL_BALL_SPEED * dir;
    this.state.ball.dy = INITIAL_BALL_SPEED * (Math.random() > 0.5 ? 1 : -1);
  }

  broadcast(payload) {
    const json = JSON.stringify(payload);
    const send = (ws) => {
      try { ws && ws.readyState === 1 && ws.send(json); } catch (_) {}
    };
    send(this.sockets.left);
    send(this.sockets.right);
    for (const s of this.sockets.spectators) send(s);
  }
}

function clamp(v, min, max) {
  return Math.max(min, Math.min(max, v));
}

// Expone una función para “enchufar” el pong al Fastify principal
export async function attachPong(fastify) {
  await fastify.register(websocket);
  fastify.get('/pong', { websocket: true }, (conn) => {
    fastify.log.info('WS conectado a /pong');
    game.handleConnection(conn.socket);
  });
}

// Instancia única del juego
const game = new Game(console);
