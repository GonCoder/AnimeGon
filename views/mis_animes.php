<?php
session_start();
require_once 'config.php';
require_once 'funciones.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener animes del usuario
function obtenerAnimesUsuario($usuario_id) {
    try {
        $conexion = obtenerConexion();
        
        $query = "SELECT lu.*, a.titulo as anime_nombre, a.imagen_portada, a.episodios_total,
                         lu.episodios_vistos, lu.fecha_agregado, lu.estado, lu.puntuacion
                  FROM lista_usuario lu 
                  LEFT JOIN animes a ON lu.anime_id = a.id 
                  WHERE lu.usuario_id = ? 
                  ORDER BY lu.fecha_agregado DESC";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute([$usuario_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$animes = obtenerAnimesUsuario($usuario_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Mis Animes</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            margin: 5% auto;
            padding: 0;
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 0 50px rgba(0, 255, 255, 0.3);
            animation: modalShow 0.3s ease;
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
        }
        
        .modal-title {
            color: white;
            margin: 0;
            font-size: 1.5rem;
            text-align: center;
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
        }
        
        .close:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
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
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00ffff;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.4);
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
        
        /* Responsive */
        @media (max-width: 768px) {
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
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
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
                <a href="mis_animes.php" class="nav-link active">📺 Mis Animes</a>
                <a href="logout.php" class="nav-link">🚪 Cerrar Sesión</a>
            </div>
        </div>
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
                <input type="text" id="searchInput" class="search-input" placeholder="🔍 Buscar animes...">
                <button class="btn-agregar" onclick="abrirModal()">
                    ➕ Agregar Anime
                </button>
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
                    $estado_text = 'Pendiente';
                    
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
                                $estado_class = 'estado-pausado';
                                $estado_text = 'Abandonado';
                                break;
                        }
                    }
                    ?>
                    
                    <div class="anime-card" data-anime-name="<?= htmlspecialchars(strtolower($anime['anime_nombre'] ?? $anime['titulo'] ?? 'Sin nombre')) ?>">
                        <?php if (!empty($anime['imagen_url'])): ?>
                            <img src="<?= htmlspecialchars($anime['imagen_url']) ?>" alt="<?= htmlspecialchars($anime['anime_nombre'] ?? $anime['nombre']) ?>" class="anime-image">
                        <?php else: ?>
                            <div class="anime-image" style="display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.5); font-size: 3rem;">
                                🎭
                            </div>
                        <?php endif; ?>
                        
                        <div class="anime-info">
                            <h3 class="anime-name"><?= htmlspecialchars($anime['anime_nombre'] ?? $anime['titulo'] ?? 'Sin nombre') ?></h3>
                            
                            <div class="anime-progress">
                                <span class="progress-text">
                                    <?= $anime['episodios_vistos'] ?> / <?= $anime['episodios_total'] ?: '?' ?> episodios
                                </span>
                            </div>
                            
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $progreso ?>%"></div>
                            </div>
                            
                            <div class="anime-meta">
                                <span class="estado-badge <?= $estado_class ?>"><?= $estado_text ?></span>
                                <span><?= date('d/m/Y', strtotime($anime['fecha_agregado'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para agregar anime -->
    <div id="animeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ Agregar Nuevo Anime</h2>
                <span class="close" onclick="cerrarModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="animeForm" action="procesar_anime.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="nombre">📝 Nombre del Anime</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Ej: Attack on Titan">
                    </div>
                    
                    <div class="form-group">
                        <label for="total_episodios">📊 Total de Episodios</label>
                        <input type="number" id="total_episodios" name="total_episodios" min="1" placeholder="Ej: 25">
                    </div>
                    
                    <div class="form-group">
                        <label for="capitulos_vistos">👁️ Episodios Vistos</label>
                        <input type="number" id="capitulos_vistos" name="capitulos_vistos" min="0" value="0" placeholder="Ej: 12">
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">🎯 Estado</label>
                        <select id="estado" name="estado" required>
                            <option value="plan de ver">⏳ Plan de Ver</option>
                            <option value="viendo">👀 Viendo</option>
                            <option value="completado">✅ Completado</option>
                            <option value="en pausa">⏸️ En Pausa</option>
                            <option value="abandonado">❌ Abandonado</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="imagen">🖼️ Imagen del Anime</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="imagen" name="imagen" class="file-input" accept="image/jpeg,image/jpg,image/png">
                            <label for="imagen" class="file-input-label">
                                📎 Seleccionar imagen (JPG, PNG - máx. 1MB)
                            </label>
                        </div>
                        <div class="file-info">Formatos: JPG, PNG | Tamaño máximo: 1MB</div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn-submit">✅ Agregar Anime</button>
                        <button type="button" class="btn-cancel" onclick="cerrarModal()">❌ Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funcionalidad del modal
        function abrirModal() {
            document.getElementById('animeModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function cerrarModal() {
            document.getElementById('animeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('animeForm').reset();
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('animeModal');
            if (event.target == modal) {
                cerrarModal();
            }
        }
        
        // Filtrado en tiempo real
        document.getElementById('searchInput').addEventListener('input', function() {
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
        
        // Actualizar nombre del archivo seleccionado
        document.getElementById('imagen').addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Seleccionar imagen (JPG, PNG - máx. 1MB)';
            const label = document.querySelector('.file-input-label');
            
            if (this.files[0]) {
                // Verificar tamaño del archivo
                const fileSize = this.files[0].size / 1024 / 1024; // MB
                if (fileSize > 1) {
                    alert('⚠️ El archivo es demasiado grande. Máximo 1MB permitido.');
                    this.value = '';
                    label.textContent = '📎 Seleccionar imagen (JPG, PNG - máx. 1MB)';
                    return;
                }
                
                label.innerHTML = `📎 ${fileName} <span style="color: #00ff88;">✓</span>`;
            } else {
                label.textContent = '📎 Seleccionar imagen (JPG, PNG - máx. 1MB)';
            }
        });
        
        // Validación del formulario
        document.getElementById('animeForm').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const totalEpisodios = document.getElementById('total_episodios').value;
            const capitulosVistos = document.getElementById('capitulos_vistos').value;
            
            if (!nombre) {
                alert('⚠️ Por favor ingresa el nombre del anime.');
                e.preventDefault();
                return;
            }
            
            if (totalEpisodios && capitulosVistos && parseInt(capitulosVistos) > parseInt(totalEpisodios)) {
                alert('⚠️ Los episodios vistos no pueden ser más que el total de episodios.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>