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

// Obtener datos completos del usuario
function obtenerDatosCompletos($usuario_id) {
    try {
        $conexion = obtenerConexion();
        $query = "SELECT id, nombre, username, email, fecha_registro FROM usuarios WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

// Obtener estadísticas del usuario
function obtenerEstadisticasUsuario($usuario_id) {
    try {
        $conexion = obtenerConexion();
        
        // Estadísticas básicas
        $query = "SELECT 
                    COUNT(*) as total_animes,
                    COUNT(CASE WHEN estado = 'Completado' THEN 1 END) as completados,
                    COUNT(CASE WHEN estado = 'Viendo' THEN 1 END) as viendo,
                    SUM(episodios_vistos) as episodios_totales
                  FROM lista_usuario WHERE usuario_id = ?";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Contar favoritos
        $query_favs = "SELECT COUNT(*) as favoritos FROM favoritos WHERE usuario_id = ?";
        $stmt_favs = $conexion->prepare($query_favs);
        $stmt_favs->execute([$usuario_id]);
        $favs = $stmt_favs->fetch(PDO::FETCH_ASSOC);
        
        return array_merge($stats, $favs);
    } catch (Exception $e) {
        return [
            'total_animes' => 0,
            'completados' => 0,
            'viendo' => 0,
            'episodios_totales' => 0,
            'favoritos' => 0
        ];
    }
}

$usuario = obtenerDatosCompletos($usuario_id);
$estadisticas = obtenerEstadisticasUsuario($usuario_id);

if (!$usuario) {
    header("Location: logout.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Mi Perfil</title>
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
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0a0a0a, #1a1a2e, #16213e);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
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
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-indicator:hover {
            background: rgba(0, 255, 0, 0.2);
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.4);
        }
        
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 5px rgba(0, 255, 0, 0.3); }
            50% { box-shadow: 0 0 15px rgba(0, 255, 0, 0.6); }
            100% { box-shadow: 0 0 5px rgba(0, 255, 0, 0.3); }
        }
        
        /* Navbar Styles */
        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 2px solid rgba(138, 43, 226, 0.3);
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
            color: #8a2be2;
            text-shadow: 0 0 20px rgba(138, 43, 226, 0.6);
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
            color: #8a2be2;
            border-color: rgba(138, 43, 226, 0.5);
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.3);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #8a2be2, #9932cc);
            color: white;
            border-color: transparent;
        }
        
        /* Menú hamburguesa */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            background: rgba(138, 43, 226, 0.2);
            border: 2px solid rgba(138, 43, 226, 0.6);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1006;
        }
        
        .hamburger:hover {
            background: rgba(138, 43, 226, 0.3);
            border-color: rgba(138, 43, 226, 0.8);
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
        }
        
        .hamburger span {
            width: 25px;
            height: 3px;
            background: #8a2be2;
            margin: 3px 0;
            transition: all 0.3s ease;
            border-radius: 2px;
            box-shadow: 0 0 8px rgba(138, 43, 226, 0.7);
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
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.95), rgba(153, 50, 204, 0.95));
            backdrop-filter: blur(15px);
            border-left: 3px solid rgba(138, 43, 226, 0.6);
            padding: 80px 20px 20px;
            flex-direction: column;
            gap: 0;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1005;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.4);
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
            background: rgba(138, 43, 226, 0.1);
            backdrop-filter: blur(10px);
            display: block;
            width: 100%;
            box-sizing: border-box;
            font-weight: 600;
        }
        
        .nav-menu.mobile .nav-link:hover {
            background: rgba(138, 43, 226, 0.25);
            border-color: rgba(138, 43, 226, 0.5);
            transform: translateX(-5px);
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.4);
        }
        
        .nav-menu.mobile .nav-link.active {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.3), rgba(153, 50, 204, 0.3));
            border-color: #9932cc;
            color: #dda0dd;
            box-shadow: 0 0 25px rgba(153, 50, 204, 0.5);
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
        
        /* Estilos específicos para perfil */
        .perfil-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .perfil-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .perfil-title {
            color: #8a2be2;
            font-size: 2.5rem;
            text-shadow: 0 0 20px rgba(138, 43, 226, 0.6);
            margin-bottom: 10px;
        }
        
        .perfil-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }
        
        /* Grid de contenido */
        .perfil-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        /* Panel de información del usuario */
        .user-info-panel {
            background: rgba(138, 43, 226, 0.1);
            border: 2px solid rgba(138, 43, 226, 0.3);
            border-radius: 15px;
            padding: 25px;
        }
        
        .user-info-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #8a2be2, #9932cc);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 15px;
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
        }
        
        .user-name {
            color: #8a2be2;
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .user-email {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .join-date {
            color: rgba(138, 43, 226, 0.8);
            font-size: 0.8rem;
        }
        
        /* Estadísticas */
        .user-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 25px;
        }
        
        .stat-item {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            border: 1px solid rgba(138, 43, 226, 0.2);
        }
        
        .stat-number {
            color: #8a2be2;
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        /* Panel de configuración */
        .config-panel {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(138, 43, 226, 0.3);
            border-radius: 15px;
            padding: 0;
            overflow: hidden;
        }
        
        .config-header {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.2), rgba(153, 50, 204, 0.2));
            padding: 20px;
            border-bottom: 2px solid rgba(138, 43, 226, 0.3);
        }
        
        .config-title {
            color: #8a2be2;
            font-size: 1.3rem;
            margin: 0;
        }
        
        .config-body {
            padding: 25px;
        }
        
        /* Secciones de configuración */
        .config-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(138, 43, 226, 0.2);
        }
        
        .config-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            color: #8a2be2;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #dda0dd;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(138, 43, 226, 0.3);
            border-radius: 8px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.4);
        }
        
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Botones */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8a2be2, #9932cc);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.6);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff4757, #ff3742);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255, 71, 87, 0.6);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: 2px solid transparent;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }
        
        /* Estilos para la sección de username */
        .username-section {
            margin-top: 15px;
        }
        
        .username-current {
            background: rgba(138, 43, 226, 0.1);
            border: 1px solid rgba(138, 43, 226, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .username-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        
        .username-value {
            color: #da70d6;
            font-weight: bold;
            font-size: 1.1rem;
            text-shadow: 0 0 10px rgba(218, 112, 214, 0.5);
        }
        
        .username-edit-form {
            background: rgba(138, 43, 226, 0.05);
            border: 1px solid rgba(138, 43, 226, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-help {
            display: block;
            margin-top: 5px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
            font-style: italic;
        }
        
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-secondary.active {
            background: linear-gradient(135deg, #8a2be2, #da70d6);
            color: white;
            border-color: transparent;
        }
        
        .btn-secondary.active:hover {
            background: linear-gradient(135deg, #9932cc, #e666e6);
        }
        
        .btn-secondary:hover {
            background: rgba(138, 43, 226, 0.3);
            transform: translateY(-2px);
        }
        
        /* Zona de peligro */
        .danger-zone {
            background: rgba(255, 71, 87, 0.1);
            border: 2px solid rgba(255, 71, 87, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .danger-title {
            color: #ff4757;
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .danger-description {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        /* Modales */
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
            margin: 8% auto;
            padding: 0;
            border: 2px solid rgba(138, 43, 226, 0.4);
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 0 40px rgba(138, 43, 226, 0.3);
            animation: modalSlideIn 0.4s ease;
            overflow: hidden;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-100px) scale(0.8); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #8a2be2, #9932cc);
            padding: 25px;
            text-align: center;
        }
        
        .modal-icon {
            font-size: 3rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .modal-title {
            color: white;
            margin: 0;
            font-size: 1.4rem;
            font-weight: bold;
        }
        
        .modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .modal-message {
            color: #ffffff;
            font-size: 1.1rem;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .modal-submessage {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, #ff4757, #ff3742);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(255, 71, 87, 0.6);
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #666, #444);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(102, 102, 102, 0.6);
        }
        
        /* Mensajes de estado */
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        
        .message.success {
            background: rgba(0, 255, 136, 0.2);
            border: 2px solid #00ff88;
            color: #00ff88;
        }
        
        .message.error {
            background: rgba(255, 0, 127, 0.2);
            border: 2px solid #ff007f;
            color: #ff007f;
        }
        
        .message.warning {
            background: rgba(255, 193, 7, 0.2);
            border: 2px solid #ffc107;
            color: #ffc107;
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
            
            .perfil-grid {
                grid-template-columns: 1fr;
                gap: 20px;
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
            
            .user-stats {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .nav-menu.mobile {
                width: 90vw;
            }
            
            .nav-logo h2 {
                font-size: 1.4rem;
            }
            
            .perfil-title {
                font-size: 2rem;
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
                <li><a href="hub.php" class="nav-link">🌐 Hub</a></li>
                <li><a href="perfil.php" class="nav-link active">👤 Mi Perfil</a></li>
                <li><a href="logout.php" class="nav-link">🔴 Cerrar Sesión</a></li>
            </ul>
            <span class="user-indicator" onclick="window.location.href='perfil.php'">🟢 <?= htmlspecialchars($usuario['nombre']) ?></span>
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
            <li><a href="hub.php" class="nav-link" onclick="closeMobileMenu()">🌐 Hub</a></li>
            <li><a href="perfil.php" class="nav-link active" onclick="closeMobileMenu()">👤 Mi Perfil</a></li>
            <li><a href="logout.php" class="nav-link" onclick="closeMobileMenu()">🔴 Cerrar Sesión</a></li>
        </ul>
        
        <!-- Overlay para cerrar el menú -->
        <div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>
    </nav>

    <div class="perfil-container">
        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="message success">
                ✅ <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
            </div>
            <?php unset($_SESSION['mensaje_exito']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="message error">
                ❌ <?= htmlspecialchars($_SESSION['mensaje_error']) ?>
            </div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>
        
        <div class="perfil-header">
            <h1 class="perfil-title">👤 Mi Perfil</h1>
            <p class="perfil-subtitle">Gestiona tu cuenta y configuración</p>
        </div>

        <div class="perfil-grid">
            <!-- Panel de información del usuario -->
            <div class="user-info-panel">
                <div class="user-info-header">
                    <div class="user-avatar">👤</div>
                    <div class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                    <div class="user-email"><?= htmlspecialchars($usuario['email']) ?></div>
                    <div class="join-date">
                        Miembro desde: <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?>
                    </div>
                </div>
                
                <div class="user-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= $estadisticas['total_animes'] ?></span>
                        <span class="stat-label">Animes en Lista</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $estadisticas['completados'] ?></span>
                        <span class="stat-label">Completados</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $estadisticas['viendo'] ?></span>
                        <span class="stat-label">Viendo</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $estadisticas['favoritos'] ?></span>
                        <span class="stat-label">Favoritos</span>
                    </div>
                    <div class="stat-item" style="grid-column: 1 / -1;">
                        <span class="stat-number"><?= $estadisticas['episodios_totales'] ?></span>
                        <span class="stat-label">Episodios Vistos</span>
                    </div>
                </div>
            </div>

            <!-- Panel de configuración -->
            <div class="config-panel">
                <div class="config-header">
                    <h2 class="config-title">⚙️ Configuración de Cuenta</h2>
                </div>
                <div class="config-body">
                    <!-- Cambiar nombre -->
                    <div class="config-section">
                        <h3 class="section-title">📝 Cambiar Nombre</h3>
                        <form id="changeNameForm">
                            <div class="form-group">
                                <label for="nuevo_nombre">Nuevo nombre</label>
                                <input type="text" 
                                       id="nuevo_nombre" 
                                       name="nuevo_nombre" 
                                       value="<?= htmlspecialchars($usuario['nombre']) ?>" 
                                       required 
                                       minlength="3"
                                       maxlength="50"
                                       placeholder="Ingresa tu nuevo nombre">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                💾 Guardar Nombre
                            </button>
                        </form>
                    </div>

                    <!-- Cambiar nombre de usuario (username) -->
                    <div class="config-section">
                        <h3 class="section-title">👤 Cambiar Nombre de Usuario</h3>
                        <div class="username-section">
                            <div class="username-current">
                                <span class="username-label">Nombre de usuario actual:</span>
                                <span class="username-value">@<?= htmlspecialchars($usuario['username']) ?></span>
                            </div>
                            
                            <button type="button" class="btn btn-secondary" id="revealUsernameBtn" onclick="toggleUsernameEdit()">
                                👁️ Mostrar editor de usuario
                            </button>
                            
                            <div class="username-edit-form" id="usernameEditForm" style="display: none;">
                                <form id="changeUsernameForm">
                                    <div class="form-group">
                                        <label for="nuevo_username">Nuevo nombre de usuario</label>
                                        <input type="text" 
                                               id="nuevo_username" 
                                               name="nuevo_username" 
                                               value="<?= htmlspecialchars($usuario['username']) ?>" 
                                               required 
                                               minlength="3"
                                               maxlength="30"
                                               pattern="[a-zA-Z0-9_]+"
                                               placeholder="nuevo_username">
                                        <small class="form-help">Solo letras, números y guiones bajos. Mínimo 3 caracteres.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="password_username">Confirma tu contraseña actual</label>
                                        <input type="password" 
                                               id="password_username" 
                                               name="password_username" 
                                               required 
                                               placeholder="Contraseña actual">
                                    </div>
                                    <div class="form-buttons">
                                        <button type="submit" class="btn btn-primary">
                                            💾 Cambiar Username
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="toggleUsernameEdit()">
                                            ❌ Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Cambiar email -->
                    <div class="config-section">
                        <h3 class="section-title">📧 Cambiar Email</h3>
                        <form id="changeEmailForm">
                            <div class="form-group">
                                <label for="nuevo_email">Nuevo email</label>
                                <input type="email" 
                                       id="nuevo_email" 
                                       name="nuevo_email" 
                                       value="<?= htmlspecialchars($usuario['email']) ?>" 
                                       required 
                                       placeholder="nuevo@email.com">
                            </div>
                            <div class="form-group">
                                <label for="password_email">Confirma tu contraseña actual</label>
                                <input type="password" 
                                       id="password_email" 
                                       name="password_email" 
                                       required 
                                       placeholder="Contraseña actual">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                📧 Cambiar Email
                            </button>
                        </form>
                    </div>

                    <!-- Cambiar contraseña -->
                    <div class="config-section">
                        <h3 class="section-title">🔒 Cambiar Contraseña</h3>
                        <form id="changePasswordForm">
                            <div class="form-group">
                                <label for="password_actual">Contraseña actual</label>
                                <input type="password" 
                                       id="password_actual" 
                                       name="password_actual" 
                                       required 
                                       placeholder="Tu contraseña actual">
                            </div>
                            <div class="form-group">
                                <label for="password_nueva">Nueva contraseña</label>
                                <input type="password" 
                                       id="password_nueva" 
                                       name="password_nueva" 
                                       required 
                                       minlength="6"
                                       placeholder="Mínimo 6 caracteres">
                            </div>
                            <div class="form-group">
                                <label for="password_confirmar">Confirmar nueva contraseña</label>
                                <input type="password" 
                                       id="password_confirmar" 
                                       name="password_confirmar" 
                                       required 
                                       placeholder="Repite la nueva contraseña">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                🔒 Cambiar Contraseña
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zona de peligro -->
        <div class="danger-zone">
            <h3 class="danger-title">⚠️ Zona de Peligro</h3>
            <p class="danger-description">
                Esta acción eliminará permanentemente tu cuenta y todos los datos asociados, incluyendo tu lista de animes, favoritos y valoraciones. Esta acción <strong>no se puede deshacer</strong>.
            </p>
            <button class="btn btn-danger" onclick="confirmarEliminacion()">
                🗑️ Eliminar Cuenta
            </button>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar cuenta -->
    <div id="deleteAccountModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">⚠️</div>
                <h3 class="modal-title">Eliminar Cuenta</h3>
            </div>
            <div class="modal-body">
                <div class="modal-message">
                    ¿Estás completamente seguro de que quieres eliminar tu cuenta?
                </div>
                <div class="modal-submessage">
                    Esta acción eliminará permanentemente:
                    <br>• Tu lista de animes (<?= $estadisticas['total_animes'] ?> animes)
                    <br>• Tus favoritos (<?= $estadisticas['favoritos'] ?> animes)
                    <br>• Todas tus valoraciones y progreso
                    <br>• Tu cuenta de usuario
                    <br><br><strong>Esta acción NO se puede deshacer.</strong>
                </div>
                <div class="modal-buttons">
                    <button class="btn-confirm" onclick="eliminarCuenta()">
                        🗑️ Sí, eliminar todo
                    </button>
                    <button class="btn-cancel" onclick="cerrarModalEliminacion()">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
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

        // Función para mostrar mensajes
        function mostrarMensaje(texto, tipo = 'success') {
            const mensaje = document.createElement('div');
            mensaje.className = `message ${tipo}`;
            mensaje.innerHTML = (tipo === 'success' ? '✅ ' : '❌ ') + texto;
            
            // Insertar al principio del contenedor
            const container = document.querySelector('.perfil-container');
            container.insertBefore(mensaje, container.firstChild);
            
            // Scroll hacia arriba para mostrar el mensaje
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Remover después de 5 segundos
            setTimeout(() => {
                mensaje.remove();
            }, 5000);
        }

        // Manejar formulario de cambio de nombre
        document.getElementById('changeNameForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('accion', 'cambiar_nombre');
            
            try {
                const response = await fetch('../backend/api/actualizar_perfil.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    mostrarMensaje(result.message, 'success');
                    // Actualizar el nombre en la página
                    document.querySelector('.user-name').textContent = formData.get('nuevo_nombre');
                    document.querySelector('.user-indicator').innerHTML = '🟢 ' + formData.get('nuevo_nombre');
                } else {
                    mostrarMensaje(result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarMensaje('Error al actualizar el nombre', 'error');
            }
        });

        // Función para mostrar/ocultar el editor de username
        window.toggleUsernameEdit = function() {
            const editForm = document.getElementById('usernameEditForm');
            const button = document.getElementById('revealUsernameBtn');
            
            if (editForm.style.display === 'none') {
                editForm.style.display = 'block';
                button.innerHTML = '👁️ Ocultar editor de usuario';
                button.classList.add('active');
            } else {
                editForm.style.display = 'none';
                button.innerHTML = '👁️ Mostrar editor de usuario';
                button.classList.remove('active');
                // Limpiar el formulario cuando se oculte
                document.getElementById('changeUsernameForm').reset();
                document.getElementById('nuevo_username').value = '<?= htmlspecialchars($usuario['username']) ?>';
            }
        }

        // Manejar formulario de cambio de username
        document.getElementById('changeUsernameForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('accion', 'cambiar_username');
            
            const nuevoUsername = formData.get('nuevo_username');
            
            // Validación del lado del cliente
            if (!/^[a-zA-Z0-9_]+$/.test(nuevoUsername)) {
                mostrarMensaje('El nombre de usuario solo puede contener letras, números y guiones bajos', 'error');
                return;
            }
            
            if (nuevoUsername.length < 3 || nuevoUsername.length > 30) {
                mostrarMensaje('El nombre de usuario debe tener entre 3 y 30 caracteres', 'error');
                return;
            }
            
            try {
                const response = await fetch('../backend/api/actualizar_perfil.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    mostrarMensaje(result.message, 'success');
                    // Actualizar el username en la página
                    document.querySelector('.username-value').textContent = '@' + nuevoUsername;
                    // Ocultar el formulario
                    toggleUsernameEdit();
                } else {
                    mostrarMensaje(result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarMensaje('Error al actualizar el nombre de usuario', 'error');
            }
        });

        // Manejar formulario de cambio de email
        document.getElementById('changeEmailForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('accion', 'cambiar_email');
            
            try {
                const response = await fetch('../backend/api/actualizar_perfil.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    mostrarMensaje(result.message, 'success');
                    // Actualizar el email en la página
                    document.querySelector('.user-email').textContent = formData.get('nuevo_email');
                    // Limpiar el campo de contraseña
                    document.getElementById('password_email').value = '';
                } else {
                    mostrarMensaje(result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarMensaje('Error al actualizar el email', 'error');
            }
        });

        // Manejar formulario de cambio de contraseña
        document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const passwordNueva = document.getElementById('password_nueva').value;
            const passwordConfirmar = document.getElementById('password_confirmar').value;
            
            // Validar que las contraseñas coincidan
            if (passwordNueva !== passwordConfirmar) {
                mostrarMensaje('Las contraseñas no coinciden', 'error');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('accion', 'cambiar_password');
            
            try {
                const response = await fetch('../backend/api/actualizar_perfil.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    mostrarMensaje(result.message, 'success');
                    // Limpiar todos los campos de contraseña
                    this.reset();
                } else {
                    mostrarMensaje(result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarMensaje('Error al cambiar la contraseña', 'error');
            }
        });

        // Funciones para eliminar cuenta
        function confirmarEliminacion() {
            const modal = document.getElementById('deleteAccountModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function cerrarModalEliminacion() {
            const modal = document.getElementById('deleteAccountModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        async function eliminarCuenta() {
            try {
                const response = await fetch('../backend/api/eliminar_cuenta.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        confirmar: true
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Mostrar mensaje de éxito y redirigir
                    mostrarMensaje('Cuenta eliminada exitosamente. Redirigiendo...', 'success');
                    setTimeout(() => {
                        window.location.href = '../index.php';
                    }, 2000);
                } else {
                    mostrarMensaje(result.message, 'error');
                    cerrarModalEliminacion();
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarMensaje('Error al eliminar la cuenta', 'error');
                cerrarModalEliminacion();
            }
        }
        
        // Cerrar modal con escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalEliminacion();
            }
        });
        
        // Cerrar modal haciendo clic en el fondo
        document.getElementById('deleteAccountModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalEliminacion();
            }
        });
    </script>
</body>
</html>