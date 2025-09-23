<?php
session_start();
require_once '../config/config.php';
require_once '../config/funciones.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Solo permitir POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['anime_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de anime requerido']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$anime_id = intval($input['anime_id']);

try {
    $conexion = obtenerConexion();
    
    // Verificar que el anime existe en la lista del usuario
    $query_verificar = "SELECT id FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
    $stmt_verificar = $conexion->prepare($query_verificar);
    $stmt_verificar->execute([$usuario_id, $anime_id]);
    
    if (!$stmt_verificar->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Anime no encontrado en tu lista']);
        exit();
    }
    
    // Verificar si ya está en favoritos
    $query_favorito = "SELECT id FROM favoritos WHERE usuario_id = ? AND anime_id = ?";
    $stmt_favorito = $conexion->prepare($query_favorito);
    $stmt_favorito->execute([$usuario_id, $anime_id]);
    $es_favorito = $stmt_favorito->fetch();
    
    if ($es_favorito) {
        // Quitar de favoritos
        $query_delete = "DELETE FROM favoritos WHERE usuario_id = ? AND anime_id = ?";
        $stmt_delete = $conexion->prepare($query_delete);
        $stmt_delete->execute([$usuario_id, $anime_id]);
        
        echo json_encode([
            'success' => true,
            'favorito' => false,
            'mensaje' => 'Quitado de favoritos'
        ]);
    } else {
        // Agregar a favoritos
        $query_insert = "INSERT INTO favoritos (usuario_id, anime_id) VALUES (?, ?)";
        $stmt_insert = $conexion->prepare($query_insert);
        $stmt_insert->execute([$usuario_id, $anime_id]);
        
        echo json_encode([
            'success' => true,
            'favorito' => true,
            'mensaje' => 'Agregado a favoritos'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>