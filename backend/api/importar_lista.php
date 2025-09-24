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

// Función para buscar o crear anime
function buscarOCrearAnime($conexion, $anime_data) {
    // Buscar anime existente por título
    $query_buscar = "SELECT id FROM animes WHERE titulo = ? OR titulo_original = ? OR titulo_ingles = ?";
    $stmt_buscar = $conexion->prepare($query_buscar);
    $stmt_buscar->execute([
        $anime_data['titulo'] ?? '',
        $anime_data['titulo_original'] ?? '',
        $anime_data['titulo_ingles'] ?? ''
    ]);
    
    $anime_existente = $stmt_buscar->fetch(PDO::FETCH_ASSOC);
    
    if ($anime_existente) {
        return $anime_existente['id'];
    }
    
    // Crear nuevo anime
    $query_crear = "INSERT INTO animes (titulo, titulo_original, titulo_ingles, tipo, estado, episodios_total, imagen_portada) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_crear = $conexion->prepare($query_crear);
    $stmt_crear->execute([
        $anime_data['titulo'] ?? 'Anime Importado',
        $anime_data['titulo_original'] ?? null,
        $anime_data['titulo_ingles'] ?? null,
        $anime_data['tipo'] ?? 'TV',
        $anime_data['estado_anime'] ?? 'Finalizado',
        $anime_data['episodios_total'] ?? null,
        $anime_data['imagen_portada'] ?? null
    ]);
    
    return $conexion->lastInsertId();
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
        $stmt_actualizar->execute([
            $anime_data['episodios_vistos'] ?? 0,
            $anime_data['mi_estado'] ?? 'Plan de Ver',
            $anime_data['puntuacion'] ?: null,
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
        $stmt_crear->execute([
            $usuario_id,
            $anime_id,
            $anime_data['episodios_vistos'] ?? 0,
            $anime_data['mi_estado'] ?? 'Plan de Ver',
            $anime_data['puntuacion'] ?: null,
            $anime_data['fecha_agregado'] ?? date('Y-m-d H:i:s')
        ]);
        
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