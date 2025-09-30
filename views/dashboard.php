<?php
// dashboard.php - Página principal después del login

require_once '../backend/config/funciones.php';

// Requiere que el usuario esté logueado
requiereSesion();

// Obtener datos del usuario actual
$usuario = obtenerUsuarioActual();

// Función para obtener estadísticas del usuario
function obtenerEstadisticasUsuario($usuario_id) {
    try {
        $conexion = obtenerConexion();
        
        // Contar animes vistos (completados por el usuario)
        $stmt_vistos = $conexion->prepare("SELECT COUNT(*) FROM lista_usuario WHERE usuario_id = :usuario_id AND estado = 'Completado'");
        $stmt_vistos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_vistos->execute();
        $animes_vistos = $stmt_vistos->fetchColumn();
        
        // Contar puntuaciones realizadas por el usuario
        $stmt_puntuaciones = $conexion->prepare("SELECT COUNT(*) FROM lista_usuario WHERE usuario_id = :usuario_id AND puntuacion IS NOT NULL AND puntuacion > 0");
        $stmt_puntuaciones->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_puntuaciones->execute();
        $puntuaciones_realizadas = $stmt_puntuaciones->fetchColumn();
        
        // Contar total en lista (todos los animes del usuario)
        $stmt_total = $conexion->prepare("SELECT COUNT(*) FROM lista_usuario WHERE usuario_id = :usuario_id");
        $stmt_total->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_total->execute();
        $total_lista = $stmt_total->fetchColumn();
        
        // Calcular horas vistas basado en episodios_vistos reales
        // 20 minutos por episodio, cada 3 episodios = 1 hora
        $stmt_horas = $conexion->prepare("
            SELECT COALESCE(SUM(lu.episodios_vistos), 0) as total_episodios_vistos
            FROM lista_usuario lu 
            WHERE lu.usuario_id = :usuario_id
        ");
        $stmt_horas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_horas->execute();
        $total_episodios_vistos = $stmt_horas->fetchColumn();
        
        // Calcular horas (20 minutos por episodio, cada 3 episodios = 1 hora)
        $minutos_totales = $total_episodios_vistos * 20;
        $horas_vistas = round($minutos_totales / 60, 1);
        
        return [
            'animes_vistos' => $animes_vistos,
            'puntuaciones_realizadas' => $puntuaciones_realizadas,
            'total_lista' => $total_lista,
            'horas_vistas' => $horas_vistas
        ];
        
    } catch (Exception $e) {
        error_log("Error al obtener estadísticas: " . $e->getMessage());
        return [
            'animes_vistos' => 0,
            'puntuaciones_realizadas' => 0,
            'total_lista' => 0,
            'horas_vistas' => 0
        ];
    }
}

// Función para obtener actividad reciente del usuario
function obtenerActividadReciente($usuario_id, $limite = 5) {
    try {
        $conexion = obtenerConexion();
        
        // Obtener actividad de lista_usuario
        $stmt_lista = $conexion->prepare("
            SELECT 
                a.titulo,
                lu.estado,
                lu.fecha_agregado,
                lu.fecha_actualizacion,
                lu.puntuacion,
                lu.episodios_vistos,
                'lista' as tipo,
                CASE 
                    WHEN lu.fecha_actualizacion > lu.fecha_agregado THEN 'actualizado'
                    ELSE 'agregado'
                END as tipo_actividad,
                GREATEST(lu.fecha_agregado, lu.fecha_actualizacion) as fecha_actividad
            FROM lista_usuario lu
            INNER JOIN animes a ON lu.anime_id = a.id
            WHERE lu.usuario_id = :usuario_id
        ");
        $stmt_lista->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_lista->execute();
        $actividad_lista = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener actividad de favoritos
        $stmt_favoritos = $conexion->prepare("
            SELECT 
                a.titulo,
                'favorito' as estado,
                f.fecha_agregado,
                f.fecha_agregado as fecha_actualizacion,
                NULL as puntuacion,
                NULL as episodios_vistos,
                'favorito' as tipo,
                'agregado' as tipo_actividad,
                f.fecha_agregado as fecha_actividad
            FROM favoritos f
            INNER JOIN animes a ON f.anime_id = a.id
            WHERE f.usuario_id = :usuario_id
        ");
        $stmt_favoritos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_favoritos->execute();
        $actividad_favoritos = $stmt_favoritos->fetchAll(PDO::FETCH_ASSOC);
        
        // Combinar ambas actividades
        $todas_actividades = array_merge($actividad_lista, $actividad_favoritos);
        
        // Ordenar por fecha de actividad más reciente
        usort($todas_actividades, function($a, $b) {
            return strtotime($b['fecha_actividad']) - strtotime($a['fecha_actividad']);
        });
        
        // Limitar resultados
        return array_slice($todas_actividades, 0, $limite);
        
    } catch (Exception $e) {
        error_log("Error al obtener actividad reciente: " . $e->getMessage());
        return [];
    }
}

// Obtener estadísticas del usuario actual
$estadisticas = obtenerEstadisticasUsuario($usuario['id']);

// Obtener actividad reciente
$actividad_reciente = obtenerActividadReciente($usuario['id']);

// Función para obtener los animes mejor puntuados
function obtenerTopAnimesPuntuados($limite = 3) {
    try {
        $conexion = obtenerConexion();
        
        $query = "
            SELECT 
                a.id,
                a.titulo,
                a.imagen_portada,
                ROUND(AVG(CAST(lu.puntuacion AS DECIMAL(3,1))), 1) as media_puntuacion,
                COUNT(lu.puntuacion) as total_puntuaciones,
                GROUP_CONCAT(
                    CONCAT(u.nombre, ':', lu.puntuacion) 
                    ORDER BY lu.puntuacion DESC, lu.fecha_actualizacion DESC 
                    LIMIT 5
                ) as top_usuarios
            FROM animes a
            INNER JOIN lista_usuario lu ON a.id = lu.anime_id
            INNER JOIN usuarios u ON lu.usuario_id = u.id
            WHERE lu.puntuacion IS NOT NULL 
                AND lu.puntuacion > 0
            GROUP BY a.id, a.titulo, a.imagen_portada
            HAVING COUNT(lu.puntuacion) >= 1
            ORDER BY media_puntuacion DESC, total_puntuaciones DESC
            LIMIT :limite
        ";
        
        $stmt = $conexion->prepare($query);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al obtener top animes puntuados: " . $e->getMessage());
        return [];
    }
}

// Función para obtener todos los animes puntuados con ranking
function obtenerTodosAnimesPuntuados() {
    try {
        $conexion = obtenerConexion();
        
        $query = "
            SELECT 
                a.id,
                a.titulo,
                a.titulo_original,
                a.imagen_portada,
                a.tipo,
                a.episodios_total,
                ROUND(AVG(CAST(lu.puntuacion AS DECIMAL(3,1))), 1) as media_puntuacion,
                COUNT(lu.puntuacion) as total_puntuaciones,
                GROUP_CONCAT(
                    CONCAT(u.nombre, ':', lu.puntuacion, ':', u.username) 
                    ORDER BY lu.puntuacion DESC, lu.fecha_actualizacion DESC 
                ) as usuarios_puntuaciones
            FROM animes a
            INNER JOIN lista_usuario lu ON a.id = lu.anime_id
            INNER JOIN usuarios u ON lu.usuario_id = u.id
            WHERE lu.puntuacion IS NOT NULL 
                AND lu.puntuacion > 0
            GROUP BY a.id, a.titulo, a.titulo_original, a.imagen_portada, a.tipo, a.episodios_total
            HAVING COUNT(lu.puntuacion) >= 1
            ORDER BY media_puntuacion DESC, total_puntuaciones DESC
        ";
        
        $stmt = $conexion->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al obtener todos los animes puntuados: " . $e->getMessage());
        return [];
    }
}

// Obtener datos para la nueva sección de puntuajes
$top_animes_puntuados = obtenerTopAnimesPuntuados(3);
$todos_animes_puntuados = obtenerTodosAnimesPuntuados();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Dashboard</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../favicon.svg">
    
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="logo">AnimeGon</h1>
                <div class="user-menu">
                    <span class="user-name dashboard-user-name" onclick="window.location.href='perfil.php'">🟢 <?= escape($usuario['nombre']) ?></span>
                    <a href="logout.php" class="btn-logout" title="Cerrar sesión con confirmación">🔴Salir</a>
                </div>
                <div class="hamburger" onclick="toggleMobileMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <nav class="nav" id="navMenu">
                    <ul>
                        <li><a href="dashboard.php" class="active">Inicio</a></li>
                        <li><a href="mis_animes.php">Mis Animes</a></li>
                        <li><a href="favoritos.php">Favoritos</a></li>
                        <li><a href="recomendados.php">Recomendados</a></li>
                        <li><a href="hub.php">Hub</a></li>
                        <li><a href="perfil.php">Mi Perfil</a></li>
                    </ul>
                </nav>
            </div>
        </div>
        <div class="nav-overlay" id="navOverlay" onclick="closeMobileMenu()"></div>
    </header>

    <main class="main">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-card">
                    <h2>¡Bienvenido de vuelta, <?= escape($usuario['nombre']) ?>!</h2>
                    <p>Username: 🟢<strong><?= escape($usuario['username']) ?></strong></p>
                    <p>Email: <strong><?= escape($usuario['email']) ?></strong></p>
                </div>
            </section>

            <section class="dashboard-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📺</div>
                        <div class="stat-content">
                            <h3>Animes Vistos</h3>
                            <p class="stat-number"><?= $estadisticas['animes_vistos'] ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-content">
                            <h3>Puntuaciones</h3>
                            <p class="stat-number"><?= $estadisticas['puntuaciones_realizadas'] ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📋</div>
                        <div class="stat-content">
                            <h3>En Lista</h3>
                            <p class="stat-number"><?= $estadisticas['total_lista'] ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⏰</div>
                        <div class="stat-content">
                            <h3>Horas Vistas</h3>
                            <p class="stat-number"><?= $estadisticas['horas_vistas'] ?>h</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="quick-actions">
                <h3>Acciones Rápidas</h3>
                <div class="actions-grid">
                    <div class="action-card">
                        <h4>🌐 Explorar Hub</h4>
                        <p>Descubre nuevos animes y ve las puntuaciones de la comunidad</p>
                        <button class="btn-action" onclick="console.log('Botón Hub clicked'); window.location.href='hub.php'">Explorar</button>
                        
                        <!-- Búsqueda rápida de animes -->
                        <div class="quick-search-section">
                            <h5 class="dashboard-quick-search-title">🔍 Búsqueda Rápida</h5>
                            <div class="search-container">
                                <input type="text" id="quickSearchInput" class="quick-search-input" placeholder="🔍 Buscar anime..." onkeypress="handleQuickSearchEnter(event)">
                                <button class="btn-quick-search" onclick="ejecutarBusquedaRapida()">Buscar</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-card">
                        <h4>📝 Gestionar Lista</h4>
                        <p>Administra tu lista personal de animes</p>
                        <button class="btn-action" onclick="console.log('Botón Lista clicked'); window.location.href='mis_animes.php'">Ver Lista</button>
                        <!-- Botón para agregar nuevo anime -->
                <div class="add-anime-section">
                    <button class="btn-add-anime" onclick="abrirModalAgregarAnime()">
                        ➕ Agregar Nuevo Anime
                    </button>
                </div>
                    </div>
                    
                    <div class="action-card">
                        <h4>🏆 Ver Puntuajes</h4>
                        <p class="dashboard-ranking-description">Animes mejor valorados por la comunidad</p>
                        <button class="btn-action dashboard-ranking-btn" onclick="abrirModalPuntuajes()">Ver Ranking</button>
                        
                        <!-- Preview de top 3 animes mejor puntuados -->
                        <?php if (!empty($top_animes_puntuados)): ?>
                            <div class="top-animes-preview">
                                <h5 class="dashboard-top3-title">🥇 Top 3 Mejor Puntuados</h5>
                                <div class="preview-cards">
                                    <?php foreach ($top_animes_puntuados as $index => $anime): ?>
                                        <div class="preview-card" data-anime-id="<?= $anime['id'] ?>">
                                            <div class="preview-rank"><?= $index + 1 ?></div>
                                            <img src="<?= htmlspecialchars($anime['imagen_portada'] ?: '../img/default-anime.jpg') ?>" alt="<?= htmlspecialchars($anime['titulo']) ?>" class="preview-image">
                                            <div class="preview-info">
                                                <div class="preview-title"><?= htmlspecialchars(substr($anime['titulo'], 0, 25)) ?><?= strlen($anime['titulo']) > 25 ? '...' : '' ?></div>
                                                <div class="preview-rating">⭐ <?= $anime['media_puntuacion'] ?>/10</div>
                                                <div class="preview-votes">(<?= $anime['total_puntuaciones'] ?> votos)</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                
            </section>

            <section class="recent-activity">
                <h3>Actividad Reciente</h3>
                <div class="activity-list">
                    <?php if (!empty($actividad_reciente)): ?>
                        <?php foreach ($actividad_reciente as $actividad): ?>
                            <div class="activity-item">
                                <?php
                                // Determinar ícono y mensaje según el tipo de actividad
                                $icono = '📺';
                                $mensaje = '';
                                $tiempo = '';
                                
                                if ($actividad['tipo'] == 'favorito') {
                                    // Actividad de favoritos
                                    $icono = '⭐';
                                    $mensaje = "Agregaste <strong>" . htmlspecialchars($actividad['titulo']) . "</strong> a favoritos";
                                    $tiempo = date('d/m/Y H:i', strtotime($actividad['fecha_agregado']));
                                } elseif ($actividad['tipo_actividad'] == 'agregado') {
                                    // Anime agregado a la lista
                                    $icono = '➕';
                                    $mensaje = "Agregaste <strong>" . htmlspecialchars($actividad['titulo']) . "</strong> a tu lista";
                                    $tiempo = date('d/m/Y H:i', strtotime($actividad['fecha_agregado']));
                                } else {
                                    // Anime actualizado en la lista
                                    if ($actividad['estado'] == 'Completado') {
                                        $icono = '✅';
                                        $mensaje = "Completaste <strong>" . htmlspecialchars($actividad['titulo']) . "</strong>";
                                    } elseif ($actividad['puntuacion'] > 0) {
                                        $icono = '🎯';
                                        $mensaje = "Puntuaste <strong>" . htmlspecialchars($actividad['titulo']) . "</strong> con " . $actividad['puntuacion'] . "/10";
                                    } elseif ($actividad['episodios_vistos'] > 0) {
                                        $icono = '📺';
                                        $mensaje = "Viste " . $actividad['episodios_vistos'] . " episodios de <strong>" . htmlspecialchars($actividad['titulo']) . "</strong>";
                                    } else {
                                        $icono = '📝';
                                        $mensaje = "Actualizaste <strong>" . htmlspecialchars($actividad['titulo']) . "</strong>";
                                    }
                                    $tiempo = date('d/m/Y H:i', strtotime($actividad['fecha_actualizacion']));
                                }
                                ?>
                                <div class="activity-icon"><?= $icono ?></div>
                                <div class="activity-content">
                                    <p><?= $mensaje ?></p>
                                    <small><?= $tiempo ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-icon">🎉</div>
                            <div class="activity-content">
                                <p><strong>¡Te has registrado en AnimeGon!</strong></p>
                                <small>Bienvenido a nuestra comunidad de anime</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 AnimeGon - Tu plataforma de seguimiento de anime favorita</p>
        </div>
    </footer>

    <script>
        // Animación de entrada para las tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .action-card, .welcome-card');
            
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });

            // Animación de contador para los números de estadísticas
            animateCounters();
        });

        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            
            counters.forEach(counter => {
                const target = parseFloat(counter.textContent.replace(/[^\d.]/g, ''));
                const isHours = counter.textContent.includes('h');
                const duration = 2000; // 2 segundos
                const increment = target / (duration / 16); // 60fps
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    
                    if (isHours) {
                        counter.textContent = current.toFixed(1) + 'h';
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 16);
            });
        }

        // Confirmar logout
        document.querySelector('.btn-logout').addEventListener('click', function(e) {
            e.preventDefault();
            
            const modal = document.getElementById('confirmLogoutModal');
            const confirmBtn = document.getElementById('confirmLogoutBtn');
            const cancelBtn = document.getElementById('cancelLogoutBtn');
            
            // Fallback si el modal no existe
            if (!modal || !confirmBtn || !cancelBtn) {
                const confirmed = confirm('¿Estás seguro de que quieres cerrar sesión?');
                if (confirmed) {
                    window.location.href = 'logout.php';
                }
                return;
            }
            
            // Mostrar el modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevenir scroll
            
            // Configurar los botones
            confirmBtn.onclick = () => {
                window.location.href = 'logout.php';
            };
            
            cancelBtn.onclick = () => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            };
            
            // Cerrar con escape
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            
            document.addEventListener('keydown', handleEscape);
            
            // Cerrar al hacer clic en el fondo
            modal.onclick = (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            };
        });

        // Funciones para el modal de agregar anime
        function abrirModalAgregarAnime() {
            const modal = document.getElementById('animeModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function cerrarModalAgregarAnime() {
            const modal = document.getElementById('animeModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Limpiar formulario
            document.getElementById('animeForm').reset();
        }

        // Cerrar modales al hacer click fuera de ellos
        window.addEventListener('click', function(event) {
            const animeModal = document.getElementById('animeModal');
            const puntuajesModal = document.getElementById('puntuajesModal');
            
            if (event.target === animeModal) {
                cerrarModalAgregarAnime();
            }
            
            if (event.target === puntuajesModal) {
                cerrarModalPuntuajes();
            }
        });

        // Cerrar modales con la tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const animeModal = document.getElementById('animeModal');
                const puntuajesModal = document.getElementById('puntuajesModal');
                
                if (animeModal.style.display === 'block') {
                    cerrarModalAgregarAnime();
                }
                
                if (puntuajesModal.style.display === 'block') {
                    cerrarModalPuntuajes();
                }
            }
        });

        // Funciones para el modal de puntuajes
        function abrirModalPuntuajes() {
            const modal = document.getElementById('puntuajesModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function cerrarModalPuntuajes() {
            const modal = document.getElementById('puntuajesModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Función para abrir el modal de valoraciones del Hub (reutilizada)
        function abrirModalValoracionesHub(animeId, animeTitle) {
            // Esta función se conectará con el modal existente del Hub
            // Por ahora, redirigimos al Hub con el anime específico
            window.location.href = `hub.php?anime=${animeId}`;
        }

        // Funciones para búsqueda rápida
        function ejecutarBusquedaRapida() {
            const searchInput = document.getElementById('quickSearchInput');
            const searchTerm = searchInput.value.trim();
            
            if (searchTerm) {
                // Almacenar el término de búsqueda en localStorage para que el Hub lo recoja
                localStorage.setItem('hubSearchTerm', searchTerm);
                window.location.href = 'hub.php';
            } else {
                // Si está vacío, solo ir al Hub
                localStorage.removeItem('hubSearchTerm');
                window.location.href = 'hub.php';
            }
        }

        function handleQuickSearchEnter(event) {
            if (event.key === 'Enter') {
                ejecutarBusquedaRapida();
            }
        }

        // Event listeners para las preview cards y formulario
        document.addEventListener('DOMContentLoaded', function() {
            const previewCards = document.querySelectorAll('.preview-card');
            previewCards.forEach(card => {
                card.addEventListener('click', function() {
                    const animeId = this.dataset.animeId;
                    abrirModalPuntuajes();
                });
            });

            // Manejar envío del formulario
            const animeForm = document.getElementById('animeForm');
            if (animeForm) {
                animeForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Agregando...';
            
                try {
                const formData = new FormData(this);
                const response = await fetch('../backend/api/procesar_anime.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });                const result = await response.json();
                
                if (result.success) {
                    // Mostrar mensaje de éxito
                    const mensaje = document.createElement('div');
                    mensaje.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(0, 255, 0, 0.2); border: 2px solid #00ff00; border-radius: 10px; padding: 15px 25px; color: #00ff00; z-index: 10000; font-weight: bold;';
                    mensaje.innerHTML = '✅ ' + result.message;
                    document.body.appendChild(mensaje);
                    
                    setTimeout(() => {
                        mensaje.remove();
                    }, 3000);
                    
                    // Cerrar modal y recargar página para actualizar estadísticas
                    cerrarModalAgregarAnime();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                    
                } else {
                    // Mostrar mensaje de error
                    const mensaje = document.createElement('div');
                    mensaje.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(255, 71, 87, 0.2); border: 2px solid #ff4757; border-radius: 10px; padding: 15px 25px; color: #ff4757; z-index: 10000; font-weight: bold;';
                    mensaje.innerHTML = '❌ ' + result.message;
                    document.body.appendChild(mensaje);
                    
                    setTimeout(() => {
                        mensaje.remove();
                    }, 5000);
                }
                
            } catch (error) {
                console.error('Error:', error);
                const mensaje = document.createElement('div');
                mensaje.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(255, 71, 87, 0.2); border: 2px solid #ff4757; border-radius: 10px; padding: 15px 25px; color: #ff4757; z-index: 10000; font-weight: bold;';
                mensaje.innerHTML = '❌ Error al procesar la solicitud';
                document.body.appendChild(mensaje);
                
                setTimeout(() => {
                    mensaje.remove();
                }, 5000);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
                });
            }
        });
    </script>

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

    <!-- Modal para agregar nuevo anime -->
    <div id="animeModal" class="anime-modal">
        <div class="anime-modal-content">
            <div class="anime-modal-header">
                <h2 class="anime-modal-title">➕ Agregar Nuevo Anime</h2>
                <button class="anime-modal-close" onclick="cerrarModalAgregarAnime()">&times;</button>
            </div>
            <div class="anime-modal-body">
                <form id="animeForm" action="../backend/api/procesar_anime.php" method="POST" enctype="multipart/form-data">
                    <!-- Información básica -->
                    <h4 class="modal-form-section-title">📝 Información Básica</h4>
                    
                    <div class="modal-form-group">
                        <label for="nombre" class="modal-form-label">📝 Nombre del Anime (Español)</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Ej: Ataque a los Titanes" class="modal-form-input">
                    </div>
                    
                    <div class="modal-form-grid-2">
                        <div>
                            <label for="titulo_original" class="modal-form-label">🏮 Título Original (Japonés)</label>
                            <input type="text" id="titulo_original" name="titulo_original" placeholder="Ej: 進撃の巨人" class="modal-form-input">
                            <small class="modal-form-help">Opcional: Título en idioma original</small>
                        </div>
                        
                        <div>
                            <label for="titulo_ingles" class="modal-form-label">🇺🇸 Título en Inglés</label>
                            <input type="text" id="titulo_ingles" name="titulo_ingles" placeholder="Ej: Attack on Titan" class="modal-form-input">
                            <small class="modal-form-help">Opcional: Título oficial en inglés</small>
                        </div>
                    </div>
                    
                    <!-- Detalles del anime -->
                    <h4 class="modal-details-section-title">🎬 Detalles del Anime</h4>
                    
                    <div class="modal-tracking-grid">
                        <div>
                            <label for="tipo" class="modal-form-label">🎬 Tipo de Anime</label>
                            <select id="tipo" name="tipo" required>
                                <option value="TV">📺 Serie TV</option>
                                <option value="OVA">💽 OVA</option>
                                <option value="Película">🎬 Película</option>
                                <option value="Especial">⭐ Especial</option>
                                <option value="ONA">🌐 ONA (Web)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="estado_anime" class="modal-form-label">📊 Estado del Anime</label>
                            <select id="estado_anime" name="estado_anime" required>
                                <option value="Finalizado">✅ Finalizado</option>
                                <option value="Emitiendo">📡 Emitiendo</option>
                                <option value="Próximamente">🔜 Próximamente</option>
                                <option value="Cancelado">❌ Cancelado</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="total_episodios" class="modal-form-label">📊 Total de Episodios</label>
                            <input type="number" id="total_episodios" name="total_episodios" min="1" placeholder="Ej: 25" class="modal-form-input">
                            <small class="modal-form-help">Deja vacío si no se conoce</small>
                        </div>
                    </div>
                    
                    <!-- Mi seguimiento -->
                    <h4 class="modal-tracking-section-title">🎯 Mi Seguimiento</h4>
                    
                    <div class="modal-tracking-grid">
                        <div>
                            <label for="capitulos_vistos" class="modal-form-label">👁️ Episodios Vistos</label>
                            <input type="number" id="capitulos_vistos" name="capitulos_vistos" min="0" value="0" placeholder="Ej: 12" class="modal-form-input">
                        </div>
                        
                        <div>
                            <label for="estado" class="modal-form-label">🎯 Mi Estado</label>
                            <select id="estado" name="estado" required>
                                <option value="Plan de Ver">⏳ Plan de Ver</option>
                                <option value="Viendo">👀 Viendo</option>
                                <option value="Completado">✅ Completado</option>
                                <option value="En Pausa">⏸️ En Pausa</option>
                                <option value="Abandonado">❌ Abandonado</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="puntuacion" class="modal-form-label">⭐ Mi Puntuación</label>
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
                            <small class="modal-form-help">Opcional: Califica del 1 al 10</small>
                        </div>
                    </div>
                    
                    <!-- URL AnimeFLV -->
                    <div class="modal-form-group">
                        <label for="animeflv_url_name" class="modal-form-label">🌐 Nombre URL AnimeFLV (Opcional)</label>
                        <input type="text" id="animeflv_url_name" name="animeflv_url_name" 
                               placeholder="Ej: attack-on-titan" class="modal-form-input"
                               pattern="[a-z0-9\-]+" title="Solo letras minúsculas, números y guiones">
                        <small class="modal-form-help">Para seguimiento automático de episodios en AnimeFLV</small>
                    </div>
                    
                    <!-- Imagen -->
                    <h4 class="modal-section-title">🖼️ Imagen del Anime</h4>
                    
                    <div class="modal-image-grid">
                        <div>
                            <label for="imagen_url" class="modal-form-label">🌐 URL de imagen (Recomendado)</label>
                            <input type="url" id="imagen_url" name="imagen_url" placeholder="https://example.com/imagen.jpg" class="modal-form-input">
                            <small class="modal-form-help">Más rápido y ahorra espacio</small>
                        </div>
                        
                        <div>
                            <label for="imagen" class="modal-form-label">📎 Subir desde dispositivo</label>
                            <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/jpg,image/png,image/x-icon" class="modal-form-input">
                            <small class="modal-form-help">JPG, PNG, ICO (máx. 1MB)</small>
                        </div>
                    </div>
                    
                    <div class="modal-buttons-container">
                        <button type="submit" class="modal-submit-btn">✅ Agregar Anime</button>
                        <button type="button" onclick="cerrarModalAgregarAnime()" class="modal-cancel-btn">❌ Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Puntuajes Completo -->
    <div id="puntuajesModal" class="puntuajes-modal">
        <div class="puntuajes-modal-content">
            <div class="puntuajes-modal-header">
                <h2 class="puntuajes-modal-title">
                    🏆 Ranking de Animes por Puntuación
                </h2>
                <button class="puntuajes-modal-close" onclick="cerrarModalPuntuajes()">&times;</button>
            </div>
            <div class="puntuajes-modal-body">
                <?php if (!empty($todos_animes_puntuados)): ?>
                    <div class="puntuajes-grid">
                        <?php foreach ($todos_animes_puntuados as $index => $anime): ?>
                            <div class="puntuaje-card">
                                <div class="puntuaje-card-header">
                                    <img src="<?= htmlspecialchars($anime['imagen_portada'] ?: '../img/default-anime.jpg') ?>" 
                                         alt="<?= htmlspecialchars($anime['titulo']) ?>" 
                                         class="puntuaje-anime-image">
                                    <div class="puntuaje-anime-info">
                                        <div class="puntuaje-anime-title">
                                            #<?= $index + 1 ?> <?= htmlspecialchars($anime['titulo']) ?>
                                        </div>
                                        <?php if (!empty($anime['titulo_original'])): ?>
                                            <div class="puntuaje-anime-type">
                                                🏮 <?= htmlspecialchars($anime['titulo_original']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="puntuaje-anime-type">
                                            <?= htmlspecialchars($anime['tipo']) ?>
                                            <?php if ($anime['episodios_total']): ?>
                                                • <?= $anime['episodios_total'] ?> episodios
                                            <?php endif; ?>
                                        </div>
                                        <div class="puntuaje-stats">
                                            <div class="puntuaje-media">⭐ <?= $anime['media_puntuacion'] ?>/10</div>
                                            <div class="puntuaje-votos"><?= $anime['total_puntuaciones'] ?> puntuaciones</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ranking-usuarios">
                                    <div class="ranking-title">🏅 Top Usuarios que lo Puntuaron:</div>
                                    <div class="ranking-list">
                                        <?php 
                                        $usuarios = explode(',', $anime['usuarios_puntuaciones']);
                                        $mostrados = 0;
                                        foreach ($usuarios as $usuario_data): 
                                            if ($mostrados >= 5) break;
                                            $partes = explode(':', $usuario_data);
                                            if (count($partes) >= 3):
                                                $nombre = $partes[0];
                                                $puntuacion = $partes[1];
                                                $username = $partes[2];
                                                $mostrados++;
                                        ?>
                                            <div class="ranking-item">
                                                <span class="ranking-usuario"><?= htmlspecialchars($nombre) ?> (@<?= htmlspecialchars($username) ?>)</span>
                                                <span class="ranking-puntuacion"><?= $puntuacion ?>/10</span>
                                            </div>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                                
                                <button class="btn-ver-detalle" onclick="abrirModalValoracionesHub(<?= $anime['id'] ?>, '<?= htmlspecialchars($anime['titulo']) ?>')">
                                    👁️ Ver Todas las Valoraciones
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🎯</div>
                        <h3 class="empty-state-title">Sin Puntuaciones Aún</h3>
                        <p>La comunidad aún no ha puntuado ningún anime.</p>
                        <p>¡Sé el primero en agregar y puntuar animes!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Funciones para el menú hamburguesa
        window.toggleMobileMenu = function() {
            const hamburger = document.querySelector('.hamburger');
            const navMenu = document.getElementById('navMenu');
            const navOverlay = document.getElementById('navOverlay');
            
            hamburger.classList.toggle('active');
            
            if (!navMenu.classList.contains('mobile')) {
                navMenu.classList.add('mobile');
            }
            
            navMenu.classList.toggle('active');
            navOverlay.classList.toggle('active');
            
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : 'auto';
        }
        
        window.closeMobileMenu = function() {
            const hamburger = document.querySelector('.hamburger');
            const navMenu = document.getElementById('navMenu');
            const navOverlay = document.getElementById('navOverlay');
            
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            navOverlay.classList.remove('active');
            
            if (window.innerWidth > 768) {
                navMenu.classList.remove('mobile');
            }
            
            document.body.style.overflow = 'auto';
        }
        
        // Event listeners para el menú hamburguesa
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar menú móvil al hacer clic en un enlace
            document.querySelectorAll('.nav ul li a').forEach(link => {
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