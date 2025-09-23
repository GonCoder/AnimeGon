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

// Obtener animes favoritos del usuario
function obtenerAnimesFavoritos($usuario_id) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT lu.*, a.titulo as anime_nombre, a.imagen_portada, a.episodios_total,
                         lu.episodios_vistos, lu.fecha_agregado, lu.estado, lu.puntuacion, lu.favorito, a.id as anime_id,
                         a.tipo, a.estado as estado_anime
                  FROM lista_usuario lu 
                  LEFT JOIN animes a ON lu.anime_id = a.id 
                  WHERE lu.usuario_id = ? AND lu.favorito = TRUE
                  ORDER BY lu.fecha_agregado DESC";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$animes_favoritos = obtenerAnimesFavoritos($usuario_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Mis Favoritos</title>
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
        }
        
        .nav-logo h2 {
            color: #00ffff;
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.6);
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
        
        /* Responsive */
        @media (max-width: 768px) {
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
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2>🎌 AnimeGon</h2>
            </div>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                <a href="mis_animes.php" class="nav-link">📺 Mis Animes</a>
                <a href="favoritos.php" class="nav-link active">⭐ Favoritos</a>
                <a href="logout.php" class="nav-link">🚪 Cerrar Sesión</a>
            </div>
        </div>
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
                        <?= count($animes_favoritos) ?> anime<?= count($animes_favoritos) != 1 ? 's' : '' ?> favorito<?= count($animes_favoritos) != 1 ? 's' : '' ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="animes-grid" id="animesGrid">
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
                        
                        <?php if (!empty($anime['imagen_url'])): ?>
                            <img src="<?= htmlspecialchars($anime['imagen_url']) ?>" alt="<?= htmlspecialchars($anime['anime_nombre'] ?? $anime['nombre']) ?>" class="anime-image">
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
    </div>

    <script src="../frontend/assets/js/animes.js"></script>
    <script>
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
    </script>
</body>
</html>