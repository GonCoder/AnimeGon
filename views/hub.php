<?php
session_start();
require_once '../backend/config/config.php';
require_once '../backend/config/funciones.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener datos del usuario
function obtenerDatosUsuario($usuario_id) {
    try {
        $conexion = obtenerConexion();
        $query = "SELECT nombre, username FROM usuarios WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['nombre' => 'Usuario', 'username' => ''];
    }
}

$usuario = obtenerDatosUsuario($usuario_id);

// Obtener animes del hub (solo primeros 12 para carga inicial)
function obtenerAnimesHub($usuario_id, $limite = 12) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT DISTINCT a.*, 
                         COALESCE(primer_usuario.username, 'Sistema') as subido_por,
                         COALESCE(stats.usuarios_que_lo_tienen, 0) as usuarios_que_lo_tienen,
                         COALESCE(stats.puntuacion_promedio, 0) as puntuacion_promedio,
                         COALESCE(stats.total_valoraciones, 0) as total_valoraciones,
                         CASE WHEN usuario_actual.anime_id IS NOT NULL THEN 1 ELSE 0 END as ya_lo_tiene,
                         usuario_actual.estado as mi_estado,
                         usuario_actual.episodios_vistos as mis_episodios_vistos,
                         usuario_actual.puntuacion as mi_puntuacion
                  FROM animes a
                  -- Obtener el primer usuario que agregó este anime
                  LEFT JOIN (
                      SELECT anime_id, 
                             MIN(usuario_id) as primer_usuario_id,
                             MIN(fecha_agregado) as primera_fecha
                      FROM lista_usuario 
                      GROUP BY anime_id
                  ) primer_registro ON a.id = primer_registro.anime_id
                  LEFT JOIN usuarios primer_usuario ON primer_registro.primer_usuario_id = primer_usuario.id
                  -- Estadísticas del anime
                  LEFT JOIN (
                      SELECT anime_id,
                             COUNT(DISTINCT usuario_id) as usuarios_que_lo_tienen,
                             ROUND(AVG(CASE WHEN puntuacion IS NOT NULL AND puntuacion > 0 THEN puntuacion END), 1) as puntuacion_promedio,
                             COUNT(CASE WHEN puntuacion IS NOT NULL AND puntuacion > 0 THEN 1 END) as total_valoraciones
                      FROM lista_usuario 
                      GROUP BY anime_id
                  ) stats ON a.id = stats.anime_id
                  -- Verificar si el usuario actual ya tiene este anime
                  LEFT JOIN lista_usuario usuario_actual ON a.id = usuario_actual.anime_id AND usuario_actual.usuario_id = ?
                  ORDER BY 
                      CASE WHEN usuario_actual.anime_id IS NULL THEN 0 ELSE 1 END, -- Primero los que no tiene
                      stats.usuarios_que_lo_tienen DESC, 
                      stats.puntuacion_promedio DESC, 
                      a.titulo ASC
                  LIMIT ?";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id, $limite]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error en obtenerAnimesHub: " . $e->getMessage());
        
        // Consulta de respaldo más simple
        try {
            $query_simple = "SELECT a.*, 'Sistema' as subido_por, 0 as usuarios_que_lo_tienen, 
                             0 as puntuacion_promedio, 0 as total_valoraciones, 0 as ya_lo_tiene, '' as mi_estado, 
                             0 as mis_episodios_vistos, 0 as mi_puntuacion
                             FROM animes a
                             ORDER BY a.titulo ASC
                             LIMIT ?";
            
            $stmt = $conexion->prepare($query_simple);
            $stmt->execute([$limite]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            error_log("Error en consulta de respaldo: " . $e2->getMessage());
            return [];
        }
    }
}

// Obtener el total de animes en el hub
function obtenerTotalAnimesHub($usuario_id) {
    try {
        $conexion = obtenerConexion();
        $query = "SELECT COUNT(DISTINCT a.id) as total FROM animes a";
        $stmt = $conexion->prepare($query);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['total'];
    } catch (Exception $e) {
        return 0;
    }
}

$animes_hub = obtenerAnimesHub($usuario_id);
$total_animes_hub = obtenerTotalAnimesHub($usuario_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Hub Comunitario</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../favicon.svg">
    
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
        /* Reset y estilos base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Estilos para indicador de usuario */
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-indicator {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid #00ff00;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.85rem;
            color: #00ff00;
            font-weight: 500;
            white-space: nowrap;
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 5px rgba(0, 255, 0, 0.3); }
            50% { box-shadow: 0 0 15px rgba(0, 255, 0, 0.6); }
            100% { box-shadow: 0 0 5px rgba(0, 255, 0, 0.3); }
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0a0a0a, #1a2e1a, #16213e);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }
        
        /* Navbar Styles */
        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 2px solid rgba(0, 255, 0, 0.3);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .nav-logo h2 {
            color: #00ff00;
            text-shadow: 0 0 20px rgba(0, 255, 0, 0.6);
            font-size: 1.8rem;
        }
        
        .nav-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
            list-style: none;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.4rem 0.7rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .nav-link:hover {
            color: #00ff00;
            border-color: rgba(0, 255, 0, 0.5);
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.3);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #00ff00, #00cc00);
            color: #000;
            border-color: transparent;
        }
        
        /* Estilos específicos para hub */
        .hub-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .hub-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .hub-title {
            color: #00ff00;
            font-size: 2.5rem;
            text-shadow: 0 0 20px rgba(0, 255, 0, 0.6);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-input {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 255, 0, 0.3);
            border-radius: 25px;
            padding: 12px 20px;
            color: white;
            width: 300px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #00ff00;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.4);
        }
        
        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .animes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .anime-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(0, 255, 0, 0.3);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .anime-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 255, 0, 0.8);
            box-shadow: 0 10px 30px rgba(0, 255, 0, 0.4);
        }
        
        .community-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #00ff00, #00cc00);
            color: #000;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            z-index: 10;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.6);
        }
        
        .my-anime-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #00ff00, #00aa00);
            color: #000;
            border: 2px solid #00ff00;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            z-index: 10;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.8);
            animation: pulseOwned 2s infinite;
        }
        
        @keyframes pulseOwned {
            0% { box-shadow: 0 0 20px rgba(0, 255, 0, 0.8); }
            50% { box-shadow: 0 0 30px rgba(0, 255, 0, 1); }
            100% { box-shadow: 0 0 20px rgba(0, 255, 0, 0.8); }
        }
        
        .anime-card.ya-tengo {
            border-color: rgba(0, 255, 0, 0.6);
            background: rgba(0, 255, 0, 0.08);
        }
        
        .anime-card.ya-tengo:hover {
            border-color: rgba(0, 255, 0, 0.9);
        }
        
        .anime-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .anime-image.loaded {
            opacity: 1;
        }
        
        .anime-info {
            padding: 20px;
        }
        
        .anime-name {
            color: #00ff00;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tipo-badge {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: normal;
            border: 1px solid rgba(0, 255, 0, 0.4);
        }
        
        .anime-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .users-count {
            color: #00ff88;
            font-weight: bold;
        }
        
        .rating-badge {
            background: linear-gradient(135deg, #00ff00, #00cc00);
            color: #000;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .rating-badge.clickeable {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .rating-badge.clickeable:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 255, 0, 0.4);
            border-color: rgba(0, 255, 0, 0.6);
        }
        
        .rating-badge.sin-puntuaciones {
            background: linear-gradient(135deg, #2a2a2a 0%, #3a3a3a 100%);
            color: #00ff00;
            border: 1px dashed #00ff00;
            animation: pulse 2s infinite;
        }

        .rating-badge.sin-puntuaciones:hover {
            background: linear-gradient(135deg, #004400 0%, #006600 100%);
            border: 1px solid #00ff00;
            animation: none;
            transform: scale(1.05);
        }

        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }
        
        /* Estilos para estado del anime */
        .estado-anime {
            margin: 8px 0;
        }
        
        .estado-anime-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: bold;
            border: 1px solid;
        }
        
        .estado-anime-badge.finalizado {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
            border-color: rgba(0, 255, 0, 0.4);
        }
        
        .estado-anime-badge.emitiendo {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border-color: rgba(255, 193, 7, 0.4);
            animation: pulse 2s infinite;
        }
        
        .estado-anime-badge.proximamente {
            background: rgba(0, 123, 255, 0.2);
            color: #007bff;
            border-color: rgba(0, 123, 255, 0.4);
        }
        
        .estado-anime-badge.cancelado {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border-color: rgba(220, 53, 69, 0.4);
        }
        
        .estado-anime-badge.desconocido {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
            border-color: rgba(108, 117, 125, 0.4);
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .anime-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 15px;
        }
        
        .subido-por {
            font-size: 0.8rem;
            color: rgba(0, 255, 0, 0.8);
        }
        
        .anime-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-action {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-agregar {
            background: linear-gradient(135deg, #00ff00, #00cc00);
            color: #000;
        }
        
        .btn-agregar:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.6);
        }
        
        .btn-ya-tengo {
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.15), rgba(0, 255, 0, 0.25));
            color: #00ff88;
            border: 2px solid rgba(0, 255, 136, 0.6);
            border-radius: 12px;
            cursor: default;
            opacity: 0.9;
            position: relative;
            overflow: hidden;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-shadow: 0 0 8px rgba(0, 255, 136, 0.3);
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            margin-top: 12px;
        }
        
        .btn-ya-tengo::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .btn-ya-tengo:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 20px rgba(0, 255, 136, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.15);
            border-color: rgba(0, 255, 136, 0.8);
        }
        
        .btn-ya-tengo:hover::before {
            left: 100%;
        }
        
        .mi-estado {
            display: block;
            font-size: 0.75rem;
            color: rgba(0, 255, 136, 0.8);
            font-weight: normal;
            margin-top: 3px;
            font-style: italic;
            text-shadow: none;
            letter-spacing: normal;
        }
        
        .mi-info-rapida {
            margin-top: 8px;
            text-align: center;
            color: rgba(0, 255, 0, 0.8);
            font-size: 0.8rem;
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(0, 255, 0, 0.3);
            border-radius: 8px;
            padding: 5px 8px;
        }
        
        .no-animes {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .no-animes h3 {
            color: #00ff00;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        /* Estilos del Modal para agregar anime */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1a2e1a, #16213e, #0f0f23);
            margin: 2% auto;
            padding: 0;
            border: 2px solid rgba(0, 255, 0, 0.3);
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 0 30px rgba(0, 255, 0, 0.5);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #0f0f23, #1a2e1a);
            padding: 20px;
            border-bottom: 2px solid rgba(0, 255, 0, 0.3);
            border-radius: 13px 13px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            color: #00ff00;
            margin: 0;
            font-size: 1.5rem;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.6);
        }
        
        .close {
            color: #ff007f;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close:hover {
            background: rgba(255, 0, 127, 0.2);
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-section-title {
            color: #00ff00;
            font-size: 1.2rem;
            margin: 25px 0 15px 0;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.6);
            border-bottom: 2px solid rgba(0, 255, 0, 0.3);
            padding-bottom: 8px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #00ff88;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 255, 0, 0.3);
            border-radius: 8px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #00ff00;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.4);
        }
        
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2300ff88" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 20px;
            cursor: pointer;
        }
        
        .form-group select option {
            background: #1a1a1a !important;
            color: white !important;
            padding: 10px;
            border: none;
        }
        
        .form-group select option:checked {
            background: #00ff88 !important;
            color: #000 !important;
        }
        
        .form-group select option:hover {
            background: rgba(0, 255, 136, 0.3) !important;
            color: white !important;
        }
        
        .form-row {
            display: grid;
            gap: 20px;
        }
        
        .form-row-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .form-row-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .file-info {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 5px;
        }
        
        .form-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-submit, .btn-cancel {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #00ff00, #00cc00);
            color: #000;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.6);
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #ff4757, #ff3742);
            color: white;
        }
        
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255, 71, 87, 0.6);
        }
        
        .anime-preview {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(0, 255, 0, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .anime-preview h4 {
            color: #00ff00;
            margin-bottom: 10px;
        }
        
        .anime-preview-info {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px 15px;
            font-size: 0.9rem;
        }
        
        .anime-preview-label {
            color: #00ff88;
            font-weight: bold;
        }
        
        .anime-preview-value {
            color: white;
        }

        /* Estilos específicos para modal de puntuaciones */
        .ratings-header {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(0, 255, 0, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .ratings-average {
            font-size: 2.5rem;
            color: #00ff00;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.6);
            margin-bottom: 10px;
        }
        
        .ratings-stats {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
        
        .ratings-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid rgba(0, 255, 0, 0.3);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
        }
        
        .rating-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid rgba(0, 255, 0, 0.2);
            transition: background 0.3s ease;
        }
        
        .rating-item:last-child {
            border-bottom: none;
        }
        
        .rating-item:hover {
            background: rgba(0, 255, 0, 0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .username {
            color: #00ff88;
            font-weight: bold;
        }
        
        .rating-date {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
        }
        
        .user-rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .rating-value {
            background: linear-gradient(135deg, #00ff00, #00cc00);
            color: #000;
            padding: 4px 8px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .no-ratings {
            text-align: center;
            padding: 40px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .no-ratings h3 {
            color: #00ff00;
            margin-bottom: 10px;
        }
        
        /* Estilos para loading spinner */
        .loading-container {
            text-align: center;
            padding: 40px;
            color: #00ff00;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            margin: 0 auto 15px;
            border: 4px solid rgba(0, 255, 0, 0.2);
            border-top: 4px solid #00ff00;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            font-size: 1rem;
            margin: 0;
        }

        /* Sección de valoración del usuario */
        .user-rating-section {
            background: linear-gradient(135deg, #0f1419 0%, #1a2332 100%);
            border: 1px solid #00ff00;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .user-rating-title {
            color: #00ff00;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .rating-input-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .rating-stars {
            display: flex;
            gap: 5px;
        }

        .rating-star {
            font-size: 24px;
            color: #666;
            cursor: pointer;
            transition: color 0.2s;
        }

        .rating-star:hover,
        .rating-star.active {
            color: #ffd700;
        }

        .rating-number {
            background: #0f1419;
            border: 1px solid #00ff00;
            color: #00ff00;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            min-width: 50px;
            text-align: center;
        }

        .submit-rating-btn {
            background: linear-gradient(135deg, #00ff00 0%, #00cc00 100%);
            color: #0f1419;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-rating-btn:hover {
            background: linear-gradient(135deg, #00cc00 0%, #009900 100%);
            transform: translateY(-2px);
        }

        .current-rating {
            color: #00ff00;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .change-rating-btn {
            background: transparent;
            color: #00ff00;
            border: 1px solid #00ff00;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .change-rating-btn:hover {
            background: #00ff00;
            color: #0f1419;
        }

        /* Menú hamburguesa */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            background: rgba(0, 255, 0, 0.1);
            border: 2px solid rgba(0, 255, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1006;
        }
        
        .hamburger:hover {
            background: rgba(0, 255, 0, 0.2);
            border-color: rgba(0, 255, 0, 0.5);
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.3);
        }
        
        .hamburger span {
            width: 25px;
            height: 3px;
            background: #00ff00;
            margin: 3px 0;
            transition: all 0.3s ease;
            border-radius: 2px;
            box-shadow: 0 0 5px rgba(0, 255, 0, 0.5);
        }
        
        .hamburger.active span:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }
        
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger.active span:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }
        
        /* Menú móvil */
        .nav-menu.mobile {
            display: none;
            position: fixed;
            top: 0;
            right: -100%;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, rgba(0, 46, 26, 0.98), rgba(0, 62, 33, 0.98));
            backdrop-filter: blur(15px);
            border-left: 2px solid rgba(0, 255, 0, 0.3);
            padding: 80px 20px 20px;
            flex-direction: column;
            gap: 0;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1005;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.3);
        }
        
        .nav-menu.mobile.show {
            right: 0;
            display: flex;
        }
        
        .nav-menu.mobile li {
            list-style: none;
            width: 100%;
        }
        
        .nav-menu.mobile .nav-link {
            padding: 15px 20px;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s ease;
            color: #ffffff;
            text-decoration: none;
            border: 2px solid transparent;
            background: rgba(0, 255, 0, 0.05);
            backdrop-filter: blur(10px);
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        
        .nav-menu.mobile .nav-link:hover {
            background: rgba(0, 255, 0, 0.15);
            border-color: rgba(0, 255, 0, 0.3);
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.2);
        }
        
        .nav-menu.mobile .nav-link.active {
            background: linear-gradient(135deg, rgba(0, 255, 0, 0.2), rgba(0, 204, 0, 0.2));
            border-color: #00ff00;
            color: #00ff00;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
        }
        
        /* Overlay del menú */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .mobile-overlay.show {
            display: block;
            opacity: 1;
        }

        /* Estilos para cargar más animes del hub */
        .load-more-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 40px 0;
            gap: 20px;
        }

        .load-more-btn {
            background: linear-gradient(135deg, #00ff00, #00aa00);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 255, 0, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            min-width: 200px;
        }

        .load-more-btn:hover {
            background: linear-gradient(135deg, #00aa00, #00ff00);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 255, 0, 0.4);
        }

        .load-more-btn:active {
            transform: translateY(0);
        }

        .load-more-btn:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .load-more-text {
            font-size: 1rem;
        }

        .load-more-count {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .loading-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #00ff00;
            font-size: 1rem;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(0, 255, 0, 0.3);
            border-top: 2px solid #00ff00;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .nav-menu {
                gap: 0.8rem;
            }
            
            .nav-link {
                padding: 0.3rem 0.6rem;
                font-size: 0.85rem;
            }
            
            .nav-logo h2 {
                font-size: 1.6rem;
            }
        }
        
        @media (max-width: 768px) {
            .hamburger {
                display: flex;
                order: 3;
            }
            
            .nav-menu:not(.mobile) {
                display: none;
            }
            
            .nav-menu.mobile {
                display: flex;
            }
            
            .nav-container {
                padding: 0 15px;
            }
            
            .nav-logo {
                order: 1;
            }
            
            .user-indicator {
                order: 2;
                font-size: 0.75rem;
                padding: 4px 8px;
            }
            
            .hub-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-section {
                justify-content: center;
            }
            
            .search-input {
                width: 100%;
                max-width: 300px;
            }
            
            .animes-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .form-row-2, .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .form-buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .nav-logo h2 {
                font-size: 1.4rem;
            }
            
            .user-indicator {
                display: none;
            }
            
            .nav-menu.mobile {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2>🎌 AnimeGon</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">📊 Dashboard</a></li>
                <li><a href="mis_animes.php" class="nav-link">📺 Mis Animes</a></li>
                <li><a href="favoritos.php" class="nav-link">⭐ Favoritos</a></li>
                <li><a href="recomendados.php" class="nav-link">🎯 Recomendados</a></li>
                <li><a href="hub.php" class="nav-link active">🌐 Hub</a></li>
                <li><a href="perfil.php" class="nav-link">👤 Mi Perfil</a></li>
                <li><a href="logout.php" class="nav-link">🔴 Cerrar Sesión</a></li>
            </ul>
            <span class="user-indicator" onclick="window.location.href='perfil.php'" style="cursor: pointer;">🟢 <?= htmlspecialchars($usuario['nombre']) ?></span>
            <div class="hamburger" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        
        <!-- Menú móvil -->
        <ul class="nav-menu mobile" id="mobileMenu">
            <li><a href="dashboard.php" class="nav-link" onclick="closeMobileMenu()">📊 Dashboard</a></li>
            <li><a href="mis_animes.php" class="nav-link" onclick="closeMobileMenu()">📺 Mis Animes</a></li>
            <li><a href="favoritos.php" class="nav-link" onclick="closeMobileMenu()">⭐ Favoritos</a></li>
            <li><a href="recomendados.php" class="nav-link" onclick="closeMobileMenu()">🎯 Recomendados</a></li>
            <li><a href="hub.php" class="nav-link active" onclick="closeMobileMenu()">🌐 Hub</a></li>
            <li><a href="perfil.php" class="nav-link" onclick="closeMobileMenu()">👤 Mi Perfil</a></li>
            <li><a href="logout.php" class="nav-link" onclick="closeMobileMenu()">🔴 Cerrar Sesión</a></li>
        </ul>
        
        <!-- Overlay para cerrar el menú -->
        <div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>
    </nav>

    <div class="hub-container">
        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="mensaje-exito" style="background: rgba(0, 255, 0, 0.2); border: 2px solid #00ff00; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #00ff00; text-align: center;">
                ✅ <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
            </div>
            <?php unset($_SESSION['mensaje_exito']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="mensaje-error" style="background: rgba(255, 0, 127, 0.2); border: 2px solid #ff007f; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #ff007f; text-align: center;">
                ❌ <?= htmlspecialchars($_SESSION['mensaje_error']) ?>
            </div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>
        
        <div class="hub-header">
            <h1 class="hub-title">🌐 Hub Comunitario</h1>
            <?php if (!empty($animes_hub)): ?>
                <div class="filter-section">
                    <input type="text" id="searchInput" class="search-input" placeholder="🔍 Buscar animes en el hub...">
                    <?php 
                    $disponibles = array_filter($animes_hub, function($anime) { return !$anime['ya_lo_tiene']; });
                    $ya_tengo = array_filter($animes_hub, function($anime) { return $anime['ya_lo_tiene']; });
                    ?>
                    <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <span>📺 Total: <?= $total_animes_hub ?> anime<?= $total_animes_hub != 1 ? 's' : '' ?></span>
                        <span style="color: #00ff88;">🆕 Disponibles: <?= count($disponibles) ?></span>
                        <span style="color: #00ff00;">✅ Ya tienes: <?= count($ya_tengo) ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div style="background: rgba(255, 193, 7, 0.2); border: 2px solid #ffc107; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #ffc107;">
                    <strong>🔍 Debug Info:</strong> No se encontraron animes. 
                    <a href="debug_hub.php" style="color: #ffc107; text-decoration: underline;">Ver diagnóstico completo</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="animes-grid" id="animesGrid">
            <?php if (empty($animes_hub)): ?>
                <div class="no-animes" style="grid-column: 1 / -1;">
                    <h3>🌐 ¡El hub está vacío!</h3>
                    <p>Aún no hay animes compartidos por la comunidad. Sé el primero en agregar animes a tu lista para compartirlos con otros usuarios.</p>
                </div>
            <?php else: ?>
                <?php foreach ($animes_hub as $anime): ?>
                    <div class="anime-card <?= $anime['ya_lo_tiene'] ? 'ya-tengo' : '' ?>" data-anime-name="<?= htmlspecialchars(strtolower($anime['titulo'] ?? 'Sin nombre')) ?>">
                        <?php if ($anime['ya_lo_tiene']): ?>
                            <div class="my-anime-badge" title="Ya tienes este anime">
                                ✅
                            </div>
                        <?php else: ?>
                            <div class="community-badge" title="Anime de la comunidad">
                                🌐
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($anime['imagen_portada'])): ?>
                            <?php 
                            // Ajustar ruta para imágenes locales desde views/
                            $ruta_imagen = $anime['imagen_portada'];
                            if (strpos($ruta_imagen, 'img/') === 0) {
                                $ruta_imagen = '../' . $ruta_imagen;
                            }
                            ?>
                            <img src="<?= htmlspecialchars($ruta_imagen) ?>" 
                                 alt="<?= htmlspecialchars($anime['titulo']) ?>" 
                                 class="anime-image" 
                                 loading="lazy"
                                 onload="this.style.opacity='1'"
                                 onerror="this.src='../img/no-image.png'; this.style.opacity='1'">
                        <?php else: ?>
                            <div class="anime-image" style="display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.5); font-size: 3rem;">
                                🎭
                            </div>
                        <?php endif; ?>
                        
                        <div class="anime-info">
                            <h3 class="anime-name">
                                <?= htmlspecialchars($anime['titulo'] ?? 'Sin nombre') ?>
                                <?php if (!empty($anime['tipo'])): ?>
                                    <span class="tipo-badge"><?= htmlspecialchars($anime['tipo']) ?></span>
                                <?php endif; ?>
                            </h3>
                            
                            <?php if (!empty($anime['titulo_original']) || !empty($anime['titulo_ingles'])): ?>
                                <div style="margin-bottom: 12px; font-size: 0.85rem; opacity: 0.8;">
                                    <?php if (!empty($anime['titulo_original'])): ?>
                                        <div style="color: #00ff00; margin-bottom: 3px;">
                                            🇯🇵 <?= htmlspecialchars($anime['titulo_original']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($anime['titulo_ingles'])): ?>
                                        <div style="color: #00ff88;">
                                            🇺🇸 <?= htmlspecialchars($anime['titulo_ingles']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="anime-stats">
                                <span class="users-count">
                                    👥 <?= $anime['usuarios_que_lo_tienen'] ?> usuario<?= $anime['usuarios_que_lo_tienen'] != 1 ? 's' : '' ?>
                                </span>
                                <?php if (!empty($anime['puntuacion_promedio']) && $anime['total_valoraciones'] > 0): ?>
                                    <span class="rating-badge clickeable" 
                                          data-anime-id="<?= $anime['id'] ?>"
                                          data-anime-titulo="<?= htmlspecialchars($anime['titulo']) ?>"
                                          title="Ver todas las puntuaciones (<?= $anime['total_valoraciones'] ?> valoraciones)">
                                        ⭐ <?= number_format($anime['puntuacion_promedio'], 1) ?> (<?= $anime['total_valoraciones'] ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="rating-badge clickeable sin-puntuaciones" 
                                          data-anime-id="<?= $anime['id'] ?>"
                                          data-anime-titulo="<?= htmlspecialchars($anime['titulo']) ?>"
                                          title="¡Sé el primero en puntuar este anime!">
                                        ⭐ Sin valorar
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($anime['estado'])): ?>
                                <div class="estado-anime">
                                    <?php
                                    // Determinar ícono y clase para el estado del anime
                                    $estado_anime_icon = '';
                                    $estado_anime_class = '';
                                    switch($anime['estado']) {
                                        case 'Finalizado':
                                            $estado_anime_icon = '✅';
                                            $estado_anime_class = 'finalizado';
                                            break;
                                        case 'Emitiendo':
                                            $estado_anime_icon = '📡';
                                            $estado_anime_class = 'emitiendo';
                                            break;
                                        case 'Próximamente':
                                            $estado_anime_icon = '🔜';
                                            $estado_anime_class = 'proximamente';
                                            break;
                                        case 'Cancelado':
                                            $estado_anime_icon = '❌';
                                            $estado_anime_class = 'cancelado';
                                            break;
                                        default:
                                            $estado_anime_icon = '❓';
                                            $estado_anime_class = 'desconocido';
                                    }
                                    ?>
                                    <span class="estado-anime-badge <?= $estado_anime_class ?>">
                                        <?= $estado_anime_icon ?> <?= htmlspecialchars($anime['estado']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="anime-meta">
                                <span class="subido-por">
                                    📤 Subido por: <?= htmlspecialchars($anime['subido_por']) ?>
                                </span>
                                <?php if (!empty($anime['episodios_total'])): ?>
                                    <span><?= $anime['episodios_total'] ?> episodios</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="anime-actions">
                                <?php if ($anime['ya_lo_tiene']): ?>
                                    <!-- El usuario ya tiene este anime -->
                                    <button class="btn-action btn-ya-tengo" disabled>
                                        ✅ Ya tienes este anime en tu lista
                                    </button>
                                    <div class="mi-info-rapida">
                                        <small>
                                            📊 <?= htmlspecialchars($anime['mi_estado']) ?> 
                                            | 📺 <?= $anime['mis_episodios_vistos'] ?>/<?= $anime['episodios_total'] ?: '?' ?>
                                            <?php if ($anime['mi_puntuacion']): ?>
                                                | ⭐ <?= $anime['mi_puntuacion'] ?>/10
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <!-- El usuario puede agregar este anime -->
                                    <button class="btn-action btn-agregar" 
                                            data-anime-id="<?= $anime['id'] ?>"
                                            data-anime-titulo="<?= htmlspecialchars($anime['titulo']) ?>"
                                            data-anime-episodios="<?= $anime['episodios_total'] ?: 0 ?>">
                                        ➕ Agregar a Mi Lista
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Botón para cargar más animes del hub -->
        <?php if ($total_animes_hub > 12): ?>
        <div class="load-more-container" id="loadMoreContainer">
            <button class="load-more-btn" id="loadMoreBtn" onclick="cargarMasAnimesHub()">
                <span class="load-more-text">📄 Cargar más animes</span>
                <span class="load-more-count">(<?= min(12, $total_animes_hub - 12) ?> de <?= $total_animes_hub - 12 ?> restantes)</span>
            </button>
            <div class="loading-indicator" id="loadingIndicator" style="display: none;">
                <div class="spinner"></div>
                <span>Cargando animes...</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal para agregar anime a mi lista -->
    <div id="addToListModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ Agregar a Mi Lista</h2>
                <span class="close" onclick="cerrarModalAgregar()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Vista previa del anime -->
                <div class="anime-preview" id="animePreview">
                    <h4>📺 Anime seleccionado:</h4>
                    <div class="anime-preview-info">
                        <span class="anime-preview-label">Título:</span>
                        <span class="anime-preview-value" id="previewTitulo">-</span>
                        <span class="anime-preview-label">Episodios totales:</span>
                        <span class="anime-preview-value" id="previewEpisodios">-</span>
                    </div>
                </div>
                
                <form id="addToListForm">
                    <input type="hidden" id="anime_id" name="anime_id">
                    
                    <!-- Mi seguimiento -->
                    <h4 class="form-section-title">🎯 Mi Seguimiento</h4>
                    
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label for="episodios_vistos">👁️ Episodios Vistos</label>
                            <input type="number" id="episodios_vistos" name="episodios_vistos" min="0" value="0" placeholder="Ej: 0">
                            <div class="file-info">¿En qué episodio vas?</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">🎯 Mi Estado</label>
                            <select id="estado" name="estado" required>
                                <option value="Plan de Ver">⏳ Plan de Ver</option>
                                <option value="Viendo">👀 Viendo</option>
                                <option value="Completado">✅ Completado</option>
                                <option value="En Pausa">⏸️ En Pausa</option>
                                <option value="Abandonado">❌ Abandonado</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="puntuacion">⭐ Mi Puntuación</label>
                            <select id="puntuacion" name="puntuacion">
                                <option value="">Sin puntuar</option>
                                <option value="10">⭐ 10 - Obra Maestra</option>
                                <option value="9">⭐ 9 - Excelente</option>
                                <option value="8">⭐ 8 - Muy Bueno</option>
                                <option value="7">⭐ 7 - Bueno</option>
                                <option value="6">⭐ 6 - Decente</option>
                                <option value="5">⭐ 5 - Promedio</option>
                                <option value="4">⭐ 4 - Malo</option>
                                <option value="3">⭐ 3 - Muy Malo</option>
                                <option value="2">⭐ 2 - Horrible</option>
                                <option value="1">⭐ 1 - Desastre</option>
                            </select>
                            <div class="file-info">Opcional: Califica del 1 al 10</div>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn-submit">✅ Agregar a Mi Lista</button>
                        <button type="button" class="btn-cancel" onclick="cerrarModalAgregar()">❌ Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar puntuaciones detalladas -->
    <div id="ratingsModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title">⭐ Puntuaciones de la Comunidad</h2>
                <span class="close" onclick="cerrarModalPuntuaciones()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Sección de valoración del usuario -->
                <div id="userRatingSection" class="user-rating-section">
                    <!-- Contenido dinámico -->
                </div>
                
                <!-- Sección de puntuaciones de la comunidad -->
                <div id="ratingsContent">
                    <div class="loading" style="text-align: center; padding: 40px; color: #00ff00;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">🔄</div>
                        <p>Cargando puntuaciones...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../frontend/assets/js/animes.js"></script>
    <script>
        // Variables globales para paginación del hub
        let paginaActualHub = 1;
        let totalPaginasHub = <?= ceil($total_animes_hub / 12) ?>;
        let cargandoMasHub = false;
        
        // Variables globales para filtros del hub
        let filtroSoloDisponibles = false;
        let filtroSoloTengo = false;

        // Función para crear una tarjeta de anime del hub
        function crearTarjetaAnimeHub(anime) {
            let rutaImagen = anime.imagen_portada;
            if (rutaImagen && rutaImagen.startsWith('img/')) {
                rutaImagen = '../' + rutaImagen;
            }

            return `
                <div class="anime-card ${anime.ya_lo_tiene ? 'ya-tengo' : ''}" 
                     data-anime-name="${(anime.titulo || 'Sin nombre').toLowerCase()}"
                     data-anime-id="${anime.id}">
                    
                    ${anime.ya_lo_tiene ? `
                        <div class="my-anime-badge" title="Ya tienes este anime">
                            ✅
                        </div>
                    ` : `
                        <div class="community-badge" title="Anime de la comunidad">
                            🌐
                        </div>
                    `}
                    
                    ${rutaImagen ? `
                        <img src="${rutaImagen}" 
                             alt="${anime.titulo || 'Sin nombre'}" 
                             class="anime-image" 
                             loading="lazy"
                             onload="this.style.opacity='1'"
                             onerror="this.src='../img/no-image.png'; this.style.opacity='1'">
                    ` : `
                        <div class="anime-image" style="display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.5); font-size: 3rem; opacity: 1;">
                            🎭
                        </div>
                    `}
                    
                    <div class="anime-info">
                        <h3 class="anime-name">
                            ${anime.titulo || 'Sin nombre'}
                            ${anime.tipo ? `<span class="tipo-badge">${anime.tipo}</span>` : ''}
                        </h3>
                        
                        ${(anime.titulo_original || anime.titulo_ingles) ? `
                            <div style="margin-bottom: 12px; font-size: 0.85rem; opacity: 0.8;">
                                ${anime.titulo_original ? `
                                    <div style="color: #ffd700; margin-bottom: 3px;">
                                        🇯🇵 ${anime.titulo_original}
                                    </div>
                                ` : ''}
                                ${anime.titulo_ingles ? `
                                    <div style="color: #00ffff;">
                                        🇺🇸 ${anime.titulo_ingles}
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                        
                        <div class="anime-stats">
                            ${anime.episodios_total ? `
                                <span class="stat-badge">📺 ${anime.episodios_total} eps</span>
                            ` : ''}
                            ${anime.usuarios_que_lo_tienen > 0 ? `
                                <span class="stat-badge">👥 ${anime.usuarios_que_lo_tienen}</span>
                            ` : ''}
                            ${anime.puntuacion_promedio > 0 ? `
                                <span class="rating-badge ${anime.total_valoraciones > 0 ? 'clickeable' : ''}" 
                                      data-anime-id="${anime.id}"
                                      data-anime-titulo="${anime.titulo || 'Sin nombre'}">
                                    ⭐ ${parseFloat(anime.puntuacion_promedio).toFixed(1)}
                                    ${anime.total_valoraciones > 0 ? ` (${anime.total_valoraciones})` : ''}
                                </span>
                            ` : ''}
                        </div>
                        
                        <div class="subido-por">
                            📤 Subido por: <strong>${anime.subido_por || 'Sistema'}</strong>
                        </div>
                        
                        <div class="anime-actions">
                            ${anime.ya_lo_tiene ? `
                                <button class="btn-ya-tengo" data-anime-id="${anime.id}">
                                    ✅ Ya lo tengo
                                    ${anime.mi_estado ? `<span class="mi-estado">(${anime.mi_estado})</span>` : ''}
                                </button>
                            ` : `
                                <button class="btn-action btn-agregar" data-anime-id="${anime.id}" 
                                        data-anime-titulo="${anime.titulo || 'Sin nombre'}"
                                        data-anime-imagen="${rutaImagen || ''}"
                                        data-anime-episodios="${anime.episodios_total || 0}">
                                    ➕ Agregar a Mi Lista
                                </button>
                            `}
                        </div>
                    </div>
                </div>
            `;
        }

        // Función para cargar más animes del hub
        async function cargarMasAnimesHub() {
            if (cargandoMasHub || paginaActualHub >= totalPaginasHub) {
                return;
            }

            cargandoMasHub = true;
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const loadingIndicator = document.getElementById('loadingIndicator');

            // Mostrar indicador de carga
            loadMoreBtn.style.display = 'none';
            loadingIndicator.style.display = 'flex';

            try {
                const nextPage = paginaActualHub + 1;
                
                // Construir URL con parámetros actuales de filtro
                const params = new URLSearchParams();
                params.set('pagina', nextPage.toString());
                params.set('limite', '12');
                
                const searchTerm = document.getElementById('searchInput')?.value?.trim() || '';
                
                if (searchTerm) {
                    params.set('busqueda', searchTerm);
                }
                
                if (filtroSoloDisponibles) {
                    params.set('solo_disponibles', 'true');
                }
                
                if (filtroSoloTengo) {
                    params.set('solo_tengo', 'true');
                }
                
                const response = await fetch(`../backend/api/obtener_animes_hub_paginados.php?${params.toString()}`);
                
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }

                const data = await response.json();

                if (data.success && data.animes.length > 0) {
                    const grid = document.getElementById('animesGrid');
                    
                    // Agregar nuevos animes al grid
                    data.animes.forEach(anime => {
                        const animeHTML = crearTarjetaAnimeHub(anime);
                        grid.insertAdjacentHTML('beforeend', animeHTML);
                    });

                    paginaActualHub = nextPage;

                    // Actualizar botón o ocultarlo si no hay más páginas
                    if (data.paginacion.hay_mas) {
                        const restantes = data.paginacion.total_registros - (paginaActualHub * 12);
                        const siguientesCarga = Math.min(12, restantes);
                        
                        loadMoreBtn.querySelector('.load-more-count').textContent = 
                            `(${siguientesCarga} de ${restantes} restantes)`;
                        loadMoreBtn.style.display = 'flex';
                    } else {
                        // Ocultar completamente el contenedor si no hay más animes
                        document.getElementById('loadMoreContainer').style.display = 'none';
                    }

                    // Reinicializar event listeners para los nuevos elementos
                    inicializarEventListeners();

                    showNotification(`Se cargaron ${data.animes.length} animes más`, 'success');
                } else {
                    showNotification('No se encontraron más animes', 'info');
                    document.getElementById('loadMoreContainer').style.display = 'none';
                }

            } catch (error) {
                console.error('Error al cargar más animes del hub:', error);
                showNotification('Error al cargar más animes', 'error');
                loadMoreBtn.style.display = 'flex';
            } finally {
                loadingIndicator.style.display = 'none';
                cargandoMasHub = false;
            }
        }

        // Función para inicializar event listeners
        function inicializarEventListeners() {
            // Event listeners para botones "Agregar a Mi Lista"
            document.querySelectorAll('.btn-agregar:not([data-listener]):not([disabled])').forEach(button => {
                button.setAttribute('data-listener', 'true');
                button.addEventListener('click', function() {
                    const animeId = this.getAttribute('data-anime-id');
                    const animeTitulo = this.getAttribute('data-anime-titulo');
                    const animeEpisodios = this.getAttribute('data-anime-episodios');
                    
                    abrirModalAgregar(animeId, animeTitulo, animeEpisodios);
                });
            });

            // Event listeners para botones "Ya lo tengo"
            document.querySelectorAll('.btn-ya-tengo:not([data-listener])').forEach(button => {
                button.setAttribute('data-listener', 'true');
                button.addEventListener('mouseenter', function() {
                    this.style.cursor = 'help';
                });
                
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Opcional: mostrar más información o redirigir a "Mis Animes"
                    console.log('Este anime ya está en tu lista');
                });
            });

            // Event listeners para ratings clickeables
            document.querySelectorAll('.rating-badge.clickeable:not([data-listener])').forEach(badge => {
                badge.setAttribute('data-listener', 'true');
                badge.addEventListener('click', function() {
                    const animeId = this.getAttribute('data-anime-id');
                    const animeTitulo = this.getAttribute('data-anime-titulo');
                    
                    abrirModalPuntuaciones(animeId, animeTitulo);
                });
            });
        }

        // Filtrado mejorado para hub con paginación
        async function aplicarFiltrosHub() {
            const searchTerm = document.getElementById('searchInput')?.value?.trim() || '';
            
            // Resetear paginación
            paginaActualHub = 1;
            cargandoMasHub = false;
            
            try {
                // Construir URL con parámetros de filtro
                const params = new URLSearchParams();
                params.set('pagina', '1');
                params.set('limite', '12');
                
                if (searchTerm) {
                    params.set('busqueda', searchTerm);
                }
                
                if (filtroSoloDisponibles) {
                    params.set('solo_disponibles', 'true');
                }
                
                if (filtroSoloTengo) {
                    params.set('solo_tengo', 'true');
                }
                
                const response = await fetch(`../backend/api/obtener_animes_hub_paginados.php?${params.toString()}`);
                
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    const grid = document.getElementById('animesGrid');
                    const loadMoreContainer = document.getElementById('loadMoreContainer');
                    
                    // Limpiar grid actual
                    grid.innerHTML = '';
                    
                    if (data.animes.length === 0) {
                        // Mostrar mensaje de no resultados
                        grid.innerHTML = `
                            <div class="no-animes" style="grid-column: 1 / -1;">
                                <h3>🔍 No se encontraron animes</h3>
                                <p>Intenta ajustar tus filtros de búsqueda.</p>
                            </div>
                        `;
                        loadMoreContainer.style.display = 'none';
                    } else {
                        // Agregar nuevos animes filtrados
                        data.animes.forEach(anime => {
                            const animeHTML = crearTarjetaAnimeHub(anime);
                            grid.insertAdjacentHTML('beforeend', animeHTML);
                        });
                        
                        // Actualizar paginación
                        totalPaginasHub = data.paginacion.total_paginas;
                        
                        // Mostrar/ocultar botón cargar más
                        if (data.paginacion.hay_mas) {
                            const restantes = data.paginacion.total_registros - 12;
                            const siguientesCarga = Math.min(12, restantes);
                            
                            document.querySelector('.load-more-count').textContent = 
                                `(${siguientesCarga} de ${restantes} restantes)`;
                            loadMoreContainer.style.display = 'flex';
                        } else {
                            loadMoreContainer.style.display = 'none';
                        }

                        // Inicializar event listeners para nuevos elementos
                        inicializarEventListeners();
                    }
                } else {
                    showNotification('Error al aplicar filtros', 'error');
                }
                
            } catch (error) {
                console.error('Error al aplicar filtros del hub:', error);
                showNotification('Error de conexión al filtrar animes', 'error');
                
                // Fallback: aplicar filtros localmente
                aplicarFiltrosLocalesHub();
            }
        }

        // Función de respaldo para filtros locales
        function aplicarFiltrosLocalesHub() {
            const searchTerm = document.getElementById('searchInput')?.value?.toLowerCase() || '';
            const animeCards = document.querySelectorAll('.anime-card');
            
            animeCards.forEach(card => {
                const animeName = card.getAttribute('data-anime-name');
                if (searchTerm === '' || animeName.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Event listener para búsqueda con debounce
        document.getElementById('searchInput')?.addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(aplicarFiltrosHub, 500); // 500ms de debounce
        });
        
        // Aplicar búsqueda automática si viene del Dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar event listeners para elementos existentes
            inicializarEventListeners();
            
            // Verificar si hay un término de búsqueda guardado desde el Dashboard
            const savedSearchTerm = localStorage.getItem('hubSearchTerm');
            if (savedSearchTerm) {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = savedSearchTerm;
                    
                    // Trigger the search
                    const event = new Event('input', { bubbles: true });
                    searchInput.dispatchEvent(event);
                    
                    // Scroll to search input para que el usuario vea que se aplicó la búsqueda
                    searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Limpiar el término guardado
                    localStorage.removeItem('hubSearchTerm');
                }
            }
        });

        // Configurar event listeners para los botones de agregar
        document.addEventListener('DOMContentLoaded', function() {
            // Solo agregar listeners a botones habilitados (no deshabilitados)
            document.querySelectorAll('.btn-agregar:not([disabled])').forEach(button => {
                button.addEventListener('click', function() {
                    const animeId = this.getAttribute('data-anime-id');
                    const animeTitulo = this.getAttribute('data-anime-titulo');
                    const animeEpisodios = this.getAttribute('data-anime-episodios');
                    
                    abrirModalAgregar(animeId, animeTitulo, animeEpisodios);
                });
            });
            
            // Agregar tooltips informativos a los botones deshabilitados
            document.querySelectorAll('.btn-ya-tengo').forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.cursor = 'help';
                });
                
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Opcional: mostrar más información o redirigir a "Mis Animes"
                    console.log('Este anime ya está en tu lista');
                });
            });
            
            // Event listeners para badges de puntuación clickeables
            document.querySelectorAll('.rating-badge.clickeable').forEach(badge => {
                badge.addEventListener('click', function() {
                    const animeId = this.getAttribute('data-anime-id');
                    const animeTitulo = this.getAttribute('data-anime-titulo');
                    
                    abrirModalPuntuaciones(animeId, animeTitulo);
                });
            });
        });
        
        // Función para abrir el modal de agregar
        function abrirModalAgregar(animeId, titulo, episodiosTotales) {
            const modal = document.getElementById('addToListModal');
            const previewTitulo = document.getElementById('previewTitulo');
            const previewEpisodios = document.getElementById('previewEpisodios');
            const animeIdInput = document.getElementById('anime_id');
            
            // Actualizar la vista previa
            previewTitulo.textContent = titulo;
            previewEpisodios.textContent = episodiosTotales || 'Desconocido';
            
            // Establecer el ID del anime
            animeIdInput.value = animeId;
            
            // Mostrar el modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Función para cerrar el modal
        function cerrarModalAgregar() {
            const modal = document.getElementById('addToListModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Limpiar el formulario
            document.getElementById('addToListForm').reset();
        }
        
        // Cerrar modal con escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalAgregar();
            }
        });
        
        // No cerrar modal haciendo clic fuera para evitar cierres accidentales
        // Los usuarios pueden usar la X, Cancelar, o la tecla Escape
        
        // === FUNCIONES PARA MODAL DE PUNTUACIONES ===
        
        let currentRating = 0;
        let currentAnimeId = null;

        // Función para abrir el modal de puntuaciones
        async function abrirModalPuntuaciones(animeId, animeTitulo) {
            const modal = document.getElementById('ratingsModal');
            const modalTitle = modal.querySelector('.modal-title');
            const ratingsContent = document.getElementById('ratingsContent');
            const userRatingSection = document.getElementById('userRatingSection');
            
            currentAnimeId = animeId;
            
            // Actualizar título del modal
            modalTitle.innerHTML = `⭐ Puntuaciones: ${animeTitulo}`;
            
            // Mostrar loading
            ratingsContent.innerHTML = `
                <div class="loading-container">
                    <div class="spinner"></div>
                    <p class="loading-text">Cargando puntuaciones...</p>
                </div>
            `;
            
            // Limpiar sección de usuario
            userRatingSection.innerHTML = '';
            
            // Mostrar modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            try {
                // Hacer fetch a la API
                const response = await fetch(`../backend/api/obtener_puntuajes_anime.php?anime_id=${animeId}`);
                const data = await response.json();
                
                if (data.success) {
                    // Mostrar sección de valoración del usuario
                    mostrarSeccionValoracionUsuario(data.puntuacion_usuario);
                    mostrarPuntuaciones(data);
                } else {
                    mostrarErrorPuntuaciones(data.error || 'Error desconocido');
                }
                
            } catch (error) {
                console.error('Error al cargar puntuaciones:', error);
                mostrarErrorPuntuaciones('Error de conexión al servidor');
            }
        }
        
        // Función para mostrar las puntuaciones en el modal
        function mostrarPuntuaciones(data) {
            const ratingsContent = document.getElementById('ratingsContent');
            const { anime, puntuajes, estadisticas } = data;
            
            let html = '';
            
            // Header con estadísticas generales
            html += `
                <div class="ratings-header">
                    <div class="ratings-average">⭐ ${estadisticas.promedio}/10</div>
                    <div class="ratings-stats">
                        📊 ${estadisticas.total_valoraciones} valoración${estadisticas.total_valoraciones !== 1 ? 'es' : ''}
                        ${estadisticas.total_valoraciones > 0 ? `| 📈 ${estadisticas.puntuacion_maxima} máx | 📉 ${estadisticas.puntuacion_minima} mín` : ''}
                    </div>
                </div>
            `;
            
            if (puntuajes.length > 0) {
                html += '<div class="ratings-list">';
                
                puntuajes.forEach(puntuaje => {
                    const fecha = puntuaje.fecha_actualizacion ? 
                        new Date(puntuaje.fecha_actualizacion).toLocaleDateString('es-ES') : 
                        'Fecha desconocida';
                    
                    html += `
                        <div class="rating-item">
                            <div class="user-info">
                                <span class="username">👤 ${puntuaje.username}</span>
                                <span class="rating-date">${fecha}</span>
                            </div>
                            <div class="user-rating">
                                <span class="rating-value">⭐ ${puntuaje.puntuacion}/10</span>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
            } else {
                html += `
                    <div class="no-ratings">
                        <h3>😔 Sin puntuaciones aún</h3>
                        <p>Este anime aún no ha sido puntuado por ningún usuario.</p>
                    </div>
                `;
            }
            
            ratingsContent.innerHTML = html;
        }
        
        // Función para mostrar error en el modal
        function mostrarErrorPuntuaciones(error) {
            const ratingsContent = document.getElementById('ratingsContent');
            ratingsContent.innerHTML = `
                <div class="no-ratings">
                    <h3 style="color: #ff007f;">❌ Error</h3>
                    <p style="color: #ff007f;">${error}</p>
                </div>
            `;
        }
        
        // Función para cerrar el modal de puntuaciones
        function cerrarModalPuntuaciones() {
            const modal = document.getElementById('ratingsModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Cerrar modal de puntuaciones con escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const ratingsModal = document.getElementById('ratingsModal');
                if (ratingsModal.style.display === 'block') {
                    cerrarModalPuntuaciones();
                }
            }
        });
        
        // Cerrar modal de puntuaciones haciendo clic en el fondo
        document.getElementById('ratingsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalPuntuaciones();
            }
        });
        
        // Manejar envío del formulario
        document.getElementById('addToListForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('../backend/api/agregar_a_lista.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    cerrarModalAgregar();
                    
                    // Mostrar mensaje de éxito
                    const mensaje = document.createElement('div');
                    mensaje.style.cssText = 'background: rgba(25, 76, 25, 0.83); border: 2px solid #00ff00; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #00ff00; text-align: center; position: fixed; top: 20px; left: 15%; transform: translateX(-50%); z-index: 9999; max-width: 500px;';
                    mensaje.innerHTML = '✅ ' + result.message;
                    document.body.appendChild(mensaje);
                    
                    // Actualizar la card para mostrar que ya la tiene
                    const animeCard = document.querySelector(`[data-anime-id="${formData.get('anime_id')}"]`).closest('.anime-card');
                    if (animeCard) {
                        // Agregar clase ya-tengo
                        animeCard.classList.add('ya-tengo');
                        
                        // Cambiar badge
                        const badge = animeCard.querySelector('.community-badge');
                        if (badge) {
                            badge.className = 'my-anime-badge';
                            badge.title = 'Ya tienes este anime';
                            badge.innerHTML = '✅';
                        }
                        
                        // Cambiar botón de acción
                        const actionsDiv = animeCard.querySelector('.anime-actions');
                        if (actionsDiv) {
                            const estado = formData.get('estado') || 'Plan de Ver';
                            const episodios = formData.get('episodios_vistos') || '0';
                            const puntuacion = formData.get('puntuacion') || '';
                            const episodiosTotales = formData.get('episodios_total') || '?';
                            
                            actionsDiv.innerHTML = `
                                <button class="btn-action btn-ya-tengo" disabled>
                                    ✅ Ya tienes este anime en tu lista
                                </button>
                                <div class="mi-info-rapida">
                                    <small>
                                        📊 ${estado} | 📺 ${episodios}/${episodiosTotales}
                                        ${puntuacion ? `| ⭐ ${puntuacion}/10` : ''}
                                    </small>
                                </div>
                            `;
                        }
                        
                        // Animación de éxito
                        animeCard.style.transition = 'all 0.5s ease';
                        animeCard.style.transform = 'scale(1.05)';
                        setTimeout(() => {
                            animeCard.style.transform = 'scale(1)';
                        }, 300);
                    }
                    
                    // Remover mensaje después de 5 segundos
                    setTimeout(() => {
                        mensaje.remove();
                    }, 5000);
                    
                } else {
                    // Mostrar mensaje de error
                    const mensaje = document.createElement('div');
                    mensaje.style.cssText = 'background: rgba(73, 0, 37, 0.85); border: 2px solid #ff007f; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #ff007f; text-align: center; position: fixed; top: 20px; left: 15%; transform: translateX(-50%); z-index: 9999; max-width: 500px;';
                    mensaje.innerHTML = '❌ ' + result.message;
                    document.body.appendChild(mensaje);
                    
                    setTimeout(() => {
                        mensaje.remove();
                    }, 5000);
                }
                
            } catch (error) {
                console.error('Error:', error);
                alert('Error al agregar el anime a tu lista');
            }
        });

        // Funciones para manejar la valoración del usuario
        function mostrarSeccionValoracionUsuario(puntuacionActual) {
            const userRatingSection = document.getElementById('userRatingSection');
            
            if (puntuacionActual) {
                // Usuario ya ha valorado
                userRatingSection.innerHTML = `
                    <div class="user-rating-title">Tu valoración</div>
                    <div class="current-rating">⭐ ${puntuacionActual}/10</div>
                    <button class="change-rating-btn" onclick="mostrarFormularioValoracion(${puntuacionActual})">
                        Cambiar valoración
                    </button>
                `;
            } else {
                // Usuario no ha valorado
                mostrarFormularioValoracion(0);
            }
        }

        function mostrarFormularioValoracion(valoracionActual = 0) {
            const userRatingSection = document.getElementById('userRatingSection');
            currentRating = valoracionActual;
            
            userRatingSection.innerHTML = `
                <div class="user-rating-title">${valoracionActual > 0 ? 'Cambiar valoración' : 'Valora este anime'}</div>
                <div class="rating-input-container">
                    <div class="rating-stars">
                        ${Array.from({length: 10}, (_, i) => `
                            <span class="rating-star ${i + 1 <= currentRating ? 'active' : ''}" 
                                  data-rating="${i + 1}" 
                                  onclick="seleccionarValoracion(${i + 1})">⭐</span>
                        `).join('')}
                    </div>
                    <div class="rating-number" id="ratingNumber">${currentRating || '-'}/10</div>
                </div>
                <button class="submit-rating-btn" onclick="enviarValoracion()" ${currentRating === 0 ? 'disabled' : ''}>
                    ${valoracionActual > 0 ? 'Actualizar valoración' : 'Enviar valoración'}
                </button>
            `;
        }

        function seleccionarValoracion(rating) {
            currentRating = rating;
            
            // Actualizar estrellas
            const stars = document.querySelectorAll('.rating-star');
            stars.forEach((star, index) => {
                if (index + 1 <= rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
            
            // Actualizar número
            document.getElementById('ratingNumber').textContent = `${rating}/10`;
            
            // Habilitar botón
            document.querySelector('.submit-rating-btn').disabled = false;
        }

        async function enviarValoracion() {
            if (currentRating === 0 || !currentAnimeId) return;
            
            const submitBtn = document.querySelector('.submit-rating-btn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';
            
            try {
                const response = await fetch('../backend/api/guardar_valoracion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        anime_id: currentAnimeId,
                        puntuacion: currentRating
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Mostrar mensaje de éxito
                    const mensaje = document.createElement('div');
                    mensaje.style.cssText = 'background: rgba(0, 255, 0, 0.2); border: 2px solid #00ff00; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #00ff00; text-align: center; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; max-width: 500px;';
                    mensaje.innerHTML = '✅ ' + data.mensaje;
                    document.body.appendChild(mensaje);
                    
                    setTimeout(() => {
                        mensaje.remove();
                    }, 3000);
                    
                    // Recargar las puntuaciones
                    const animeTitulo = document.querySelector('#ratingsModal .modal-title').textContent.replace('⭐ Puntuaciones: ', '');
                    abrirModalPuntuaciones(currentAnimeId, animeTitulo);
                } else {
                    const mensaje = document.createElement('div');
                    mensaje.style.cssText = 'background: rgba(255, 0, 127, 0.2); border: 2px solid #ff007f; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #ff007f; text-align: center; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; max-width: 500px;';
                    mensaje.innerHTML = '❌ Error: ' + data.error;
                    document.body.appendChild(mensaje);
                    
                    setTimeout(() => {
                        mensaje.remove();
                    }, 3000);
                    
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            } catch (error) {
                console.error('Error al enviar valoración:', error);
                const mensaje = document.createElement('div');
                mensaje.style.cssText = 'background: rgba(255, 0, 127, 0.2); border: 2px solid #ff007f; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #ff007f; text-align: center; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; max-width: 500px;';
                mensaje.innerHTML = '❌ Error al enviar la valoración';
                document.body.appendChild(mensaje);
                
                setTimeout(() => {
                    mensaje.remove();
                }, 3000);
                
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }

        // Actualizar función de cerrar modal para limpiar variables
        function cerrarModalPuntuaciones() {
            const modal = document.getElementById('ratingsModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            currentAnimeId = null;
            currentRating = 0;
        }

        // Función para mostrar notificaciones
        function showNotification(message, type = 'info') {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            
            // Estilos inline básicos
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                color: white;
                font-weight: bold;
                z-index: 10000;
                max-width: 400px;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
                ${type === 'success' ? 'background: linear-gradient(135deg, #00ff00, #00aa00);' : ''}
                ${type === 'error' ? 'background: linear-gradient(135deg, #ff0000, #aa0000);' : ''}
                ${type === 'info' ? 'background: linear-gradient(135deg, #00ffff, #0080ff);' : ''}
                ${type === 'warning' ? 'background: linear-gradient(135deg, #ffaa00, #ff6600);' : ''}
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            `;
            
            document.body.appendChild(notification);
            
            // Animar entrada
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Animar salida y eliminar
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }
        
        // Funciones del menú hamburguesa
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const hamburger = document.querySelector('.hamburger');
            
            if (mobileMenu && mobileOverlay && hamburger) {
                const isOpen = mobileMenu.classList.contains('show');
                
                if (isOpen) {
                    closeMobileMenu();
                } else {
                    mobileMenu.classList.add('show');
                    mobileOverlay.classList.add('show');
                    hamburger.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
        }
        
        function closeMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const hamburger = document.querySelector('.hamburger');
            
            if (mobileMenu && mobileOverlay && hamburger) {
                mobileMenu.classList.remove('show');
                mobileOverlay.classList.remove('show');
                hamburger.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        // Event listeners para el menú hamburguesa
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar menú móvil al hacer clic en un enlace
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    closeMobileMenu();
                });
            });
            
            // Cerrar menú móvil al redimensionar la ventana a desktop
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    closeMobileMenu();
                }
            });
        });
    </script>
</body>
</html>