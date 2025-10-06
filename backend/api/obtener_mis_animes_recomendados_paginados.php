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
    
    // Consulta para obtener animes del usuario que puede recomendar
    $query = "SELECT 
                lu.id as user_anime_id,
                lu.anime_id,
                a.titulo,
                a.titulo_original,
                a.titulo_ingles,
                a.tipo,
                a.episodios_total,
                a.imagen_portada,
                lu.episodios_vistos,
                lu.puntuacion,
                lu.estado
              FROM lista_usuario lu 
              INNER JOIN animes a ON lu.anime_id = a.id 
              WHERE lu.usuario_id = ?
              ORDER BY a.titulo ASC
              LIMIT ? OFFSET ?";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute([$usuario_id, $limite, $offset]);
    $animes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener total de registros para paginación
    $query_count = "SELECT COUNT(*) as total 
                    FROM lista_usuario lu 
                    INNER JOIN animes a ON lu.anime_id = a.id 
                    WHERE lu.usuario_id = ?";
    
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
        'error' => 'Error al obtener animes para recomendar: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>