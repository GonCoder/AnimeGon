<?php
// registro.php - Página de registro de usuarios

require_once '../backend/config/funciones.php';

// Redirigir si ya está logueado
redirigirSiLogueado();

$mensaje = '';
$tipoMensaje = '';

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    
    // Validar que las contraseñas coincidan
    if ($password !== $confirmPassword) {
        $mensaje = 'Las contraseñas no coinciden';
        $tipoMensaje = 'error';
    } elseif (!empty($username) && !empty($email) && !empty($password) && !empty($nombre)) {
        $resultado = registrarUsuario($username, $email, $password, $nombre);
        
        if ($resultado['exito']) {
            $mensaje = $resultado['mensaje'] . '. Ahora puedes iniciar sesión.';
            $tipoMensaje = 'exito';
            // Limpiar campos después del registro exitoso
            $_POST = [];
        } else {
            $mensaje = $resultado['mensaje'];
            $tipoMensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor, completa todos los campos';
        $tipoMensaje = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Registro</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../favicon.svg">
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="register-box">
            <div class="register-header">
                <h1>AnimeGon</h1>
                <h2>Crear Cuenta</h2>
            </div>

            <?php if ($mensaje): ?>
                <div class="mensaje <?= $tipoMensaje ?>">
                    <?= escape($mensaje) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="registro.php" class="register-form">
                <div class="input-group">
                    <label for="nombre">Nombre Completo:</label>
                    <input 
                        type="text" 
                        id="nombre" 
                        name="nombre" 
                        value="<?= escape($_POST['nombre'] ?? '') ?>"
                        placeholder="Tu nombre completo"
                        required
                        maxlength="100"
                    >
                </div>

                <div class="input-group">
                    <label for="username">Username:</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?= escape($_POST['username'] ?? '') ?>"
                        placeholder="Elige un username único"
                        required
                        pattern="[a-zA-Z0-9_]{3,20}"
                        title="3-20 caracteres: letras, números y guión bajo"
                        maxlength="20"
                    >
                    <small>3-20 caracteres: solo letras, números y guión bajo (_)</small>
                </div>

                <div class="input-group">
                    <label for="email">Email:</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= escape($_POST['email'] ?? '') ?>"
                        placeholder="tu@email.com"
                        required
                        maxlength="100"
                    >
                </div>

                <div class="input-group">
                    <label for="password">Contraseña:</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Mínimo 6 caracteres"
                        required
                        minlength="6"
                    >
                </div>

                <div class="input-group">
                    <label for="confirm_password">Confirmar Contraseña:</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Repite tu contraseña"
                        required
                        minlength="6"
                    >
                </div>

                <button type="submit" class="btn-register">Crear Cuenta</button>
            </form>

            <div class="register-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
                <p><a href="index.php">Volver al inicio</a></p>
            </div>
        </div>
    </div>

    <script>
        // Enfocar el primer input al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('nombre').focus();
        });

        // Validación en tiempo real de contraseñas
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        function validarContraseñas() {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Las contraseñas no coinciden');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        password.addEventListener('input', validarContraseñas);
        confirmPassword.addEventListener('input', validarContraseñas);

        // Validación del username en tiempo real
        const username = document.getElementById('username');
        username.addEventListener('input', function() {
            const value = this.value;
            const regex = /^[a-zA-Z0-9_]{3,20}$/;
            
            if (value && !regex.test(value)) {
                this.setCustomValidity('Username debe tener 3-20 caracteres (letras, números y _)');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validación del formulario antes del envío
        document.querySelector('.register-form').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (!nombre || !username || !email || !password || !confirmPassword) {
                e.preventDefault();
                alert('Por favor, completa todos los campos');
                return false;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
                return false;
            }
        });
    </script>
</body>
</html>