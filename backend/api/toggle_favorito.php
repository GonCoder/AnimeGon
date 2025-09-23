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
    
    // Verificar que el anime pertenece al usuario
    $query_verificar = "SELECT favorito FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
    $stmt_verificar = $conexion->prepare($query_verificar);
    $stmt_verificar->execute([$usuario_id, $anime_id]);
    $resultado = $stmt_verificar->fetch();
    
    if (!$resultado) {
        http_response_code(404);
        echo json_encode(['error' => 'Anime no encontrado en tu lista']);
        exit();
    }
    
    // Alternar el estado de favorito
    $nuevo_favorito = !$resultado['favorito'];
    
    $query_update = "UPDATE lista_usuario SET favorito = ? WHERE usuario_id = ? AND anime_id = ?";
    $stmt_update = $conexion->prepare($query_update);
    $stmt_update->execute([$nuevo_favorito, $usuario_id, $anime_id]);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'favorito' => $nuevo_favorito,
        'mensaje' => $nuevo_favorito ? 'Agregado a favoritos' : 'Removido de favoritos'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>