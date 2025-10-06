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
    $limite = isset($_GET['limite']) ? max(1, min(20, intval($_GET['limite']))) : 12;
    $offset = ($pagina - 1) * $limite;
    
    $conexion = obtenerConexion();
    
    // Consulta para obtener animes favoritos del usuario
    $query = "SELECT 
                lu.id,
                lu.anime_id,
                lu.episodios_vistos,
                lu.fecha_agregado,
                lu.estado,
                lu.puntuacion,
                a.titulo as anime_nombre,
                a.titulo_original,
                a.titulo_ingles,
                a.imagen_portada,
                a.episodios_total,
                a.tipo,
                a.estado as estado_anime,
                1 as favorito
              FROM favoritos f
              INNER JOIN lista_usuario lu ON f.usuario_id = lu.usuario_id AND f.anime_id = lu.anime_id
              LEFT JOIN animes a ON f.anime_id = a.id 
              WHERE f.usuario_id = ?
              ORDER BY f.fecha_agregado DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute([$usuario_id, $limite, $offset]);
    $animes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener total de registros para paginación
    $query_count = "SELECT COUNT(*) as total 
                    FROM favoritos f
                    WHERE f.usuario_id = ?";
    
    $stmt_count = $conexion->prepare($query_count);
    $stmt_count->execute([$usuario_id]);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limite);
    
    // Preparar respuesta
    $respuesta = [
        'success' => true,
        'animes' => $animes,
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
        'error' => 'Error al obtener animes favoritos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>