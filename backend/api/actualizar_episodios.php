<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['anime_id']) || !isset($data['accion'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$anime_id = (int)$data['anime_id'];
$accion = $data['accion']; // 'incrementar' o 'decrementar'

try {
    // Obtener conexión a la base de datos
    $pdo = obtenerConexion();
    
    // Intentar obtener información con el campo animeflv_url_name
    // Si falla (campo no existe), usar consulta sin ese campo
    try {
        $stmt = $pdo->prepare("
            SELECT lu.episodios_vistos, lu.animeflv_url_name, a.episodios_total, a.titulo
            FROM lista_usuario lu
            INNER JOIN animes a ON lu.anime_id = a.id
            WHERE lu.usuario_id = ? AND lu.anime_id = ?
        ");
        $stmt->execute([$usuario_id, $anime_id]);
        $anime_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback: consulta sin el campo animeflv_url_name (campo no existe aún)
        $stmt = $pdo->prepare("
            SELECT lu.episodios_vistos, a.episodios_total, a.titulo, NULL as animeflv_url_name
            FROM lista_usuario lu
            INNER JOIN animes a ON lu.anime_id = a.id
            WHERE lu.usuario_id = ? AND lu.anime_id = ?
        ");
        $stmt->execute([$usuario_id, $anime_id]);
        $anime_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$anime_data) {
        echo json_encode(['success' => false, 'message' => 'Anime no encontrado en tu lista']);
        exit;
    }

    $episodios_actuales = $anime_data['episodios_vistos'];
    $episodios_total = $anime_data['episodios_total'];
    $animeflv_url_name = $anime_data['animeflv_url_name'];
    $titulo_anime = $anime_data['titulo'];

    // Calcular nuevos episodios según la acción
    if ($accion === 'incrementar') {
        $nuevos_episodios = $episodios_actuales + 1;
        
        // Validar que no exceda el total de episodios (si está definido)
        if ($episodios_total && $nuevos_episodios > $episodios_total) {
            echo json_encode([
                'success' => false, 
                'message' => 'Ya has visto todos los episodios disponibles (' . $episodios_total . ')'
            ]);
            exit;
        }
    } elseif ($accion === 'decrementar') {
        $nuevos_episodios = max(0, $episodios_actuales - 1); // No permitir episodios negativos
        
        if ($episodios_actuales <= 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Ya estás en el episodio 0'
            ]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        exit;
    }

    // Actualizar episodios en la base de datos
    $stmt = $pdo->prepare("
        UPDATE lista_usuario 
        SET episodios_vistos = ?, 
            fecha_actualizacion = CURRENT_TIMESTAMP 
        WHERE usuario_id = ? AND anime_id = ?
    ");
    $result = $stmt->execute([$nuevos_episodios, $usuario_id, $anime_id]);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar episodios']);
        exit;
    }

    // Preparar respuesta
    $response = [
        'success' => true,
        'episodios_anteriores' => $episodios_actuales,
        'episodios_nuevos' => $nuevos_episodios,
        'titulo' => $titulo_anime,
        'accion' => $accion
    ];

    // Si es incremento y tiene URL de AnimeFLV, generar URL del episodio
    if ($accion === 'incrementar' && !empty($animeflv_url_name)) {
        $episode_url = "https://www3.animeflv.net/ver/" . $animeflv_url_name . "-" . $nuevos_episodios;
        $response['animeflv_url'] = $episode_url;
        $response['message'] = "Episodio actualizado a {$nuevos_episodios}. ¡Abriendo AnimeFLV!";
    } else {
        $response['message'] = "Episodios actualizados a {$nuevos_episodios}";
    }

    // Si no tiene URL de AnimeFLV configurada, sugerir configurarla
    if (empty($animeflv_url_name)) {
        $response['warning'] = 'Configura el nombre URL de AnimeFLV para acceso directo a los episodios';
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error en actualizar_episodios.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>