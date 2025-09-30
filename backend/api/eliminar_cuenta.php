<?php
session_start();
require_once '../config/config.php';
require_once '../config/funciones.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['confirmar']) || $input['confirmar'] !== true) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Confirmación requerida']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

try {
    $conexion = obtenerConexion();
    $conexion->beginTransaction();
    
    // Obtener información del usuario antes de eliminar
    $query_info = "SELECT nombre, email FROM usuarios WHERE id = ?";
    $stmt_info = $conexion->prepare($query_info);
    $stmt_info->execute([$usuario_id]);
    $usuario_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario_info) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Log de la eliminación para auditoria
    error_log("Eliminando cuenta del usuario: {$usuario_info['nombre']} (ID: {$usuario_id}, Email: {$usuario_info['email']})");
    
    // 1. Eliminar favoritos del usuario
    $query_favoritos = "DELETE FROM favoritos WHERE usuario_id = ?";
    $stmt_favoritos = $conexion->prepare($query_favoritos);
    $stmt_favoritos->execute([$usuario_id]);
    $favoritos_eliminados = $stmt_favoritos->rowCount();
    
    // 2. Eliminar lista de animes del usuario
    $query_lista = "DELETE FROM lista_usuario WHERE usuario_id = ?";
    $stmt_lista = $conexion->prepare($query_lista);
    $stmt_lista->execute([$usuario_id]);
    $lista_eliminados = $stmt_lista->rowCount();
    
    // 3. Verificar si hay otras tablas relacionadas que necesiten limpieza
    // (Puntuaciones, comentarios, etc. - agregar según sea necesario)
    
    // Opcional: Si tienes tabla de puntuaciones o valoraciones
    /*
    $query_puntuaciones = "DELETE FROM puntuaciones WHERE usuario_id = ?";
    $stmt_puntuaciones = $conexion->prepare($query_puntuaciones);
    $stmt_puntuaciones->execute([$usuario_id]);
    $puntuaciones_eliminadas = $stmt_puntuaciones->rowCount();
    */
    
    // 4. Finalmente, eliminar el usuario
    $query_usuario = "DELETE FROM usuarios WHERE id = ?";
    $stmt_usuario = $conexion->prepare($query_usuario);
    $stmt_usuario->execute([$usuario_id]);
    $usuario_eliminado = $stmt_usuario->rowCount();
    
    if ($usuario_eliminado === 0) {
        throw new Exception('No se pudo eliminar el usuario');
    }
    
    // Confirmar transacción
    $conexion->commit();
    
    // Log de éxito
    error_log("Cuenta eliminada exitosamente - Usuario: {$usuario_info['nombre']}, Lista: {$lista_eliminados} animes, Favoritos: {$favoritos_eliminados}");
    
    // Destruir la sesión
    session_destroy();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Cuenta eliminada exitosamente',
        'data' => [
            'animes_eliminados' => $lista_eliminados,
            'favoritos_eliminados' => $favoritos_eliminados,
            'usuario' => $usuario_info['nombre']
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback en caso de error
    if ($conexion->inTransaction()) {
        $conexion->rollback();
    }
    
    error_log("Error al eliminar cuenta (Usuario ID: {$usuario_id}): " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar la cuenta: ' . $e->getMessage()
    ]);
}
?>