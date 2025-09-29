<?php
session_start();
require_once '../config/config.php';
require_once '../config/funciones.php';

header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener datos del formulario
$anime_id = isset($_POST['anime_id']) ? (int)$_POST['anime_id'] : 0;
$episodios_vistos = isset($_POST['episodios_vistos']) ? (int)$_POST['episodios_vistos'] : 0;
$estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'Plan de Ver';
$puntuacion = isset($_POST['puntuacion']) && $_POST['puntuacion'] !== '' ? (int)$_POST['puntuacion'] : null;

// Validar datos
if ($anime_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de anime inválido']);
    exit();
}

if ($episodios_vistos < 0) {
    echo json_encode(['success' => false, 'message' => 'El número de episodios vistos no puede ser negativo']);
    exit();
}

$estados_validos = ['Plan de Ver', 'Viendo', 'Completado', 'En Pausa', 'Abandonado'];
if (!in_array($estado, $estados_validos)) {
    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
    exit();
}

if ($puntuacion !== null && ($puntuacion < 1 || $puntuacion > 10)) {
    echo json_encode(['success' => false, 'message' => 'La puntuación debe estar entre 1 y 10']);
    exit();
}

try {
    $conexion = obtenerConexion();
    
    // Verificar si el anime existe
    $query_anime = "SELECT * FROM animes WHERE id = ?";
    $stmt_anime = $conexion->prepare($query_anime);
    $stmt_anime->execute([$anime_id]);
    $anime_original = $stmt_anime->fetch(PDO::FETCH_ASSOC);
    
    if (!$anime_original) {
        echo json_encode(['success' => false, 'message' => 'El anime no existe']);
        exit();
    }
    
    // Verificar si el usuario ya tiene este anime en su lista
    $query_verificar = "SELECT COUNT(*) FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
    $stmt_verificar = $conexion->prepare($query_verificar);
    $stmt_verificar->execute([$usuario_id, $anime_id]);
    
    if ($stmt_verificar->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Este anime ya está en tu lista']);
        exit();
    }
    
    // Validar episodios vistos vs episodios totales
    if ($anime_original['episodios_total'] && $episodios_vistos > $anime_original['episodios_total']) {
        echo json_encode(['success' => false, 'message' => 'No puedes haber visto más episodios de los que tiene el anime']);
        exit();
    }
    
    // Ajustar automáticamente el estado si ha visto todos los episodios
    if ($anime_original['episodios_total'] && $episodios_vistos >= $anime_original['episodios_total'] && $estado !== 'Completado') {
        $estado = 'Completado';
    }
    
    // Iniciar transacción
    $conexion->beginTransaction();
    
    try {
        // Crear una copia del anime para el usuario (insertando en animes si no existe una copia idéntica)
        // En lugar de duplicar, usaremos directamente el anime_id original y la tabla lista_usuario
        // para hacer el seguimiento personalizado del usuario
        
        // Insertar en lista_usuario con los datos personalizados
        $query_insertar = "INSERT INTO lista_usuario (
            usuario_id, 
            anime_id, 
            episodios_vistos, 
            estado, 
            puntuacion, 
            fecha_agregado
        ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt_insertar = $conexion->prepare($query_insertar);
        $stmt_insertar->execute([
            $usuario_id,
            $anime_id,
            $episodios_vistos,
            $estado,
            $puntuacion
        ]);
        
        // Confirmar transacción
        $conexion->commit();
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true, 
            'message' => 'Anime agregado exitosamente a tu lista',
            'anime_titulo' => $anime_original['titulo'],
            'estado' => $estado,
            'episodios_vistos' => $episodios_vistos
        ]);
        
    } catch (Exception $e) {
        $conexion->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error al agregar anime a lista: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>