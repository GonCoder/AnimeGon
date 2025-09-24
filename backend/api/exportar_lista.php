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
                a.tipo,
                a.estado as estado_anime,
                a.episodios_total,
                a.imagen_portada,
                lu.episodios_vistos,
                lu.estado as mi_estado,
                lu.puntuacion,
                lu.fecha_agregado,
                (CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END) as es_favorito
              FROM lista_usuario lu 
              LEFT JOIN animes a ON lu.anime_id = a.id 
              LEFT JOIN favoritos f ON lu.usuario_id = f.usuario_id AND lu.anime_id = f.anime_id
              WHERE lu.usuario_id = ? 
              ORDER BY lu.fecha_agregado DESC";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute([$usuario_id]);
    $animes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar datos para exportación
    $datos_exportacion = [
        'info' => [
            'exportado_por' => $usuario['nombre'] ?? 'Usuario AnimeGon',
            'fecha_exportacion' => date('Y-m-d H:i:s'),
            'total_animes' => count($animes),
            'version_formato' => '1.0',
            'aplicacion' => 'AnimeGon'
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
            
            $contenido_txt .= "   🎬 Tipo: " . ($anime['tipo'] ?? 'N/A') . "\n";
            $contenido_txt .= "   📊 Estado del anime: " . ($anime['estado_anime'] ?? 'N/A') . "\n";
            $contenido_txt .= "   🎯 Mi estado: " . ($anime['mi_estado'] ?? 'N/A') . "\n";
            $contenido_txt .= "   📺 Progreso: " . ($anime['episodios_vistos'] ?? 0) . "/" . ($anime['episodios_total'] ?? '?') . " episodios\n";
            
            if (!empty($anime['puntuacion'])) {
                $contenido_txt .= "   ⭐ Mi puntuación: " . $anime['puntuacion'] . "/10\n";
            }
            
            $contenido_txt .= "   📅 Agregado: " . date('d/m/Y', strtotime($anime['fecha_agregado'])) . "\n";
            
            if ($anime['es_favorito']) {
                $contenido_txt .= "   ⭐ FAVORITO\n";
            }
            
            if (!empty($anime['imagen_portada'])) {
                $contenido_txt .= "   🖼️ Imagen: " . $anime['imagen_portada'] . "\n";
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
        echo json_encode($datos_exportacion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al exportar la lista: ' . $e->getMessage()]);
}
?>