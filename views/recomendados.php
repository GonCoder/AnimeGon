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

// Obtener mis animes para recomendar
function obtenerMisAnimesParaRecomendar($usuario_id) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT a.id as anime_id, a.titulo, a.titulo_original, a.titulo_ingles, 
                         a.imagen_portada, a.tipo, lu.puntuacion, lu.estado, lu.episodios_vistos,
                         a.episodios_total
                  FROM lista_usuario lu 
                  INNER JOIN animes a ON lu.anime_id = a.id 
                  WHERE lu.usuario_id = ? 
                  ORDER BY lu.puntuacion DESC, lu.fecha_agregado DESC";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Obtener todos los usuarios de la plataforma (excepto el actual)
function obtenerTodosUsuarios($usuario_id_actual) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT id, username, nombre, apellidos 
                  FROM usuarios 
                  WHERE id != ? AND activo = 1 
                  ORDER BY username ASC";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id_actual]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Obtener recomendaciones recibidas
function obtenerRecomendacionesRecibidas($usuario_id) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT r.*, a.titulo, a.titulo_original, a.titulo_ingles, a.imagen_portada,
                         a.tipo, a.episodios_total, u.username as emisor_username, u.nombre as emisor_nombre,
                         CASE WHEN lu.id IS NOT NULL THEN 1 ELSE 0 END as ya_en_lista
                  FROM recomendaciones r
                  INNER JOIN animes a ON r.anime_id = a.id
                  INNER JOIN usuarios u ON r.usuario_emisor_id = u.id
                  LEFT JOIN lista_usuario lu ON lu.usuario_id = ? AND lu.anime_id = r.anime_id
                  WHERE r.usuario_receptor_id = ?
                  ORDER BY r.fecha_creacion DESC";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id, $usuario_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$usuario = obtenerDatosUsuario($usuario_id);
$mis_animes = obtenerMisAnimesParaRecomendar($usuario_id);
$todos_usuarios = obtenerTodosUsuarios($usuario_id);
$recomendaciones_recibidas = obtenerRecomendacionesRecibidas($usuario_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Recomendados</title>
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
            background: rgba(138, 43, 226, 0.2);
            border: 1px solid #8a2be2;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.85rem;
            color: #da70d6;
            font-weight: 500;
            white-space: nowrap;
            animation: pulse-glow-violet 2s infinite;
        }
        
        @keyframes pulse-glow-violet {
            0% { box-shadow: 0 0 5px rgba(138, 43, 226, 0.3); }
            50% { box-shadow: 0 0 15px rgba(138, 43, 226, 0.6); }
            100% { box-shadow: 0 0 5px rgba(138, 43, 226, 0.3); }
        }

        /* Reset y estilos base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0a0a0a, #1a0a2e, #2d1b3e);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }
        
        /* Navbar Styles */
        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 2px solid rgba(138, 43, 226, 0.4);
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
            color: #da70d6;
            text-shadow: 0 0 20px rgba(218, 112, 214, 0.6);
            font-size: 1.8rem;
        }
        
        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .nav-link:hover {
            color: #da70d6;
            border-color: rgba(218, 112, 214, 0.5);
            box-shadow: 0 0 15px rgba(218, 112, 214, 0.3);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #8a2be2, #da70d6);
            color: white;
            border-color: transparent;
        }
        
        /* Estilos específicos para la página de recomendados */
        .recomendados-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .recomendados-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .recomendados-title {
            color: #da70d6;
            font-size: 2.5rem;
            text-shadow: 0 0 20px rgba(218, 112, 214, 0.6);
            margin: 0;
        }
        
        /* Layout de recomendaciones */
        .recommendations-container {
            display: flex;
            height: calc(100vh - 200px);
            gap: 20px;
        }
        
        .recommendations-section {
            flex: 1;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 20px;
            overflow-y: auto;
        }
        
        .section-title {
            color: #da70d6;
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid rgba(218, 112, 214, 0.4);
            padding-bottom: 10px;
            text-shadow: 0 0 10px rgba(218, 112, 214, 0.5);
        }
        
        .anime-recommendation-card {
            background: rgba(138, 43, 226, 0.1);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid rgba(138, 43, 226, 0.3);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .anime-recommendation-card:hover {
            background: rgba(138, 43, 226, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }
        
        .anime-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .anime-card-image {
            width: 60px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 1.5rem;
        }
        
        .anime-card-info {
            flex: 1;
        }
        
        .anime-title {
            color: #ffffff;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .anime-details {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 5px;
        }
        
        .btn-recommend {
            background: linear-gradient(135deg, #8a2be2, #da70d6);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-recommend:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 43, 226, 0.5);
        }
        
        .received-recommendation {
            border-left: 4px solid #00ffff;
        }
        
        .recommendation-meta {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 10px;
        }
        
        .recommendation-rating {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            display: inline-block;
            margin-right: 10px;
        }
        
        .recommendation-message {
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 3px solid #ff007f;
            font-style: italic;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .no-content {
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            padding: 40px 20px;
        }
        
        /* Estilos para botones de acciones de recomendaciones */
        .recommendation-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-add-to-list {
            background: linear-gradient(135deg, #8a2be2, #da70d6);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-add-to-list:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 43, 226, 0.5);
        }
        
        .btn-discard {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-discard:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.5);
        }
        
        /* Estilos para el modal de agregar anime */
        .anime-preview {
            background: rgba(138, 43, 226, 0.1);
            border: 2px solid rgba(138, 43, 226, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .anime-preview h4 {
            color: #da70d6;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(218, 112, 214, 0.5);
        }
        
        .anime-preview-info {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 5px 15px;
            align-items: center;
        }
        
        .anime-preview-label {
            font-weight: bold;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .anime-preview-value {
            color: #da70d6;
            font-weight: bold;
        }
        
        .form-section-title {
            color: #da70d6;
            font-size: 1.2rem;
            margin: 20px 0 15px 0;
            text-shadow: 0 0 10px rgba(218, 112, 214, 0.5);
        }
        
        .form-row {
            display: grid;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row-3 {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        /* Modal styles */
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
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff007f, #00ffff);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #00ffff;
            font-weight: bold;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .users-checkbox-list {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
        }
        
        .user-checkbox {
            display: flex;
            align-items: center;
            padding: 8px;
            margin: 4px 0;
            border-radius: 6px;
            transition: background 0.3s ease;
        }
        
        .user-checkbox:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .user-checkbox input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .character-count {
            text-align: right;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 5px;
        }
        
        .character-count.warning {
            color: #ffc107;
        }
        
        .character-count.danger {
            color: #dc3545;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover, .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: #ff007f;
        }
        
        /* Estilos para el input de búsqueda */
        .search-container {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
        }
        
        .search-input-recommend {
            background: rgba(138, 43, 226, 0.1);
            border: 2px solid rgba(138, 43, 226, 0.4);
            border-radius: 25px;
            padding: 12px 20px;
            color: white;
            width: 100%;
            max-width: 400px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-input-recommend:focus {
            outline: none;
            border-color: #da70d6;
            box-shadow: 0 0 20px rgba(218, 112, 214, 0.4);
        }
        
        .search-input-recommend::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* Estilos para modales de confirmación y mensaje */
        .confirm-modal, .message-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }
        
        .confirm-modal-content, .message-modal-content {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            margin: 15% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(138, 43, 226, 0.4);
            animation: modalSlideIn 0.3s ease;
        }
        
        .confirm-modal-header, .message-modal-header {
            background: linear-gradient(135deg, #8a2be2, #da70d6);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .confirm-modal-body, .message-modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .confirm-modal-body p, .message-modal-body p {
            color: white;
            font-size: 1.1rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .confirm-buttons, .message-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.5);
        }
        
        .btn-cancel-confirm {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-cancel-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.5);
        }
        
        .btn-ok {
            background: linear-gradient(135deg, #8a2be2, #da70d6);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-ok:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 43, 226, 0.5);
        }
        
        .success-icon {
            color: #28a745;
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .error-icon {
            color: #dc3545;
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .recommendations-container {
                flex-direction: column;
                height: auto;
            }
            
            .recommendations-section {
                height: 50vh;
            }
            
            .confirm-modal-content, .message-modal-content {
                margin: 20% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2>🎌 AnimeGon</h2>
                <span class="user-indicator">🟢 <?= htmlspecialchars($usuario['nombre']) ?></span>
            </div>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                <a href="mis_animes.php" class="nav-link">📺 Mis Animes</a>
                <a href="favoritos.php" class="nav-link">⭐ Favoritos</a>
                <a href="recomendados.php" class="nav-link active">🎯 Recomendados</a>
                <a href="hub.php" class="nav-link">🌐 Hub</a>
                <a href="logout.php" class="nav-link">🔴 Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="recomendados-container">
        <div class="recomendados-header">
            <h1 class="recomendados-title">🎯 Recomendados</h1>
        </div>

    <!-- Contenedor principal -->
    <div class="recommendations-container">
        <!-- Sección izquierda: Mis animes para recomendar -->
        <div class="recommendations-section">
            <h2 class="section-title">📤 Recomendar a mis amigos</h2>
            
            <div class="search-container">
                <input type="text" id="searchInputRecommend" class="search-input-recommend" placeholder="🔍 Buscar mis animes para recomendar...">
            </div>
            
            <?php if (!empty($mis_animes)): ?>
                <?php foreach ($mis_animes as $anime): ?>
                    <div class="anime-recommendation-card">
                        <div class="anime-card-header">
                            <?php if (!empty($anime['imagen_portada'])): ?>
                                <?php 
                                $ruta_imagen = $anime['imagen_portada'];
                                if (strpos($ruta_imagen, 'img/') === 0) {
                                    $ruta_imagen = '../' . $ruta_imagen;
                                }
                                ?>
                                <img src="<?= htmlspecialchars($ruta_imagen) ?>" alt="<?= htmlspecialchars($anime['titulo']) ?>" class="anime-card-image">
                            <?php else: ?>
                                <div class="anime-card-image">🎭</div>
                            <?php endif; ?>
                            
                            <div class="anime-card-info">
                                <div class="anime-title"><?= htmlspecialchars($anime['titulo']) ?></div>
                                
                                <?php if (!empty($anime['titulo_original'])): ?>
                                    <div class="anime-details">🇯🇵 <?= htmlspecialchars($anime['titulo_original']) ?></div>
                                <?php endif; ?>
                                
                                <div class="anime-details">
                                    📺 <?= htmlspecialchars($anime['tipo']) ?> | 
                                    👁️ <?= $anime['episodios_vistos'] ?>/<?= $anime['episodios_total'] ?: '?' ?> episodios
                                    <?php if ($anime['puntuacion']): ?>
                                        | ⭐ <?= number_format($anime['puntuacion'], 1) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <button class="btn-recommend" onclick="abrirModalRecomendacion(<?= $anime['anime_id'] ?>, '<?= htmlspecialchars($anime['titulo'], ENT_QUOTES) ?>')">
                            🎯 Recomendar este anime
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-content">
                    <p>📺 No tienes animes en tu lista para recomendar</p>
                    <p>¡Agrega algunos animes primero!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sección derecha: Recomendaciones recibidas -->
        <div class="recommendations-section">
            <h2 class="section-title">📥 Recomendaciones recibidas</h2>
            
            <?php if (!empty($recomendaciones_recibidas)): ?>
                <?php foreach ($recomendaciones_recibidas as $recomendacion): ?>
                    <div class="anime-recommendation-card received-recommendation">
                        <div class="recommendation-meta">
                            👤 <strong><?= htmlspecialchars($recomendacion['emisor_nombre']) ?></strong> 
                            (@<?= htmlspecialchars($recomendacion['emisor_username']) ?>) • 
                            📅 <?= date('d/m/Y H:i', strtotime($recomendacion['fecha_creacion'])) ?>
                        </div>
                        
                        <div class="anime-card-header">
                            <?php if (!empty($recomendacion['imagen_portada'])): ?>
                                <?php 
                                $ruta_imagen = $recomendacion['imagen_portada'];
                                if (strpos($ruta_imagen, 'img/') === 0) {
                                    $ruta_imagen = '../' . $ruta_imagen;
                                }
                                ?>
                                <img src="<?= htmlspecialchars($ruta_imagen) ?>" alt="<?= htmlspecialchars($recomendacion['titulo']) ?>" class="anime-card-image">
                            <?php else: ?>
                                <div class="anime-card-image">🎭</div>
                            <?php endif; ?>
                            
                            <div class="anime-card-info">
                                <div class="anime-title"><?= htmlspecialchars($recomendacion['titulo']) ?></div>
                                
                                <?php if (!empty($recomendacion['titulo_original'])): ?>
                                    <div class="anime-details">🇯🇵 <?= htmlspecialchars($recomendacion['titulo_original']) ?></div>
                                <?php endif; ?>
                                
                                <div class="anime-details">
                                    📺 <?= htmlspecialchars($recomendacion['tipo']) ?>
                                    <?php if ($recomendacion['episodios_total']): ?>
                                        | 📊 <?= $recomendacion['episodios_total'] ?> episodios
                                    <?php endif; ?>
                                </div>
                                
                                <div style="margin-top: 8px;">
                                    <span class="recommendation-rating">⭐ <?= $recomendacion['valoracion_recomendacion'] ?>/10</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="recommendation-message">
                            <?= nl2br(htmlspecialchars($recomendacion['mensaje_recomendacion'])) ?>
                        </div>
                        
                        <div class="recommendation-actions">
                            <?php if ($recomendacion['ya_en_lista']): ?>
                                <button class="btn-discard" onclick="descartarRecomendacion(<?= $recomendacion['id'] ?>)">
                                    ✅ Ya tienes este anime, descartar
                                </button>
                            <?php else: ?>
                                <button class="btn-add-to-list" onclick="abrirModalAgregarRecomendado(<?= $recomendacion['anime_id'] ?>, '<?= htmlspecialchars($recomendacion['titulo'], ENT_QUOTES) ?>', <?= $recomendacion['episodios_total'] ?: 'null' ?>, <?= $recomendacion['id'] ?>)">
                                    ➕ Añadir anime a mi lista
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-content">
                    <p>📥 No tienes recomendaciones aún</p>
                    <p>¡Espera a que tus amigos te recomienden animes!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para enviar recomendación -->
    <div id="recommendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🎯 Recomendar Anime</h2>
                <span class="close" onclick="cerrarModalRecomendacion()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="recommendForm">
                    <input type="hidden" id="recommend_anime_id" name="anime_id">
                    
                    <div class="form-group">
                        <h3 id="anime_title" style="color: #00ffff; margin-bottom: 20px;"></h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="valoracion">⭐ Tu valoración de este anime (1-10)</label>
                        <select id="valoracion" name="valoracion" required>
                            <option value="">Selecciona una puntuación</option>
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
                    </div>
                    
                    <div class="form-group">
                        <label for="mensaje">💬 ¿Por qué recomiendas este anime?</label>
                        <textarea id="mensaje" name="mensaje" placeholder="Cuéntales por qué deberían ver este anime..." maxlength="3000" required></textarea>
                        <div class="character-count" id="charCount">0 / 3000 caracteres</div>
                    </div>
                    
                    <div class="form-group">
                        <label>👥 Selecciona a quién enviar la recomendación</label>
                        <div class="users-checkbox-list">
                            <?php foreach ($todos_usuarios as $usuario_item): ?>
                                <div class="user-checkbox">
                                    <input type="checkbox" name="usuarios[]" value="<?= $usuario_item['id'] ?>" id="user_<?= $usuario_item['id'] ?>">
                                    <label for="user_<?= $usuario_item['id'] ?>">
                                        <strong><?= htmlspecialchars($usuario_item['nombre']) ?></strong>
                                        <?php if ($usuario_item['apellidos']): ?>
                                            <?= htmlspecialchars($usuario_item['apellidos']) ?>
                                        <?php endif; ?>
                                        (@<?= htmlspecialchars($usuario_item['username']) ?>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn-submit">🚀 Enviar recomendación</button>
                        <button type="button" class="btn-cancel" onclick="cerrarModalRecomendacion()">❌ Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Esperar a que el DOM esté completamente cargado
        document.addEventListener('DOMContentLoaded', function() {
            // Variables globales
            let currentAnimeId = null;
            let currentAnimeTitle = '';

            // Función para abrir modal de recomendación
            window.abrirModalRecomendacion = function(animeId, animeTitle) {
            currentAnimeId = animeId;
            currentAnimeTitle = animeTitle;
            
            document.getElementById('recommend_anime_id').value = animeId;
            document.getElementById('anime_title').textContent = animeTitle;
            document.getElementById('recommendModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Resetear formulario
            document.getElementById('recommendForm').reset();
            document.getElementById('recommend_anime_id').value = animeId;
            updateCharCount();
        }

            // Función para cerrar modal
            window.cerrarModalRecomendacion = function() {
            document.getElementById('recommendModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

            // Contador de caracteres
            function updateCharCount() {
            const textarea = document.getElementById('mensaje');
            const charCount = document.getElementById('charCount');
            const current = textarea.value.length;
            const max = 3000;
            
            charCount.textContent = `${current} / ${max} caracteres`;
            charCount.className = 'character-count';
            
            if (current > max * 0.9) {
                charCount.classList.add('danger');
            } else if (current > max * 0.8) {
                charCount.classList.add('warning');
            }
        }

        // Event listener para contador de caracteres
        document.getElementById('mensaje').addEventListener('input', updateCharCount);

        // Manejar envío del formulario
        document.getElementById('recommendForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const selectedUsers = Array.from(document.querySelectorAll('input[name="usuarios[]"]:checked')).map(cb => cb.value);
            
            if (selectedUsers.length === 0) {
                mostrarModalMensaje('error', 'Selección Requerida', 'Por favor selecciona al menos un usuario para enviar la recomendación');
                return;
            }
            
            try {
                const response = await fetch('../backend/api/enviar_recomendacion.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    cerrarModalRecomendacion();
                    mostrarModalMensaje('success', '¡Éxito!', `Recomendación enviada exitosamente a ${selectedUsers.length} usuario(s)`);
                } else {
                    mostrarModalMensaje('error', 'Error', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarModalMensaje('error', 'Error de Conexión', 'Error de conexión al enviar la recomendación');
            }
        });

            // Funciones para el modal de agregar anime
            window.abrirModalAgregarRecomendado = function(animeId, titulo, episodiosTotal, recomendacionId) {
            const modal = document.getElementById('addToListModal');
            document.getElementById('anime_id').value = animeId;
            document.getElementById('recomendacion_id').value = recomendacionId;
            document.getElementById('previewTitulo').textContent = titulo;
            document.getElementById('previewEpisodios').textContent = episodiosTotal || 'Desconocido';
            
            // Resetear formulario
            document.getElementById('addToListForm').reset();
            document.getElementById('anime_id').value = animeId;
            document.getElementById('recomendacion_id').value = recomendacionId;
            
            modal.style.display = 'block';
        }
            
            window.cerrarModalAgregar = function() {
            document.getElementById('addToListModal').style.display = 'none';
        }
            
            // Función para descartar recomendación
            window.descartarRecomendacion = function(recomendacionId) {
                mostrarModalConfirmacion(
                    '🗑️ Descartar Recomendación',
                    '¿Estás seguro de que quieres descartar esta recomendación? Esta acción no se puede deshacer.',
                    async function() {
                        try {
                            const response = await fetch('../backend/api/descartar_recomendacion.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ recomendacion_id: recomendacionId })
                            });
                            
                            const result = await response.json();
                            
                            if (result.success) {
                                mostrarModalMensaje('success', '¡Éxito!', 'Recomendación descartada exitosamente', function() {
                                    location.reload();
                                });
                            } else {
                                mostrarModalMensaje('error', 'Error', result.message);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            mostrarModalMensaje('error', 'Error de Conexión', 'Error de conexión al descartar la recomendación');
                        }
                    }
                );
            }
        
        // Manejar formulario de agregar anime
        document.getElementById('addToListForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('../backend/api/procesar_anime.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    cerrarModalAgregar();
                    mostrarModalMensaje('success', '¡Éxito!', 'Anime agregado exitosamente a tu lista', function() {
                        location.reload();
                    });
                } else {
                    mostrarModalMensaje('error', 'Error', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarModalMensaje('error', 'Error de Conexión', 'Error de conexión al procesar la solicitud');
            }
        });

        // Filtrado para animes para recomendar
        document.getElementById('searchInputRecommend')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const animeCards = document.querySelectorAll('.recommendations-section:first-child .anime-recommendation-card');
            
            animeCards.forEach(card => {
                const animeTitle = card.querySelector('.anime-title');
                const animeDetails = card.querySelector('.anime-details');
                
                if (animeTitle && animeDetails) {
                    const titleText = animeTitle.textContent.toLowerCase();
                    const detailsText = animeDetails.textContent.toLowerCase();
                    
                    if (titleText.includes(searchTerm) || detailsText.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        });
        
        // Funciones para modales de confirmación y mensaje
        function mostrarModalConfirmacion(titulo, mensaje, onConfirm) {
            document.getElementById('confirmModalTitle').textContent = titulo;
            document.getElementById('confirmModalMessage').textContent = mensaje;
            document.getElementById('confirmModal').style.display = 'block';
            
            document.getElementById('btnConfirm').onclick = function() {
                document.getElementById('confirmModal').style.display = 'none';
                if (onConfirm) onConfirm();
            };
        }
        
        function cerrarModalConfirmacion() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        function mostrarModalMensaje(tipo, titulo, mensaje, callback) {
            document.getElementById('messageModalTitle').textContent = titulo;
            document.getElementById('messageModalMessage').textContent = mensaje;
            
            const iconElement = document.getElementById('messageIcon');
            if (tipo === 'success') {
                iconElement.innerHTML = '✅';
                iconElement.className = 'success-icon';
            } else {
                iconElement.innerHTML = '❌';
                iconElement.className = 'error-icon';
            }
            
            document.getElementById('messageModal').style.display = 'block';
            
            document.getElementById('btnOk').onclick = function() {
                document.getElementById('messageModal').style.display = 'none';
                if (callback) callback();
            };
        }
        
        function cerrarModalMensaje() {
            document.getElementById('messageModal').style.display = 'none';
        }
        
        // Cerrar modal con clic fuera
        window.onclick = function(event) {
            const recommendModal = document.getElementById('recommendModal');
            const addModal = document.getElementById('addToListModal');
            const confirmModal = document.getElementById('confirmModal');
            const messageModal = document.getElementById('messageModal');
            
            if (event.target === recommendModal) {
                cerrarModalRecomendacion();
            }
            if (event.target === addModal) {
                cerrarModalAgregar();
            }
            if (event.target === confirmModal) {
                cerrarModalConfirmacion();
            }
            if (event.target === messageModal) {
                cerrarModalMensaje();
            }
        }

        }); // Fin del DOMContentLoaded
    </script>

    <!-- Modal para agregar anime a mi lista desde recomendaciones -->
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
                    <input type="hidden" id="recomendacion_id" name="recomendacion_id">
                    
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

    <!-- Modal de confirmación -->
    <div id="confirmModal" class="confirm-modal">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header">
                <h2 id="confirmModalTitle">Confirmar Acción</h2>
                <span class="close" onclick="cerrarModalConfirmacion()">&times;</span>
            </div>
            <div class="confirm-modal-body">
                <p id="confirmModalMessage">¿Estás seguro de que quieres realizar esta acción?</p>
                <div class="confirm-buttons">
                    <button id="btnConfirm" class="btn-confirm">✅ Confirmar</button>
                    <button class="btn-cancel-confirm" onclick="cerrarModalConfirmacion()">❌ Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de mensaje -->
    <div id="messageModal" class="message-modal">
        <div class="message-modal-content">
            <div class="message-modal-header">
                <h2 id="messageModalTitle">Mensaje</h2>
                <span class="close" onclick="cerrarModalMensaje()">&times;</span>
            </div>
            <div class="message-modal-body">
                <div id="messageIcon" class="success-icon">✅</div>
                <p id="messageModalMessage">Mensaje de información</p>
                <div class="message-buttons">
                    <button id="btnOk" class="btn-ok">👍 Entendido</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>