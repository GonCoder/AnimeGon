<?php
session_start();

// Headers JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Función para respuesta JSON
function responder($success, $message, $debug = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($debug !== null) {
        $response['debug'] = $debug;
    }
    echo json_encode($response);
    exit();
}

// Test básico de conectividad
try {
    // Verificar sesión
    if (!isset($_SESSION['usuario_id'])) {
        responder(false, 'Usuario no autenticado', ['session' => 'No hay sesión activa']);
    }
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(false, 'Método no permitido', ['method' => $_SERVER['REQUEST_METHOD']]);
    }
    
    // Test de conexión a base de datos
    require_once '../config/config.php';
    
    $conexion = obtenerConexion();
    
    // Test simple de query
    $stmt = $conexion->prepare("SELECT 1 as test");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result && $result['test'] == 1) {
        responder(true, 'Conexión exitosa', [
            'usuario_id' => $_SESSION['usuario_id'],
            'post_data' => $_POST,
            'db_test' => 'OK'
        ]);
    } else {
        responder(false, 'Test de base de datos fallido');
    }
    
} catch (Exception $e) {
    responder(false, 'Error: ' . $e->getMessage(), [
        'error_type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>