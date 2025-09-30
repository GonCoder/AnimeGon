<?php
session_start();
require_once '../config/config.php';
require_once '../config/funciones.php';

// Configurar headers para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Función para enviar respuesta JSON
function enviarRespuesta($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    enviarRespuesta(false, 'Usuario no autenticado');
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuesta(false, 'Método no permitido');
}

// Debug: Log de datos recibidos
error_log("POST data: " . print_r($_POST, true));

$usuario_id = $_SESSION['usuario_id'];

try {
    // Obtener datos del formulario
    $anime_id = intval($_POST['anime_id'] ?? 0);
    $recomendacion_id = intval($_POST['recomendacion_id'] ?? 0);
    $episodios_vistos = intval($_POST['episodios_vistos'] ?? 0);
    $estado = $_POST['estado'] ?? 'Plan de Ver';
    $puntuacion = !empty($_POST['puntuacion']) ? floatval($_POST['puntuacion']) : null;
    
    // Validaciones básicas
    if ($anime_id <= 0) {
        enviarRespuesta(false, 'ID de anime inválido');
    }
    
    if ($episodios_vistos < 0) {
        enviarRespuesta(false, 'Los episodios vistos no pueden ser negativos');
    }
    
    $estados_validos = ['Plan de Ver', 'Viendo', 'Completado', 'En Pausa', 'Abandonado'];
    if (!in_array($estado, $estados_validos)) {
        enviarRespuesta(false, 'Estado no válido');
    }
    
    if (!is_null($puntuacion) && ($puntuacion < 1 || $puntuacion > 10)) {
        enviarRespuesta(false, 'La puntuación debe estar entre 1 y 10');
    }
    
    // Debug: Intentar conexión
    error_log("Intentando conectar a la base de datos...");
    $conexion = obtenerConexion();
    error_log("Conexión exitosa, iniciando transacción...");
    $conexion->beginTransaction();
    
    // Verificar si el anime existe
    $query_anime = "SELECT id, titulo, episodios_total FROM animes WHERE id = ?";
    $stmt_anime = $conexion->prepare($query_anime);
    $stmt_anime->execute([$anime_id]);
    $anime = $stmt_anime->fetch(PDO::FETCH_ASSOC);
    
    if (!$anime) {
        $conexion->rollBack();
        enviarRespuesta(false, 'El anime no existe');
    }
    
    // Verificar si el usuario ya tiene este anime en su lista
    $query_check = "SELECT id FROM lista_usuario WHERE usuario_id = ? AND anime_id = ?";
    $stmt_check = $conexion->prepare($query_check);
    $stmt_check->execute([$usuario_id, $anime_id]);
    
    if ($stmt_check->fetch()) {
        $conexion->rollBack();
        enviarRespuesta(false, 'Ya tienes este anime en tu lista');
    }
    
    // Validar episodios vistos vs total
    if ($anime['episodios_total'] && $episodios_vistos > $anime['episodios_total']) {
        $conexion->rollBack();
        enviarRespuesta(false, 'Los episodios vistos no pueden ser más que el total (' . $anime['episodios_total'] . ')');
    }
    
    // Agregar anime a la lista del usuario
    $query_insert = "INSERT INTO lista_usuario (usuario_id, anime_id, episodios_vistos, estado, puntuacion, fecha_agregado) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt_insert = $conexion->prepare($query_insert);
    $stmt_insert->execute([$usuario_id, $anime_id, $episodios_vistos, $estado, $puntuacion]);
    
    // Si se proporcionó ID de recomendación, marcarla como leída
    if ($recomendacion_id > 0) {
        $query_update_rec = "UPDATE recomendaciones SET leido = 1, fecha_lectura = NOW() WHERE id = ? AND usuario_receptor_id = ?";
        $stmt_update_rec = $conexion->prepare($query_update_rec);
        $stmt_update_rec->execute([$recomendacion_id, $usuario_id]);
        error_log("Recomendación marcada como leída: ID $recomendacion_id para usuario $usuario_id");
    }
    
    $conexion->commit();
    
    enviarRespuesta(true, 'Anime agregado exitosamente a tu lista', [
        'anime_titulo' => $anime['titulo'],
        'estado' => $estado,
        'debug_info' => [
            'usuario_id' => $usuario_id,
            'anime_id' => $anime_id,
            'episodios_vistos' => $episodios_vistos,
            'recomendacion_id' => $recomendacion_id,
            'puntuacion' => $puntuacion
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($conexion)) {
        $conexion->rollBack();
    }
    error_log("Error PDO en procesar_recomendacion.php: " . $e->getMessage());
    enviarRespuesta(false, 'Error de base de datos: ' . $e->getMessage());
} catch (Exception $e) {
    if (isset($conexion)) {
        $conexion->rollBack();
    }
    error_log("Error general en procesar_recomendacion.php: " . $e->getMessage());
    enviarRespuesta(false, 'Error interno del servidor: ' . $e->getMessage());
}
?>