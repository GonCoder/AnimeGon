<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

try {
    $pdo = obtenerConexion();
    
    // Obtener recomendaciones recibidas
    $stmt = $pdo->prepare("
        SELECT r.*, 
               a.titulo, a.titulo_original, a.titulo_ingles, a.imagen_portada,
               a.tipo, a.episodios_total, a.sinopsis,
               u.username as emisor_username, u.nombre as emisor_nombre, u.apellidos as emisor_apellidos
        FROM recomendaciones r
        INNER JOIN animes a ON r.anime_id = a.id
        INNER JOIN usuarios u ON r.usuario_emisor_id = u.id
        WHERE r.usuario_receptor_id = ?
        ORDER BY r.fecha_creacion DESC
    ");
    $stmt->execute([$usuario_id]);
    $recomendaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_recibidas,
            COUNT(CASE WHEN leido = 1 THEN 1 END) as leidas,
            COUNT(CASE WHEN leido = 0 THEN 1 END) as no_leidas
        FROM recomendaciones 
        WHERE usuario_receptor_id = ?
    ");
    $stmt_stats->execute([$usuario_id]);
    $estadisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Obtener recomendaciones enviadas (para futuras funcionalidades)
    $stmt_enviadas = $pdo->prepare("
        SELECT r.*, 
               a.titulo, a.imagen_portada,
               u.username as receptor_username, u.nombre as receptor_nombre
        FROM recomendaciones r
        INNER JOIN animes a ON r.anime_id = a.id
        INNER JOIN usuarios u ON r.usuario_receptor_id = u.id
        WHERE r.usuario_emisor_id = ?
        ORDER BY r.fecha_creacion DESC
        LIMIT 10
    ");
    $stmt_enviadas->execute([$usuario_id]);
    $recomendaciones_enviadas = $stmt_enviadas->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'recomendaciones_recibidas' => $recomendaciones,
        'recomendaciones_enviadas' => $recomendaciones_enviadas,
        'estadisticas' => $estadisticas
    ]);
    
} catch (Exception $e) {
    error_log("Error en obtener_recomendaciones.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>