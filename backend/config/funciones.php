<?php
// funciones.php - Funciones para el sistema de autenticación

require_once 'config.php';

// Iniciar sesión si no está iniciada
function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Verificar si el usuario está logueado
function usuarioLogueado() {
    iniciarSesion();
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

// Redirigir si no está logueado
function requiereSesion($redirigir = 'login.php') {
    if (!usuarioLogueado()) {
        header("Location: $redirigir");
        exit();
    }
}

// Redirigir si ya está logueado
function redirigirSiLogueado($redirigir = 'dashboard.php') {
    if (usuarioLogueado()) {
        header("Location: $redirigir");
        exit();
    }
}

// Validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validar username (solo letras, números y guión bajo)
function validarUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

// Validar contraseña (mínimo 6 caracteres)
function validarPassword($password) {
    return strlen($password) >= 6;
}

// Verificar si un username ya existe
function usernameExiste($username) {
    $conexion = obtenerConexion();
    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->rowCount() > 0;
}

// Verificar si un email ya existe
function emailExiste($email) {
    $conexion = obtenerConexion();
    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->rowCount() > 0;
}

// Registrar un nuevo usuario
function registrarUsuario($username, $email, $password, $nombre) {
    // Validaciones
    if (!validarUsername($username)) {
        return ['exito' => false, 'mensaje' => 'El username debe tener entre 3-20 caracteres (solo letras, números y _)'];
    }
    
    if (!validarEmail($email)) {
        return ['exito' => false, 'mensaje' => 'El email no es válido'];
    }
    
    if (!validarPassword($password)) {
        return ['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres'];
    }
    
    if (empty($nombre)) {
        return ['exito' => false, 'mensaje' => 'El nombre es obligatorio'];
    }
    
    // Verificar duplicados
    if (usernameExiste($username)) {
        return ['exito' => false, 'mensaje' => 'El username ya está en uso'];
    }
    
    if (emailExiste($email)) {
        return ['exito' => false, 'mensaje' => 'El email ya está registrado'];
    }
    
    try {
        $conexion = obtenerConexion();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conexion->prepare("INSERT INTO usuarios (username, email, password, nombre) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $passwordHash, $nombre]);
        
        return ['exito' => true, 'mensaje' => 'Usuario registrado exitosamente'];
    } catch (PDOException $e) {
        return ['exito' => false, 'mensaje' => 'Error al registrar usuario: ' . $e->getMessage()];
    }
}

// Autenticar usuario
function autenticarUsuario($username, $password) {
    try {
        $conexion = obtenerConexion();
        $stmt = $conexion->prepare("SELECT id, username, email, password, nombre FROM usuarios WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        
        if ($stmt->rowCount() === 1) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $usuario['password'])) {
                // Actualizar último acceso
                $updateStmt = $conexion->prepare("UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->execute([$usuario['id']]);
                
                // Iniciar sesión
                iniciarSesion();
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['username'] = $usuario['username'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['nombre'] = $usuario['nombre'];
                
                return ['exito' => true, 'mensaje' => 'Login exitoso'];
            }
        }
        
        return ['exito' => false, 'mensaje' => 'Username/email o contraseña incorrectos'];
    } catch (PDOException $e) {
        return ['exito' => false, 'mensaje' => 'Error en la autenticación: ' . $e->getMessage()];
    }
}

// Cerrar sesión
function cerrarSesion() {
    iniciarSesion();
    session_unset();
    session_destroy();
}

// Obtener datos del usuario actual
function obtenerUsuarioActual() {
    if (!usuarioLogueado()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'nombre' => $_SESSION['nombre']
    ];
}

// Escapar HTML para mostrar de forma segura
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>