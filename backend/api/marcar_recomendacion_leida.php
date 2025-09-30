<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$data = json_decode(file_get_contents('php://input'), true);

$recomendacion_id = (int)($data['recomendacion_id'] ?? 0);
$accion = $data['accion'] ?? 'marcar_leido'; // 'marcar_leido' o 'marcar_no_leido'

if ($recomendacion_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de recomendación inválido']);
    exit;
}

try {
    $pdo = obtenerConexion();
    
    // Verificar que la recomendación pertenece al usuario
    $stmt_check = $pdo->prepare("
        SELECT id FROM recomendaciones 
        WHERE id = ? AND usuario_receptor_id = ?
    ");
    $stmt_check->execute([$recomendacion_id, $usuario_id]);
    
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Recomendación no encontrada']);
        exit;
    }
    
    // Actualizar estado de lectura
    $leido = ($accion === 'marcar_leido') ? 1 : 0;
    $fecha_lectura = ($accion === 'marcar_leido') ? 'CURRENT_TIMESTAMP' : 'NULL';
    
    $stmt = $pdo->prepare("
        UPDATE recomendaciones 
        SET leido = ?, fecha_lectura = " . $fecha_lectura . " 
        WHERE id = ?
    ");
    $stmt->execute([$leido, $recomendacion_id]);
    
    $mensaje = ($accion === 'marcar_leido') ? 'Recomendación marcada como leída' : 'Recomendación marcada como no leída';
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje
    ]);
    
} catch (Exception $e) {
    error_log("Error en marcar_recomendacion_leida.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>