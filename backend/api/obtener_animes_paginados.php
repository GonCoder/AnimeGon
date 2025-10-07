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
    $filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
    $solo_viendo = isset($_GET['solo_viendo']) && $_GET['solo_viendo'] === 'true';
    $solo_favoritos = isset($_GET['solo_favoritos']) && $_GET['solo_favoritos'] === 'true';
    
    $conexion = obtenerConexion();
    
    // Construir consulta base
    $query_base = "FROM lista_usuario lu 
                   LEFT JOIN animes a ON lu.anime_id = a.id 
                   LEFT JOIN favoritos f ON lu.usuario_id = f.usuario_id AND lu.anime_id = f.anime_id
                   WHERE lu.usuario_id = ?";
    
    $parametros = [$usuario_id];
    
    // Aplicar filtros
    if (!empty($filtro_busqueda)) {
        $query_base .= " AND (a.titulo LIKE ? OR a.titulo_original LIKE ? OR a.titulo_ingles LIKE ?)";
        $busqueda_param = '%' . $filtro_busqueda . '%';
        $parametros[] = $busqueda_param;
        $parametros[] = $busqueda_param;
        $parametros[] = $busqueda_param;
    }
    
    if (!empty($filtro_estado)) {
        $query_base .= " AND lu.estado = ?";
        $parametros[] = $filtro_estado;
    }
    
    if ($solo_viendo) {
        $query_base .= " AND lu.estado = 'Viendo'";
    }
    
    if ($solo_favoritos) {
        $query_base .= " AND f.id IS NOT NULL";
    }
    
    // Obtener total de registros para paginación
    $query_count = "SELECT COUNT(*) as total " . $query_base;
    $stmt_count = $conexion->prepare($query_count);
    $stmt_count->execute($parametros);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limite);
    
    // Obtener animes paginados
    $query_animes = "SELECT lu.*, a.titulo as anime_nombre, a.titulo_original, a.titulo_ingles, 
                            a.imagen_portada, a.episodios_total, lu.episodios_vistos, lu.fecha_agregado, 
                            lu.estado, lu.puntuacion, a.animeflv_url_name, a.id as anime_id,
                            a.tipo, a.estado as estado_anime,
                            (CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END) as favorito
                     " . $query_base . "
                     ORDER BY a.titulo ASC
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
        'error' => 'Error al obtener animes: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>