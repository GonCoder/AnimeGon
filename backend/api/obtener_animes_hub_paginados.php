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
    $limite = isset($_GET['limite']) ? max(1, min(50, intval($_GET['limite']))) : 12; // Máximo 50, por defecto 12
    $offset = ($pagina - 1) * $limite;
    
    // Obtener parámetros de filtros
    $filtro_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
    $mostrar_solo_disponibles = isset($_GET['solo_disponibles']) && $_GET['solo_disponibles'] === 'true';
    $mostrar_solo_tengo = isset($_GET['solo_tengo']) && $_GET['solo_tengo'] === 'true';
    
    $conexion = obtenerConexion();
    
    // Construir consulta base para hub
    $query_base = "FROM animes a
                   -- Obtener el primer usuario que agregó este anime
                   LEFT JOIN (
                       SELECT anime_id, 
                              MIN(usuario_id) as primer_usuario_id,
                              MIN(fecha_agregado) as primera_fecha
                       FROM lista_usuario 
                       GROUP BY anime_id
                   ) primer_registro ON a.id = primer_registro.anime_id
                   LEFT JOIN usuarios primer_usuario ON primer_registro.primer_usuario_id = primer_usuario.id
                   -- Estadísticas del anime
                   LEFT JOIN (
                       SELECT anime_id,
                              COUNT(DISTINCT usuario_id) as usuarios_que_lo_tienen,
                              ROUND(AVG(CASE WHEN puntuacion IS NOT NULL AND puntuacion > 0 THEN puntuacion END), 1) as puntuacion_promedio,
                              COUNT(CASE WHEN puntuacion IS NOT NULL AND puntuacion > 0 THEN 1 END) as total_valoraciones
                       FROM lista_usuario 
                       GROUP BY anime_id
                   ) stats ON a.id = stats.anime_id
                   -- Verificar si el usuario actual ya tiene este anime
                   LEFT JOIN lista_usuario usuario_actual ON a.id = usuario_actual.anime_id AND usuario_actual.usuario_id = ?
                   WHERE 1=1";
    
    $parametros = [$usuario_id];
    
    // Aplicar filtros
    if (!empty($filtro_busqueda)) {
        $query_base .= " AND (a.titulo LIKE ? OR a.titulo_original LIKE ? OR a.titulo_ingles LIKE ?)";
        $busqueda_param = '%' . $filtro_busqueda . '%';
        $parametros[] = $busqueda_param;
        $parametros[] = $busqueda_param;
        $parametros[] = $busqueda_param;
    }
    
    if ($mostrar_solo_disponibles) {
        $query_base .= " AND usuario_actual.anime_id IS NULL";
    }
    
    if ($mostrar_solo_tengo) {
        $query_base .= " AND usuario_actual.anime_id IS NOT NULL";
    }
    
    // Obtener total de registros para paginación
    $query_count = "SELECT COUNT(DISTINCT a.id) as total " . $query_base;
    $stmt_count = $conexion->prepare($query_count);
    $stmt_count->execute($parametros);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limite);
    
    // Obtener animes paginados
    $query_animes = "SELECT DISTINCT a.*, 
                            COALESCE(primer_usuario.username, 'Sistema') as subido_por,
                            COALESCE(stats.usuarios_que_lo_tienen, 0) as usuarios_que_lo_tienen,
                            COALESCE(stats.puntuacion_promedio, 0) as puntuacion_promedio,
                            COALESCE(stats.total_valoraciones, 0) as total_valoraciones,
                            CASE WHEN usuario_actual.anime_id IS NOT NULL THEN 1 ELSE 0 END as ya_lo_tiene,
                            usuario_actual.estado as mi_estado,
                            usuario_actual.episodios_vistos as mis_episodios_vistos,
                            usuario_actual.puntuacion as mi_puntuacion
                     " . $query_base . "
                     ORDER BY 
                         CASE WHEN usuario_actual.anime_id IS NULL THEN 0 ELSE 1 END, -- Primero los que no tiene
                         stats.usuarios_que_lo_tienen DESC, 
                         stats.puntuacion_promedio DESC, 
                         a.titulo ASC
                     LIMIT ? OFFSET ?";
    
    $parametros[] = $limite;
    $parametros[] = $offset;
    
    $stmt_animes = $conexion->prepare($query_animes);
    $stmt_animes->execute($parametros);
    $animes = $stmt_animes->fetchAll(PDO::FETCH_ASSOC);
    
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
        'error' => 'Error al obtener animes del hub: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>