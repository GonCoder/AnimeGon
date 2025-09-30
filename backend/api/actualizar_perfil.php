<?php
session_start();
require_once '../config/config.php';
require_once '../config/funciones.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
       } catch (PDOException $e) {
        error_log("Error al cambiar contraseña: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar la contraseña'];
    }
}

// Función para cambiar el username
function cambiarUsername($conexion, $usuario_id, $datos) {
    try {
        // Validar datos de entrada
        $nuevo_username = trim($datos['nuevo_username'] ?? '');
        $password_actual = trim($datos['password_username'] ?? '');
        
        if (empty($nuevo_username) || empty($password_actual)) {
            return ['success' => false, 'message' => 'Todos los campos son obligatorios'];
        }
        
        // Validaciones del username
        if (strlen($nuevo_username) < 3 || strlen($nuevo_username) > 30) {
            return ['success' => false, 'message' => 'El nombre de usuario debe tener entre 3 y 30 caracteres'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $nuevo_username)) {
            return ['success' => false, 'message' => 'El nombre de usuario solo puede contener letras, números y guiones bajos'];
        }
        
        // Verificar contraseña actual
        $query_user = "SELECT password, username FROM usuarios WHERE id = ?";
        $stmt_user = $conexion->prepare($query_user);
        $stmt_user->execute([$usuario_id]);
        $usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario || !password_verify($password_actual, $usuario['password'])) {
            return ['success' => false, 'message' => 'La contraseña actual es incorrecta'];
        }
        
        // Verificar si el nuevo username ya existe
        if (strtolower($nuevo_username) !== strtolower($usuario['username'])) {
            $query_check = "SELECT id FROM usuarios WHERE LOWER(username) = LOWER(?) AND id != ?";
            $stmt_check = $conexion->prepare($query_check);
            $stmt_check->execute([$nuevo_username, $usuario_id]);
            
            if ($stmt_check->rowCount() > 0) {
                return ['success' => false, 'message' => 'Este nombre de usuario ya está en uso'];
            }
        }
        
        // Actualizar el username
        $query = "UPDATE usuarios SET username = ? WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$nuevo_username, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Nombre de usuario actualizado correctamente'
            ];
        } else {
            return ['success' => false, 'message' => 'No se realizaron cambios. El nombre de usuario es el mismo'];
        }
        
    } catch (PDOException $e) {
        error_log("Error al cambiar username: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error de base de datos al cambiar nombre de usuario'];
    }
}
?>json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$accion = $_POST['accion'] ?? '';

try {
    $conexion = obtenerConexion();
    
    switch ($accion) {
        case 'cambiar_nombre':
            $resultado = cambiarNombre($conexion, $usuario_id, $_POST);
            break;
            
        case 'cambiar_email':
            $resultado = cambiarEmail($conexion, $usuario_id, $_POST);
            break;
            
        case 'cambiar_password':
            $resultado = cambiarPassword($conexion, $usuario_id, $_POST);
            break;
            
        case 'cambiar_username':
            $resultado = cambiarUsername($conexion, $usuario_id, $_POST);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    error_log("Error en actualizar_perfil.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}

function cambiarNombre($conexion, $usuario_id, $datos) {
    $nuevo_nombre = trim($datos['nuevo_nombre'] ?? '');
    
    // Validaciones
    if (empty($nuevo_nombre)) {
        return ['success' => false, 'message' => 'El nombre no puede estar vacío'];
    }
    
    if (strlen($nuevo_nombre) < 3) {
        return ['success' => false, 'message' => 'El nombre debe tener al menos 3 caracteres'];
    }
    
    if (strlen($nuevo_nombre) > 50) {
        return ['success' => false, 'message' => 'El nombre no puede tener más de 50 caracteres'];
    }
    
    // Verificar que el nombre no contenga caracteres especiales peligrosos
    if (!preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\-_\.]+$/', $nuevo_nombre)) {
        return ['success' => false, 'message' => 'El nombre contiene caracteres no permitidos'];
    }
    
    try {
        // Verificar si el nombre ya existe (caso insensitivo)
        $query_check = "SELECT id FROM usuarios WHERE LOWER(nombre) = LOWER(?) AND id != ?";
        $stmt_check = $conexion->prepare($query_check);
        $stmt_check->execute([$nuevo_nombre, $usuario_id]);
        
        if ($stmt_check->rowCount() > 0) {
            return ['success' => false, 'message' => 'Este nombre ya está en uso'];
        }
        
        // Actualizar el nombre
        $query = "UPDATE usuarios SET nombre = ? WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$nuevo_nombre, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            // Actualizar la sesión
            $_SESSION['usuario_nombre'] = $nuevo_nombre;
            
            return [
                'success' => true,
                'message' => 'Nombre actualizado correctamente'
            ];
        } else {
            return ['success' => false, 'message' => 'No se realizaron cambios'];
        }
        
    } catch (PDOException $e) {
        error_log("Error al cambiar nombre: " . $e->getMessage());
        error_log("Error details: " . print_r($e, true));
        return ['success' => false, 'message' => 'Error de base de datos al cambiar nombre: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("Error general al cambiar nombre: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error general: ' . $e->getMessage()];
    }
}

function cambiarEmail($conexion, $usuario_id, $datos) {
    $nuevo_email = trim($datos['nuevo_email'] ?? '');
    $password_actual = $datos['password_email'] ?? '';
    
    // Validaciones
    if (empty($nuevo_email)) {
        return ['success' => false, 'message' => 'El email no puede estar vacío'];
    }
    
    if (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El formato del email no es válido'];
    }
    
    if (empty($password_actual)) {
        return ['success' => false, 'message' => 'Debes confirmar tu contraseña actual'];
    }
    
    try {
        // Verificar la contraseña actual
        $query_user = "SELECT email, password FROM usuarios WHERE id = ?";
        $stmt_user = $conexion->prepare($query_user);
        $stmt_user->execute([$usuario_id]);
        $usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario || !password_verify($password_actual, $usuario['password'])) {
            return ['success' => false, 'message' => 'La contraseña actual es incorrecta'];
        }
        
        // Verificar si el email ya existe
        $query_check = "SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?) AND id != ?";
        $stmt_check = $conexion->prepare($query_check);
        $stmt_check->execute([$nuevo_email, $usuario_id]);
        
        if ($stmt_check->rowCount() > 0) {
            return ['success' => false, 'message' => 'Este email ya está registrado'];
        }
        
        // Actualizar el email
        $query = "UPDATE usuarios SET email = ? WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$nuevo_email, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Email actualizado correctamente'
            ];
        } else {
            return ['success' => false, 'message' => 'No se realizaron cambios'];
        }
        
    } catch (PDOException $e) {
        error_log("Error al cambiar email: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar el email'];
    }
}

function cambiarPassword($conexion, $usuario_id, $datos) {
    $password_actual = $datos['password_actual'] ?? '';
    $password_nueva = $datos['password_nueva'] ?? '';
    $password_confirmar = $datos['password_confirmar'] ?? '';
    
    // Validaciones
    if (empty($password_actual)) {
        return ['success' => false, 'message' => 'Debes ingresar tu contraseña actual'];
    }
    
    if (empty($password_nueva)) {
        return ['success' => false, 'message' => 'Debes ingresar una nueva contraseña'];
    }
    
    if (strlen($password_nueva) < 6) {
        return ['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres'];
    }
    
    if ($password_nueva !== $password_confirmar) {
        return ['success' => false, 'message' => 'Las contraseñas no coinciden'];
    }
    
    if ($password_actual === $password_nueva) {
        return ['success' => false, 'message' => 'La nueva contraseña debe ser diferente a la actual'];
    }
    
    try {
        // Verificar la contraseña actual
        $query_user = "SELECT password FROM usuarios WHERE id = ?";
        $stmt_user = $conexion->prepare($query_user);
        $stmt_user->execute([$usuario_id]);
        $usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario || !password_verify($password_actual, $usuario['password'])) {
            return ['success' => false, 'message' => 'La contraseña actual es incorrecta'];
        }
        
        // Encriptar la nueva contraseña
        $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
        
        // Actualizar la contraseña
        $query = "UPDATE usuarios SET password = ? WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$password_hash, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Contraseña actualizada correctamente'
            ];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar la contraseña'];
        }
        
    } catch (PDOException $e) {
        error_log("Error al cambiar contraseña: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar la contraseña'];
    }
}
?>