<?php
// recuperar_password_public.php - API pública para recuperación de contraseña por email

header('Content-Type: application/json');
require_once '../config/funciones.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    // Obtener el email del formulario
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        throw new Exception('El email es obligatorio');
    }
    
    if (!validarEmail($email)) {
        throw new Exception('El formato del email no es válido');
    }
    
    // Buscar el usuario por email
    $conexion = obtenerConexion();
    $query = "SELECT id, nombre, username, email FROM usuarios WHERE email = ? AND activo = TRUE";
    $stmt = $conexion->prepare($query);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        // Por seguridad, no revelamos si el email existe o no
        echo json_encode([
            'success' => true,
            'message' => '📧 Si el email existe en nuestro sistema, recibirás un enlace de recuperación en los próximos minutos. Revisa también tu carpeta de spam.'
        ]);
        exit();
    }
    
    $usuario_id = $usuario['id'];
    
    // Generar token único y seguro
    $token = bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
    
    // Establecer fecha de expiración (15 minutos desde ahora)
    $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Obtener información adicional para auditoría
    $ip_solicitud = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Limpiar tokens expirados del usuario (housekeeping)
    $cleanup_query = "DELETE FROM password_reset_tokens WHERE usuario_id = ? AND (fecha_expiracion < NOW() OR usado = TRUE)";
    $cleanup_stmt = $conexion->prepare($cleanup_query);
    $cleanup_stmt->execute([$usuario_id]);
    
    // Insertar el nuevo token en la base de datos
    $insert_query = "INSERT INTO password_reset_tokens (usuario_id, token, fecha_expiracion, ip_solicitud, user_agent) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conexion->prepare($insert_query);
    $insert_stmt->execute([$usuario_id, $token, $fecha_expiracion, $ip_solicitud, $user_agent]);
    
    if ($insert_stmt->rowCount() === 0) {
        throw new Exception('Error al generar el token de recuperación');
    }
    
    // Preparar el email con enlace de recuperación
    $to = $usuario['email'];
    $subject = '🔑 AnimeGon - Recupera tu Contraseña';
    
    // Crear URL de recuperación
    $reset_url = "https://animegon.alwaysdata.net/views/reset_password.php?token=" . $token;
    
    // Cuerpo del email en HTML
    $body = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Recuperar Contraseña - AnimeGon</title>
        <style>
            body {
                font-family: 'Arial', sans-serif;
                background-color: #121212;
                color: #ffffff;
                margin: 0;
                padding: 20px;
                line-height: 1.6;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #1e1e1e;
                border: 2px solid #8a2be2;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 0 30px rgba(138, 43, 226, 0.3);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .logo {
                font-size: 2.5rem;
                color: #8a2be2;
                margin-bottom: 10px;
                text-shadow: 0 0 10px rgba(138, 43, 226, 0.5);
            }
            .title {
                color: #ffffff;
                font-size: 1.8rem;
                margin: 0;
                font-weight: bold;
            }
            .content {
                color: #ffffff;
                line-height: 1.7;
                margin-bottom: 30px;
                font-size: 16px;
            }
            .content p {
                color: #ffffff;
                margin-bottom: 15px;
            }
            .btn-reset {
                display: inline-block;
                background: linear-gradient(45deg, #8a2be2, #bb86fc);
                color: #ffffff !important;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                font-size: 18px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3);
                box-shadow: 0 4px 15px rgba(138, 43, 226, 0.4);
                transition: all 0.3s ease;
            }
            .btn-reset:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(138, 43, 226, 0.6);
            }
            .instructions {
                background-color: #1a2d1a;
                border: 2px solid #4caf50;
                border-radius: 10px;
                padding: 20px;
                margin: 25px 0;
            }
            .instructions-title {
                color: #4caf50;
                font-weight: bold;
                font-size: 1.1rem;
                margin-bottom: 10px;
            }
            .instructions p {
                color: #ffffff;
                margin: 0;
            }
            .warning {
                background-color: #2d1810;
                border: 2px solid #ff6b35;
                border-radius: 10px;
                padding: 20px;
                margin: 25px 0;
            }
            .warning-title {
                color: #ff6b35;
                font-weight: bold;
                font-size: 1.1rem;
                margin-bottom: 10px;
            }
            .warning p {
                color: #ffffff;
                margin: 0;
                font-size: 15px;
            }
            .footer {
                text-align: center;
                font-size: 14px;
                color: #cccccc;
                border-top: 1px solid #444444;
                padding-top: 20px;
                margin-top: 30px;
            }
            .footer p {
                color: #cccccc;
                margin: 5px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>🎌 AnimeGon</div>
                <h1 class='title'>🔑 Recuperar Contraseña</h1>
            </div>
            
            <div class='content'>
                <p>Hola <strong style='color: #8a2be2;'>{$usuario['nombre']}</strong>,</p>
                
                <p>Has solicitado restablecer tu contraseña de AnimeGon. Para crear una nueva contraseña, haz clic en el botón de abajo:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$reset_url}' class='btn-reset'>
                        🔑 Crear Nueva Contraseña
                    </a>
                </div>
                
                <div class='instructions'>
                    <div class='instructions-title'>📋 Instrucciones:</div>
                    <p>1. Haz clic en el botón \"Crear Nueva Contraseña\"<br>
                    2. Se abrirá una página segura donde podrás establecer tu nueva contraseña<br>
                    3. Ingresa tu nueva contraseña dos veces para confirmarla<br>
                    4. ¡Listo! Podrás iniciar sesión con tu nueva contraseña</p>
                </div>
                
                <div class='warning'>
                    <div class='warning-title'>⚠️ Importante:</div>
                    <p>Este enlace de recuperación expira en <strong>15 minutos</strong> por seguridad. Si no lo usas en ese tiempo, deberás solicitar uno nuevo.</p>
                </div>
                
                <div style='background-color: #1a2d1a; border: 2px solid #4caf50; border-radius: 10px; padding: 15px; margin: 20px 0; font-size: 14px;'>
                    <p style='color: #4caf50; font-weight: bold; margin: 0 0 8px 0;'>🔒 Enlace Seguro:</p>
                    <p style='color: #ffffff; margin: 0; word-break: break-all; font-family: monospace; font-size: 12px;'>{$reset_url}</p>
                </div>
                
                <p style='text-align: center; margin-top: 30px;'>
                    <strong style='color: #ffffff;'>¡Disfruta viendo anime en AnimeGon! 🎌</strong>
                </p>
            </div>
            
            <div class='footer'>
                <p>Este email fue enviado automáticamente desde AnimeGon</p>
                <p>📅 " . date('d/m/Y H:i:s') . "</p>
                <p>No respondas a este correo</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Headers para email HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: AnimeGon <noreply@animegon.alwaysdata.net>" . "\r\n";
    $headers .= "Reply-To: noreply@animegon.alwaysdata.net" . "\r\n";
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
        error_log("Enlace de recuperación enviado para usuario ID: {$usuario_id}, Email: {$to}, Token: {$token}");
        
        echo json_encode([
            'success' => true,
            'message' => "📧 Enlace de recuperación enviado a {$to}. Revisa tu bandeja de entrada y carpeta de spam. <br><small>El enlace expira en 15 minutos.</small>"
        ]);
    } else {
        // En desarrollo local, mostrar el enlace para poder probar
        error_log("Email no enviado. URL de recuperación: {$reset_url}");
        
        echo json_encode([
            'success' => true,
            'message' => "✅ Enlace de recuperación generado exitosamente.<br><small>En producción se enviaría por email.</small>",
            'dev_url' => $reset_url // Solo para desarrollo
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error en recuperar_password_public.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>