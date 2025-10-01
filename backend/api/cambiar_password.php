<?php
// cambiar_password.php - API para cambiar contraseña con token de recuperación

header('Content-Type: application/json');
require_once '../config/funciones.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    // Obtener y validar los datos del formulario
    $token = $_POST['token'] ?? '';
    $usuario_id = $_POST['usuario_id'] ?? '';
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    
    // Validaciones básicas
    if (empty($token) || empty($usuario_id) || empty($nueva_password) || empty($confirmar_password)) {
        throw new Exception('Todos los campos son obligatorios');
    }
    
    if ($nueva_password !== $confirmar_password) {
        throw new Exception('Las contraseñas no coinciden');
    }
    
    if (strlen($nueva_password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }
    
    // Verificar que el token es válido y no ha expirado
    $conexion = obtenerConexion();
    
    $query = "SELECT rt.*, u.username, u.email, u.nombre 
              FROM password_reset_tokens rt 
              INNER JOIN usuarios u ON rt.usuario_id = u.id 
              WHERE rt.token = ? AND rt.usuario_id = ? AND rt.fecha_expiracion > NOW() AND rt.usado = FALSE";
    $stmt = $conexion->prepare($query);
    $stmt->execute([$token, $usuario_id]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$token_data) {
        throw new Exception('El enlace de recuperación ha expirado o no es válido');
    }
    
    // Hashear la nueva contraseña
    $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
    
    // Iniciar transacción para asegurar consistencia
    $conexion->beginTransaction();
    
    try {
        // Actualizar la contraseña del usuario
        $update_password = "UPDATE usuarios SET password = ?, ultimo_acceso = NOW() WHERE id = ?";
        $stmt_password = $conexion->prepare($update_password);
        $stmt_password->execute([$password_hash, $usuario_id]);
        
        if ($stmt_password->rowCount() === 0) {
            throw new Exception('Error al actualizar la contraseña del usuario');
        }
        
        // Marcar el token como usado
        $mark_used = "UPDATE password_reset_tokens SET usado = TRUE, fecha_uso = NOW() WHERE token = ?";
        $stmt_used = $conexion->prepare($mark_used);
        $stmt_used->execute([$token]);
        
        // Invalidar todos los otros tokens pendientes del usuario por seguridad
        $invalidate_tokens = "UPDATE password_reset_tokens SET usado = TRUE WHERE usuario_id = ? AND usado = FALSE AND token != ?";
        $stmt_invalidate = $conexion->prepare($invalidate_tokens);
        $stmt_invalidate->execute([$usuario_id, $token]);
        
        // Confirmar transacción
        $conexion->commit();
        
        // Cerrar cualquier sesión activa del usuario por seguridad
        iniciarSesion(); // Asegurar que la sesión esté iniciada
        
        // Si hay una sesión activa del mismo usuario, cerrarla
        if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] == $usuario_id) {
            session_destroy();
        }
        
        // También limpiar cualquier cookie de sesión
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Log de la acción para auditoría
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        error_log("Contraseña cambiada exitosamente - Usuario ID: {$usuario_id}, Username: {$token_data['username']}, IP: {$ip_address}");
        
        // Respuesta exitosa con instrucción de cerrar sesión
        echo json_encode([
            'success' => true,
            'message' => '✅ ¡Contraseña actualizada exitosamente!',
            'redirect' => 'login.php',
            'logout' => true,
            'details' => 'Tu nueva contraseña ha sido guardada y tu sesión cerrada por seguridad.',
            'usuario' => [
                'username' => $token_data['username'],
                'email' => $token_data['email'],
                'nombre' => $token_data['nombre']
            ]
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error en cambiar_password.php: " . $e->getMessage());
    
    // Determinar código de estado HTTP apropiado
    $status_code = 400; // Bad Request por defecto
    if (strpos($e->getMessage(), 'expirado') !== false || strpos($e->getMessage(), 'válido') !== false) {
        $status_code = 410; // Gone - recurso expirado
    } elseif (strpos($e->getMessage(), 'obligatorios') !== false) {
        $status_code = 422; // Unprocessable Entity
    }
    
    http_response_code($status_code);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>