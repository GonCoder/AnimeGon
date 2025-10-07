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

// Obtener animes favoritos del usuario con paginación
function obtenerAnimesFavoritos($usuario_id, $limite = 12) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT lu.*, a.titulo as anime_nombre, a.titulo_original, a.titulo_ingles, a.imagen_portada, a.episodios_total,
                         lu.episodios_vistos, lu.fecha_agregado, lu.estado, lu.puntuacion, a.id as anime_id,
                         a.tipo, a.estado as estado_anime, 1 as favorito
                  FROM favoritos f
                  INNER JOIN lista_usuario lu ON f.usuario_id = lu.usuario_id AND f.anime_id = lu.anime_id
                  LEFT JOIN animes a ON f.anime_id = a.id 
                  WHERE f.usuario_id = ?
                  ORDER BY a.titulo ASC
                  LIMIT ?";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id, $limite]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Obtener total de animes favoritos
function obtenerTotalAnimesFavoritos($usuario_id) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT COUNT(*) as total 
                  FROM favoritos f
                  WHERE f.usuario_id = ?";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        return 0;
    }
}

$animes_favoritos = obtenerAnimesFavoritos($usuario_id);
$total_favoritos = obtenerTotalAnimesFavoritos($usuario_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Mis Favoritos</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../favicon.svg">
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
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

        /* Reset y estilos base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0a0a0a, #1a1a2e, #16213e);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }
        
        /* Navbar Styles */
        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 2px solid rgba(0, 255, 255, 0.3);
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
            position: relative;
        }
        
        /* Menú hamburguesa */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            background: rgba(255, 140, 0, 0.2);
            border: 2px solid rgba(255, 140, 0, 0.6);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1006;
        }
        
        .hamburger:hover {
            background: rgba(255, 140, 0, 0.3);
            border-color: rgba(255, 140, 0, 0.8);
            box-shadow: 0 0 20px rgba(255, 140, 0, 0.5);
        }
        
        .hamburger span {
            width: 25px;
            height: 3px;
            background: #ff8c00;
            margin: 3px 0;
            transition: all 0.3s ease;
            border-radius: 2px;
            box-shadow: 0 0 8px rgba(255, 140, 0, 0.7);
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
            background: linear-gradient(135deg, rgba(205, 92, 0, 0.85), rgba(139, 69, 19, 0.85));
            backdrop-filter: blur(15px);
            border-left: 3px solid rgba(205, 92, 0, 0.7);
            padding: 80px 20px 20px;
            flex-direction: column;
            gap: 0;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1005;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.5);
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
            background: rgba(255, 140, 0, 0.1);
            backdrop-filter: blur(10px);
            display: block;
            width: 100%;
            box-sizing: border-box;
            font-weight: 600;
        }
        
        .nav-menu.mobile .nav-link:hover {
            background: rgba(255, 140, 0, 0.25);
            border-color: rgba(255, 140, 0, 0.5);
            transform: translateX(-5px);
            box-shadow: 0 5px 20px rgba(255, 140, 0, 0.4);
        }
        
        .nav-menu.mobile .nav-link.active {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.3), rgba(255, 140, 0, 0.3));
            border-color: #ffb000;
            color: #ffb000;
            box-shadow: 0 0 25px rgba(255, 176, 0, 0.5);
            font-weight: 700;
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
        
        .nav-logo h2 {
            color: #00ffff;
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.6);
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
            color: #00ffff;
            border-color: rgba(0, 255, 255, 0.5);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            border-color: transparent;
        }
        
        /* Estilos específicos para favoritos */
        .favoritos-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .favoritos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .favoritos-title {
            color: #ffd700;
            font-size: 2.5rem;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.6);
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
            border: 2px solid rgba(255, 215, 0, 0.3);
            border-radius: 25px;
            padding: 12px 20px;
            color: white;
            width: 300px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
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
            border: 2px solid rgba(255, 215, 0, 0.3);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .anime-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 215, 0, 0.8);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.4);
        }
        
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            z-index: 10;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.6);
        }
        
        .favorite-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
        }
        
        .anime-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
        }
        
        .anime-info {
            padding: 20px;
        }
        
        .anime-name {
            color: #ffd700;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tipo-badge {
            background: rgba(255, 215, 0, 0.2);
            color: #ffd700;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: normal;
            border: 1px solid rgba(255, 215, 0, 0.4);
        }
        
        .anime-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .progress-text {
            color: #ff007f;
            font-weight: bold;
        }
        
        .puntuacion-badge {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 10px;
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
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border-color: rgba(40, 167, 69, 0.4);
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
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffd700, #ff007f);
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .anime-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 15px;
        }
        
        .anime-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-action {
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-editar {
            background: linear-gradient(135deg, #00ffff, #0080ff);
            color: white;
        }
        
        .btn-editar:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.6);
        }
        
        .btn-eliminar {
            background: linear-gradient(135deg, #ff4757, #ff3742);
            color: white;
        }
        
        .btn-eliminar:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(255, 71, 87, 0.6);
        }
        
        .btn-quitar-favorito {
            background: linear-gradient(135deg, #ff6b9d, #c44569);
            color: white;
        }
        
        .btn-quitar-favorito:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(255, 107, 157, 0.6);
        }
        
        .estado-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .estado-viendo {
            background: rgba(0, 255, 255, 0.2);
            color: #00ffff;
        }
        
        .estado-completado {
            background: rgba(0, 255, 136, 0.2);
            color: #00ff88;
        }
        
        .estado-pausado {
            background: rgba(255, 215, 0, 0.2);
            color: #ffd700;
        }
        
        .estado-pendiente {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .estado-abandonado {
            background: rgba(255, 71, 87, 0.2);
            color: #ff4757;
        }
        
        .no-favoritos {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .no-favoritos h3 {
            color: #ffd700;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .btn-ir-animes {
            background: linear-gradient(135deg, #00ffff, #0080ff);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            margin-top: 20px;
        }
        
        .btn-ir-animes:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.6);
        }
        
        /* Estilos del Modal */
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
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f0f23);
            margin: 2% auto;
            padding: 0;
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.5);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #0f0f23, #1a1a2e);
            padding: 20px;
            border-bottom: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 13px 13px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            color: #00ffff;
            margin: 0;
            font-size: 1.5rem;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.6);
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
            color: #ffd700;
            font-size: 1.2rem;
            margin: 25px 0 15px 0;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.6);
            border-bottom: 2px solid rgba(255, 215, 0, 0.3);
            padding-bottom: 8px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #00ffff;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 8px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #00ffff;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.4);
        }
        
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2300ffff" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
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
            background: #00ffff !important;
            color: #000 !important;
        }
        
        .form-group select option:hover {
            background: rgba(0, 255, 255, 0.3) !important;
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
        
        .form-full-width {
            grid-column: 1 / -1;
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
            background: linear-gradient(135deg, #00ff88, #00cc6a);
            color: white;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.6);
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #ff4757, #ff3742);
            color: white;
        }
        
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255, 71, 87, 0.6);
        }
        
        .file-input-wrapper {
            position: relative;
        }
        
        .file-input {
            display: none;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }
        
        .file-input-label:hover {
            border-color: #00ffff;
            background: rgba(0, 255, 255, 0.1);
        }
        
        /* Estilos para modales de confirmación */
        .confirm-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
        }
        
        .confirm-modal-content {
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f0f23);
            margin: 8% auto;
            padding: 0;
            border: 2px solid rgba(255, 107, 157, 0.4);
            border-radius: 20px;
            width: 90%;
            max-width: 450px;
            max-height: 85vh;
            box-shadow: 0 0 40px rgba(255, 107, 157, 0.3);
            animation: confirmModalSlideIn 0.4s ease;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        @keyframes confirmModalSlideIn {
            from { transform: translateY(-100px) scale(0.8); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        
        .confirm-modal-header {
            background: linear-gradient(135deg, #ff6b9d, #c44569);
            padding: 25px;
            text-align: center;
            border-bottom: 2px solid rgba(255, 107, 157, 0.3);
        }
        
        .confirm-modal-icon {
            font-size: 3rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .confirm-modal-title {
            color: white;
            margin: 0;
            font-size: 1.4rem;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }
        
        .confirm-modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .confirm-modal-message {
            color: #ffffff;
            font-size: 1.1rem;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .confirm-modal-submessage {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .confirm-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            padding: 0 25px 25px 25px;
            box-sizing: border-box;
        }
        
        .btn-confirm, .btn-cancel-confirm {
            padding: 12px 20px;
            border: none;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            flex: 1;
            max-width: 130px;
            min-width: 90px;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, #ff6b9d, #c44569);
            color: white;
        }
        
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(255, 107, 157, 0.6);
        }
        
        .btn-cancel-confirm {
            background: linear-gradient(135deg, #666, #444);
            color: white;
        }
        
        .btn-cancel-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(102, 102, 102, 0.6);
        }

        /* Estilos para botón "Cargar más" */
        .load-more-container {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
        }
        
        .load-more-btn {
            background: linear-gradient(135deg, #8a2be2, #da70d6);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin: 0 auto;
            display: inline-block;
            min-width: 200px;
        }
        
        .load-more-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(138, 43, 226, 0.4);
            background: linear-gradient(135deg, #9932cc, #e6e6fa);
        }
        
        .load-more-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .loading-indicator {
            display: none;
            color: #8a2be2;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .loading-indicator.show {
            display: block;
        }
        
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #8a2be2;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Efectos de lazy loading para imágenes */
        .anime-image[loading="lazy"] {
            opacity: 0.3;
            transition: opacity 0.5s ease-in-out;
        }
        
        .anime-image[loading="lazy"].loaded {
            opacity: 1;
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
            
            .favoritos-header {
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
            
            .confirm-modal-content {
                width: 98%;
                margin: 5% auto;
            }
            
            .confirm-modal-body {
                padding: 20px;
            }
            
            .btn-confirm, .btn-cancel-confirm {
                padding: 12px 15px;
                font-size: 0.85rem;
                flex: 1;
                min-width: 85px;
                max-width: 120px;
            }
            
            .confirm-modal-buttons {
                gap: 12px;
                padding: 0 20px 20px 20px;
                margin: 15px 0 0 0;
            }
            
            .confirm-modal-content {
                width: 95%;
                margin: 5% auto;
                max-width: 380px;
            }
        }
        
        @media (max-width: 480px) {
            .nav-menu.mobile {
                width: 90vw;
            }
            
            .nav-logo h2 {
                font-size: 1.4rem;
            }
            
            .user-indicator {
                font-size: 0.7rem;
                padding: 3px 6px;
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
                <li><a href="favoritos.php" class="nav-link active">⭐ Favoritos</a></li>
                <li><a href="recomendados.php" class="nav-link">🎯 Recomendados</a></li>
                <li><a href="hub.php" class="nav-link">🌐 Hub</a></li>
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
            <li><a href="favoritos.php" class="nav-link active" onclick="closeMobileMenu()">⭐ Favoritos</a></li>
            <li><a href="recomendados.php" class="nav-link" onclick="closeMobileMenu()">🎯 Recomendados</a></li>
            <li><a href="hub.php" class="nav-link" onclick="closeMobileMenu()">🌐 Hub</a></li>
            <li><a href="perfil.php" class="nav-link" onclick="closeMobileMenu()">👤 Mi Perfil</a></li>
            <li><a href="logout.php" class="nav-link" onclick="closeMobileMenu()">🔴 Cerrar Sesión</a></li>
        </ul>
        
        <!-- Overlay para cerrar el menú -->
        <div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>
    </nav>

    <div class="favoritos-container">
        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="mensaje-exito" style="background: rgba(0, 255, 136, 0.2); border: 2px solid #00ff88; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #00ff88; text-align: center;">
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
        
        <div class="favoritos-header">
            <h1 class="favoritos-title">⭐ Mis Favoritos</h1>
            <?php if (!empty($animes_favoritos)): ?>
                <div class="filter-section">
                    <input type="text" id="searchInput" class="search-input" placeholder="🔍 Buscar en favoritos...">
                    <span style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
                        <?= $total_favoritos ?> anime<?= $total_favoritos != 1 ? 's' : '' ?> favorito<?= $total_favoritos != 1 ? 's' : '' ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="animes-grid" id="favoritosContainer">
            <?php if (empty($animes_favoritos)): ?>
                <div class="no-favoritos" style="grid-column: 1 / -1;">
                    <h3>⭐ ¡Aún no tienes animes favoritos!</h3>
                    <p>Visita tu lista de animes y marca con la estrella ⭐ aquellos que más te gusten.</p>
                    <a href="mis_animes.php" class="btn-ir-animes">
                        📺 Ir a Mis Animes
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($animes_favoritos as $anime): ?>
                    <?php
                    $progreso = 0;
                    if ($anime['episodios_total'] > 0) {
                        $progreso = ($anime['episodios_vistos'] / $anime['episodios_total']) * 100;
                    }
                    
                    $estado_class = 'estado-pendiente';
                    $estado_text = 'Plan de Ver';
                    
                    if (isset($anime['estado'])) {
                        switch ($anime['estado']) {
                            case 'Viendo':
                                $estado_class = 'estado-viendo';
                                $estado_text = 'Viendo';
                                break;
                            case 'Completado':
                                $estado_class = 'estado-completado';
                                $estado_text = 'Completado';
                                break;
                            case 'En Pausa':
                                $estado_class = 'estado-pausado';
                                $estado_text = 'En Pausa';
                                break;
                            case 'Plan de Ver':
                                $estado_class = 'estado-pendiente';
                                $estado_text = 'Plan de Ver';
                                break;
                            case 'Abandonado':
                                $estado_class = 'estado-abandonado';
                                $estado_text = 'Abandonado';
                                break;
                        }
                    }
                    ?>
                    
                    <div class="anime-card" data-anime-name="<?= htmlspecialchars(strtolower($anime['anime_nombre'] ?? $anime['titulo'] ?? 'Sin nombre')) ?>">
                        <button class="favorite-btn favorito" 
                                data-anime-id="<?= $anime['anime_id'] ?>" 
                                onclick="toggleFavorito(<?= $anime['anime_id'] ?>, this)"
                                title="Quitar de favoritos">
                            ⭐
                        </button>
                        
                        <?php if (!empty($anime['imagen_portada'])): ?>
                            <?php 
                            // Ajustar ruta para imágenes locales desde views/
                            $ruta_imagen = $anime['imagen_portada'];
                            if (strpos($ruta_imagen, 'img/') === 0) {
                                $ruta_imagen = '../' . $ruta_imagen;
                            }
                            ?>
                            <img src="<?= htmlspecialchars($ruta_imagen) ?>" 
                                 alt="<?= htmlspecialchars($anime['anime_nombre'] ?? $anime['nombre']) ?>" 
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
                                <?= htmlspecialchars($anime['anime_nombre'] ?? $anime['titulo'] ?? 'Sin nombre') ?>
                                <?php if (!empty($anime['tipo'])): ?>
                                    <span class="tipo-badge"><?= htmlspecialchars($anime['tipo']) ?></span>
                                <?php endif; ?>
                            </h3>
                            
                            <?php if (!empty($anime['titulo_original']) || !empty($anime['titulo_ingles'])): ?>
                                <div style="margin-bottom: 12px; font-size: 0.85rem; opacity: 0.8;">
                                    <?php if (!empty($anime['titulo_original'])): ?>
                                        <div style="color: #ffd700; margin-bottom: 3px;">
                                            🇯🇵 <?= htmlspecialchars($anime['titulo_original']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($anime['titulo_ingles'])): ?>
                                        <div style="color: #00ffff;">
                                            🇺🇸 <?= htmlspecialchars($anime['titulo_ingles']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="anime-progress">
                                <span class="progress-text">
                                    <?= $anime['episodios_vistos'] ?> / <?= $anime['episodios_total'] ?: '?' ?> episodios
                                </span>
                                <?php if (!empty($anime['puntuacion'])): ?>
                                    <span class="puntuacion-badge">
                                        ⭐ <?= number_format($anime['puntuacion'], 1) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($anime['estado_anime'])): ?>
                                <div class="estado-anime">
                                    <?php
                                    // Determinar ícono y clase para el estado del anime
                                    $estado_anime_icon = '';
                                    $estado_anime_class = '';
                                    switch($anime['estado_anime']) {
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
                                        <?= $estado_anime_icon ?> <?= htmlspecialchars($anime['estado_anime']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $progreso ?>%"></div>
                            </div>
                            
                            <div class="anime-meta">
                                <span class="estado-badge <?= $estado_class ?>"><?= $estado_text ?></span>
                                <span><?= date('d/m/Y', strtotime($anime['fecha_agregado'])) ?></span>
                            </div>
                            
                            <div class="anime-actions">
                                <button class="btn-action btn-editar" data-anime-id="<?= $anime['anime_id'] ?>">
                                    ✏️ Editar
                                </button>
                                <button class="btn-action btn-quitar-favorito" data-anime-id="<?= $anime['anime_id'] ?>" data-anime-nombre="<?= htmlspecialchars($anime['anime_nombre'] ?? $anime['titulo'] ?? 'Sin nombre') ?>">
                                    💔 Quitar de Favoritos
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Botón para cargar más favoritos -->
        <?php if ($total_favoritos > 12): ?>
        <div class="load-more-container" id="loadMoreFavoritosContainer">
            <button class="load-more-btn" id="cargarMasFavoritos" onclick="cargarMasFavoritos()">
                <span class="load-more-text">📄 Cargar más favoritos</span>
                <span class="load-more-count">(<?= min(12, $total_favoritos - 12) ?> de <?= $total_favoritos - 12 ?> restantes)</span>
            </button>
            <div class="loading-indicator" id="loadingFavoritos" style="display: none;">
                <div class="spinner"></div>
                <span>Cargando favoritos...</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de confirmación para quitar de favoritos -->
    <div id="confirmRemoveFavoriteModal" class="confirm-modal">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon">💔</div>
                <h3 class="confirm-modal-title">Quitar de Favoritos</h3>
            </div>
            <div class="confirm-modal-body">
                <div class="confirm-modal-message" id="removeFavoriteMessage">
                    ¿Estás seguro de que quieres quitar este anime de tus favoritos?
                </div>
                <div class="confirm-modal-submessage">
                    El anime permanecerá en tu lista, solo se quitará de favoritos. Podrás volver a marcarlo como favorito cuando quieras.
                </div>
                <div class="confirm-modal-buttons">
                    <button class="btn-confirm" id="confirmRemoveFavoriteBtn">
                        💔 Sí, quitar
                    </button>
                    <button class="btn-cancel-confirm" id="cancelRemoveFavoriteBtn">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar anime -->
    <div id="editAnimeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">✏️ Editar Anime</h2>
                <span class="close" onclick="cerrarModalEditar()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editAnimeForm" enctype="multipart/form-data">
                    <input type="hidden" id="edit_anime_id" name="anime_id">
                    
                    <!-- Información básica -->
                    <h4 class="form-section-title">📝 Información Básica</h4>
                    
                    <div class="form-group form-full-width">
                        <label for="edit_nombre">📝 Nombre del Anime (Español)</label>
                        <input type="text" id="edit_nombre" name="nombre" required placeholder="Ej: Ataque a los Titanes">
                    </div>
                    
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label for="edit_titulo_original">🏮 Título Original (Japonés)</label>
                            <input type="text" id="edit_titulo_original" name="titulo_original" placeholder="Ej: 進撃の巨人">
                            <div class="file-info">Opcional: Título en idioma original</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_titulo_ingles">🇺🇸 Título en Inglés</label>
                            <input type="text" id="edit_titulo_ingles" name="titulo_ingles" placeholder="Ej: Attack on Titan">
                            <div class="file-info">Opcional: Título oficial en inglés</div>
                        </div>
                    </div>
                    
                    <!-- Detalles del anime -->
                    <h4 class="form-section-title">🎬 Detalles del Anime</h4>
                    
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label for="edit_tipo">🎬 Tipo de Anime</label>
                            <select id="edit_tipo" name="tipo" required>
                                <option value="TV">📺 Serie TV</option>
                                <option value="OVA">💽 OVA</option>
                                <option value="Película">🎬 Película</option>
                                <option value="Especial">⭐ Especial</option>
                                <option value="ONA">🌐 ONA (Web)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_estado_anime">📊 Estado del Anime</label>
                            <select id="edit_estado_anime" name="estado_anime" required>
                                <option value="Finalizado">✅ Finalizado</option>
                                <option value="Emitiendo">📡 Emitiendo</option>
                                <option value="Próximamente">🔜 Próximamente</option>
                                <option value="Cancelado">❌ Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_total_episodios">📊 Total de Episodios</label>
                            <input type="number" id="edit_total_episodios" name="total_episodios" min="1" placeholder="Ej: 25">
                            <div class="file-info">Deja vacío si no se conoce</div>
                        </div>
                    </div>
                    
                    <!-- Mi seguimiento -->
                    <h4 class="form-section-title">🎯 Mi Seguimiento</h4>
                    
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label for="edit_capitulos_vistos">👁️ Episodios Vistos</label>
                            <input type="number" id="edit_capitulos_vistos" name="episodios_vistos" min="0" placeholder="Ej: 12">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_estado">🎯 Mi Estado</label>
                            <select id="edit_estado" name="estado" required>
                                <option value="Plan de Ver">⏳ Plan de Ver</option>
                                <option value="Viendo">👀 Viendo</option>
                                <option value="Completado">✅ Completado</option>
                                <option value="En Pausa">⏸️ En Pausa</option>
                                <option value="Abandonado">❌ Abandonado</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_puntuacion">⭐ Mi Puntuación</label>
                            <select id="edit_puntuacion" name="puntuacion">
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
                    
                    <!-- URL de AnimeFLV -->
                    <h4 class="form-section-title">🔗 Enlace Externo (opcional)</h4>
                    
                    <div class="form-group form-full-width">
                        <label for="edit_animeflv_url_name">🔗 URL de AnimeFLV</label>
                        <input type="text" id="edit_animeflv_url_name" name="animeflv_url_name" placeholder="Ej: shingeki-no-kyojin">
                        <div class="file-info">Opcional: Nombre del anime en la URL de AnimeFLV</div>
                    </div>

                    <!-- Imagen -->
                    <h4 class="form-section-title">🖼️ Nueva Imagen (opcional)</h4>
                    
                    <div class="form-group form-full-width image-section">
                        <label for="edit_imagen_url" style="display: none;">🖼️ Nueva Imagen (opcional)</label>
                        
                        <div class="form-row form-row-2">
                            <!-- Opción de URL -->
                            <div>
                                <label for="edit_imagen_url" style="font-size: 0.9rem; color: #28a745;">🌐 Nueva URL de imagen</label>
                                <input type="url" id="edit_imagen_url" name="imagen_url" class="form-control" 
                                       placeholder="https://example.com/imagen.jpg" 
                                       style="margin-top: 5px;">
                                <div class="file-info">URL de imagen online</div>
                            </div>
                            
                            <!-- Opción de subir archivo -->
                            <div>
                                <label for="edit_imagen" style="font-size: 0.9rem; color: #666;">📎 Subir nueva imagen</label>
                                <div class="file-input-wrapper" style="margin-top: 5px;">
                                    <input type="file" id="edit_imagen" name="imagen" class="file-input" accept="image/jpeg,image/jpg,image/png,image/x-icon">
                                    <label for="edit_imagen" class="file-input-label">
                                        📎 JPG, PNG, ICO (máx. 1MB)
                                    </label>
                                </div>
                                <div class="file-info">Archivo desde dispositivo</div>
                            </div>
                        </div>
                        
                        <div class="file-info" style="text-align: center; margin-top: 10px;">💡 Deja ambos campos vacíos para mantener la imagen actual</div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn-submit">✅ Guardar Cambios</button>
                        <button type="button" class="btn-cancel" onclick="cerrarModalEditar()">❌ Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../frontend/assets/js/animes.js"></script>
    <script>
        // Función para configurar event listeners en nuevos elementos
        function configurarEventListenersFavoritos() {
            // Asegurar que AnimeManager esté disponible
            if (!window.animeManager && typeof AnimeManager !== 'undefined') {
                window.animeManager = new AnimeManager();
            }
            
            // Event listeners para botones de editar (usar la clase correcta)
            const editButtons = document.querySelectorAll('.btn-action.btn-editar:not(.configured)');
            console.log('Configurando event listeners para', editButtons.length, 'botones de editar');
            
            editButtons.forEach(button => {
                button.classList.add('configured');
                button.addEventListener('click', function() {
                    console.log('Botón editar clickeado');
                    const animeId = this.getAttribute('data-anime-id');
                    console.log('Anime ID:', animeId);
                    console.log('AnimeManager disponible:', !!window.animeManager);
                    
                    if (animeId) {
                        // Asegurar que AnimeManager esté disponible en el momento del click
                        if (!window.animeManager && typeof AnimeManager !== 'undefined') {
                            window.animeManager = new AnimeManager();
                        }
                        
                        if (window.animeManager) {
                            console.log('Llamando abrirModalEditar para anime', animeId);
                            window.animeManager.abrirModalEditar(animeId);
                        } else {
                            console.error('AnimeManager no está disponible');
                        }
                    } else {
                        console.error('No se encontró anime ID');
                    }
                });
            });
            
            // Event listeners para botones de quitar favoritos (usar la clase correcta)
            document.querySelectorAll('.btn-action.btn-quitar-favorito:not(.configured)').forEach(button => {
                button.classList.add('configured');
                button.addEventListener('click', function() {
                    const animeId = this.getAttribute('data-anime-id');
                    const animeNombre = this.getAttribute('data-anime-nombre');
                    if (animeId && animeNombre) {
                        confirmarQuitarDeFavoritos(animeId, animeNombre, this);
                    }
                });
            });
        }

        // Inicializar el AnimeManager cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Crear instancia del AnimeManager si no existe
            if (typeof animeManager === 'undefined') {
                window.animeManager = new AnimeManager();
            }
            
            // Configurar event listeners iniciales
            configurarEventListenersFavoritos();
        });
        
        // Filtrado específico para favoritos
        document.getElementById('searchInput')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const animeCards = document.querySelectorAll('.anime-card');
            
            animeCards.forEach(card => {
                const animeName = card.getAttribute('data-anime-name');
                if (animeName.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Función específica para quitar de favoritos con modal personalizado
        function confirmarQuitarDeFavoritos(animeId, animeNombre, buttonElement) {
            const modal = document.getElementById('confirmRemoveFavoriteModal');
            const removeFavoriteMessage = document.getElementById('removeFavoriteMessage');
            const confirmBtn = document.getElementById('confirmRemoveFavoriteBtn');
            const cancelBtn = document.getElementById('cancelRemoveFavoriteBtn');
            
            // Actualizar el mensaje con el título del anime
            removeFavoriteMessage.textContent = `¿Estás seguro de que quieres quitar "${animeNombre}" de tus favoritos?`;
            
            // Mostrar el modal
            modal.style.display = 'flex';
            
            // Configurar los botones
            confirmBtn.onclick = () => {
                modal.style.display = 'none';
                
                // Buscar el botón de estrella de favoritos en la misma tarjeta
                const card = buttonElement.closest('.anime-card');
                const favoriteButton = card.querySelector('.favorite-btn.favorito');
                
                if (favoriteButton) {
                    // Simular clic en el botón de estrella para quitar de favoritos
                    favoriteButton.click();
                } else {
                    // Si no encuentra el botón, llamar directamente a toggleFavorito
                    if (typeof toggleFavorito === 'function') {
                        toggleFavorito(animeId, favoriteButton);
                    }
                }
            };
            
            cancelBtn.onclick = () => {
                modal.style.display = 'none';
            };
            
            // Cerrar con escape
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    modal.style.display = 'none';
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            
            document.addEventListener('keydown', handleEscape);
            
            // Cerrar al hacer clic en el fondo
            modal.onclick = (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            };
        }
        
        // Override del toggleFavorito para actualizar la página cuando se quite un favorito
        const originalToggleFavorito = window.toggleFavorito;
        window.toggleFavorito = function(animeId, button) {
            originalToggleFavorito(animeId, button);
            
            // Si se quita de favoritos, ocultar la tarjeta después de un delay
            setTimeout(() => {
                if (!button.classList.contains('favorito')) {
                    const card = button.closest('.anime-card');
                    card.style.transition = 'all 0.5s ease';
                    card.style.transform = 'scale(0)';
                    card.style.opacity = '0';
                    
                    setTimeout(() => {
                        card.remove();
                        
                        // Verificar si no quedan favoritos
                        const remainingCards = document.querySelectorAll('.anime-card');
                        if (remainingCards.length === 0) {
                            location.reload(); // Recargar para mostrar el mensaje de "no hay favoritos"
                        }
                    }, 500);
                }
            }, 1000);
        };
        
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
        
        // Variables globales para paginación
        let paginaFavoritos = 1;
        let totalFavoritos = <?php echo isset($total_favoritos) ? (int)$total_favoritos : 0; ?>;
        const favoritosPorPagina = 12;
        
        // Función para cargar más animes favoritos
        window.cargarMasFavoritos = async function() {
            const btnCargarMas = document.getElementById('cargarMasFavoritos');
            const loadingIndicator = document.getElementById('loadingFavoritos');
            const container = document.getElementById('favoritosContainer');
            
            // Mostrar loading
            btnCargarMas.disabled = true;
            loadingIndicator.classList.add('show');
            
            try {
                paginaFavoritos++;
                const response = await fetch(`../backend/api/obtener_favoritos_paginados.php?pagina=${paginaFavoritos}&limite=${favoritosPorPagina}`);
                const data = await response.json();
                
                if (data.success && data.animes.length > 0) {
                    // Agregar nuevos animes al contenedor
                    data.animes.forEach(anime => {
                        const animeCard = crearTarjetaFavorito(anime);
                        container.appendChild(animeCard);
                    });
                    
                    // Actualizar contador
                    const favoritosRestantes = totalFavoritos - (paginaFavoritos * favoritosPorPagina);
                    if (favoritosRestantes > 0) {
                        btnCargarMas.innerHTML = `<span class="load-more-text">📄 Cargar más favoritos</span><span class="load-more-count">(${Math.min(favoritosPorPagina, favoritosRestantes)} de ${favoritosRestantes} restantes)</span>`;
                    } else {
                        btnCargarMas.style.display = 'none';
                    }
                    
                    // Activar lazy loading para nuevas imágenes
                    activarLazyLoading();
                    
                    // Configurar event listeners para los nuevos elementos
                    configurarEventListenersFavoritos();
                } else {
                    btnCargarMas.style.display = 'none';
                }
            } catch (error) {
                console.error('Error al cargar más favoritos:', error);
            } finally {
                btnCargarMas.disabled = false;
                loadingIndicator.classList.remove('show');
            }
        };
        
        // Función para crear tarjetas de anime dinámicamente
        function crearTarjetaFavorito(anime) {
            const div = document.createElement('div');
            div.className = 'anime-card';
            
            const progreso = anime.episodios_total > 0 ? (anime.episodios_vistos / anime.episodios_total) * 100 : 0;
            const imagenSrc = anime.imagen_portada ? (anime.imagen_portada.startsWith('img/') ? '../' + anime.imagen_portada : anime.imagen_portada) : '../img/no-image.png';
            
            div.innerHTML = `
                <div class="anime-image-container">
                    <img src="${imagenSrc}" 
                         alt="${anime.anime_nombre || anime.nombre || 'Sin nombre'}" 
                         class="anime-image" 
                         loading="lazy"
                         onload="this.style.opacity='1'"
                         onerror="this.src='../img/no-image.png'; this.style.opacity='1'">
                    
                    <div class="anime-progress-overlay">
                        <div class="anime-progress-bar">
                            <div class="anime-progress-fill" style="width: ${progreso}%"></div>
                        </div>
                        <div class="anime-progress-text">${Math.round(progreso)}%</div>
                    </div>
                </div>
                
                <div class="anime-info">
                    <h3 class="anime-name">
                        ${anime.anime_nombre || anime.titulo || 'Sin nombre'}
                    </h3>
                    <div class="anime-details">
                        <span class="anime-type">${anime.tipo || 'N/A'}</span>
                        <span class="anime-episodes">${anime.episodios_vistos}/${anime.episodios_total || '?'}</span>
                        <span class="anime-status ${anime.estado?.toLowerCase() || 'sin-definir'}">${anime.estado || 'Sin definir'}</span>
                        ${anime.puntuacion ? `<span class="anime-rating">⭐ ${anime.puntuacion}</span>` : ''}
                    </div>
                    
                    <div class="anime-actions">
                        <button class="btn-action btn-editar" data-anime-id="${anime.anime_id}">
                            ✏️ Editar
                        </button>
                        <button class="btn-action btn-quitar-favorito" data-anime-id="${anime.anime_id}" data-anime-nombre="${anime.anime_nombre || anime.titulo}">
                            💔 Quitar de Favoritos
                        </button>
                    </div>
                </div>
            `;
            
            return div;
        }
        
        // Función para activar lazy loading en las nuevas imágenes
        function activarLazyLoading() {
            const imagenes = document.querySelectorAll('.anime-image[loading="lazy"]:not(.loaded)');
            
            imagenes.forEach(img => {
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                });
                
                // Si la imagen ya está cargada
                if (img.complete) {
                    img.classList.add('loaded');
                }
            });
        }
        
        // Activar lazy loading inicial
        activarLazyLoading();

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
            
            // Variables globales para paginación
            let paginaFavoritos = 1;
            let totalFavoritos = <?php echo isset($total_favoritos) ? (int)$total_favoritos : 0; ?>;
            const favoritosPorPagina = 12;
            
            // Función para cargar más favoritos
            window.cargarMasFavoritos = async function() {
                const btnCargarMas = document.getElementById('cargarMasFavoritos');
                const loadingIndicator = document.getElementById('loadingFavoritos');
                const container = document.getElementById('favoritosContainer');
                
                // Mostrar loading
                btnCargarMas.disabled = true;
                loadingIndicator.classList.add('show');
                
                try {
                    paginaFavoritos++;
                    const response = await fetch(`../backend/api/obtener_favoritos_paginados.php?pagina=${paginaFavoritos}&limite=${favoritosPorPagina}`);
                    const data = await response.json();
                    
                    if (data.success && data.animes.length > 0) {
                        // Agregar nuevos favoritos al contenedor
                        data.animes.forEach(anime => {
                            const animeCard = crearTarjetaFavorito(anime);
                            container.appendChild(animeCard);
                        });
                        
                        // Actualizar contador
                        const favoritosRestantes = totalFavoritos - (paginaFavoritos * favoritosPorPagina);
                        if (favoritosRestantes > 0) {
                            btnCargarMas.innerHTML = `<span class="load-more-text">📄 Cargar más favoritos</span>
                                                       <span class="load-more-count">(${Math.min(favoritosPorPagina, favoritosRestantes)} de ${favoritosRestantes} restantes)</span>`;
                        } else {
                            btnCargarMas.style.display = 'none';
                        }
                        
                        // Activar lazy loading para nuevas imágenes
                        activarLazyLoading();
                        
                        // Configurar event listeners para los nuevos elementos
                        configurarEventListenersFavoritos();
                    } else {
                        btnCargarMas.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Error al cargar más favoritos:', error);
                } finally {
                    btnCargarMas.disabled = false;
                    loadingIndicator.classList.remove('show');
                }
            };
            
            // Función para crear tarjetas de favoritos dinámicamente
            function crearTarjetaFavorito(anime) {
                const div = document.createElement('div');
                div.className = 'anime-card';
                div.setAttribute('data-anime-name', anime.anime_nombre.toLowerCase());
                
                const progreso = anime.episodios_total > 0 ? (anime.episodios_vistos / anime.episodios_total) * 100 : 0;
                
                // Determinar estado y clase
                let estadoClass = '';
                let estadoText = '';
                if (anime.estado) {
                    switch (anime.estado) {
                        case 'Viendo':
                            estadoClass = 'estado-viendo';
                            estadoText = 'Viendo';
                            break;
                        case 'Completado':
                            estadoClass = 'estado-completado';
                            estadoText = 'Completado';
                            break;
                        case 'En Pausa':
                            estadoClass = 'estado-pausado';
                            estadoText = 'En Pausa';
                            break;
                        case 'Plan de Ver':
                            estadoClass = 'estado-pendiente';
                            estadoText = 'Plan de Ver';
                            break;
                        case 'Abandonado':
                            estadoClass = 'estado-abandonado';
                            estadoText = 'Abandonado';
                            break;
                        default:
                            estadoClass = '';
                            estadoText = anime.estado;
                    }
                }
                
                // Determinar estado del anime
                let estadoAnimeIcon = '';
                let estadoAnimeClass = '';
                if (anime.estado_anime) {
                    switch(anime.estado_anime) {
                        case 'Finalizado':
                            estadoAnimeIcon = '✅';
                            estadoAnimeClass = 'finalizado';
                            break;
                        case 'Emitiendo':
                            estadoAnimeIcon = '📡';
                            estadoAnimeClass = 'emitiendo';
                            break;
                        case 'Próximamente':
                            estadoAnimeIcon = '🔜';
                            estadoAnimeClass = 'proximamente';
                            break;
                        case 'Cancelado':
                            estadoAnimeIcon = '❌';
                            estadoAnimeClass = 'cancelado';
                            break;
                        default:
                            estadoAnimeIcon = '❓';
                            estadoAnimeClass = 'desconocido';
                    }
                }
                
                div.innerHTML = `
                    <button class="favorite-btn favorito" 
                            data-anime-id="${anime.anime_id}" 
                            onclick="toggleFavorito(${anime.anime_id}, this)"
                            title="Quitar de favoritos">
                        ⭐
                    </button>
                    
                    ${anime.imagen_portada ? `
                        <img src="${anime.imagen_portada.startsWith('img/') ? '../' + anime.imagen_portada : anime.imagen_portada}" 
                             alt="${anime.anime_nombre}" 
                             class="anime-image" 
                             loading="lazy"
                             onload="this.style.opacity='1'"
                             onerror="this.src='../img/no-image.png'; this.style.opacity='1'">
                    ` : `
                        <div class="anime-image" style="display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.5); font-size: 3rem;">
                            🎭
                        </div>
                    `}
                    
                    <div class="anime-info">
                        <h3 class="anime-name">
                            ${anime.anime_nombre}
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
                        
                        <div class="anime-progress">
                            <span class="progress-text">
                                ${anime.episodios_vistos} / ${anime.episodios_total || '?'} episodios
                            </span>
                            ${anime.puntuacion ? `
                                <span class="puntuacion-badge">
                                    ⭐ ${parseFloat(anime.puntuacion).toFixed(1)}
                                </span>
                            ` : ''}
                        </div>
                        
                        ${anime.estado_anime ? `
                            <div class="estado-anime">
                                <span class="estado-anime-badge ${estadoAnimeClass}">
                                    ${estadoAnimeIcon} ${anime.estado_anime}
                                </span>
                            </div>
                        ` : ''}
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${progreso}%"></div>
                        </div>
                        
                        <div class="anime-meta">
                            <span class="estado-badge ${estadoClass}">${estadoText}</span>
                            <span>${new Date(anime.fecha_agregado).toLocaleDateString('es-ES')}</span>
                        </div>
                        
                        <div class="anime-actions">
                            <button class="btn-action btn-editar" data-anime-id="${anime.anime_id}">
                                ✏️ Editar
                            </button>
                            <button class="btn-action btn-quitar-favorito" data-anime-id="${anime.anime_id}" data-anime-nombre="${anime.anime_nombre}">
                                💔 Quitar de Favoritos
                            </button>
                        </div>
                    </div>
                `;
                
                return div;
            }
            
            // Función para activar lazy loading en las nuevas imágenes
            function activarLazyLoading() {
                const imagenes = document.querySelectorAll('.anime-image[loading="lazy"]:not(.loaded)');
                
                imagenes.forEach(img => {
                    img.addEventListener('load', function() {
                        this.classList.add('loaded');
                    });
                    
                    // Si la imagen ya está cargada
                    if (img.complete) {
                        img.classList.add('loaded');
                    }
                });
            }
            

            
            // Activar lazy loading inicial
            activarLazyLoading();
        });
    </script>
</body>
</html>