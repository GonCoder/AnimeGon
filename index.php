<?php
// index.php - Página principal de AnimeGon

require_once 'backend/config/funciones.php';

// Redirigir al dashboard si ya está logueado
redirigirSiLogueado();

// Verificar si viene de logout
$mostrarMensajeLogout = isset($_GET['logout']) && $_GET['logout'] == '1';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Tu plataforma de anime favorita</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="apple-touch-icon" href="favicon.ico">
    
    <link rel="stylesheet" href="frontend/assets/css/style.css">
</head>
<body>
    <header class="hero-header">
        <div class="container">
            <nav class="nav">
                <div class="logo">
                    <h1 style="margin-right: 30px;">AnimeGon</h1>
                </div>
                <div class="nav-links">
                    <a href="views/login.php" class="btn-nav">Iniciar Sesión</a>
                    <a href="views/registro.php" class="btn-nav btn-primary">Registrarse</a>
                </div>
            </nav>
            
            <div class="hero-content">
                <?php if ($mostrarMensajeLogout): ?>
                    <div class="mensaje exito">
                        ¡Sesión cerrada exitosamente! Gracias por usar AnimeGon.
                    </div>
                <?php endif; ?>
                
                <h2 class="hero-title">Bienvenido a AnimeGon</h2>
                <p class="hero-subtitle">Tu plataforma definitiva para seguir y descubrir anime</p>
                
                <div class="hero-features">
                    <div class="feature">
                        <div class="feature-icon">📺</div>
                        <h3>Seguimiento Personal</h3>
                        <p>Lleva el control de todos los animes que has visto</p>
                    </div>
                    
                    <div class="feature">
                        <div class="feature-icon">⭐</div>
                        <h3>Lista de Favoritos</h3>
                        <p>Guarda tus animes favoritos y accede a ellos fácilmente</p>
                    </div>
                    
                    <div class="feature">
                        <div class="feature-icon">📊</div>
                        <h3>Estadísticas</h3>
                        <p>Ve tus estadísticas de visualización y progreso</p>
                    </div>
                </div>
                
                <div class="hero-actions">
                    <a href="views/registro.php" class="btn-hero btn-primary">Comenzar Gratis</a>
                    <a href="views/login.php" class="btn-hero btn-secondary">Ya tengo cuenta</a>
                </div>
            </div>
        </div>
    </header>

    <section class="benefits">
        <div class="container">
            <h2>¿Por qué elegir AnimeGon?</h2>
            
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">🔒</div>
                    <h3>Datos Seguros</h3>
                    <p>Tus datos están protegidos con las mejores prácticas de seguridad</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">📱</div>
                    <h3>Multiplataforma</h3>
                    <p>Accede desde cualquier dispositivo, en cualquier momento</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">🚀</div>
                    <h3>Rápido y Fácil</h3>
                    <p>Interfaz intuitiva y respuesta rápida para una mejor experiencia</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">🆓</div>
                    <h3>Completamente Gratis</h3>
                    <p>Todas las funciones disponibles sin costo alguno</p>
                </div>
            </div>
        </div>
    </section>

    <section class="getting-started">
        <div class="container">
            <h2>Comenzar es muy fácil</h2>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Regístrate</h3>
                    <p>Crea tu cuenta gratuita en menos de 2 minutos</p>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Añade Animes</h3>
                    <p>Comienza a agregar los animes que has visto o quieres ver</p>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Disfruta</h3>
                    <p>Lleva el control de tu progreso y descubre nuevos animes</p>
                </div>
            </div>
            
            <div class="cta-section">
                <h3>¿Listo para comenzar tu aventura anime?</h3>
                <a href="views/registro.php" class="btn-cta">Crear mi cuenta gratis</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>AnimeGon</h4>
                    <p>Tu plataforma de seguimiento de anime favorita</p>
                </div>
                
                <div class="footer-section">
                    <h4>Enlaces</h4>
                    <ul>
                        <li><a href="views/login.php">Iniciar Sesión</a></li>
                        <li><a href="views/registro.php">Registrarse</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Soporte</h4>
                    <ul>
                        <li><a href="https://chatgpt.com/">Ayuda</a></li>
                        <li><a href="#WIP">Contacto</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 AnimeGon. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            // Fade in de elementos
            const elements = document.querySelectorAll('.feature, .benefit-card, .step');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });

            elements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'all 0.6s ease';
                observer.observe(el);
            });

            // Ocultar mensaje de logout después de 5 segundos
            const mensajeLogout = document.querySelector('.mensaje.exito');
            if (mensajeLogout) {
                setTimeout(() => {
                    mensajeLogout.style.opacity = '0';
                    setTimeout(() => {
                        mensajeLogout.remove();
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>