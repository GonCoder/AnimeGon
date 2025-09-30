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

try {
    $conexion = obtenerConexion();
    
    // Habilitar modo de error para debugging
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener información del usuario para incluir en la exportación
    $query_usuario = "SELECT nombre FROM usuarios WHERE id = ?";
    $stmt_usuario = $conexion->prepare($query_usuario);
    $stmt_usuario->execute([$usuario_id]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    // Obtener todos los animes del usuario con información completa
    $query = "SELECT 
                a.titulo,
                a.titulo_original,
                a.titulo_ingles,
                a.sinopsis,
                a.tipo,
                a.estado as estado_anime,
                a.episodios_total,
                a.episodios_emitidos,
                a.duracion_episodio,
                a.fecha_inicio,
                a.fecha_fin,
                a.temporada,
                a.año,
                a.clasificacion,
                a.puntuacion_promedio,
                a.total_votos,
                a.popularidad,
                a.imagen_portada,
                a.trailer_url,
                a.sitio_oficial,
                a.estudio_id,
                lu.episodios_vistos,
                lu.animeflv_url_name,
                lu.estado as mi_estado,
                lu.puntuacion as mi_puntuacion,
                lu.fecha_agregado,
                (CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END) as es_favorito,
                GROUP_CONCAT(g.nombre SEPARATOR ', ') as generos
              FROM lista_usuario lu 
              LEFT JOIN animes a ON lu.anime_id = a.id 
              LEFT JOIN favoritos f ON lu.usuario_id = f.usuario_id AND lu.anime_id = f.anime_id
              LEFT JOIN anime_generos ag ON a.id = ag.anime_id
              LEFT JOIN generos g ON ag.genero_id = g.id
              WHERE lu.usuario_id = ? 
              GROUP BY lu.id
              ORDER BY lu.fecha_agregado DESC";
    
    $stmt = $conexion->prepare($query);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . implode(', ', $conexion->errorInfo()));
    }
    
    $result = $stmt->execute([$usuario_id]);
    if (!$result) {
        throw new Exception('Error al ejecutar la consulta: ' . implode(', ', $stmt->errorInfo()));
    }
    
    $animes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para debugging (remover en producción)
    error_log("Exportar lista - Usuario ID: $usuario_id, Animes encontrados: " . count($animes));
    
    // Limpiar datos para evitar problemas de encoding
    foreach ($animes as &$anime) {
        foreach ($anime as $key => &$value) {
            if (is_string($value)) {
                // Limpiar caracteres problemáticos y asegurar UTF-8
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
            }
        }
    }
    unset($anime, $value); // Limpiar referencias
    
    // Preparar datos para exportación
    $datos_exportacion = [
        'info' => [
            'exportado_por' => $usuario['nombre'] ?? 'Usuario AnimeGon',
            'fecha_exportacion' => date('Y-m-d H:i:s'),
            'total_animes' => count($animes),
            'version_formato' => '2.0',
            'aplicacion' => 'AnimeGon',
            'campos_incluidos' => [
                'informacion_basica' => ['titulo', 'titulo_original', 'titulo_ingles', 'sinopsis'],
                'detalles_anime' => ['tipo', 'estado', 'episodios', 'duracion', 'fechas', 'temporada', 'año', 'clasificacion'],
                'puntuaciones' => ['puntuacion_promedio', 'total_votos', 'mi_puntuacion'],
                'seguimiento_personal' => ['episodios_vistos', 'mi_estado', 'es_favorito', 'fecha_agregado'],
                'multimedia' => ['imagen_portada', 'trailer_url', 'sitio_oficial', 'animeflv_url_name'],
                'taxonomia' => ['generos', 'estudio_id']
            ]
        ],
        'animes' => $animes
    ];
    
    // Generar nombre de archivo
    $fecha = date('Y-m-d_H-i-s');
    $nombre_usuario = preg_replace('/[^a-zA-Z0-9_-]/', '', $usuario['nombre'] ?? 'usuario');
    $nombre_archivo = "AnimeGon_Lista_{$nombre_usuario}_{$fecha}";
    
    // Determinar formato de exportación (por defecto JSON)
    $formato = $_GET['formato'] ?? 'json';
    
    if ($formato === 'txt') {
        // Exportar como texto legible
        $contenido_txt = "=== LISTA DE ANIMES - ANIMEGON ===\n";
        $contenido_txt .= "Exportado por: " . ($usuario['nombre'] ?? 'Usuario') . "\n";
        $contenido_txt .= "Fecha: " . date('d/m/Y H:i:s') . "\n";
        $contenido_txt .= "Total de animes: " . count($animes) . "\n";
        $contenido_txt .= str_repeat("=", 50) . "\n\n";
        
        foreach ($animes as $anime) {
            $contenido_txt .= "📺 " . ($anime['titulo'] ?? 'Sin título') . "\n";
            
            if (!empty($anime['titulo_original'])) {
                $contenido_txt .= "   🇯🇵 Título original: " . $anime['titulo_original'] . "\n";
            }
            if (!empty($anime['titulo_ingles'])) {
                $contenido_txt .= "   🇺🇸 Título inglés: " . $anime['titulo_ingles'] . "\n";
            }
            
            if (!empty($anime['sinopsis'])) {
                $contenido_txt .= "   📖 Sinopsis: " . substr($anime['sinopsis'], 0, 150) . "...\n";
            }
            
            $contenido_txt .= "   🎬 Tipo: " . ($anime['tipo'] ?? 'N/A') . "\n";
            $contenido_txt .= "   📊 Estado del anime: " . ($anime['estado_anime'] ?? 'N/A') . "\n";
            $contenido_txt .= "   🎯 Mi estado: " . ($anime['mi_estado'] ?? 'N/A') . "\n";
            $contenido_txt .= "   📺 Progreso: " . ($anime['episodios_vistos'] ?? 0) . "/" . ($anime['episodios_total'] ?? '?') . " episodios\n";
            
            if (!empty($anime['año'])) {
                $contenido_txt .= "   📅 Año: " . $anime['año'] . "\n";
            }
            
            if (!empty($anime['temporada'])) {
                $contenido_txt .= "   🌸 Temporada: " . $anime['temporada'] . "\n";
            }
            
            if (!empty($anime['generos'])) {
                $contenido_txt .= "   🏷️ Géneros: " . $anime['generos'] . "\n";
            }
            
            if (!empty($anime['mi_puntuacion'])) {
                $contenido_txt .= "   ⭐ Mi puntuación: " . $anime['mi_puntuacion'] . "/10\n";
            }
            
            if (!empty($anime['puntuacion_promedio'])) {
                $contenido_txt .= "   📊 Puntuación promedio: " . $anime['puntuacion_promedio'] . "/10 (" . ($anime['total_votos'] ?? 0) . " votos)\n";
            }
            
            if (!empty($anime['duracion_episodio'])) {
                $contenido_txt .= "   ⏱️ Duración episodio: " . $anime['duracion_episodio'] . " min\n";
            }
            
            $contenido_txt .= "   📅 Agregado: " . date('d/m/Y', strtotime($anime['fecha_agregado'])) . "\n";
            
            if ($anime['es_favorito']) {
                $contenido_txt .= "   💖 FAVORITO\n";
            }
            
            if (!empty($anime['imagen_portada'])) {
                $contenido_txt .= "   🖼️ Imagen: " . $anime['imagen_portada'] . "\n";
            }
            
            if (!empty($anime['trailer_url'])) {
                $contenido_txt .= "   🎥 Trailer: " . $anime['trailer_url'] . "\n";
            }
            
            if (!empty($anime['sitio_oficial'])) {
                $contenido_txt .= "   🌐 Sitio oficial: " . $anime['sitio_oficial'] . "\n";
            }
            
            if (!empty($anime['animeflv_url_name'])) {
                $contenido_txt .= "   📺 AnimeFLV: https://animeflv.net/anime/" . $anime['animeflv_url_name'] . "\n";
            }
            
            $contenido_txt .= "\n" . str_repeat("-", 30) . "\n\n";
        }
        
        $contenido_txt .= "\n=== FIN DE LA LISTA ===\n";
        $contenido_txt .= "Generado por AnimeGon - Tu plataforma de seguimiento de anime";
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.txt"');
        echo $contenido_txt;
        
    } else {
        // Exportar como JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.json"');
        
        // Verificar si json_encode falla
        $json_output = json_encode($datos_exportacion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json_output === false) {
            throw new Exception('Error al generar JSON: ' . json_last_error_msg());
        }
        
        echo $json_output;
    }
    
} catch (Exception $e) {
    // Log del error completo para debugging
    error_log("Error en exportar_lista.php: " . $e->getMessage() . " en línea " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Error al exportar la lista: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>