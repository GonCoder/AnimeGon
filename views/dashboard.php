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
        
        // Contar favoritos (tabla separada favoritos)
        $stmt_favoritos = $conexion->prepare("SELECT COUNT(*) FROM favoritos WHERE usuario_id = :usuario_id");
        $stmt_favoritos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_favoritos->execute();
        $favoritos = $stmt_favoritos->fetchColumn();
        
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
            'favoritos' => $favoritos,
            'total_lista' => $total_lista,
            'horas_vistas' => $horas_vistas
        ];
        
    } catch (Exception $e) {
        error_log("Error al obtener estadísticas: " . $e->getMessage());
        return [
            'animes_vistos' => 0,
            'favoritos' => 0,
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Dashboard</title>
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
        /* Estilos para el modal de confirmación */
        .confirm-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }

        .confirm-modal-content {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border: 2px solid #00ff00;
            border-radius: 15px;
            padding: 0;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0, 255, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        .confirm-modal-header {
            background: linear-gradient(135deg, #00ff00 0%, #00cc00 100%);
            color: #1a1a2e;
            padding: 20px;
            border-radius: 13px 13px 0 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .confirm-modal-icon {
            font-size: 24px;
        }

        .confirm-modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        .confirm-modal-body {
            padding: 30px 20px;
            text-align: center;
        }

        .confirm-modal-message {
            color: #ffffff;
            font-size: 16px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .confirm-modal-submessage {
            color: #cccccc;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .confirm-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-confirm:hover {
            background: linear-gradient(135deg, #ff3742 0%, #ff2935 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 71, 87, 0.4);
        }

        .btn-cancel-confirm {
            background: transparent;
            color: #00ff00;
            border: 2px solid #00ff00;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-cancel-confirm:hover {
            background: #00ff00;
            color: #1a1a2e;
            transform: translateY(-2px);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.7) translateY(-50px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Mejoras específicas para el header del dashboard */
        .header-content {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex-wrap: nowrap !important;
            gap: 20px !important;
        }

        .logo {
            font-size: 1.8rem !important;
            margin: 0 !important;
            flex-shrink: 0 !important;
        }

        .nav {
            flex: 1 !important;
            display: flex !important;
            justify-content: center !important;
        }

        .nav ul {
            display: flex !important;
            gap: 15px !important;
            margin: 0 !important;
            padding: 0 !important;
            list-style: none !important;
            flex-wrap: nowrap !important;
        }

        .nav ul li {
            white-space: nowrap !important;
        }

        .nav ul li a {
            font-size: 0.9rem !important;
            padding: 8px 12px !important;
        }

        .user-menu {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            flex-shrink: 0 !important;
        }

        .user-name {
            font-size: 0.85rem !important;
            white-space: nowrap !important;
            max-width: 180px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        .btn-logout {
            font-size: 0.85rem !important;
            padding: 6px 12px !important;
            white-space: nowrap !important;
        }

        /* Responsive para header compacto */
        @media (max-width: 1200px) {
            .nav ul {
                gap: 10px !important;
            }
            
            .nav ul li a {
                font-size: 0.8rem !important;
                padding: 6px 8px !important;
            }
            
            .user-name {
                max-width: 150px !important;
            }
        }

        @media (max-width: 992px) {
            .header-content {
                gap: 15px !important;
            }
            
            .logo {
                font-size: 1.5rem !important;
            }
            
            .nav ul {
                gap: 8px !important;
            }
            
            .nav ul li a {
                font-size: 0.75rem !important;
                padding: 5px 6px !important;
            }
            
            .user-name {
                font-size: 0.8rem !important;
                max-width: 120px !important;
            }
            
            .btn-logout {
                font-size: 0.8rem !important;
                padding: 5px 8px !important;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-wrap: wrap !important;
                gap: 10px !important;
            }
            
            .nav {
                order: 3 !important;
                flex-basis: 100% !important;
                margin-top: 10px !important;
            }
            
            .user-menu {
                order: 2 !important;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="logo">AnimeGon</h1>
                <nav class="nav">
                    <ul>
                        <li><a href="dashboard.php" class="active">Inicio</a></li>
                        <li><a href="mis_animes.php">Mis Animes</a></li>
                        <li><a href="favoritos.php">Favoritos</a></li>
                        <li><a href="hub.php">🌐 Hub</a></li>
                        <li><a href="#perfil">Mi Perfil</a></li>
                    </ul>
                </nav>
                <div class="user-menu">
                    <span class="user-name">🟢 <?= escape($usuario['nombre']) ?></span>
                    <a href="logout.php" class="btn-logout" title="Cerrar sesión con confirmación">🔴Salir</a>
                </div>
            </div>
        </div>
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
                        <div class="stat-icon">⭐</div>
                        <div class="stat-content">
                            <h3>Favoritos</h3>
                            <p class="stat-number"><?= $estadisticas['favoritos'] ?></p>
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
                    </div>
                    
                    <div class="action-card">
                        <h4>📝 Gestionar Lista</h4>
                        <p>Administra tu lista personal de animes</p>
                        <button class="btn-action" onclick="console.log('Botón Lista clicked'); window.location.href='mis_animes.php'">Ver Lista</button>
                    </div>
                    
                    <div class="action-card">
                        <h4>⭐ Ver Favoritos</h4>
                        <p>Revisa tus animes favoritos</p>
                        <button class="btn-action" onclick="console.log('Botón Favoritos clicked'); window.location.href='favoritos.php'">Ver Favoritos</button>
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

</body>
</html>