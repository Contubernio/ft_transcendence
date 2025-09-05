<?php
// Configuración de la cabecera para permitir la comunicación con el frontend
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// El path a la base de datos
$db_path = '../data/transcendence.db';

// Maneja las peticiones OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Conexión a la base de datos de SQLite
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $method = $_SERVER['REQUEST_METHOD'];
    $data = null;
    
    // Solo leer el cuerpo de la petición para POST, PUT y DELETE
    if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
    }
    
    // Enrutador: utiliza el parámetro 'route' para decidir qué hacer
    $route = $_GET['route'] ?? 'players';

    switch ($route) {
        case 'players':
            handlePlayersRequest($method, $data, $pdo);
            break;
        case 'games':
            handleGamesRequest($method, $data, $pdo);
            break;
        case 'tournaments':
            handleTournamentsRequest($method, $data, $pdo);
            break;
        case 'friends':
            handleFriendsRequest($method, $data, $pdo);
            break;
        case 'chats':
            handleChatsRequest($method, $data, $pdo);
            break;
        case 'messages':
            handleMessagesRequest($method, $data, $pdo);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Ruta no encontrada.']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}

// --- Funciones de manejo para cada endpoint ---

function handlePlayersRequest($method, $data, $pdo) {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query('SELECT player_id, alias, first_name, last_name, email, creation_date, status, active FROM player');
            $players = $stmt->fetchAll();
            echo json_encode($players);
            break;
        case 'POST':
            if (!empty($data['alias']) && !empty($data['first_name']) && !empty($data['last_name']) && !empty($data['email'])) {
                $password_hash = password_hash('default_password', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO player (alias, first_name, last_name, email, password_hash) VALUES (:alias, :first_name, :last_name, :email, :password_hash)");
                $stmt->execute([
                    ':alias' => $data['alias'],
                    ':first_name' => $data['first_name'],
                    ':last_name' => $data['last_name'],
                    ':email' => $data['email'],
                    ':password_hash' => $password_hash
                ]);
                echo json_encode(['success' => true, 'message' => 'Jugador añadido correctamente.', 'id' => $pdo->lastInsertId()]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            }
            break;
        case 'PUT':
            if (!empty($data['player_id'])) {
                $updates = [];
                $params = [':player_id' => $data['player_id']];
                if (isset($data['alias'])) { $updates[] = 'alias = :alias'; $params[':alias'] = $data['alias']; }
                if (isset($data['first_name'])) { $updates[] = 'first_name = :first_name'; $params[':first_name'] = $data['first_name']; }
                if (isset($data['last_name'])) { $updates[] = 'last_name = :last_name'; $params[':last_name'] = $data['last_name']; }
                if (isset($data['email'])) { $updates[] = 'email = :email'; $params[':email'] = $data['email']; }
                if (empty($updates)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No hay datos para actualizar.']);
                    return;
                }
                $stmt = $pdo->prepare("UPDATE player SET " . implode(', ', $updates) . " WHERE player_id = :player_id");
                $result = $stmt->execute($params);
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Jugador actualizado correctamente.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Jugador no encontrado o datos no cambiados.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID del jugador es obligatorio para actualizar.']);
            }
            break;
        case 'DELETE':
            if (!empty($data['player_id'])) {
                $stmt = $pdo->prepare("DELETE FROM player WHERE player_id = :player_id");
                $result = $stmt->execute([':player_id' => $data['player_id']]);
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Jugador eliminado correctamente.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Jugador no encontrado.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID del jugador es obligatorio para eliminar.']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido para jugadores.']);
            break;
    }
}

function handleGamesRequest($method, $data, $pdo) {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query('SELECT * FROM game');
            $games = $stmt->fetchAll();
            echo json_encode($games);
            break;
        case 'POST':
            if (!empty($data['player1_id']) && !empty($data['player2_id']) && !empty($data['player1_score']) && !empty($data['player2_score'])) {
                $stmt = $pdo->prepare("INSERT INTO game (player1_id, player2_id, player1_score, player2_score, duration, winner_id) VALUES (:p1_id, :p2_id, :p1_score, :p2_score, :duration, :winner_id)");
                $winner_id = ($data['player1_score'] > $data['player2_score']) ? $data['player1_id'] : (($data['player2_score'] > $data['player1_score']) ? $data['player2_id'] : null);
                $stmt->execute([
                    ':p1_id' => $data['player1_id'],
                    ':p2_id' => $data['player2_id'],
                    ':p1_score' => $data['player1_score'],
                    ':p2_score' => $data['player2_score'],
                    ':duration' => $data['duration'] ?? 0,
                    ':winner_id' => $winner_id
                ]);
                echo json_encode(['success' => true, 'message' => 'Partida añadida correctamente.', 'id' => $pdo->lastInsertId()]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos incompletos para añadir partida.']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido para games.']);
            break;
    }
}

function handleTournamentsRequest($method, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM tournament WHERE tournament_id = :id");
                $stmt->execute([':id' => $_GET['id']]);
                $tournament = $stmt->fetch();
                if ($tournament) {
                    echo json_encode($tournament);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Torneo no encontrado.']);
                }
            } else {
                $stmt = $pdo->query('SELECT * FROM tournament');
                $tournaments = $stmt->fetchAll();
                echo json_encode($tournaments);
            }
            break;
        case 'POST':
            if (!empty($data['name'])) {
                $stmt = $pdo->prepare("INSERT INTO tournament (name) VALUES (:name)");
                $stmt->execute([':name' => $data['name']]);
                echo json_encode(['success' => true, 'message' => 'Torneo creado correctamente.', 'id' => $pdo->lastInsertId()]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'El nombre del torneo es obligatorio.']);
            }
            break;
        case 'DELETE':
            if (!empty($data['id'])) {
                $stmt = $pdo->prepare("DELETE FROM tournament WHERE tournament_id = :id");
                $result = $stmt->execute([':id' => $data['id']]);
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Torneo eliminado correctamente.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Torneo no encontrado.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID del torneo es obligatorio para eliminar.']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido para tournaments.']);
            break;
    }
}

function handleFriendsRequest($method, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if (!empty($_GET['player_id'])) {
                $stmt = $pdo->prepare("SELECT p.player_id, p.alias, f.status FROM friends f JOIN player p ON (f.player2_id = p.player_id OR f.player1_id = p.player_id) WHERE (f.player1_id = :id OR f.player2_id = :id) AND p.player_id != :id");
                $stmt->execute([':id' => $_GET['player_id']]);
                $friends = $stmt->fetchAll();
                echo json_encode($friends);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID del jugador es obligatorio para listar amigos.']);
            }
            break;
        case 'POST':
            if (!empty($data['player1_id']) && !empty($data['player2_id'])) {
                $stmt = $pdo->prepare("INSERT INTO friends (player1_id, player2_id, status) VALUES (:p1, :p2, 0)");
                $stmt->execute([':p1' => $data['player1_id'], ':p2' => $data['player2_id']]);
                echo json_encode(['success' => true, 'message' => 'Solicitud de amistad enviada.']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'IDs de los jugadores son obligatorios.']);
            }
            break;
        case 'PUT':
            if (!empty($data['player1_id']) && !empty($data['player2_id']) && isset($data['status'])) {
                $stmt = $pdo->prepare("UPDATE friends SET status = :status WHERE (player1_id = :p1 AND player2_id = :p2) OR (player1_id = :p2 AND player2_id = :p1)");
                $result = $stmt->execute([':status' => $data['status'], ':p1' => $data['player1_id'], ':p2' => $data['player2_id']]);
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Estado de amistad actualizado.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Relación de amistad no encontrada.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'IDs y estado son obligatorios para actualizar.']);
            }
            break;
        case 'DELETE':
            if (!empty($data['player1_id']) && !empty($data['player2_id'])) {
                $stmt = $pdo->prepare("DELETE FROM friends WHERE (player1_id = :p1 AND player2_id = :p2) OR (player1_id = :p2 AND player2_id = :p1)");
                $result = $stmt->execute([':p1' => $data['player1_id'], ':p2' => $data['player2_id']]);
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Relación de amistad eliminada.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Relación de amistad no encontrada.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'IDs de los jugadores son obligatorios para eliminar.']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido para friends.']);
            break;
    }
}

function handleChatsRequest($method, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if (!empty($_GET['player_id'])) {
                $stmt = $pdo->prepare("SELECT DISTINCT c.* FROM chat c JOIN messages m ON c.chat_id = m.chat_id WHERE m.sender_id = :player_id");
                $stmt->execute([':player_id' => $_GET['player_id']]);
                $chats = $stmt->fetchAll();
                echo json_encode($chats);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID del jugador es obligatorio para listar chats.']);
            }
            break;
        case 'POST':
            $is_group = $data['is_group'] ?? 0;
            $stmt = $pdo->prepare("INSERT INTO chat (is_group) VALUES (:is_group)");
            $stmt->execute([':is_group' => $is_group]);
            echo json_encode(['success' => true, 'message' => 'Chat creado.', 'chat_id' => $pdo->lastInsertId()]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido para chats.']);
            break;
    }
}

function handleMessagesRequest($method, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if (!empty($_GET['chat_id'])) {
                $stmt = $pdo->prepare("SELECT * FROM messages WHERE chat_id = :chat_id ORDER BY timestamp ASC");
                $stmt->execute([':chat_id' => $_GET['chat_id']]);
                $messages = $stmt->fetchAll();
                echo json_encode($messages);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID del chat es obligatorio para listar mensajes.']);
            }
            break;
        case 'POST':
            if (!empty($data['chat_id']) && !empty($data['sender_id']) && !empty($data['content'])) {
                $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, content) VALUES (:chat_id, :sender_id, :content)");
                $stmt->execute([
                    ':chat_id' => $data['chat_id'],
                    ':sender_id' => $data['sender_id'],
                    ':content' => $data['content']
                ]);
                echo json_encode(['success' => true, 'message' => 'Mensaje enviado.', 'message_id' => $pdo->lastInsertId()]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos incompletos para enviar mensaje.']);
            }
            break;
        case 'DELETE':
            if (!empty($data['message_id'])) {
                $stmt = $pdo->prepare("DELETE FROM messages WHERE message_id = :message_id");
                $result = $stmt->execute([':message_id' => $data['message_id']]);
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Mensaje eliminado.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Mensaje no encontrado.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID del mensaje es obligatorio para eliminar.']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido para messages.']);
            break;
    }
}
?>
