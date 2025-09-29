<?php
// dashboard.php - Página principal después del login

require_once '../backend/config/funciones.php';

// Requiere que el usuario esté logueado
requiereSesion();

// Obtener datos del usuario actual
$usuario = obtenerUsuarioActual();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Dashboard</title>
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
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
                        <li><a href="#perfil">Mi Perfil</a></li>
                    </ul>
                </nav>
                <div class="user-menu">
                    <span class="user-name">¡Hola, <?= escape($usuario['nombre']) ?>!</span>
                    <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <section class="welcome-section">
                <div class="welcome-card">
                    <h2>¡Bienvenido de vuelta, <?= escape($usuario['nombre']) ?>!</h2>
                    <p>Username: <strong><?= escape($usuario['username']) ?></strong></p>
                    <p>Email: <strong><?= escape($usuario['email']) ?></strong></p>
                </div>
            </section>

            <section class="dashboard-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📺</div>
                        <div class="stat-content">
                            <h3>Animes Vistos</h3>
                            <p class="stat-number">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-content">
                            <h3>Favoritos</h3>
                            <p class="stat-number">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📋</div>
                        <div class="stat-content">
                            <h3>En Lista</h3>
                            <p class="stat-number">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⏰</div>
                        <div class="stat-content">
                            <h3>Horas Vistas</h3>
                            <p class="stat-number">0h</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="quick-actions">
                <h3>Acciones Rápidas</h3>
                <div class="actions-grid">
                    <div class="action-card">
                        <h4>🔍 Buscar Animes</h4>
                        <p>Descubre nuevos animes para añadir a tu lista</p>
                        <button class="btn-action" onclick="alert('Función en desarrollo')">Buscar</button>
                    </div>
                    
                    <div class="action-card">
                        <h4>📝 Añadir a Lista</h4>
                        <p>Agrega animes que quieres ver próximamente</p>
                        <button class="btn-action" onclick="alert('Función en desarrollo')">Añadir</button>
                    </div>
                    
                    <div class="action-card">
                        <h4>📊 Ver Estadísticas</h4>
                        <p>Revisa tu progreso y estadísticas detalladas</p>
                        <button class="btn-action" onclick="alert('Función en desarrollo')">Ver Stats</button>
                    </div>
                </div>
            </section>

            <section class="recent-activity">
                <h3>Actividad Reciente</h3>
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon">🎉</div>
                        <div class="activity-content">
                            <p><strong>¡Te has registrado en AnimeGon!</strong></p>
                            <small>Bienvenido a nuestra comunidad de anime</small>
                        </div>
                    </div>
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
        });

        // Confirmar logout
        document.querySelector('.btn-logout').addEventListener('click', function(e) {
            e.preventDefault();
            
            const modal = document.getElementById('confirmLogoutModal');
            const confirmBtn = document.getElementById('confirmLogoutBtn');
            const cancelBtn = document.getElementById('cancelLogoutBtn');
            
            // Mostrar el modal
            modal.style.display = 'flex';
            
            // Configurar los botones
            confirmBtn.onclick = () => {
                window.location.href = 'logout.php';
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
        });
    </script>

    <!-- Modal de confirmación para cerrar sesión -->
    <div id="confirmLogoutModal" class="confirm-modal logout-modal">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon">🚪</div>
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
                        🚪 Sí, cerrar sesión
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