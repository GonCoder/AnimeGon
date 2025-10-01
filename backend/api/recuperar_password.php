<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

try {
    // Obtener y validar los datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action']) || $input['action'] !== 'enviar_password') {
        throw new Exception('Acción no válida');
    }
    
    $usuario_id = $_SESSION['usuario_id'];
    
    // Obtener datos del usuario
    $conexion = obtenerConexion();
    $query = "SELECT id, nombre, username, email, password FROM usuarios WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }
    
    if (empty($usuario['email'])) {
        throw new Exception('No hay email asociado a esta cuenta');
    }
    
    // Preparar el email
    $to = $usuario['email'];
    $subject = '🔑 AnimeGon - Recuperación de Contraseña';
    
    // Cuerpo del email en HTML
    $body = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Recuperación de Contraseña - AnimeGon</title>
        <style>
            body {
                font-family: 'Arial', sans-serif;
                background: linear-gradient(135deg, #1a1a2e, #16213e, #0f0f23);
                color: #ffffff;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: rgba(26, 26, 46, 0.95);
                border: 2px solid #8a2be2;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 0 30px rgba(138, 43, 226, 0.5);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .logo {
                font-size: 2rem;
                color: #8a2be2;
                margin-bottom: 10px;
            }
            .title {
                color: #bb86fc;
                font-size: 1.5rem;
                margin: 0;
            }
            .content {
                line-height: 1.6;
                margin-bottom: 30px;
            }
            .password-box {
                background: rgba(138, 43, 226, 0.1);
                border: 2px solid #bb86fc;
                border-radius: 10px;
                padding: 20px;
                text-align: center;
                margin: 20px 0;
            }
            .password-label {
                color: #bb86fc;
                font-size: 0.9rem;
                margin-bottom: 10px;
            }
            .password-value {
                font-family: monospace;
                font-size: 1.2rem;
                font-weight: bold;
                color: #ffffff;
                background: rgba(0, 0, 0, 0.3);
                padding: 10px;
                border-radius: 5px;
                word-break: break-all;
            }
            .warning {
                background: rgba(255, 107, 53, 0.1);
                border: 1px solid #ff6b35;
                border-radius: 8px;
                padding: 15px;
                margin: 20px 0;
            }
            .warning-title {
                color: #ff6b35;
                font-weight: bold;
                margin-bottom: 8px;
            }
            .footer {
                text-align: center;
                font-size: 0.8rem;
                color: rgba(255, 255, 255, 0.6);
                border-top: 1px solid rgba(138, 43, 226, 0.3);
                padding-top: 20px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>🎌 AnimeGon</div>
                <h1 class='title'>🔑 Recuperación de Contraseña</h1>
            </div>
            
            <div class='content'>
                <p>Hola <strong style='color: #bb86fc;'>{$usuario['nombre']}</strong>,</p>
                
                <p>Has solicitado recuperar tu contraseña desde tu perfil de AnimeGon. Lamentablemente, por seguridad, las contraseñas están hasheadas y no se pueden recuperar.</p>
                
                <div class='warning'>
                    <div class='warning-title'>⚠️ Información Importante:</div>
                    <p>En lugar de enviarte tu contraseña actual, te recomendamos cambiarla desde tu perfil una vez que inicies sesión.</p>
                </div>
                
                <p>Si no puedes acceder a tu cuenta, contacta al administrador.</p>
            </div>
            
            <div class='footer'>
                <p>Este email fue enviado desde AnimeGon</p>
                <p>📅 " . date('d/m/Y H:i:s') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Headers para email HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: AnimeGon <noreply@animegon.com>" . "\r\n";
    $headers .= "Reply-To: noreply@animegon.com" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Verificar si mail() está disponible
    if (!function_exists('mail')) {
        throw new Exception('La función mail() no está disponible en este servidor');
    }
    
    // Log antes de enviar
    error_log("Intentando enviar email de recuperación - Usuario ID: {$usuario_id}, Destino: {$to}");
    
    // Enviar el email
    $emailSent = mail($to, $subject, $body, $headers);
    
    // Log del resultado
    error_log("Resultado envío email: " . ($emailSent ? 'ÉXITO' : 'FALLO'));
    
    if ($emailSent) {
        // Log de la acción para auditoría
        error_log("Recuperación de contraseña enviada para usuario ID: {$usuario_id}, Email: {$to}");
        
        echo json_encode([
            'success' => true,
            'message' => "📧 Email enviado a {$to}. Revisa tu bandeja de entrada y carpeta de spam."
        ]);
    } else {
        // Obtener el último error de PHP
        $lastError = error_get_last();
        error_log("Error al enviar email: " . ($lastError ? $lastError['message'] : 'Error desconocido'));
        
        throw new Exception('❌ No se pudo enviar el email. En desarrollo local, la función de email no está disponible. En un hosting real funcionaría correctamente.');
    }
    
} catch (Exception $e) {
    error_log("Error en recuperar_password.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>