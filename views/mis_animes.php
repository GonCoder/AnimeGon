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

// Obtener animes del usuario (solo primeros 12 para carga inicial)
function obtenerAnimesUsuario($usuario_id, $limite = 12) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT lu.*, a.titulo as anime_nombre, a.titulo_original, a.titulo_ingles, a.imagen_portada, a.episodios_total,
                         lu.episodios_vistos, lu.fecha_agregado, lu.estado, lu.puntuacion, a.animeflv_url_name, a.id as anime_id,
                         a.tipo, a.estado as estado_anime,
                         (CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END) as favorito
                  FROM lista_usuario lu 
                  LEFT JOIN animes a ON lu.anime_id = a.id 
                  LEFT JOIN favoritos f ON lu.usuario_id = f.usuario_id AND lu.anime_id = f.anime_id
                  WHERE lu.usuario_id = ? 
                  ORDER BY lu.fecha_agregado DESC
                  LIMIT ?";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id, $limite]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Obtener el total de animes del usuario
function obtenerTotalAnimesUsuario($usuario_id) {
    try {
        $conexion = obtenerConexion();
        $query = "SELECT COUNT(*) as total FROM lista_usuario WHERE usuario_id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['total'];
    } catch (Exception $e) {
        return 0;
    }
}

$animes = obtenerAnimesUsuario($usuario_id);
$total_animes = obtenerTotalAnimesUsuario($usuario_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Mis Animes</title>
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
        
        .nav-logo h2 {
            color: #00ffff;
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.6);
            font-size: 1.8rem;
            margin: 0;
        }
        
        .nav-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
            list-style: none;
            margin: 0;
            padding: 0;
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
            background: linear-gradient(135deg, #ff007f, #bf00ff);
            color: white;
            border-color: transparent;
        }
        
        /* Botón hamburguesa */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 5px;
            z-index: 1001;
        }
        
        .hamburger span {
            width: 25px;
            height: 3px;
            background: #00ffff;
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 2px;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        
        /* Animación del botón hamburguesa */
        .hamburger.active span:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }
        
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger.active span:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }
        
        /* Estilos para menú móvil */
        .nav-menu.mobile {
            position: fixed;
            top: 0;
            left: -100%;
            width: 80%;
            max-width: 300px;
            height: 100vh;
            flex-direction: column;
            justify-content: flex-start;
            align-items: flex-start;
            background: linear-gradient(135deg, rgba(0, 26, 46, 0.98), rgba(0, 33, 62, 0.98));
            backdrop-filter: blur(20px);
            padding-top: 80px;
            gap: 0;
            transition: left 0.3s ease;
            border-right: 2px solid rgba(0, 255, 255, 0.4);
            box-shadow: 5px 0 20px rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .nav-menu.mobile.active {
            left: 0;
        }
        
        .nav-menu.mobile .nav-link {
            width: 100%;
            padding: 15px 25px;
            border-radius: 0;
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
            text-align: left;
            font-size: 1.1rem;
            color: white !important;
            display: block;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .nav-menu.mobile .nav-link:hover {
            background: rgba(0, 255, 255, 0.2);
            color: #00ffff !important;
            border-left: 4px solid #00ffff;
            transform: translateX(5px);
        }
        
        .nav-menu.mobile .nav-link.active {
            background: linear-gradient(135deg, #ff007f, #00ffff);
            color: white !important;
            border-left: 4px solid #ffffff;
        }
        
        /* Overlay para cerrar menú móvil */
        .nav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 300px;
            width: calc(100% - 300px);
            height: 100%;
            background: transparent;
            z-index: 1001;
            transition: all 0.3s ease;
        }
        
        .nav-overlay.active {
            display: block;
            opacity: 1;
        }
        
        /* Media query para overlay responsive */
        @media (max-width: 480px) {
            .nav-overlay {
                left: 90vw;
                width: 10vw;
            }
        }
        
        /* Botón hamburguesa */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 5px;
            z-index: 1001;
        }
        
        .hamburger span {
            width: 25px;
            height: 3px;
            background: #00ffff;
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 2px;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        
        /* Animación del botón hamburguesa */
        .hamburger.active span:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }
        
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger.active span:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }
        
        /* Estilos para menú móvil */
        .nav-menu.mobile {
            position: fixed;
            top: 0;
            left: -100%;
            width: 80%;
            max-width: 300px;
            height: 100vh;
            flex-direction: column;
            justify-content: flex-start;
            align-items: flex-start;
            background: linear-gradient(135deg, rgba(0, 26, 46, 0.98), rgba(0, 33, 62, 0.98));
            backdrop-filter: blur(20px);
            padding-top: 80px;
            gap: 0;
            transition: left 0.3s ease;
            border-right: 2px solid rgba(0, 255, 255, 0.4);
            box-shadow: 5px 0 20px rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .nav-menu.mobile.active {
            left: 0;
        }
        
        .nav-menu.mobile .nav-link {
            width: 100%;
            padding: 15px 25px;
            border-radius: 0;
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
            text-align: left;
            font-size: 1.1rem;
            color: white !important;
            display: block;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .nav-menu.mobile .nav-link:hover {
            background: rgba(0, 255, 255, 0.2);
            color: #00ffff !important;
            border-left: 4px solid #00ffff;
            transform: translateX(5px);
        }
        
        .nav-menu.mobile .nav-link.active {
            background: linear-gradient(135deg, #ff007f, #00ffff);
            color: white !important;
            border-left: 4px solid #ffffff;
        }
        
        /* Overlay para cerrar menú móvil */
        .nav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 300px;
            width: calc(100% - 300px);
            height: 100%;
            background: transparent;
            z-index: 1001;
            transition: all 0.3s ease;
        }
        
        .nav-overlay.active {
            display: block;
            opacity: 1;
        }
        
        /* Media query para overlay responsive */
        @media (max-width: 480px) {
            .nav-overlay {
                left: 90vw;
                width: 10vw;
            }
        }
        
        /* Estilos específicos para la página de animes */
        .animes-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .animes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .animes-title {
            color: #00ffff;
            font-size: 2.5rem;
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.6);
            margin: 0;
        }
        
        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .search-input {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 25px;
            padding: 12px 20px;
            color: white;
            width: 300px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #00ffff;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.4);
        }
        
        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* Estilos para controles de filtro específicos */
        .filter-controls {
            display: flex !important;
            gap: 15px !important;
            align-items: center !important;
            flex-wrap: wrap !important;
        }

        .btn-filtro-viendo {
            background: rgba(0, 255, 255, 0.1) !important;
            color: #00ffff !important;
            border: 2px solid rgba(0, 255, 255, 0.3) !important;
            border-radius: 25px !important;
            padding: 12px 20px !important;
            font-size: 16px !important;
            font-weight: bold !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            white-space: nowrap !important;
            position: relative !important;
            overflow: hidden !important;
            text-decoration: none !important;
        }

        .btn-filtro-viendo:before {
            content: "" !important;
            position: absolute !important;
            top: 0 !important;
            left: -100% !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(90deg, transparent, rgba(0, 255, 255, 0.2), transparent) !important;
            transition: left 0.5s !important;
        }

        .btn-filtro-viendo:hover:before {
            left: 100% !important;
        }

        .btn-filtro-viendo:hover {
            border-color: #00ffff !important;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.4) !important;
            transform: translateY(-2px) !important;
        }

        .btn-filtro-viendo.active {
            background: linear-gradient(135deg, #00ffff, #0080ff) !important;
            color: #000 !important;
            border-color: #00ffff !important;
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.6) !important;
            font-weight: 900 !important;
        }

        .btn-filtro-viendo.active:hover {
            background: linear-gradient(135deg, #0080ff, #00ffff) !important;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.8) !important;
        }

        .filtro-estado {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 2px solid rgba(255, 0, 127, 0.3) !important;
            border-radius: 25px !important;
            padding: 12px 20px !important;
            color: white !important;
            font-size: 16px !important;
            font-weight: bold !important;
            transition: all 0.3s ease !important;
            cursor: pointer !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23ff007f" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 15px center !important;
            background-size: 20px !important;
            padding-right: 50px !important;
            min-width: 200px !important;
        }

        .filtro-estado:focus {
            outline: none !important;
            border-color: #ff007f !important;
            box-shadow: 0 0 20px rgba(255, 0, 127, 0.4) !important;
        }

        .filtro-estado:hover {
            border-color: rgba(255, 0, 127, 0.6) !important;
            box-shadow: 0 0 15px rgba(255, 0, 127, 0.3) !important;
        }

        .filtro-estado option {
            background: #1a1a1a !important;
            color: white !important;
            padding: 10px !important;
            border: none !important;
        }

        .filtro-estado option:checked {
            background: #ff007f !important;
            color: white !important;
        }

        .filtro-estado option:hover {
            background: rgba(255, 0, 127, 0.3) !important;
        }
        
        .btn-agregar {
            background: linear-gradient(135deg, #ff007f, #bf00ff);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-agregar:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(255, 0, 127, 0.6);
        }
        
        /* Botones de exportar e importar */
        .btn-exportar,
        .btn-importar {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 20px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-exportar:hover {
            background: linear-gradient(135deg, #0056b3, #003f7f);
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 123, 255, 0.4);
        }
        
        .btn-importar {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .btn-importar:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(40, 167, 69, 0.4);
        }
        
        .animes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .anime-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(0, 255, 255, 0.2);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
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
        }
        
        .favorite-btn.favorito {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.6);
        }
        
        .favorite-btn:not(.favorito) {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .favorite-btn:hover {
            transform: scale(1.1);
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
        }
        
        .anime-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 255, 255, 0.6);
            box-shadow: 0 10px 30px rgba(0, 255, 255, 0.3);
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
            color: #00ffff;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tipo-badge {
            background: rgba(0, 255, 255, 0.2);
            color: #00ffff;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: normal;
            border: 1px solid rgba(0, 255, 255, 0.4);
        }
        
        .anime-progress {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Estilos para controles de episodios */
        .episode-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 12px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn-episode {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .btn-episode:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }
        
        .btn-episode:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }
        
        .btn-episode:disabled {
            background: rgba(108, 117, 125, 0.3);
            color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-episode-plus {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .btn-episode-plus:hover:not(:disabled) {
            background: linear-gradient(135deg, #218838, #1ea080);
        }
        
        .btn-episode-minus {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }
        
        .btn-episode-minus:hover:not(:disabled) {
            background: linear-gradient(135deg, #e0a800, #e8710a);
        }
        
        .episode-current {
            font-weight: bold;
            color: #00ffff;
            background: rgba(0, 255, 255, 0.1);
            padding: 6px 12px;
            border-radius: 15px;
            border: 1px solid rgba(0, 255, 255, 0.3);
            font-size: 0.9rem;
            min-width: 30px;
            text-align: center;
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
            background: linear-gradient(90deg, #00ffff, #ff007f);
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
        
        .no-animes {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .no-animes h3 {
            color: #00ffff;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        /* Modal/Lightbox Styles */
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
            overflow-y: auto;
            padding: 20px 0;
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            margin: 20px auto;
            padding: 0;
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 20px;
            width: 90%;
            max-width: 1200px;
            max-height: calc(100vh - 40px);
            box-shadow: 0 0 50px rgba(0, 255, 255, 0.3);
            animation: modalShow 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        /* Modal específico para agregar/editar anime con layout horizontal */
        #animeModal .modal-content,
        #editAnimeModal .modal-content {
            max-width: 1200px;
        }
        
        @keyframes modalShow {
            from { opacity: 0; transform: scale(0.7); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff007f, #bf00ff);
            padding: 20px;
            border-radius: 18px 18px 0 0;
            position: relative;
            flex-shrink: 0;
        }
        
        .modal-title {
            color: white;
            margin: 0;
            font-size: 1.5rem;
            text-align: center;
            padding-right: 40px;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 1001;
        }
        
        .close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
            max-height: calc(100vh - 180px);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        /* Sistema de grid para formularios horizontales */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .form-grid .form-group {
            margin-bottom: 0;
        }
        
        .form-row {
            display: grid;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .form-row-2 {
            grid-template-columns: 1fr 1fr;
        }
        
        .form-row-3 {
            grid-template-columns: 1fr 1fr 1fr;
        }
        
        .form-row-4 {
            grid-template-columns: 1fr 1fr 1fr 1fr;
        }
        
        .form-full-width {
            grid-column: 1 / -1;
        }
        
        /* Ajustes para campos específicos */
        .form-group.image-section {
            grid-column: 1 / -1;
        }
        
        /* Mejorar espaciado en grid */
        .form-row .form-group:last-child {
            margin-bottom: 0;
        }
        
        /* Ajustes para labels más compactos en grid */
        .form-row .form-group label {
            font-size: 0.95rem;
            margin-bottom: 6px;
        }
        
        .form-row .file-info {
            font-size: 0.8rem;
            margin-top: 4px;
        }
        
        /* Estilos para separadores visuales */
        .form-section-title {
            color: #00ffff;
            font-size: 1.1rem;
            margin: 25px 0 15px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid rgba(0, 255, 255, 0.3);
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        
        .form-group label {
            display: block;
            color: #00ffff;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 10px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00ffff;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.4);
        }
        
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
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
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: block;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px dashed rgba(0, 255, 255, 0.3);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .file-input-label:hover {
            border-color: #00ffff;
            background: rgba(0, 255, 255, 0.1);
        }
        
        .file-info {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 5px;
        }
        
        .form-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #00ffff, #0080ff);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.6);
        }
        
        .btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 30px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Estilos para el modal de exportación */
        .export-options {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .export-option-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(0, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .export-option-card:hover {
            border-color: rgba(0, 255, 255, 0.6);
            box-shadow: 0 10px 30px rgba(0, 255, 255, 0.2);
            transform: translateY(-3px);
        }
        
        .export-option-card:active {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(0, 255, 255, 0.3);
        }
        
        .export-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            animation: float 3s ease-in-out infinite;
        }
        
        .export-option-card h5 {
            color: #00ffff;
            font-size: 1.3rem;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        
        .export-option-card p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .export-option-card p strong {
            color: #ff007f;
        }
        
        .export-features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 15px;
        }
        
        .export-features span {
            background: rgba(0, 255, 255, 0.1);
            color: #00ffff;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            border: 1px solid rgba(0, 255, 255, 0.3);
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        
        /* Efectos especiales para las opciones */
        .export-option-card:nth-child(2) .export-icon {
            animation-delay: -1.5s;
        }
        
        .export-option-card:nth-child(3) .export-icon {
            animation-delay: -3s;
        }
        
        /* Estilos para modales de confirmación */
        .confirm-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            animation: fadeIn 0.3s ease;
        }
        
        .confirm-modal-content {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            margin: 15% auto;
            padding: 0;
            border: 2px solid rgba(255, 71, 87, 0.6);
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 0 50px rgba(255, 71, 87, 0.4);
            animation: scaleIn 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .confirm-modal-header {
            background: linear-gradient(135deg, #ff4757, #ff3742);
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .confirm-modal-icon {
            font-size: 3rem;
            margin-bottom: 10px;
            animation: shake 0.5s ease;
        }
        
        .confirm-modal-title {
            color: white;
            margin: 0;
            font-size: 1.4rem;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }
        
        .confirm-modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .confirm-modal-message {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .confirm-modal-submessage {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            margin-bottom: 30px;
        }
        
        .confirm-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, #ff4757, #ff3742);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(255, 71, 87, 0.6);
            background: linear-gradient(135deg, #ff3742, #ff2838);
        }
        
        .btn-cancel-confirm {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-cancel-confirm:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        
        /* Modal de confirmación para logout */
        .logout-modal .confirm-modal-content {
            border-color: rgba(255, 193, 7, 0.6);
            box-shadow: 0 0 50px rgba(255, 193, 7, 0.4);
        }
        
        .logout-modal .confirm-modal-header {
            background: linear-gradient(135deg, #ffc107, #ffb300);
        }
        
        .logout-modal .btn-confirm {
            background: linear-gradient(135deg, #ffc107, #ffb300);
        }
        
        .logout-modal .btn-confirm:hover {
            background: linear-gradient(135deg, #ffb300, #ffa000);
            box-shadow: 0 0 25px rgba(255, 193, 7, 0.6);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes scaleIn {
            from { 
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.7);
            }
            to { 
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Posicionamiento centrado */
        .confirm-modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
        }

        /* Estilos para cargar más animes */
        .load-more-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 40px 0;
            gap: 20px;
        }

        .load-more-btn {
            background: linear-gradient(135deg, #00ffff, #0080ff);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 255, 255, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            min-width: 200px;
        }

        .load-more-btn:hover {
            background: linear-gradient(135deg, #0080ff, #00ffff);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 255, 255, 0.4);
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
            color: #00ffff;
            font-size: 1rem;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-top: 2px solid #00ffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .form-row-4 {
                grid-template-columns: 1fr 1fr;
            }
            
            .form-row-3 {
                grid-template-columns: 1fr 1fr;
            }
        }
        
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
            
            .animes-header {
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
            
            .modal {
                padding: 10px 0;
            }
            
            .modal-content {
                width: 95%;
                margin: 10px auto;
                max-height: calc(100vh - 20px);
                max-width: none;
            }
            
            .modal-body {
                padding: 20px;
                max-height: calc(100vh - 140px);
            }
            
            /* En móviles, forzar layout vertical */
            .form-grid,
            .form-row,
            .form-row-2,
            .form-row-3,
            .form-row-4 {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-submit,
            .btn-cancel {
                width: 100%;
            }
            
            /* Estilos responsive para filtros */
            .filter-controls {
                width: 100% !important;
                justify-content: center !important;
                margin-bottom: 15px !important;
            }
            
            .btn-filtro-viendo {
                padding: 10px 16px !important;
                font-size: 14px !important;
            }
            
            .filtro-estado {
                min-width: 180px !important;
                padding: 10px 16px !important;
                padding-right: 45px !important;
                font-size: 14px !important;
            }
        }
        
        @media (max-height: 600px) {
            .modal-content {
                margin: 10px auto;
                max-height: calc(100vh - 20px);
            }
            
            .modal-body {
                max-height: calc(100vh - 120px);
                padding: 20px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .filter-controls {
                flex-direction: column !important;
                gap: 10px !important;
                width: 100% !important;
            }
            
            .btn-filtro-viendo,
            .filtro-estado {
                width: 100% !important;
                max-width: 300px !important;
            }
            
            .filtro-estado {
                min-width: auto !important;
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
                <li><a href="mis_animes.php" class="nav-link active">📺 Mis Animes</a></li>
                <li><a href="favoritos.php" class="nav-link">⭐ Favoritos</a></li>
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
        <div class="nav-menu mobile" id="navMenu">
            <a href="dashboard.php" class="nav-link" onclick="closeMobileMenu()">📊 Dashboard</a>
            <a href="mis_animes.php" class="nav-link active" onclick="closeMobileMenu()">📺 Mis Animes</a>
            <a href="favoritos.php" class="nav-link" onclick="closeMobileMenu()">⭐ Favoritos</a>
            <a href="recomendados.php" class="nav-link" onclick="closeMobileMenu()">🎯 Recomendados</a>
            <a href="hub.php" class="nav-link" onclick="closeMobileMenu()">🌐 Hub</a>
            <a href="perfil.php" class="nav-link" onclick="closeMobileMenu()">👤 Mi Perfil</a>
            <a href="logout.php" class="nav-link" onclick="closeMobileMenu()">🔴 Cerrar Sesión</a>
        </div>
        
        <!-- Overlay para cerrar el menú -->
        <div class="nav-overlay" id="navOverlay" onclick="closeMobileMenu()"></div>
    </nav>

    <div class="animes-container">
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
        
        <div class="animes-header">
            <h1 class="animes-title">📺 Mis Animes</h1>
            <div class="filter-section">
                <div class="filter-controls" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <button class="btn-filtro-viendo" id="filtroViendo" title="Filtrar animes que estoy viendo" 
                            style="background: rgba(0, 255, 255, 0.1); color: #00ffff; border: 2px solid rgba(0, 255, 255, 0.3); border-radius: 25px; padding: 12px 20px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                        👀 Viendo
                    </button>
                    <select class="filtro-estado" id="filtroEstado" 
                            style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(255, 0, 127, 0.3); border-radius: 25px; padding: 12px 20px; color: white; font-size: 16px; font-weight: bold; cursor: pointer; appearance: none; -webkit-appearance: none; -moz-appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill=\"%23ff007f\" height=\"24\" viewBox=\"0 0 24 24\" width=\"24\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M7 10l5 5 5-5z\"/></svg>'); background-repeat: no-repeat; background-position: right 15px center; background-size: 20px; padding-right: 50px; min-width: 200px;">
                        <option value="">🎯 Todos los estados</option>
                        <option value="Viendo">👀 Viendo</option>
                        <option value="Completado">✅ Completado</option>
                        <option value="En Pausa">⏸️ En Pausa</option>
                        <option value="Plan de Ver">⏳ Plan de Ver</option>
                        <option value="Abandonado">❌ Abandonado</option>
                    </select>
                </div>
                <input type="text" id="searchInput" class="search-input" placeholder="🔍 Buscar animes...">
                <div class="action-buttons">
                    <button class="btn-exportar" onclick="exportarLista()">
                        📤 Exportar
                    </button>
                    <button class="btn-importar" onclick="abrirModalImportar()">
                        📥 Importar
                    </button>
                    <button class="btn-agregar" onclick="abrirModal()">
                        ➕ Agregar Anime
                    </button>
                </div>
            </div>
        </div>

        <div class="animes-grid" id="animesGrid">
            <?php if (empty($animes)): ?>
                <div class="no-animes" style="grid-column: 1 / -1;">
                    <h3>🎭 ¡Aún no tienes animes agregados!</h3>
                    <p>Comienza agregando tus animes favoritos para hacer seguimiento de tu progreso.</p>
                    <button class="btn-agregar" onclick="abrirModal()" style="margin-top: 20px;">
                        ➕ Agregar tu primer anime
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($animes as $anime): ?>
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
                        <button class="favorite-btn <?= $anime['favorito'] ? 'favorito' : '' ?>" 
                                data-anime-id="<?= $anime['anime_id'] ?>" 
                                onclick="toggleFavorito(<?= $anime['anime_id'] ?>, this)"
                                title="<?= $anime['favorito'] ? 'Quitar de favoritos' : 'Agregar a favoritos' ?>">
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
                                <div class="progress-info">
                                    <span class="progress-text">
                                        <?= $anime['episodios_vistos'] ?> / <?= $anime['episodios_total'] ?: '?' ?> episodios
                                    </span>
                                    <?php if (!empty($anime['puntuacion'])): ?>
                                        <span class="puntuacion-badge">
                                            ⭐ <?= number_format($anime['puntuacion'], 1) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Botones de control de episodios -->
                                <div class="episode-controls">
                                    <button class="btn-episode btn-episode-minus" 
                                            data-anime-id="<?= $anime['anime_id'] ?>"
                                            data-action="decrementar"
                                            onclick="actualizarEpisodio(<?= $anime['anime_id'] ?>, 'decrementar')"
                                            title="⬅️ Episodio anterior (<?= max(0, $anime['episodios_vistos'] - 1) ?>)"
                                            <?= $anime['episodios_vistos'] <= 0 ? 'disabled' : '' ?>>
                                        ➖
                                    </button>
                                    
                                    <span class="episode-current" id="episodes-<?= $anime['anime_id'] ?>">
                                        <?= $anime['episodios_vistos'] ?>
                                    </span>
                                    
                                    <button class="btn-episode btn-episode-plus" 
                                            data-anime-id="<?= $anime['anime_id'] ?>"
                                            data-action="incrementar"
                                            data-animeflv-url="<?= htmlspecialchars($anime['animeflv_url_name'] ?? '') ?>"
                                            onclick="actualizarEpisodio(<?= $anime['anime_id'] ?>, 'incrementar')"
                                            title="➡️ Siguiente episodio (<?= $anime['episodios_vistos'] + 1 ?><?= !empty($anime['animeflv_url_name']) ? ' + AnimeFLV' : '' ?>)"
                                            <?= ($anime['episodios_total'] && $anime['episodios_vistos'] >= $anime['episodios_total']) ? 'disabled' : '' ?>>
                                        ➕
                                    </button>
                                </div>
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
                                <button class="btn-action btn-eliminar" data-anime-id="<?= $anime['anime_id'] ?>" data-anime-nombre="<?= htmlspecialchars($anime['anime_nombre'] ?? $anime['titulo'] ?? 'Sin nombre') ?>">
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Botón para cargar más animes -->
        <?php if ($total_animes > 12): ?>
        <div class="load-more-container" id="loadMoreContainer">
            <button class="load-more-btn" id="loadMoreBtn" onclick="cargarMasAnimes()">
                <span class="load-more-text">📄 Cargar más animes</span>
                <span class="load-more-count">(<?= min(12, $total_animes - 12) ?> de <?= $total_animes - 12 ?> restantes)</span>
            </button>
            <div class="loading-indicator" id="loadingIndicator" style="display: none;">
                <div class="spinner"></div>
                <span>Cargando animes...</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal para agregar anime -->
    <div id="animeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ Agregar Nuevo Anime</h2>
                <span class="close" onclick="cerrarModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="animeForm" action="../backend/api/procesar_anime.php" method="POST" enctype="multipart/form-data">
                    <!-- Información básica -->
                    <h4 class="form-section-title">📝 Información Básica</h4>
                    
                    <div class="form-group form-full-width">
                        <label for="nombre">📝 Nombre del Anime (Español)</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Ej: Ataque a los Titanes">
                    </div>
                    
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label for="titulo_original">🏮 Título Original (Japonés)</label>
                            <input type="text" id="titulo_original" name="titulo_original" placeholder="Ej: 進撃の巨人">
                            <div class="file-info">Opcional: Título en idioma original</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="titulo_ingles">🇺🇸 Título en Inglés</label>
                            <input type="text" id="titulo_ingles" name="titulo_ingles" placeholder="Ej: Attack on Titan">
                            <div class="file-info">Opcional: Título oficial en inglés</div>
                        </div>
                    </div>
                    
                    <!-- Detalles del anime -->
                    <h4 class="form-section-title">🎬 Detalles del Anime</h4>
                    
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label for="tipo">🎬 Tipo de Anime</label>
                            <select id="tipo" name="tipo" required>
                                <option value="TV">📺 Serie TV</option>
                                <option value="OVA">💽 OVA</option>
                                <option value="Película">🎬 Película</option>
                                <option value="Especial">⭐ Especial</option>
                                <option value="ONA">🌐 ONA (Web)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="estado_anime">📊 Estado del Anime</label>
                            <select id="estado_anime" name="estado_anime" required>
                                <option value="Finalizado">✅ Finalizado</option>
                                <option value="Emitiendo">📡 Emitiendo</option>
                                <option value="Próximamente">🔜 Próximamente</option>
                                <option value="Cancelado">❌ Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="total_episodios">📊 Total de Episodios</label>
                            <input type="number" id="total_episodios" name="total_episodios" min="1" placeholder="Ej: 25">
                            <div class="file-info">Deja vacío si no se conoce</div>
                        </div>
                    </div>
                    
                    <!-- Mi seguimiento -->
                    <h4 class="form-section-title">🎯 Mi Seguimiento</h4>
                    
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label for="capitulos_vistos">👁️ Episodios Vistos</label>
                            <input type="number" id="capitulos_vistos" name="capitulos_vistos" min="0" value="0" placeholder="Ej: 12">
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
                    
                    <!-- Seguimiento avanzado -->
                    <h4 class="form-section-title">🎬 Seguimiento Avanzado</h4>
                    
                    <div class="form-group form-full-width">
                        <label for="animeflv_url_name">🌐 Nombre URL AnimeFLV para seguimiento de capítulos</label>
                        <input type="text" id="animeflv_url_name" name="animeflv_url_name" 
                               placeholder="ej: jujutsu-kaisen-tv (sin espacios, solo guiones)" 
                               pattern="[a-z0-9\-]+" 
                               title="Solo letras minúsculas, números y guiones">
                        <div class="file-info">
                            📱 <strong>Opcional pero recomendado:</strong> Permite acceso directo a episodios en AnimeFLV.<br>
                            💡 <strong>Cómo encontrarlo:</strong> Ve a AnimeFLV, busca tu anime y copia la parte final de la URL.<br>
                            🔗 <strong>Ejemplo:</strong> Si la URL es "animeflv.net/anime/jujutsu-kaisen-tv" → escribe "jujutsu-kaisen-tv"
                        </div>
                    </div>
                    
                    <!-- Imagen -->
                    <h4 class="form-section-title">🖼️ Imagen del Anime</h4>
                    
                    <div class="form-group form-full-width image-section">
                        <label for="imagen_url" style="display: none;">🖼️ Imagen del Anime</label>
                        
                        <div class="form-row form-row-2">
                            <!-- Opción de URL (recomendada) -->
                            <div>
                                <label for="imagen_url" style="font-size: 0.9rem; color: #28a745;">🌐 URL de imagen (Recomendado)</label>
                                <input type="url" id="imagen_url" name="imagen_url" class="form-control" 
                                       placeholder="https://example.com/imagen.jpg" 
                                       style="margin-top: 5px;">
                                <div class="file-info">Más rápido y ahorra espacio</div>
                            </div>
                            
                            <!-- Opción de subir archivo -->
                            <div>
                                <label for="imagen" style="font-size: 0.9rem; color: #666;">📎 Subir desde dispositivo</label>
                                <div class="file-input-wrapper" style="margin-top: 5px;">
                                    <input type="file" id="imagen" name="imagen" class="file-input" accept="image/jpeg,image/jpg,image/png,image/x-icon">
                                    <label for="imagen" class="file-input-label">
                                        📎 JPG, PNG, ICO (máx. 1MB)
                                    </label>
                                </div>
                                <div class="file-info">Imagen desde tu computadora</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn-submit">✅ Agregar Anime</button>
                        <button type="button" class="btn-cancel" onclick="cerrarModal()">❌ Cancelar</button>
                    </div>
                </form>
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
                    
                    <!-- Seguimiento avanzado -->
                    <h4 class="form-section-title">🎬 Seguimiento Avanzado</h4>
                    
                    <div class="form-group form-full-width">
                        <label for="edit_animeflv_url_name">🌐 Nombre URL AnimeFLV para seguimiento de capítulos</label>
                        <input type="text" id="edit_animeflv_url_name" name="animeflv_url_name" 
                               placeholder="ej: jujutsu-kaisen-tv (sin espacios, solo guiones)" 
                               pattern="[a-z0-9\-]+" 
                               title="Solo letras minúsculas, números y guiones">
                        <div class="file-info">
                            📱 <strong>Configurar para usar botones +/- de episodios:</strong> Permite acceso directo a AnimeFLV.<br>
                            💡 <strong>Cómo encontrarlo:</strong> Ve a AnimeFLV, busca tu anime y copia la parte final de la URL.<br>
                            🔗 <strong>Ejemplo:</strong> Si la URL es "animeflv.net/anime/jujutsu-kaisen-tv" → escribe "jujutsu-kaisen-tv"
                        </div>
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

    <!-- Modal para importar lista -->
    <div id="importarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">📥 Importar Lista de Animes</h2>
                <span class="close" onclick="cerrarModalImportar()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="importarForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="archivo_importar">📁 Seleccionar archivo de lista</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="archivo_importar" name="archivo_importar" class="file-input" accept=".txt,.json">
                            <label for="archivo_importar" class="file-input-label">
                                📎 Seleccionar archivo (.txt o .json)
                            </label>
                        </div>
                        <div class="file-info">💡 Selecciona un archivo exportado desde AnimeGon</div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="reemplazar_duplicados" name="reemplazar_duplicados" style="margin-right: 8px;">
                            🔄 Reemplazar animes duplicados (si ya existen en tu lista)
                        </label>
                        <div class="file-info">Si está desmarcado, los duplicados se omitirán</div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn-submit">📥 Importar Lista</button>
                        <button type="button" class="btn-cancel" onclick="cerrarModalImportar()">❌ Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para exportar lista -->
    <div id="exportarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">📤 Exportar Lista de Animes</h2>
                <span class="close" onclick="cerrarModalExportar()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="export-options">
                    <h4 style="color: #00ffff; margin-bottom: 25px; text-align: center;">Selecciona el formato de exportación:</h4>
                    
                    <div class="export-option-card" onclick="exportarEnFormato('json')">
                        <div class="export-icon">📄</div>
                        <h5>Formato JSON</h5>
                        <p><strong>Recomendado para importar</strong></p>
                        <p>Conserva toda la información y se puede importar de vuelta a AnimeGon</p>
                        <div class="export-features">
                            <span>✅ Datos completos</span>
                            <span>✅ Reimportable</span>
                            <span>✅ Estructura organizada</span>
                        </div>
                    </div>
                    
                    <div class="export-option-card" onclick="exportarEnFormato('txt')">
                        <div class="export-icon">📝</div>
                        <h5>Formato TXT</h5>
                        <p><strong>Solo para lectura humana</strong></p>
                        <p>Texto legible y fácil de compartir, pero no se puede reimportar</p>
                        <div class="export-features">
                            <span>👁️ Fácil de leer</span>
                            <span>📄 Para compartir</span>
                            <span>🚫 No reimportable</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-buttons" style="margin-top: 30px;">
                    <button type="button" class="btn-cancel" onclick="cerrarModalExportar()">❌ Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar anime -->
    <div id="confirmDeleteModal" class="confirm-modal">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon">🗑️</div>
                <h3 class="confirm-modal-title">Eliminar Anime</h3>
            </div>
            <div class="confirm-modal-body">
                <div class="confirm-modal-message" id="deleteMessage">
                    ¿Estás seguro de que quieres eliminar este anime de tu lista?
                </div>
                <div class="confirm-modal-submessage">
                    Esta acción no se puede deshacer y el anime se eliminará permanentemente de tu lista.
                </div>
                <div class="confirm-modal-buttons">
                    <button class="btn-confirm" id="confirmDeleteBtn">
                        🗑️ Sí, eliminar
                    </button>
                    <button class="btn-cancel-confirm" id="cancelDeleteBtn">
                        ❌ Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para cerrar sesión -->
    <div id="confirmLogoutModal" class="confirm-modal logout-modal">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon">🔴</div>
                <h3 class="confirm-modal-title">Cerrar Sesión</h3>
            </div>
            <div class="confirm-modal-body">
                <div class="confirm-modal-message">
                    ¿Estás seguro de que quieres cerrar sesión?
                </div>
                <div class="confirm-modal-submessage">
                    Tendrás que iniciar sesión nuevamente para acceder a tu cuenta.
                </div>
                <div class="confirm-modal-buttons">
                    <button class="btn-confirm" id="confirmLogoutBtn">
                        🔴 Sí, cerrar sesión
                    </button>
                    <button class="btn-cancel-confirm" id="cancelLogoutBtn">
                        ❌ Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../frontend/assets/js/animes.js"></script>
    
    <script>
        // Función para actualizar episodios
        async function actualizarEpisodio(animeId, accion) {
            const button = document.querySelector(`[data-anime-id="${animeId}"][data-action="${accion}"]`);
            const episodeSpan = document.getElementById(`episodes-${animeId}`);
            const currentEpisodes = parseInt(episodeSpan.textContent);
            
            // Deshabilitar botón temporalmente
            button.disabled = true;
            button.innerHTML = accion === 'incrementar' ? '⏳' : '⏳';
            
            try {
                const response = await fetch('../backend/api/actualizar_episodios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        anime_id: animeId,
                        accion: accion
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Actualizar número de episodios
                    episodeSpan.textContent = data.episodios_nuevos;
                    
                    // Actualizar texto de progreso
                    const progressText = document.querySelector(`[data-anime-id="${animeId}"]`).closest('.anime-card').querySelector('.progress-text');
                    if (progressText) {
                        const totalEpisodes = progressText.textContent.split(' / ')[1];
                        progressText.innerHTML = `${data.episodios_nuevos} / ${totalEpisodes}`;
                    }
                    
                    // Actualizar tooltips de los botones
                    const minusBtn = document.querySelector(`[data-anime-id="${animeId}"][data-action="decrementar"]`);
                    const plusBtn = document.querySelector(`[data-anime-id="${animeId}"][data-action="incrementar"]`);
                    
                    if (minusBtn) {
                        minusBtn.title = `⬅️ Episodio anterior (${Math.max(0, data.episodios_nuevos - 1)})`;
                        minusBtn.disabled = data.episodios_nuevos <= 0;
                    }
                    
                    if (plusBtn) {
                        const animeflvUrl = plusBtn.getAttribute('data-animeflv-url');
                        plusBtn.title = `➡️ Siguiente episodio (${data.episodios_nuevos + 1}${animeflvUrl ? ' + AnimeFLV' : ''})`;
                    }
                    
                    // Actualizar barra de progreso si existe
                    const progressBar = button.closest('.anime-card').querySelector('.progress-fill');
                    if (progressBar) {
                        const totalText = progressText.textContent.split(' / ')[1];
                        const total = totalText === '?' ? null : parseInt(totalText);
                        if (total) {
                            const percentage = Math.min((data.episodios_nuevos / total) * 100, 100);
                            progressBar.style.width = percentage + '%';
                        }
                    }
                    
                    // Si es incremento y tiene URL de AnimeFLV, abrir enlace
                    if (accion === 'incrementar' && data.animeflv_url) {
                        // Mostrar notificación
                        showNotification(`🎬 Abriendo episodio ${data.episodios_nuevos} en AnimeFLV`, 'success');
                        
                        // Abrir en nueva pestaña después de un pequeño delay
                        setTimeout(() => {
                            window.open(data.animeflv_url, '_blank');
                        }, 500);
                    } else {
                        // Mostrar notificación normal
                        showNotification(data.message, 'success');
                    }
                    
                    // Mostrar warning si no tiene URL configurada
                    if (data.warning) {
                        setTimeout(() => {
                            showNotification(data.warning, 'warning');
                        }, 2000);
                    }
                    
                } else {
                    showNotification(data.message || 'Error al actualizar episodios', 'error');
                }
                
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error de conexión al actualizar episodios', 'error');
            } finally {
                // Restaurar botón
                button.disabled = false;
                button.innerHTML = accion === 'incrementar' ? '➕' : '➖';
                
                // Re-evaluar estado de botones
                const episodeSpan = document.getElementById(`episodes-${animeId}`);
                const currentEps = parseInt(episodeSpan.textContent);
                const minusBtn = document.querySelector(`[data-anime-id="${animeId}"][data-action="decrementar"]`);
                const plusBtn = document.querySelector(`[data-anime-id="${animeId}"][data-action="incrementar"]`);
                
                if (minusBtn) {
                    minusBtn.disabled = currentEps <= 0;
                }
                
                // Para el botón plus, necesitaríamos conocer el total de episodios
                // Se podría implementar con data attributes
            }
        }

        // Variables globales para paginación
        let paginaActual = 1;
        let totalPaginas = <?= ceil($total_animes / 12) ?>;
        let cargandoMas = false;
        
        // Variables globales para filtros
        let filtroViendoActivo = false;
        let estadoSeleccionado = '';

        // Función para crear una tarjeta de anime
        function crearTarjetaAnime(anime) {
            const progreso = anime.episodios_total > 0 ? (anime.episodios_vistos / anime.episodios_total) * 100 : 0;
            
            let estadoClass = 'estado-pendiente';
            let estadoText = 'Plan de Ver';
            
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
            }

            let rutaImagen = anime.imagen_portada;
            if (rutaImagen && rutaImagen.startsWith('img/')) {
                rutaImagen = '../' + rutaImagen;
            }

            let estadoAnimeIcon = '';
            let estadoAnimeClass = '';
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

            return `
                <div class="anime-card" data-anime-name="${(anime.anime_nombre || anime.titulo || 'Sin nombre').toLowerCase()}">
                    <button class="favorite-btn ${anime.favorito ? 'favorito' : ''}" 
                            data-anime-id="${anime.anime_id}" 
                            onclick="toggleFavorito(${anime.anime_id}, this)"
                            title="${anime.favorito ? 'Quitar de favoritos' : 'Agregar a favoritos'}">
                        ⭐
                    </button>
                    
                    ${rutaImagen ? `
                        <img src="${rutaImagen}" 
                             alt="${anime.anime_nombre || anime.nombre || 'Sin nombre'}" 
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
                            ${anime.anime_nombre || anime.titulo || 'Sin nombre'}
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
                            <div class="progress-info">
                                <span class="progress-text">
                                    ${anime.episodios_vistos} / ${anime.episodios_total || '?'} episodios
                                </span>
                                ${anime.puntuacion ? `
                                    <span class="puntuacion-badge">
                                        ⭐ ${parseFloat(anime.puntuacion).toFixed(1)}
                                    </span>
                                ` : ''}
                            </div>
                            
                            <div class="episode-controls">
                                <button class="btn-episode btn-episode-minus" 
                                        data-anime-id="${anime.anime_id}"
                                        data-action="decrementar"
                                        onclick="actualizarEpisodio(${anime.anime_id}, 'decrementar')"
                                        title="⬅️ Episodio anterior (${Math.max(0, anime.episodios_vistos - 1)})"
                                        ${anime.episodios_vistos <= 0 ? 'disabled' : ''}>
                                    ➖
                                </button>
                                
                                <span class="episode-current" id="episodes-${anime.anime_id}">
                                    ${anime.episodios_vistos}
                                </span>
                                
                                <button class="btn-episode btn-episode-plus" 
                                        data-anime-id="${anime.anime_id}"
                                        data-action="incrementar"
                                        data-animeflv-url="${anime.animeflv_url_name || ''}"
                                        onclick="actualizarEpisodio(${anime.anime_id}, 'incrementar')"
                                        title="➡️ Siguiente episodio (${anime.episodios_vistos + 1}${anime.animeflv_url_name ? ' + AnimeFLV' : ''})"
                                        ${(anime.episodios_total && anime.episodios_vistos >= anime.episodios_total) ? 'disabled' : ''}>
                                    ➕
                                </button>
                            </div>
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
                            <button class="btn-action btn-eliminar" data-anime-id="${anime.anime_id}" data-anime-nombre="${anime.anime_nombre || anime.titulo || 'Sin nombre'}">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // Función para cargar más animes
        async function cargarMasAnimes() {
            if (cargandoMas || paginaActual >= totalPaginas) {
                return;
            }

            cargandoMas = true;
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const loadingIndicator = document.getElementById('loadingIndicator');

            // Mostrar indicador de carga
            loadMoreBtn.style.display = 'none';
            loadingIndicator.style.display = 'flex';

            try {
                const nextPage = paginaActual + 1;
                
                // Construir URL con parámetros actuales de filtro
                const params = new URLSearchParams();
                params.set('pagina', nextPage.toString());
                params.set('limite', '12');
                
                const searchTerm = document.getElementById('searchInput').value.trim();
                const estadoSeleccionado = document.getElementById('filtroEstado') ? document.getElementById('filtroEstado').value : '';
                
                if (searchTerm) {
                    params.set('busqueda', searchTerm);
                }
                
                if (estadoSeleccionado) {
                    params.set('estado', estadoSeleccionado);
                }
                
                if (filtroViendoActivo) {
                    params.set('solo_viendo', 'true');
                }
                
                const response = await fetch(`../backend/api/obtener_animes_paginados.php?${params.toString()}`);
                
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }

                const data = await response.json();

                if (data.success && data.animes.length > 0) {
                    const grid = document.getElementById('animesGrid');
                    
                    // Agregar nuevos animes al grid
                    data.animes.forEach(anime => {
                        const animeHTML = crearTarjetaAnime(anime);
                        grid.insertAdjacentHTML('beforeend', animeHTML);
                    });

                    paginaActual = nextPage;

                    // Actualizar botón o ocultarlo si no hay más páginas
                    if (data.paginacion.hay_mas) {
                        const restantes = data.paginacion.total_registros - (paginaActual * 12);
                        const siguientesCarga = Math.min(12, restantes);
                        
                        loadMoreBtn.querySelector('.load-more-count').textContent = 
                            `(${siguientesCarga} de ${restantes} restantes)`;
                        loadMoreBtn.style.display = 'flex';
                    } else {
                        // Ocultar completamente el contenedor si no hay más animes
                        document.getElementById('loadMoreContainer').style.display = 'none';
                    }

                    // Reinicializar event listeners para los nuevos elementos
                    if (window.animeManager && window.animeManager.initializeActionButtons) {
                        window.animeManager.initializeActionButtons();
                    }

                    showNotification(`Se cargaron ${data.animes.length} animes más`, 'success');
                } else {
                    showNotification('No se encontraron más animes', 'info');
                    document.getElementById('loadMoreContainer').style.display = 'none';
                }

            } catch (error) {
                console.error('Error al cargar más animes:', error);
                showNotification('Error al cargar más animes', 'error');
                loadMoreBtn.style.display = 'flex';
            } finally {
                loadingIndicator.style.display = 'none';
                cargandoMas = false;
            }
        }
        
        // Funciones del menú hamburguesa
        function toggleMobileMenu() {
            const navMenu = document.getElementById('navMenu');
            const navOverlay = document.getElementById('navOverlay');
            const hamburger = document.querySelector('.hamburger');
            
            if (navMenu && navOverlay && hamburger) {
                const isOpen = navMenu.classList.contains('active');
                
                if (isOpen) {
                    closeMobileMenu();
                } else {
                    navMenu.classList.add('active');
                    navOverlay.classList.add('active');
                    hamburger.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
        }
        
        function closeMobileMenu() {
            const navMenu = document.getElementById('navMenu');
            const navOverlay = document.getElementById('navOverlay');
            const hamburger = document.querySelector('.hamburger');
            
            if (navMenu && navOverlay && hamburger) {
                navMenu.classList.remove('active');
                navOverlay.classList.remove('active');
                hamburger.classList.remove('active');
                document.body.style.overflow = '';
            }
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
                word-wrap: break-word;
                animation: slideIn 0.3s ease;
            `;
            
            // Colores según tipo
            switch(type) {
                case 'success':
                    notification.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                    break;
                case 'error':
                    notification.style.background = 'linear-gradient(135deg, #dc3545, #c82333)';
                    break;
                case 'warning':
                    notification.style.background = 'linear-gradient(135deg, #ffc107, #fd7e14)';
                    break;
                default:
                    notification.style.background = 'linear-gradient(135deg, #007bff, #0056b3)';
            }
            
            // Agregar al DOM
            document.body.appendChild(notification);
            
            // Eliminar después de 4 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }
        
        // Función mejorada para aplicar filtros con paginación
        async function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            const estadoSeleccionado = document.getElementById('filtroEstado') ? document.getElementById('filtroEstado').value : '';
            
            // Resetear paginación
            paginaActual = 1;
            cargandoMas = false;
            
            try {
                // Construir URL con parámetros de filtro
                const params = new URLSearchParams();
                params.set('pagina', '1');
                params.set('limite', '12');
                
                if (searchTerm) {
                    params.set('busqueda', searchTerm);
                }
                
                if (estadoSeleccionado) {
                    params.set('estado', estadoSeleccionado);
                }
                
                if (filtroViendoActivo) {
                    params.set('solo_viendo', 'true');
                }
                
                const response = await fetch(`../backend/api/obtener_animes_paginados.php?${params.toString()}`);
                
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
                            const animeHTML = crearTarjetaAnime(anime);
                            grid.insertAdjacentHTML('beforeend', animeHTML);
                        });
                        
                        // Actualizar paginación
                        totalPaginas = data.paginacion.total_paginas;
                        
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
                    }
                    
                    actualizarContadorVisible();
                } else {
                    showNotification('Error al aplicar filtros', 'error');
                }
                
            } catch (error) {
                console.error('Error al aplicar filtros:', error);
                showNotification('Error de conexión al filtrar animes', 'error');
                
                // Fallback: aplicar filtros localmente como antes
                aplicarFiltrosLocalmente();
            }
        }

        // Función de respaldo para filtros locales (método anterior)
        function aplicarFiltrosLocalmente() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const animeCards = document.querySelectorAll('.anime-card');
            
            animeCards.forEach(card => {
                let mostrar = true;
                
                // Filtro por texto de búsqueda
                const animeName = card.getAttribute('data-anime-name');
                if (searchTerm && !animeName.includes(searchTerm)) {
                    mostrar = false;
                }
                
                // Filtro "Viendo" activo
                if (filtroViendoActivo && mostrar) {
                    const estadoBadge = card.querySelector('.estado-badge');
                    if (!estadoBadge || !estadoBadge.textContent.includes('Viendo')) {
                        mostrar = false;
                    }
                }
                
                // Filtro por estado seleccionado  
                const estadoSeleccionado = document.getElementById('filtroEstado') ? document.getElementById('filtroEstado').value : '';
                if (estadoSeleccionado && mostrar) {
                    const estadoBadge = card.querySelector('.estado-badge');
                    if (!estadoBadge || !estadoBadge.textContent.includes(estadoSeleccionado)) {
                        mostrar = false;
                    }
                }
                
                card.style.display = mostrar ? 'block' : 'none';
            });
            
            // Actualizar contador si existe
            actualizarContadorVisible();
        }
        
        // Función para actualizar contador de animes visibles
        function actualizarContadorVisible() {
            const animesVisibles = document.querySelectorAll('.anime-card[style*="block"], .anime-card:not([style*="none"])').length;
            const totalAnimes = document.querySelectorAll('.anime-card').length;
            
            // Mostrar/ocultar mensaje de "no hay resultados"
            const grid = document.getElementById('animesGrid');
            let noResultsMsg = document.getElementById('noResultsMessage');
            
            if (animesVisibles === 0 && totalAnimes > 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMessage';
                    noResultsMsg.className = 'no-animes';
                    noResultsMsg.style.gridColumn = '1 / -1';
                    noResultsMsg.innerHTML = `
                        <h3>🔍 No se encontraron animes</h3>
                        <p>No hay animes que coincidan con los filtros seleccionados.</p>
                        <button class="btn-agregar" onclick="limpiarFiltros()">🔄 Limpiar filtros</button>
                    `;
                    grid.appendChild(noResultsMsg);
                }
                noResultsMsg.style.display = 'block';
            } else if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }
        }
        
        // Función para limpiar todos los filtros
        function limpiarFiltros() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filtroEstado').value = '';
            filtroViendoActivo = false;
            estadoSeleccionado = '';
            
            const btnViendo = document.getElementById('filtroViendo');
            btnViendo.classList.remove('active');
            btnViendo.textContent = '👀 Viendo';
            
            aplicarFiltros();
        }
        
        // Event listeners para filtros
        document.addEventListener('DOMContentLoaded', function() {
            // Debug: Verificar que los elementos existen
            console.log('Elementos de filtro encontrados:');
            console.log('Botón Viendo:', document.getElementById('filtroViendo'));
            console.log('Select Estado:', document.getElementById('filtroEstado'));
            console.log('Input Búsqueda:', document.getElementById('searchInput'));
            // Filtro de búsqueda con debounce para evitar demasiadas peticiones
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(aplicarFiltros, 500); // 500ms de debounce
                });
            }
            
            // Botón filtro "Viendo"
            const btnFiltroViendo = document.getElementById('filtroViendo');
            if (btnFiltroViendo) {
                btnFiltroViendo.addEventListener('click', function() {
                    filtroViendoActivo = !filtroViendoActivo;
                    
                    if (filtroViendoActivo) {
                        this.classList.add('active');
                        this.textContent = '👀 Viendo ✓';
                        // Limpiar filtro de estado si está activo
                        if (estadoSeleccionado) {
                            document.getElementById('filtroEstado').value = '';
                            estadoSeleccionado = '';
                        }
                    } else {
                        this.classList.remove('active');
                        this.textContent = '👀 Viendo';
                    }
                    
                    aplicarFiltros();
                });
            }
            
            // Select filtro por estado
            const selectFiltroEstado = document.getElementById('filtroEstado');
            if (selectFiltroEstado) {
                selectFiltroEstado.addEventListener('change', function() {
                    estadoSeleccionado = this.value;
                    
                    // Si se selecciona un estado, desactivar filtro "Viendo"
                    if (estadoSeleccionado && filtroViendoActivo) {
                        filtroViendoActivo = false;
                        const btnViendo = document.getElementById('filtroViendo');
                        btnViendo.classList.remove('active');
                        btnViendo.textContent = '👀 Viendo';
                    }
                    
                    aplicarFiltros();
                });
            }
        });

        // Agregar estilos de animación
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
</html>