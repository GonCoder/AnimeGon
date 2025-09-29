<?php
header('Content-Type: application/json');
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit();
}

if (!isset($_GET['anime_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de anime requerido']);
    exit();
}

$anime_id = (int)$_GET['anime_id'];

try {
    $conexion = obtenerConexion();
    
    // Obtener información del anime
    $query_anime = "SELECT titulo FROM animes WHERE id = :anime_id";
    $stmt_anime = $conexion->prepare($query_anime);
    $stmt_anime->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
    $stmt_anime->execute();
    $anime = $stmt_anime->fetch(PDO::FETCH_ASSOC);
    
    if (!$anime) {
        http_response_code(404);
        echo json_encode(['error' => 'Anime no encontrado']);
        exit();
    }
    
    // Obtener puntuajes individuales
    $query_puntuajes = "SELECT u.username, lu.puntuacion, lu.fecha_actualizacion
                        FROM lista_usuario lu
                        INNER JOIN usuarios u ON lu.usuario_id = u.id
                        WHERE lu.anime_id = :anime_id AND lu.puntuacion > 0
                        ORDER BY lu.puntuacion DESC, u.username ASC";
    
    $stmt_puntuajes = $conexion->prepare($query_puntuajes);
    $stmt_puntuajes->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
    $stmt_puntuajes->execute();
    $puntuajes = $stmt_puntuajes->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener puntuación del usuario actual
    $query_usuario = "SELECT puntuacion FROM lista_usuario WHERE anime_id = :anime_id AND usuario_id = :usuario_id";
    $stmt_usuario = $conexion->prepare($query_usuario);
    $stmt_usuario->bindParam(':anime_id', $anime_id, PDO::PARAM_INT);
    $stmt_usuario->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_usuario->execute();
    $puntuacion_usuario = $stmt_usuario->fetchColumn();
    
    // Calcular estadísticas
    $total_valoraciones = count($puntuajes);
    $suma_puntuajes = array_sum(array_column($puntuajes, 'puntuacion'));
    $promedio = $total_valoraciones > 0 ? round($suma_puntuajes / $total_valoraciones, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'anime' => $anime,
        'puntuajes' => $puntuajes,
        'puntuacion_usuario' => $puntuacion_usuario ? (int)$puntuacion_usuario : null,
        'estadisticas' => [
            'total_valoraciones' => $total_valoraciones,
            'promedio' => $promedio,
            'puntuacion_maxima' => $total_valoraciones > 0 ? max(array_column($puntuajes, 'puntuacion')) : 0,
            'puntuacion_minima' => $total_valoraciones > 0 ? min(array_column($puntuajes, 'puntuacion')) : 0
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error al obtener puntuajes: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>