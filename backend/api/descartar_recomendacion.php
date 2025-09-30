<?php
header('Content-Type: application/json');
session_start();

require_once '../config/config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener los datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['recomendacion_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de recomendación requerido']);
    exit();
}

$recomendacion_id = (int)$input['recomendacion_id'];
$usuario_id = $_SESSION['usuario_id'];

try {
    $conexion = obtenerConexion();
    
    // Verificar que la recomendación existe y pertenece al usuario receptor
    $query_verificar = "SELECT id FROM recomendaciones WHERE id = ? AND usuario_receptor_id = ?";
    $stmt_verificar = $conexion->prepare($query_verificar);
    $stmt_verificar->execute([$recomendacion_id, $usuario_id]);
    
    if (!$stmt_verificar->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Recomendación no encontrada o no tienes permisos']);
        exit();
    }
    
    // Eliminar la recomendación
    $query_eliminar = "DELETE FROM recomendaciones WHERE id = ? AND usuario_receptor_id = ?";
    $stmt_eliminar = $conexion->prepare($query_eliminar);
    $resultado = $stmt_eliminar->execute([$recomendacion_id, $usuario_id]);
    
    if ($resultado) {
        echo json_encode([
            'success' => true, 
            'message' => 'Recomendación descartada exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error al descartar la recomendación'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error al descartar recomendación: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor'
    ]);
}
?>