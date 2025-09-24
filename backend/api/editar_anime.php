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

// Función para actualizar anime
function actualizarAnime($usuario_id, $anime_id, $datos, $imagen_ruta = null) {
    try {
        $conexion = obtenerConexion();
        
        // Iniciar transacción
        $conexion->beginTransaction();
        
        // Verificar que el anime pertenece al usuario
        $query_verificar = "SELECT id FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
        $stmt_verificar = $conexion->prepare($query_verificar);
        $stmt_verificar->execute([$usuario_id, $anime_id]);
        
        if (!$stmt_verificar->fetch()) {
            throw new Exception('No tienes permisos para editar este anime');
        }
        
        // Actualizar información del anime en la tabla animes si es necesario
        if (isset($datos['nombre']) && !empty($datos['nombre'])) {
            $query_anime = "UPDATE animes SET titulo = ?, titulo_original = ?, titulo_ingles = ?, episodios_total = ?, tipo = ?, estado = ?";
            $params_anime = [
                $datos['nombre'], 
                $datos['titulo_original'] ?: null,
                $datos['titulo_ingles'] ?: null,
                $datos['total_episodios'] ?: null,
                $datos['tipo'] ?: 'TV',
                $datos['estado_anime'] ?: 'Finalizado'
            ];
            
            if ($imagen_ruta) {
                $query_anime .= ", imagen_portada = ?";
                $params_anime[] = $imagen_ruta;
            }
            
            $query_anime .= " WHERE id = ?";
            $params_anime[] = $anime_id;
            
            $stmt_anime = $conexion->prepare($query_anime);
            $stmt_anime->execute($params_anime);
        }
        
        // Actualizar información en lista_usuario
        $query_lista = "UPDATE lista_usuario SET episodios_vistos = ?, estado = ?, puntuacion = ? WHERE usuario_id = ? AND anime_id = ?";
        $stmt_lista = $conexion->prepare($query_lista);
        $stmt_lista->execute([
            $datos['episodios_vistos'] ?: 0,
            $datos['estado'],
            isset($datos['puntuacion']) && $datos['puntuacion'] !== '' ? (int)$datos['puntuacion'] : null,
            $usuario_id,
            $anime_id
        ]);
        
        // Confirmar transacción
        $conexion->commit();
        
        return [
            'exito' => true,
            'mensaje' => 'Anime actualizado correctamente'
        ];
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if (isset($conexion)) {
            $conexion->rollBack();
        }
        
        return [
            'exito' => false,
            'mensaje' => $e->getMessage()
        ];
    }
}

// Función para subir nueva imagen
function subirNuevaImagen($archivo) {
    $resultado = [
        'exito' => false,
        'mensaje' => '',
        'ruta' => null
    ];
    
    // Verificar si se subió un archivo
    if (!isset($archivo) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        $resultado['exito'] = true;
        $resultado['mensaje'] = 'No se seleccionó nueva imagen';
        return $resultado;
    }
    
    // Verificar errores de subida
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $resultado['mensaje'] = 'Error al subir el archivo';
        return $resultado;
    }
    
    // Verificar tamaño (1MB máximo)
    $tamaño_maximo = 1 * 1024 * 1024; // 1MB en bytes
    if ($archivo['size'] > $tamaño_maximo) {
        $resultado['mensaje'] = 'El archivo es demasiado grande. Máximo 1MB permitido.';
        return $resultado;
    }
    
    // Verificar tipo de archivo
    $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/x-icon', 'image/vnd.microsoft.icon'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tipo_archivo = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($tipo_archivo, $tipos_permitidos)) {
        $resultado['mensaje'] = 'Tipo de archivo no permitido. Solo JPG, PNG e ICO.';
        return $resultado;
    }
    
    // Generar nombre único para el archivo
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_archivo = 'anime_' . uniqid() . '_' . time() . '.' . $extension;
    $ruta_destino = '../../img/' . $nombre_archivo;

    // Crear directorio si no existe
    if (!file_exists('../../img/')) {
        mkdir('../../img/', 0755, true);
    }    // Mover archivo a destino
    if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        $resultado['exito'] = true;
        $resultado['mensaje'] = 'Imagen subida correctamente';
        $resultado['ruta'] = 'img/' . $nombre_archivo;
    } else {
        $resultado['mensaje'] = 'Error al mover el archivo al destino';
    }
    
    return $resultado;
}

// Procesar solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $respuesta = [
        'exito' => false,
        'mensaje' => '',
        'errores' => []
    ];
    
    // Obtener ID del anime
    $anime_id = intval($_POST['anime_id'] ?? 0);
    if ($anime_id <= 0) {
        $respuesta['mensaje'] = 'ID de anime no válido';
        echo json_encode($respuesta);
        exit();
    }
    
    // Validar datos
    $datos = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'titulo_original' => trim($_POST['titulo_original'] ?? ''),
        'titulo_ingles' => trim($_POST['titulo_ingles'] ?? ''),
        'tipo' => $_POST['tipo'] ?? 'TV',
        'estado_anime' => $_POST['estado_anime'] ?? 'Finalizado',
        'total_episodios' => intval($_POST['total_episodios'] ?? 0),
        'episodios_vistos' => intval($_POST['episodios_vistos'] ?? 0),
        'estado' => $_POST['estado'] ?? 'Plan de Ver',
        'puntuacion' => $_POST['puntuacion'] ?? null
    ];
    
    // Validaciones
    if (empty($datos['nombre'])) {
        $respuesta['errores'][] = 'El nombre del anime es requerido';
    }
    
    if (strlen($datos['nombre']) > 255) {
        $respuesta['errores'][] = 'El nombre del anime es demasiado largo';
    }
    
    if ($datos['total_episodios'] < 0) {
        $respuesta['errores'][] = 'El total de episodios no puede ser negativo';
    }
    
    if ($datos['episodios_vistos'] < 0) {
        $respuesta['errores'][] = 'Los episodios vistos no pueden ser negativos';
    }
    
    if ($datos['total_episodios'] > 0 && $datos['episodios_vistos'] > $datos['total_episodios']) {
        $respuesta['errores'][] = 'Los episodios vistos no pueden ser más que el total';
    }
    
    $estados_validos = ['Plan de Ver', 'Viendo', 'Completado', 'En Pausa', 'Abandonado'];
    if (!in_array($datos['estado'], $estados_validos)) {
        $respuesta['errores'][] = 'Estado no válido';
    }
    
    // Validar tipo de anime
    $tipos_validos = ['TV', 'OVA', 'Película', 'Especial', 'ONA'];
    if (!in_array($datos['tipo'], $tipos_validos)) {
        $respuesta['errores'][] = 'Tipo de anime no válido';
    }
    
    // Validar estado del anime
    $estados_anime_validos = ['Finalizado', 'Emitiendo', 'Próximamente', 'Cancelado'];
    if (!in_array($datos['estado_anime'], $estados_anime_validos)) {
        $respuesta['errores'][] = 'Estado del anime no válido';
    }
    
    // Validar puntuación
    if (!empty($datos['puntuacion'])) {
        $puntuacion = (int)$datos['puntuacion'];
        if ($puntuacion < 1 || $puntuacion > 10) {
            $respuesta['errores'][] = 'La puntuación debe estar entre 1 y 10';
        }
    }
    
    // Si hay errores, devolverlos
    if (!empty($respuesta['errores'])) {
        $respuesta['mensaje'] = implode(', ', $respuesta['errores']);
        echo json_encode($respuesta);
        exit();
    }
    
    // Procesar imagen (URL tiene prioridad sobre archivo subido)
    $imagen_ruta = null;
    
    // Verificar si se proporcionó una URL de imagen
    if (!empty($_POST['imagen_url'])) {
        $imagen_url = trim($_POST['imagen_url']);
        // Validar que sea una URL válida
        if (filter_var($imagen_url, FILTER_VALIDATE_URL)) {
            $imagen_ruta = $imagen_url;
        } else {
            $respuesta['mensaje'] = 'La URL de imagen no es válida';
            echo json_encode($respuesta);
            exit();
        }
    }
    // Si no hay URL, procesar archivo subido
    elseif (isset($_FILES['imagen'])) {
        $resultado_imagen = subirNuevaImagen($_FILES['imagen']);
        if (!$resultado_imagen['exito'] && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            $respuesta['mensaje'] = 'Error con la imagen: ' . $resultado_imagen['mensaje'];
            echo json_encode($respuesta);
            exit();
        } else {
            $imagen_ruta = $resultado_imagen['ruta'];
        }
    }
    
    // Actualizar anime
    $resultado_anime = actualizarAnime($usuario_id, $anime_id, $datos, $imagen_ruta);
    $respuesta['exito'] = $resultado_anime['exito'];
    $respuesta['mensaje'] = $resultado_anime['mensaje'];
    
    echo json_encode($respuesta);
    exit();
}

// Si no es POST, devolver error
http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
?>