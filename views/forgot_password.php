<?php
// forgot_password.php - Página para solicitar recuperación de contraseña por email

require_once '../backend/config/funciones.php';

// Si ya está logueado, redirigir al dashboard
redirigirSiLogueado('dashboard.php');

$mensaje = '';
$tipoMensaje = '';

$mensaje = '';
$tipoMensaje = '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - ¿Has olvidado tu contraseña?</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../favicon.svg">
    
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
        .forgot-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f0f23);
        }
        
        .forgot-card {
            background: rgba(26, 26, 46, 0.95);
            border: 2px solid #8a2be2;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 0 30px rgba(138, 43, 226, 0.5);
            backdrop-filter: blur(10px);
        }
        
        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .forgot-logo {
            font-size: 3rem;
            color: #8a2be2;
            margin-bottom: 15px;
            text-shadow: 0 0 20px rgba(138, 43, 226, 0.6);
        }
        
        .forgot-title {
            color: #ffffff;
            font-size: 1.8rem;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        
        .forgot-subtitle {
            color: #bb86fc;
            font-size: 1.1rem;
            margin: 0 0 10px 0;
        }
        
        .forgot-description {
            color: #cccccc;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 25px;
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
            padding: 15px;
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
        
        .btn-send-recovery {
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
        
        .btn-send-recovery:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(138, 43, 226, 0.6);
        }
        
        .btn-send-recovery:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-links {
            text-align: center;
            margin-top: 25px;
        }
        
        .back-link {
            display: inline-block;
            color: #8a2be2;
            text-decoration: none;
            margin: 5px 10px;
            padding: 8px 16px;
            border: 1px solid #8a2be2;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .back-link:hover {
            background: rgba(138, 43, 226, 0.1);
        }
        
        .info-box {
            background: rgba(76, 175, 80, 0.1);
            border: 2px solid #4caf50;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-box h4 {
            color: #4caf50;
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }
        
        .info-box ul {
            color: #ffffff;
            margin: 0;
            padding-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: rgba(46, 213, 115, 0.15);
            border: 2px solid #2ed573;
            color: #2ed573;
        }
        
        .message.error {
            background: rgba(255, 71, 87, 0.15);
            border: 2px solid #ff4757;
            color: #ff4757;
        }
        
        .message.info {
            background: rgba(54, 215, 183, 0.15);
            border: 2px solid #36d7b7;
            color: #36d7b7;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <div class="forgot-logo">🔑</div>
                <h1 class="forgot-title">¿Has olvidado tu contraseña?</h1>
                <p class="forgot-subtitle">AnimeGon</p>
                <p class="forgot-description">
                    No te preocupes, te ayudamos a recuperar el acceso a tu cuenta.
                </p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="message <?= $tipoMensaje ?>">
                    <?= $mensaje ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="forgot_password.php" id="forgotForm">
                <div class="form-group">
                    <label for="email" class="form-label">📧 Tu Email</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="ejemplo@correo.com" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required>
                </div>
                
                <button type="submit" class="btn-send-recovery" id="submitBtn">
                    📧 Enviar Enlace de Recuperación
                </button>
            </form>
            
            <div class="info-box">
                <h4>ℹ️ ¿Cómo funciona?</h4>
                <ul>
                    <li>Ingresa el email asociado a tu cuenta</li>
                    <li>Recibirás un enlace seguro por correo</li>
                    <li>Haz clic en el enlace para crear una nueva contraseña</li>
                    <li>El enlace expira en 15 minutos por seguridad</li>
                </ul>
            </div>
            
            <div class="back-links">
                <a href="login.php" class="back-link">🔙 Volver al Login</a>
                <a href="registro.php" class="back-link">📝 Crear Cuenta</a>
            </div>
        </div>
    </div>

    <!-- Script para manejar el formulario -->
    <script>
        // Función para mostrar mensajes
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
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotForm');
            const emailInput = document.getElementById('email');
            const submitBtn = document.getElementById('submitBtn');
            
            // Enfocar el input de email al cargar
            emailInput.focus();
            
            // Validación del email en tiempo real
            emailInput.addEventListener('input', function() {
                const email = emailInput.value.trim();
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailPattern.test(email)) {
                    emailInput.setCustomValidity('Por favor ingresa un email válido');
                } else {
                    emailInput.setCustomValidity('');
                }
            });
            
            // Manejar envío del formulario con AJAX
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const email = emailInput.value.trim();
                    
                    if (!email) {
                        mostrarMensaje('Por favor ingresa tu email', 'error');
                        return;
                    }
                    
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        mostrarMensaje('Por favor ingresa un email válido', 'error');
                        return;
                    }
                    
                    // Cambiar el botón mientras se procesa
                    const originalText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = '📤 Enviando...';
                    
                    try {
                        const formData = new FormData();
                        formData.append('email', email);
                        
                        const response = await fetch('../backend/api/recuperar_password_public.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            mostrarMensaje(result.message, 'success');
                            
                            // Limpiar el formulario
                            emailInput.value = '';
                            
                            // Si hay URL de desarrollo, mostrarla
                            if (result.dev_url) {
                                setTimeout(() => {
                                    mostrarMensaje(`🔗 Enlace de prueba: <a href="${result.dev_url}" target="_blank" style="color: #8a2be2;">${result.dev_url}</a>`, 'info');
                                }, 1000);
                            }
                        } else {
                            mostrarMensaje(result.error || 'Error al procesar la solicitud', 'error');
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