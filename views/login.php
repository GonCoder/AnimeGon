<?php
// login.php - Página de inicio de sesión

require_once '../backend/config/funciones.php';

// Redirigir si ya está logueado
redirigirSiLogueado();

$mensaje = '';
$tipoMensaje = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $resultado = autenticarUsuario($username, $password);
        
        if ($resultado['exito']) {
            header('Location: dashboard.php');
            exit();
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
    <title>AnimeGon - Iniciar Sesión</title>
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <div class="login-header">
                <h1>AnimeGon</h1>
                <h2>Iniciar Sesión</h2>
            </div>

            <?php if ($mensaje): ?>
                <div class="mensaje <?= $tipoMensaje ?>">
                    <?= escape($mensaje) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">
                <div class="input-group">
                    <label for="username">Username o Email:</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?= escape($_POST['username'] ?? '') ?>"
                        placeholder="Ingresa tu username o email"
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="password">Contraseña:</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Ingresa tu contraseña"
                        required
                    >
                </div>

                <button type="submit" class="btn-login">Iniciar Sesión</button>
            </form>

            <div class="login-footer">
                <p>¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
                <p><a href="index.php">Volver al inicio</a></p>
            </div>
        </div>
    </div>

    <script>
        // Enfocar el primer input al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Validación básica del lado del cliente
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                e.preventDefault();
                alert('Por favor, completa todos los campos');
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