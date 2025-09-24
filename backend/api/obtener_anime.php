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

$usuario_id = $_SESSION['usuario_id'];

// Verificar que se envió el ID del anime
if (!isset($_GET['anime_id']) || empty($_GET['anime_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de anime requerido']);
    exit();
}

$anime_id = intval($_GET['anime_id']);

try {
    $conexion = obtenerConexion();
    
    // Obtener datos del anime y de la lista del usuario
    $query = "SELECT a.id, a.titulo, a.titulo_original, a.titulo_ingles, a.episodios_total, a.imagen_portada, a.tipo, 
                     a.estado as anime_estado, lu.episodios_vistos, lu.estado as lista_estado, lu.puntuacion,
                     (CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END) as favorito
              FROM animes a
              INNER JOIN lista_usuario lu ON a.id = lu.anime_id
              LEFT JOIN favoritos f ON lu.usuario_id = f.usuario_id AND lu.anime_id = f.anime_id
              WHERE a.id = ? AND lu.usuario_id = ?";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute([$anime_id, $usuario_id]);
    $anime = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anime) {
        http_response_code(404);
        echo json_encode(['error' => 'Anime no encontrado']);
        exit();
    }
    
    // Devolver datos del anime
    echo json_encode([
        'exito' => true,
        'anime' => $anime
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>