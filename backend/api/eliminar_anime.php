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

// Función para verificar si un anime tiene otros usuarios
function verificarAnimeHuerfano($conexion, $anime_id) {
    try {
        $query = "SELECT COUNT(*) as total FROM lista_usuario WHERE anime_id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$anime_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado['total'] == 0;
    } catch (Exception $e) {
        return false;
    }
}

// Función para eliminar anime huérfano de la base de datos
function eliminarAnimeHuerfano($conexion, $anime_id) {
    try {
        // Eliminar relaciones con géneros primero
        $query_generos = "DELETE FROM anime_generos WHERE anime_id = ?";
        $stmt_generos = $conexion->prepare($query_generos);
        $stmt_generos->execute([$anime_id]);
        
        // Eliminar reseñas y comentarios relacionados
        $query_comentarios = "DELETE cr FROM comentarios_reseña cr 
                             INNER JOIN reseñas r ON cr.reseña_id = r.id 
                             WHERE r.anime_id = ?";
        $stmt_comentarios = $conexion->prepare($query_comentarios);
        $stmt_comentarios->execute([$anime_id]);
        
        $query_reseñas = "DELETE FROM reseñas WHERE anime_id = ?";
        $stmt_reseñas = $conexion->prepare($query_reseñas);
        $stmt_reseñas->execute([$anime_id]);
        
        // Finalmente eliminar el anime
        $query_anime = "DELETE FROM animes WHERE id = ?";
        $stmt_anime = $conexion->prepare($query_anime);
        $stmt_anime->execute([$anime_id]);
        
        return $stmt_anime->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Función para eliminar anime de la lista
function eliminarAnimeDeLista($conexion, $usuario_id, $anime_id) {
    try {
        // Verificar que el anime pertenece al usuario
        $query_verificar = "SELECT id FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
        $stmt_verificar = $conexion->prepare($query_verificar);
        $stmt_verificar->execute([$usuario_id, $anime_id]);
        
        if (!$stmt_verificar->fetch()) {
            throw new Exception('No tienes permisos para eliminar este anime');
        }
        
        // Eliminar de la lista del usuario
        $query_eliminar_lista = "DELETE FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
        $stmt_eliminar_lista = $conexion->prepare($query_eliminar_lista);
        $stmt_eliminar_lista->execute([$usuario_id, $anime_id]);
        
        if ($stmt_eliminar_lista->rowCount() == 0) {
            throw new Exception('No se pudo eliminar el anime de tu lista');
        }
        
        // También eliminar de favoritos si existe
        $query_eliminar_favoritos = "DELETE FROM favoritos WHERE usuario_id = ? AND anime_id = ?";
        $stmt_eliminar_favoritos = $conexion->prepare($query_eliminar_favoritos);
        $stmt_eliminar_favoritos->execute([$usuario_id, $anime_id]);
        
        $mensaje = 'Anime eliminado de tu lista correctamente';
        if ($stmt_eliminar_favoritos->rowCount() > 0) {
            $mensaje .= ' y de tus favoritos';
        }
        
        // Verificar si el anime quedó huérfano (sin usuarios)
        $animeHuerfano = verificarAnimeHuerfano($conexion, $anime_id);
        if ($animeHuerfano) {
            if (eliminarAnimeHuerfano($conexion, $anime_id)) {
                $mensaje .= '. También se eliminó de la base de datos al no tener más usuarios';
            }
        }
        
        return [
            'exito' => true,
            'mensaje' => $mensaje
        ];
        
    } catch (Exception $e) {
        throw $e;
    }
}

// Función para obtener información del anime antes de eliminar
function obtenerInfoAnime($conexion, $usuario_id, $anime_id) {
    try {
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
    
    try {
        // Obtener conexión única para toda la operación
        $conexion = obtenerConexion();
        
        // Iniciar transacción para asegurar consistencia
        $conexion->beginTransaction();
        
        // Obtener ID del anime
        $anime_id = intval($_POST['anime_id'] ?? 0);
        if ($anime_id <= 0) {
            throw new Exception('ID de anime no válido');
        }
        
        // Obtener información del anime antes de eliminar (para logging/confirmación)
        $info_anime = obtenerInfoAnime($conexion, $usuario_id, $anime_id);
        
        if (!$info_anime) {
            throw new Exception('Anime no encontrado en tu lista');
        }
        
        // Eliminar anime
        $resultado = eliminarAnimeDeLista($conexion, $usuario_id, $anime_id);
        
        // Confirmar la transacción
        $conexion->commit();
        
        $respuesta['exito'] = $resultado['exito'];
        $respuesta['mensaje'] = $resultado['mensaje'];
        
        // Agregar información del anime eliminado
        if ($resultado['exito']) {
            $respuesta['anime_eliminado'] = $info_anime['titulo'];
        }
        
    } catch (Exception $e) {
        // Revertir la transacción en caso de error
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $respuesta['mensaje'] = $e->getMessage();
    }
    
    echo json_encode($respuesta);
    exit();
}

// Si es GET, obtener información del anime para confirmación
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    
    try {
        $conexion = obtenerConexion();
        
        $anime_id = intval($_GET['anime_id'] ?? 0);
        if ($anime_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de anime no válido']);
            exit();
        }
        
        $info_anime = obtenerInfoAnime($conexion, $usuario_id, $anime_id);
        
        if (!$info_anime) {
            http_response_code(404);
            echo json_encode(['error' => 'Anime no encontrado']);
            exit();
        }
        
        echo json_encode([
            'exito' => true,
            'anime' => $info_anime
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
    }
    
    exit();
}

// Si no es POST ni GET, devolver error
http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
?>