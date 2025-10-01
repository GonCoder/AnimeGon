<?php
// reset_password.php - Página para restablecer contraseña con token

require_once '../backend/config/funciones.php';

// Verificar que se reciba un token
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = "Token de recuperación no válido o faltante.";
    $token_valido = false;
} else {
    $token = $_GET['token'];
    
    try {
        // Verificar que el token existe y no ha expirado
        $conexion = obtenerConexion();
        $query = "SELECT rt.*, u.nombre, u.email 
                  FROM password_reset_tokens rt 
                  INNER JOIN usuarios u ON rt.usuario_id = u.id 
                  WHERE rt.token = ? AND rt.fecha_expiracion > NOW() AND rt.usado = FALSE";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$token]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token_data) {
            $error = "El enlace de recuperación ha expirado o no es válido. Solicita uno nuevo.";
            $token_valido = false;
        } else {
            $token_valido = true;
            $usuario_nombre = $token_data['nombre'];
            $usuario_email = $token_data['email'];
            $usuario_id = $token_data['usuario_id'];
        }
        
    } catch (Exception $e) {
        error_log("Error al verificar token de recuperación: " . $e->getMessage());
        $error = "Error interno del servidor. Inténtalo más tarde.";
        $token_valido = false;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Restablecer Contraseña</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../favicon.svg">
    
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f0f23);
        }
        
        .reset-card {
            background: rgba(26, 26, 46, 0.95);
            border: 2px solid #8a2be2;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 0 30px rgba(138, 43, 226, 0.5);
            backdrop-filter: blur(10px);
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-logo {
            font-size: 3rem;
            color: #8a2be2;
            margin-bottom: 15px;
            text-shadow: 0 0 20px rgba(138, 43, 226, 0.6);
        }
        
        .reset-title {
            color: #ffffff;
            font-size: 1.8rem;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        
        .reset-subtitle {
            color: #bb86fc;
            font-size: 1.1rem;
            margin: 0;
        }
        
        .user-info {
            background: rgba(138, 43, 226, 0.1);
            border: 1px solid #bb86fc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .user-info p {
            color: #ffffff;
            margin: 5px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            color: #bb86fc;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid #444;
            border-radius: 8px;
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 10px rgba(138, 43, 226, 0.3);
        }
        
        .password-requirements {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid #4caf50;
            border-radius: 6px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        .password-requirements h4 {
            color: #4caf50;
            margin: 0 0 8px 0;
            font-size: 0.95rem;
        }
        
        .password-requirements ul {
            color: #ffffff;
            margin: 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            margin-bottom: 3px;
        }
        
        .btn-reset-password {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #8a2be2, #bb86fc);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-reset-password:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(138, 43, 226, 0.6);
        }
        
        .btn-reset-password:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .error-message {
            background: rgba(255, 71, 87, 0.15);
            border: 2px solid #ff4757;
            border-radius: 8px;
            padding: 20px;
            color: #ff4757;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .error-message h3 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
        }
        
        .back-link {
            display: inline-block;
            color: #8a2be2;
            text-decoration: none;
            margin-top: 15px;
            padding: 8px 16px;
            border: 1px solid #8a2be2;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: rgba(138, 43, 226, 0.1);
        }
        
        .password-strength {
            height: 4px;
            background: #333;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #ff4757; }
        .strength-medium { background: #ffa502; }
        .strength-strong { background: #2ed573; }
        
        /* Animación para el botón de éxito */
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(138, 43, 226, 0.7);
            }
            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(138, 43, 226, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(138, 43, 226, 0);
            }
        }
        
        /* Estilo adicional para mensajes de éxito */
        .password-success-message {
            animation: slideInFromBottom 0.5s ease-out;
        }
        
        @keyframes slideInFromBottom {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-logo">🎌</div>
                <h1 class="reset-title">AnimeGon</h1>
                <p class="reset-subtitle">Restablecer Contraseña</p>
            </div>
            
            <?php if (!$token_valido): ?>
                <div class="error-message">
                    <h3>🚫 Enlace No Válido</h3>
                    <p><?= htmlspecialchars($error) ?></p>
                    <a href="login.php" class="back-link">🔙 Volver al Login</a>
                </div>
            <?php else: ?>
                <div class="user-info">
                    <p><strong>Usuario:</strong> <?= htmlspecialchars($usuario_nombre) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($usuario_email) ?></p>
                </div>
                
                <form id="resetPasswordForm" method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($usuario_id) ?>">
                    
                    <div class="form-group">
                        <label for="nueva_password" class="form-label">🔑 Nueva Contraseña</label>
                        <input type="password" id="nueva_password" name="nueva_password" class="form-input" 
                               placeholder="Ingresa tu nueva contraseña" required minlength="6">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="password-requirements">
                            <h4>📋 Requisitos de la contraseña:</h4>
                            <ul>
                                <li>Mínimo 6 caracteres</li>
                                <li>Se recomienda incluir letras y números</li>
                                <li>Evita usar información personal</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmar_password" class="form-label">🔑 Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirmar_password" name="confirmar_password" class="form-input" 
                               placeholder="Confirma tu nueva contraseña" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn-reset-password" id="submitBtn">
                        🔄 Actualizar Contraseña
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="back-link">🔙 Volver al Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Script para manejar el formulario -->
    <script>
        // Función para mostrar mensajes (reemplaza notifications.js)
        function mostrarMensaje(texto, tipo = 'success') {
            // Remover mensajes existentes
            const mensajesExistentes = document.querySelectorAll('.notification-message');
            mensajesExistentes.forEach(msg => msg.remove());
            
            // Crear el elemento del mensaje
            const mensaje = document.createElement('div');
            mensaje.className = 'notification-message';
            
            // Estilos según el tipo
            let backgroundColor, borderColor, textColor;
            switch(tipo) {
                case 'success':
                    backgroundColor = 'rgba(46, 213, 115, 0.15)';
                    borderColor = '#2ed573';
                    textColor = '#2ed573';
                    break;
                case 'error':
                    backgroundColor = 'rgba(255, 71, 87, 0.15)';
                    borderColor = '#ff4757';
                    textColor = '#ff4757';
                    break;
                case 'info':
                    backgroundColor = 'rgba(54, 215, 183, 0.15)';
                    borderColor = '#36d7b7';
                    textColor = '#36d7b7';
                    break;
                default:
                    backgroundColor = 'rgba(138, 43, 226, 0.15)';
                    borderColor = '#8a2be2';
                    textColor = '#8a2be2';
            }
            
            mensaje.style.cssText = `
                position: fixed;
                top: 20px;
                left: 20px;
                right: 20px;
                max-width: 600px;
                margin: 0 auto;
                background: ${backgroundColor};
                border: 2px solid ${borderColor};
                border-radius: 10px;
                padding: 15px 20px;
                color: ${textColor};
                font-weight: bold;
                z-index: 10000;
                animation: slideInFromTop 0.5s ease-out;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                cursor: pointer;
            `;
            
            mensaje.innerHTML = texto;
            
            // Agregar al documento
            document.body.appendChild(mensaje);
            
            // Cerrar al hacer clic
            mensaje.addEventListener('click', () => {
                mensaje.style.animation = 'slideOutToTop 0.3s ease-in';
                setTimeout(() => mensaje.remove(), 300);
            });
            
            // Auto cerrar después de 5 segundos
            setTimeout(() => {
                if (mensaje.parentNode) {
                    mensaje.style.animation = 'slideOutToTop 0.3s ease-in';
                    setTimeout(() => mensaje.remove(), 300);
                }
            }, 5000);
        }
        
        // Agregar estilos para las animaciones
        if (!document.querySelector('#notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideInFromTop {
                    from {
                        opacity: 0;
                        transform: translateY(-100px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                @keyframes slideOutToTop {
                    from {
                        opacity: 1;
                        transform: translateY(0);
                    }
                    to {
                        opacity: 0;
                        transform: translateY(-100px);
                    }
                }
            `;
            document.head.appendChild(style);
        }
    </script>
    <script>
        // Función para limpiar datos de sesión/cache
        function limpiarDatosNavegador() {
            // Limpiar localStorage/sessionStorage
            if (typeof(Storage) !== "undefined") {
                localStorage.clear();
                sessionStorage.clear();
            }
            
            // Limpiar cache si está disponible
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => {
                        caches.delete(name);
                    });
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            const nuevaPassword = document.getElementById('nueva_password');
            const confirmarPassword = document.getElementById('confirmar_password');
            const submitBtn = document.getElementById('submitBtn');
            const strengthBar = document.getElementById('strengthBar');
            
            // Validación de fuerza de contraseña
            function checkPasswordStrength(password) {
                let strength = 0;
                
                if (password.length >= 6) strength += 1;
                if (password.length >= 8) strength += 1;
                if (/[a-z]/.test(password)) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                return Math.min(strength, 3);
            }
            
            function updatePasswordStrength() {
                const password = nuevaPassword.value;
                const strength = checkPasswordStrength(password);
                const percentage = (strength / 3) * 100;
                
                strengthBar.style.width = percentage + '%';
                strengthBar.className = 'password-strength-bar';
                
                if (strength === 1) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength === 2) {
                    strengthBar.classList.add('strength-medium');
                } else if (strength === 3) {
                    strengthBar.classList.add('strength-strong');
                }
            }
            
            function validatePasswords() {
                const nueva = nuevaPassword.value;
                const confirmar = confirmarPassword.value;
                
                // Verificar longitud mínima
                if (nueva.length < 6) {
                    confirmarPassword.setCustomValidity('La contraseña debe tener al menos 6 caracteres');
                    return false;
                }
                
                // Verificar que coincidan
                if (nueva !== confirmar) {
                    confirmarPassword.setCustomValidity('Las contraseñas no coinciden');
                    return false;
                }
                
                confirmarPassword.setCustomValidity('');
                return true;
            }
            
            // Event listeners
            nuevaPassword.addEventListener('input', function() {
                updatePasswordStrength();
                if (confirmarPassword.value) {
                    validatePasswords();
                }
            });
            
            confirmarPassword.addEventListener('input', validatePasswords);
            
            // Envío del formulario
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    if (!validatePasswords()) {
                        mostrarMensaje('Las contraseñas no coinciden o no cumplen los requisitos', 'error');
                        return;
                    }
                    
                    const originalText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = '🔄 Actualizando...';
                    
                    try {
                        const formData = new FormData(form);
                        const response = await fetch('../backend/api/cambiar_password.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Mostrar mensaje de éxito
                            mostrarMensaje(result.message, 'success');
                            
                            // Si se requiere logout, limpiar cualquier sesión local
                            if (result.logout) {
                                limpiarDatosNavegador();
                            }
                            
                            // Ocultar el formulario y mostrar mensaje de éxito con instrucciones
                            form.style.display = 'none';
                            
                            // Crear mensaje de éxito completo
                            const successContainer = document.createElement('div');
                            successContainer.className = 'password-success-message';
                            successContainer.innerHTML = `
                                <div style="background: rgba(46, 213, 115, 0.15); border: 2px solid #2ed573; border-radius: 10px; padding: 25px; text-align: center; margin: 20px 0;">
                                    <div style="font-size: 3rem; margin-bottom: 15px;">✅</div>
                                    <h3 style="color: #2ed573; margin: 0 0 15px 0; font-size: 1.4rem;">¡Contraseña Actualizada Exitosamente!</h3>
                                    <p style="color: #ffffff; margin: 0 0 15px 0; font-size: 1.1rem;">
                                        Tu nueva contraseña ha sido guardada correctamente.
                                    </p>
                                    <p style="color: #ffffff; margin: 0 0 20px 0; font-size: 1rem;">
                                        Por seguridad, tu sesión ha sido cerrada automáticamente.
                                    </p>
                                    <div style="background: rgba(138, 43, 226, 0.1); border: 1px solid #8a2be2; border-radius: 8px; padding: 15px; margin: 15px 0;">
                                        <p style="color: #bb86fc; margin: 0; font-weight: bold; font-size: 1.1rem;">
                                            👇 Haz clic en "Volver al Login" para iniciar sesión con tu nueva contraseña
                                        </p>
                                    </div>
                                </div>
                            `;
                            
                            // Insertar el mensaje después del formulario
                            form.parentNode.insertBefore(successContainer, form.nextSibling);
                            
                            // Hacer más visible el botón de volver al login
                            const backLink = document.querySelector('.back-link');
                            if (backLink) {
                                backLink.style.background = 'linear-gradient(45deg, #8a2be2, #bb86fc)';
                                backLink.style.color = '#ffffff';
                                backLink.style.padding = '12px 25px';
                                backLink.style.fontSize = '1.1rem';
                                backLink.style.fontWeight = 'bold';
                                backLink.style.textTransform = 'uppercase';
                                backLink.style.animation = 'pulse 2s infinite';
                                backLink.innerHTML = '🚀 Volver al Login - ¡Usar Nueva Contraseña!';
                            }
                            
                        } else {
                            // Mostrar mensaje de error detallado
                            mostrarMensaje(result.error || 'Error al actualizar la contraseña', 'error');
                            
                            // Mostrar mensaje adicional de ayuda
                            setTimeout(() => {
                                const errorHelp = document.createElement('div');
                                errorHelp.innerHTML = `
                                    <div style="background: rgba(255, 193, 7, 0.15); border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 15px 0; text-align: center;">
                                        <p style="color: #ffc107; margin: 0; font-weight: bold;">💡 ¿Necesitas ayuda?</p>
                                        <p style="color: #ffffff; margin: 10px 0 0 0; font-size: 0.95rem;">
                                            El enlace puede haber expirado (15 minutos). Solicita un nuevo enlace de recuperación.
                                        </p>
                                    </div>
                                `;
                                
                                // Buscar donde insertar el mensaje de ayuda
                                const messageContainer = document.querySelector('.reset-card');
                                if (messageContainer) {
                                    messageContainer.appendChild(errorHelp);
                                }
                            }, 1000);
                        }
                        
                    } catch (error) {
                        console.error('Error:', error);
                        mostrarMensaje('Error de conexión. Inténtalo de nuevo.', 'error');
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                });
            }
        });
    </script>
</body>
</html>