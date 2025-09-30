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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Función para manejar géneros del anime
function manejarGeneros($conexion, $anime_id, $generos_string) {
    if (empty($generos_string)) return;
    
    $generos_nombres = array_map('trim', explode(',', $generos_string));
    
    foreach ($generos_nombres as $genero_nombre) {
        if (empty($genero_nombre)) continue;
        
        // Buscar o crear género
        $query_genero = "SELECT id FROM generos WHERE nombre = ?";
        $stmt_genero = $conexion->prepare($query_genero);
        $stmt_genero->execute([$genero_nombre]);
        $genero = $stmt_genero->fetch();
        
        if (!$genero) {
            // Crear género si no existe
            $query_crear_genero = "INSERT INTO generos (nombre) VALUES (?)";
            $stmt_crear_genero = $conexion->prepare($query_crear_genero);
            $stmt_crear_genero->execute([$genero_nombre]);
            $genero_id = $conexion->lastInsertId();
        } else {
            $genero_id = $genero['id'];
        }
        
        // Asociar género con anime
        $query_asociar = "INSERT IGNORE INTO anime_generos (anime_id, genero_id) VALUES (?, ?)";
        $stmt_asociar = $conexion->prepare($query_asociar);
        $stmt_asociar->execute([$anime_id, $genero_id]);
    }
}

// Función para buscar o crear anime
function buscarOCrearAnime($conexion, $anime_data) {
    // Buscar anime existente por título (solo si hay título)
    $titulo = $anime_data['titulo'] ?? '';
    if (empty($titulo)) {
        $titulo = 'Anime Importado ' . date('Y-m-d H:i:s');
    }
    
    $query_buscar = "SELECT id FROM animes WHERE titulo = ? OR (titulo_original IS NOT NULL AND titulo_original = ?) OR (titulo_ingles IS NOT NULL AND titulo_ingles = ?)";
    $stmt_buscar = $conexion->prepare($query_buscar);
    $stmt_buscar->execute([
        $titulo,
        $anime_data['titulo_original'] ?? null,
        $anime_data['titulo_ingles'] ?? null
    ]);
    
    $anime_existente = $stmt_buscar->fetch(PDO::FETCH_ASSOC);
    
    if ($anime_existente) {
        $anime_id = $anime_existente['id'];
        
        // Si tenemos animeflv_url_name en los datos, actualizar el anime existente
        if (!empty($anime_data['animeflv_url_name'])) {
            $query_actualizar_anime = "UPDATE animes SET animeflv_url_name = ? WHERE id = ? AND (animeflv_url_name IS NULL OR animeflv_url_name = '')";
            $stmt_actualizar_anime = $conexion->prepare($query_actualizar_anime);
            $stmt_actualizar_anime->execute([$anime_data['animeflv_url_name'], $anime_id]);
            
            error_log("Actualizando anime existente ID $anime_id con URL: " . $anime_data['animeflv_url_name']);
        }
        
        return $anime_id;
    }
    
    // Crear nuevo anime
    $query_crear = "INSERT INTO animes (
        titulo, titulo_original, titulo_ingles, sinopsis, tipo, estado, 
        episodios_total, episodios_emitidos, duracion_episodio, fecha_inicio, 
        fecha_fin, temporada, año, clasificacion, puntuacion_promedio, 
        total_votos, popularidad, imagen_portada, trailer_url, sitio_oficial, animeflv_url_name
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_crear = $conexion->prepare($query_crear);
    $stmt_crear->execute([
        $titulo,
        $anime_data['titulo_original'] ?? null,
        $anime_data['titulo_ingles'] ?? null,
        $anime_data['sinopsis'] ?? null,
        $anime_data['tipo'] ?? 'TV',
        $anime_data['estado_anime'] ?? 'Finalizado',
        $anime_data['episodios_total'] ?? null,
        $anime_data['episodios_emitidos'] ?? null,
        $anime_data['duracion_episodio'] ?? null,
        $anime_data['fecha_inicio'] ?? null,
        $anime_data['fecha_fin'] ?? null,
        $anime_data['temporada'] ?? null,
        $anime_data['año'] ?? null,
        $anime_data['clasificacion'] ?? 'PG-13',
        $anime_data['puntuacion_promedio'] ?? 0.00,
        $anime_data['total_votos'] ?? 0,
        $anime_data['popularidad'] ?? 0,
        $anime_data['imagen_portada'] ?? null,
        $anime_data['trailer_url'] ?? null,
        $anime_data['sitio_oficial'] ?? null,
        $anime_data['animeflv_url_name'] ?? null
    ]);
    
    $anime_id = $conexion->lastInsertId();
    
    // Manejar géneros si están presentes
    if (!empty($anime_data['generos'])) {
        manejarGeneros($conexion, $anime_id, $anime_data['generos']);
    }
    
    return $anime_id;
}

// Función para agregar anime a la lista del usuario
function agregarAnimeALista($conexion, $usuario_id, $anime_id, $anime_data, $reemplazar) {
    // Verificar si ya existe en la lista del usuario
    $query_verificar = "SELECT id FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
    $stmt_verificar = $conexion->prepare($query_verificar);
    $stmt_verificar->execute([$usuario_id, $anime_id]);
    $existe = $stmt_verificar->fetch();
    
    if ($existe && !$reemplazar) {
        return 'duplicado';
    }
    
    if ($existe && $reemplazar) {
        // Actualizar existente
        $query_actualizar = "UPDATE lista_usuario 
                            SET episodios_vistos = ?, estado = ?, puntuacion = ?, fecha_agregado = ?
                            WHERE usuario_id = ? AND anime_id = ?";
        $stmt_actualizar = $conexion->prepare($query_actualizar);
        
        // Debug logging para verificar datos en actualización
        error_log("ACTUALIZAR - Lista Usuario - Anime: " . ($anime_data['titulo'] ?? 'Sin título'));
        
        $stmt_actualizar->execute([
            $anime_data['episodios_vistos'] ?? 0,
            $anime_data['mi_estado'] ?? 'Plan de Ver',
            $anime_data['mi_puntuacion'] ?: null,
            $anime_data['fecha_agregado'] ?? date('Y-m-d H:i:s'),
            $usuario_id,
            $anime_id
        ]);
        
        return 'actualizado';
    } else {
        // Crear nuevo
        $query_crear = "INSERT INTO lista_usuario (usuario_id, anime_id, episodios_vistos, estado, puntuacion, fecha_agregado)
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_crear = $conexion->prepare($query_crear);
        
        // Debug logging para verificar datos
        error_log("INSERTAR - Lista Usuario - Anime: " . ($anime_data['titulo'] ?? 'Sin título'));
        
        $parametros_insert = [
            $usuario_id,
            $anime_id,
            $anime_data['episodios_vistos'] ?? 0,
            $anime_data['mi_estado'] ?? 'Plan de Ver',
            $anime_data['mi_puntuacion'] ?: null,
            $anime_data['fecha_agregado'] ?? date('Y-m-d H:i:s')
        ];
        
        $stmt_crear->execute($parametros_insert);
        
        // Agregar a favoritos si corresponde
        if (!empty($anime_data['es_favorito']) && $anime_data['es_favorito'] == 1) {
            $query_favorito = "INSERT IGNORE INTO favoritos (usuario_id, anime_id, fecha_agregado) VALUES (?, ?, NOW())";
            $stmt_favorito = $conexion->prepare($query_favorito);
            $stmt_favorito->execute([$usuario_id, $anime_id]);
        }
        
        return 'agregado';
    }
}

try {
    header('Content-Type: application/json');
    
    // Verificar que se subió un archivo
    if (!isset($_FILES['archivo_importar']) || $_FILES['archivo_importar']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se seleccionó un archivo válido');
    }
    
    $archivo = $_FILES['archivo_importar'];
    $reemplazar_duplicados = isset($_POST['reemplazar_duplicados']) && $_POST['reemplazar_duplicados'] === 'on';
    
    // Verificar tamaño del archivo (máximo 5MB)
    if ($archivo['size'] > 5 * 1024 * 1024) {
        throw new Exception('El archivo es demasiado grande (máximo 5MB)');
    }
    
    // Leer contenido del archivo
    $contenido = file_get_contents($archivo['tmp_name']);
    if ($contenido === false) {
        throw new Exception('Error al leer el archivo');
    }
    
    $animes_datos = [];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if ($extension === 'json') {
        // Procesar archivo JSON
        $datos_json = json_decode($contenido, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('El archivo JSON no es válido');
        }
        
        if (!isset($datos_json['animes']) || !is_array($datos_json['animes'])) {
            throw new Exception('El archivo no tiene el formato de AnimeGon esperado');
        }
        
        $animes_datos = $datos_json['animes'];
        
    } elseif ($extension === 'txt') {
        throw new Exception('La importación desde archivos TXT no está soportada aún. Usa archivos JSON.');
    } else {
        throw new Exception('Formato de archivo no soportado. Use .json');
    }
    
    if (empty($animes_datos)) {
        throw new Exception('No se encontraron animes en el archivo');
    }
    
    $conexion = obtenerConexion();
    $conexion->beginTransaction();
    
    $resultados = [
        'agregados' => 0,
        'actualizados' => 0,
        'duplicados' => 0,
        'errores' => 0,
        'total_procesados' => 0
    ];
    
    foreach ($animes_datos as $anime_data) {
        try {
            $resultados['total_procesados']++;
            
            // Debug: Log de datos de anime recibidos
            error_log("Procesando anime: " . json_encode([
                'titulo' => $anime_data['titulo'] ?? 'Sin título',
                'animeflv_url_name' => $anime_data['animeflv_url_name'] ?? 'No encontrado',
                'mi_estado' => $anime_data['mi_estado'] ?? 'No encontrado',
                'episodios_vistos' => $anime_data['episodios_vistos'] ?? 'No encontrado'
            ]));
            
            // Buscar o crear el anime
            $anime_id = buscarOCrearAnime($conexion, $anime_data);
            
            // Agregar a la lista del usuario
            $resultado = agregarAnimeALista($conexion, $usuario_id, $anime_id, $anime_data, $reemplazar_duplicados);
            
            switch ($resultado) {
                case 'agregado':
                    $resultados['agregados']++;
                    break;
                case 'actualizado':
                    $resultados['actualizados']++;
                    break;
                case 'duplicado':
                    $resultados['duplicados']++;
                    break;
            }
            
        } catch (Exception $e) {
            $resultados['errores']++;
            error_log("Error importando anime: " . $e->getMessage());
        }
    }
    
    $conexion->commit();
    
    // Generar mensaje de resultado
    $mensaje = "Importación completada:\n";
    $mensaje .= "• Animes agregados: {$resultados['agregados']}\n";
    if ($resultados['actualizados'] > 0) {
        $mensaje .= "• Animes actualizados: {$resultados['actualizados']}\n";
    }
    if ($resultados['duplicados'] > 0) {
        $mensaje .= "• Animes duplicados omitidos: {$resultados['duplicados']}\n";
    }
    if ($resultados['errores'] > 0) {
        $mensaje .= "• Errores encontrados: {$resultados['errores']}\n";
    }
    
    echo json_encode([
        'exito' => true,
        'mensaje' => $mensaje,
        'resultados' => $resultados
    ]);
    
} catch (Exception $e) {
    if (isset($conexion)) {
        $conexion->rollBack();
    }
    
    echo json_encode([
        'exito' => false,
        'mensaje' => $e->getMessage()
    ]);
}
?>