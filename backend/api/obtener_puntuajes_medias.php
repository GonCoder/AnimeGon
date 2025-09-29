<?php
header('Content-Type: application/json');
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit();
}

try {
    $conexion = obtenerConexion();
    // Reutiliza lógica del hub: promedio y total valoraciones por anime activo
    $sql = "SELECT a.id, a.titulo, 
                   ROUND(AVG(NULLIF(lu.puntuacion,0)),1) AS media,
                   COUNT(CASE WHEN lu.puntuacion > 0 THEN 1 END) AS total_valoraciones
            FROM animes a
            INNER JOIN lista_usuario lu ON a.id = lu.anime_id
            WHERE a.activo = 1
            GROUP BY a.id, a.titulo
            ORDER BY media DESC NULLS LAST, total_valoraciones DESC, a.titulo ASC";

    $stmt = $conexion->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'animes' => $data]);
} catch (Exception $e) {
    error_log('Error obtener_puntuajes_medias: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>