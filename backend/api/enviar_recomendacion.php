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

$usuario_emisor_id = $_SESSION['usuario_id'];

// Validar datos del formulario
$anime_id = (int)($_POST['anime_id'] ?? 0);
$valoracion = (int)($_POST['valoracion'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');
$usuarios_receptores = $_POST['usuarios'] ?? [];

// Validaciones
if ($anime_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de anime inválido']);
    exit;
}

if ($valoracion < 1 || $valoracion > 10) {
    echo json_encode(['success' => false, 'message' => 'La valoración debe estar entre 1 y 10']);
    exit;
}

if (empty($mensaje)) {
    echo json_encode(['success' => false, 'message' => 'El mensaje de recomendación es requerido']);
    exit;
}

if (strlen($mensaje) > 3000) {
    echo json_encode(['success' => false, 'message' => 'El mensaje no puede exceder 3000 caracteres']);
    exit;
}

if (empty($usuarios_receptores) || !is_array($usuarios_receptores)) {
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar al menos un usuario']);
    exit;
}

try {
    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    
    // Verificar que el anime existe y pertenece a la lista del usuario
    $stmt = $pdo->prepare("
        SELECT a.titulo 
        FROM animes a
        INNER JOIN lista_usuario lu ON a.id = lu.anime_id
        WHERE a.id = ? AND lu.usuario_id = ?
    ");
    $stmt->execute([$anime_id, $usuario_emisor_id]);
    $anime = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anime) {
        throw new Exception('No puedes recomendar un anime que no está en tu lista');
    }
    
    $recomendaciones_enviadas = 0;
    $errores = [];
    
    // Preparar la consulta de inserción
    $stmt_insert = $pdo->prepare("
        INSERT INTO recomendaciones (usuario_emisor_id, usuario_receptor_id, anime_id, valoracion_recomendacion, mensaje_recomendacion)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($usuarios_receptores as $usuario_receptor_id) {
        $usuario_receptor_id = (int)$usuario_receptor_id;
        
        // Verificar que el usuario receptor existe y está activo
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND activo = 1");
        $stmt_check->execute([$usuario_receptor_id]);
        
        if (!$stmt_check->fetch()) {
            $errores[] = "Usuario con ID $usuario_receptor_id no existe o no está activo";
            continue;
        }
        
        // Verificar que no se está auto-recomendando
        if ($usuario_receptor_id == $usuario_emisor_id) {
            $errores[] = "No puedes recomendarte animes a ti mismo";
            continue;
        }
        
        try {
            // Intentar insertar la recomendación
            $stmt_insert->execute([
                $usuario_emisor_id,
                $usuario_receptor_id,
                $anime_id,
                $valoracion,
                $mensaje
            ]);
            $recomendaciones_enviadas++;
        } catch (PDOException $e) {
            // Si es error de duplicado (ya recomendado antes)
            if ($e->getCode() == '23000') {
                $errores[] = "Ya habías recomendado este anime a este usuario anteriormente";
            } else {
                $errores[] = "Error al enviar recomendación a usuario ID $usuario_receptor_id";
            }
        }
    }
    
    $pdo->commit();
    
    // Preparar respuesta
    if ($recomendaciones_enviadas > 0) {
        $mensaje_exito = "Se enviaron $recomendaciones_enviadas recomendación(es) exitosamente";
        
        if (!empty($errores)) {
            $mensaje_exito .= ". Algunos envíos fallaron: " . implode(', ', $errores);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $mensaje_exito,
            'recomendaciones_enviadas' => $recomendaciones_enviadas,
            'errores' => $errores
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo enviar ninguna recomendación. Errores: ' . implode(', ', $errores),
            'errores' => $errores
        ]);
    }
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Error en enviar_recomendacion.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>