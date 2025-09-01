<?php
// Configuración de la cabecera para permitir la comunicación con el frontend
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// La URL del archivo de la base de datos
//$db_path = '../transcendence.db';
$db_path = '../data/transcendence.db';

// El código siguiente solo se ejecuta si la petición es un OPTIONS,
// lo que se utiliza para las peticiones CORS.
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
    
    switch ($method) {
        case 'GET':
            // Lógica para obtener todos los jugadores
            $stmt = $pdo->query('SELECT player_id, alias, first_name, last_name, email FROM player');
            $players = $stmt->fetchAll();
            echo json_encode($players);
            break;

        case 'POST':
            // Lógica para añadir un nuevo jugador
            if (!empty($data['alias']) && !empty($data['nombre']) && !empty($data['apellido']) && !empty($data['email'])) {
                // Se simula un hash de contraseña ya que el formulario no la pide
                $password_hash = password_hash('default_password', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO player (alias, first_name, last_name, email, password_hash) VALUES (:alias, :first_name, :last_name, :email, :password_hash)");
                $stmt->execute([
                    ':alias' => $data['alias'],
                    ':first_name' => $data['nombre'],
                    ':last_name' => $data['apellido'],
                    ':email' => $data['email'],
                    ':password_hash' => $password_hash
                ]);
                echo json_encode(['success' => true, 'message' => 'Jugador añadido correctamente.']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            }
            break;

        case 'PUT':
            // Lógica para actualizar un jugador
            if (!empty($data['id']) && !empty($data['alias'])) {
                $stmt = $pdo->prepare("UPDATE player SET alias = :alias WHERE player_id = :id");
                $result = $stmt->execute([':alias' => $data['alias'], ':id' => $data['id']]);

                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Jugador actualizado correctamente.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Jugador no encontrado o alias no cambiado.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID y nuevo alias son obligatorios para actualizar.']);
            }
            break;

        case 'DELETE':
            // Lógica para eliminar un jugador
            if (!empty($data['id'])) {
                $stmt = $pdo->prepare("DELETE FROM player WHERE player_id = :id");
                $result = $stmt->execute([':id' => $data['id']]);

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
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
