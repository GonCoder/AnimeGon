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

// Función para eliminar anime de la lista
function eliminarAnimeDeLista($usuario_id, $anime_id) {
    try {
        $conexion = obtenerConexion();
        
        // Verificar que el anime pertenece al usuario
        $query_verificar = "SELECT id FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
        $stmt_verificar = $conexion->prepare($query_verificar);
        $stmt_verificar->execute([$usuario_id, $anime_id]);
        
        if (!$stmt_verificar->fetch()) {
            throw new Exception('No tienes permisos para eliminar este anime');
        }
        
        // Eliminar de la lista del usuario
        $query_eliminar = "DELETE FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
        $stmt_eliminar = $conexion->prepare($query_eliminar);
        $stmt_eliminar->execute([$usuario_id, $anime_id]);
        
        if ($stmt_eliminar->rowCount() > 0) {
            return [
                'exito' => true,
                'mensaje' => 'Anime eliminado de tu lista correctamente'
            ];
        } else {
            throw new Exception('No se pudo eliminar el anime');
        }
        
    } catch (Exception $e) {
        return [
            'exito' => false,
            'mensaje' => $e->getMessage()
        ];
    }
}

// Función para obtener información del anime antes de eliminar
function obtenerInfoAnime($usuario_id, $anime_id) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT a.titulo, lu.estado, lu.episodios_vistos 
                  FROM lista_usuario lu
                  LEFT JOIN animes a ON lu.anime_id = a.id
                  WHERE lu.usuario_id = ? AND lu.anime_id = ?";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id, $anime_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return null;
    }
}

// Procesar solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $respuesta = [
        'exito' => false,
        'mensaje' => ''
    ];
    
    // Obtener ID del anime
    $anime_id = intval($_POST['anime_id'] ?? 0);
    if ($anime_id <= 0) {
        $respuesta['mensaje'] = 'ID de anime no válido';
        echo json_encode($respuesta);
        exit();
    }
    
    // Obtener información del anime antes de eliminar (para logging/confirmación)
    $info_anime = obtenerInfoAnime($usuario_id, $anime_id);
    
    if (!$info_anime) {
        $respuesta['mensaje'] = 'Anime no encontrado en tu lista';
        echo json_encode($respuesta);
        exit();
    }
    
    // Eliminar anime
    $resultado = eliminarAnimeDeLista($usuario_id, $anime_id);
    $respuesta['exito'] = $resultado['exito'];
    $respuesta['mensaje'] = $resultado['mensaje'];
    
    // Agregar información del anime eliminado
    if ($resultado['exito']) {
        $respuesta['anime_eliminado'] = $info_anime['titulo'];
    }
    
    echo json_encode($respuesta);
    exit();
}

// Si es GET, obtener información del anime para confirmación
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    
    $anime_id = intval($_GET['anime_id'] ?? 0);
    if ($anime_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de anime no válido']);
        exit();
    }
    
    $info_anime = obtenerInfoAnime($usuario_id, $anime_id);
    
    if (!$info_anime) {
        http_response_code(404);
        echo json_encode(['error' => 'Anime no encontrado']);
        exit();
    }
    
    echo json_encode([
        'exito' => true,
        'anime' => $info_anime
    ]);
    exit();
}

// Si no es POST ni GET, devolver error
http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
?>