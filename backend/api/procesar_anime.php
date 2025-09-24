<?php
session_start();
require_once '../config/config.php';
require_once '../config/funciones.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../views/login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Función para subir imagen
function subirImagen($archivo) {
    $resultado = [
        'exito' => false,
        'mensaje' => '',
        'ruta' => null
    ];
    
    // Verificar si se subió un archivo
    if (!isset($archivo) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        $resultado['exito'] = true;
        $resultado['mensaje'] = 'No se seleccionó imagen';
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
        $resultado['ruta'] = 'img/' . $nombre_archivo; // Ruta relativa desde la raíz del proyecto
    } else {
        $resultado['mensaje'] = 'Error al mover el archivo al destino';
    }
    
    return $resultado;
}

// Función para agregar anime
function agregarAnime($usuario_id, $datos, $imagen_ruta = null) {
    try {
        $conexion = obtenerConexion();
        
        // Iniciar transacción
        $conexion->beginTransaction();
        
        // Primero, verificar si el anime ya existe en la tabla animes
        $query_buscar = "SELECT id FROM animes WHERE LOWER(titulo) = LOWER(?)";
        $stmt_buscar = $conexion->prepare($query_buscar);
        $stmt_buscar->execute([$datos['nombre']]);
        $anime_existente = $stmt_buscar->fetch();
        
        if ($anime_existente) {
            $anime_id = $anime_existente['id'];
            
            // Verificar si el usuario ya tiene este anime
            $query_verificar = "SELECT id FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
            $stmt_verificar = $conexion->prepare($query_verificar);
            $stmt_verificar->execute([$usuario_id, $anime_id]);
            
            if ($stmt_verificar->fetch()) {
                throw new Exception('Ya tienes este anime en tu lista');
            }
        } else {
            // Crear nuevo anime en la tabla animes
            $query_anime = "INSERT INTO animes (titulo, titulo_original, titulo_ingles, episodios_total, imagen_portada, tipo, estado) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_anime = $conexion->prepare($query_anime);
            $stmt_anime->execute([
                $datos['nombre'],
                $datos['titulo_original'] ?: null,
                $datos['titulo_ingles'] ?: null,
                $datos['total_episodios'] ?: null,
                $imagen_ruta,
                $datos['tipo'] ?: 'TV',
                $datos['estado_anime'] ?: 'Finalizado'
            ]);
            $anime_id = $conexion->lastInsertId();
        }
        
        // Agregar anime a la lista del usuario
        $query_usuario_anime = "INSERT INTO lista_usuario (usuario_id, anime_id, episodios_vistos, estado, puntuacion, fecha_agregado) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt_usuario_anime = $conexion->prepare($query_usuario_anime);
        $stmt_usuario_anime->execute([
            $usuario_id,
            $anime_id,
            $datos['capitulos_vistos'] ?: 0,
            $datos['estado'],
            !empty($datos['puntuacion']) ? $datos['puntuacion'] : null
        ]);
        
        // Confirmar transacción
        $conexion->commit();
        
        return [
            'exito' => true,
            'mensaje' => 'Anime agregado correctamente a tu lista'
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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuesta = [
        'exito' => false,
        'mensaje' => '',
        'errores' => []
    ];
    
    // Validar datos requeridos
    $datos = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'titulo_original' => trim($_POST['titulo_original'] ?? ''),
        'titulo_ingles' => trim($_POST['titulo_ingles'] ?? ''),
        'total_episodios' => intval($_POST['total_episodios'] ?? 0),
        'capitulos_vistos' => intval($_POST['capitulos_vistos'] ?? 0),
        'estado' => $_POST['estado'] ?? 'Plan de Ver',
        'tipo' => $_POST['tipo'] ?? 'TV',
        'estado_anime' => $_POST['estado_anime'] ?? 'Finalizado',
        'puntuacion' => !empty($_POST['puntuacion']) ? floatval($_POST['puntuacion']) : null
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
    
    if ($datos['capitulos_vistos'] < 0) {
        $respuesta['errores'][] = 'Los episodios vistos no pueden ser negativos';
    }
    
    if ($datos['total_episodios'] > 0 && $datos['capitulos_vistos'] > $datos['total_episodios']) {
        $respuesta['errores'][] = 'Los episodios vistos no pueden ser más que el total';
    }
    
    $estados_validos = ['Plan de Ver', 'Viendo', 'Completado', 'En Pausa', 'Abandonado'];
    if (!in_array($datos['estado'], $estados_validos)) {
        $respuesta['errores'][] = 'Estado no válido';
    }
    
    $tipos_validos = ['TV', 'OVA', 'Película', 'Especial', 'ONA'];
    if (!in_array($datos['tipo'], $tipos_validos)) {
        $respuesta['errores'][] = 'Tipo de anime no válido';
    }
    
    $estados_anime_validos = ['Emitiendo', 'Finalizado', 'Próximamente', 'Cancelado'];
    if (!in_array($datos['estado_anime'], $estados_anime_validos)) {
        $respuesta['errores'][] = 'Estado del anime no válido';
    }
    
    if (!is_null($datos['puntuacion']) && ($datos['puntuacion'] < 1 || $datos['puntuacion'] > 10)) {
        $respuesta['errores'][] = 'La puntuación debe estar entre 1 y 10';
    }
    
    // Si hay errores, mostrarlos
    if (!empty($respuesta['errores'])) {
        $respuesta['mensaje'] = implode('<br>', $respuesta['errores']);
    } else {
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
            }
        }
        // Si no hay URL, procesar archivo subido
        elseif (isset($_FILES['imagen'])) {
            $resultado_imagen = subirImagen($_FILES['imagen']);
            if (!$resultado_imagen['exito'] && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
                $respuesta['mensaje'] = 'Error con la imagen: ' . $resultado_imagen['mensaje'];
            } else {
                $imagen_ruta = $resultado_imagen['ruta'];
            }
        }
        
        // Si no hay errores con la imagen, proceder a agregar anime
        if (empty($respuesta['mensaje'])) {
            $resultado_anime = agregarAnime($usuario_id, $datos, $imagen_ruta);
            $respuesta['exito'] = $resultado_anime['exito'];
            $respuesta['mensaje'] = $resultado_anime['mensaje'];
        }
    }
    
    // Redirigir con mensaje
    if ($respuesta['exito']) {
        $_SESSION['mensaje_exito'] = $respuesta['mensaje'];
    } else {
        $_SESSION['mensaje_error'] = $respuesta['mensaje'];
    }
    
    header("Location: ../../views/mis_animes.php");
    exit();
}

// Si no es POST, redirigir
header("Location: ../../views/mis_animes_new.php");
exit();
?>