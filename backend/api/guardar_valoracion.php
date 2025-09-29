<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['anime_id']) || !isset($input['puntuacion'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

$anime_id = (int)$input['anime_id'];
$puntuacion = (int)$input['puntuacion'];
$user_id = $_SESSION['usuario_id'];

if ($puntuacion < 1 || $puntuacion > 10) {
    http_response_code(400);
    echo json_encode(['error' => 'La puntuación debe estar entre 1 y 10']);
    exit;
}

try {
    $conexion = obtenerConexion();
    
    // Verificar que el anime existe
    $stmt_anime = $conexion->prepare("SELECT id FROM animes WHERE id = :anime_id");
    $stmt_anime->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
    $stmt_anime->execute();
    
    if (!$stmt_anime->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Anime no encontrado']);
        exit;
    }

    // Verificar si el usuario ya tiene este anime en su lista
    $stmt_lista = $conexion->prepare("SELECT id FROM lista_usuario WHERE usuario_id = :user_id AND anime_id = :anime_id");
    $stmt_lista->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_lista->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
    $stmt_lista->execute();
    
    if ($stmt_lista->fetch()) {
        // Actualizar puntuación existente
        $stmt_update = $conexion->prepare("UPDATE lista_usuario SET puntuacion = :puntuacion, fecha_actualizacion = NOW() WHERE usuario_id = :user_id AND anime_id = :anime_id");
        $stmt_update->bindParam(':puntuacion', $puntuacion, PDO::PARAM_INT);
        $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
        $stmt_update->execute();
        
        echo json_encode(['success' => true, 'mensaje' => 'Valoración actualizada']);
    } else {
        // Agregar anime a la lista con puntuación
        $stmt_insert = $conexion->prepare("INSERT INTO lista_usuario (usuario_id, anime_id, estado, puntuacion, fecha_agregado, fecha_actualizacion) VALUES (:user_id, :anime_id, 'Completado', :puntuacion, NOW(), NOW())");
        $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':puntuacion', $puntuacion, PDO::PARAM_INT);
        $stmt_insert->execute();
        
        echo json_encode(['success' => true, 'mensaje' => 'Anime agregado a tu lista con valoración']);
    }
    
} catch (PDOException $e) {
    error_log('Error guardar_valoracion: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>