<?php
session_start();
require_once '../config/config.php';
require_once '../config/funciones.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit();
}

// Configurar respuesta JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $usuario_id = $_SESSION['usuario_id'];
    
    // Obtener parámetros de paginación
    $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $limite = isset($_GET['limite']) ? max(1, min(20, intval($_GET['limite']))) : 6;
    $offset = ($pagina - 1) * $limite;
    
    $conexion = obtenerConexion();
    
    // Consulta para obtener recomendaciones recibidas
    $query = "SELECT 
                r.id,
                r.anime_id,
                r.usuario_emisor_id,
                r.usuario_receptor_id,
                r.mensaje_recomendacion as mensaje,
                r.valoracion_recomendacion,
                r.leido as estado,
                r.fecha_creacion,
                a.titulo,
                a.titulo_original,
                a.titulo_ingles,  
                a.tipo,
                a.episodios_total,
                a.imagen_portada,
                a.sinopsis as descripcion,
                a.puntuacion_promedio as puntuacion_media,
                u.nombre as emisor_nombre,
                u.username as emisor_username,
                CASE WHEN lu.id IS NOT NULL THEN 1 ELSE 0 END as ya_en_lista
              FROM recomendaciones r
              INNER JOIN animes a ON r.anime_id = a.id
              INNER JOIN usuarios u ON r.usuario_emisor_id = u.id
              LEFT JOIN lista_usuario lu ON lu.usuario_id = ? AND lu.anime_id = r.anime_id
              WHERE r.usuario_receptor_id = ?
              ORDER BY a.titulo ASC
              LIMIT ? OFFSET ?";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute([$usuario_id, $usuario_id, $limite, $offset]);
    $recomendaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener total de registros para paginación
    $query_count = "SELECT COUNT(*) as total 
                    FROM recomendaciones r
                    WHERE r.usuario_receptor_id = ?";
    
    $stmt_count = $conexion->prepare($query_count);
    $stmt_count->execute([$usuario_id]);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limite);
    
    // Preparar respuesta
    $respuesta = [
        'success' => true,
        'recomendaciones' => $recomendaciones,
        'paginacion' => [
            'pagina_actual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_registros' => $total_registros,
            'limite' => $limite,
            'hay_mas' => $pagina < $total_paginas
        ]
    ];
    
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener recomendaciones: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>