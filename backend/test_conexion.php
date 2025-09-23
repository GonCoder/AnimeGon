<?php
// test_conexion.php - Archivo para probar la conexión a Alwaysdata

require_once 'config/config.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimeGon - Test de Conexión Alwaysdata</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #2a2a2a 100%);
            color: white;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(30, 30, 30, 0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.3);
            border: 2px solid rgba(0, 255, 255, 0.3);
        }
        h1 {
            color: #00ffff;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        .test-section {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            border-left: 4px solid #00ffff;
        }
        .success {
            border-left-color: #00ff88;
            background: rgba(0, 255, 136, 0.1);
        }
        .error {
            border-left-color: #ff007f;
            background: rgba(255, 0, 127, 0.1);
        }
        .warning {
            border-left-color: #ffd700;
            background: rgba(255, 215, 0, 0.1);
        }
        .info {
            background: rgba(102, 126, 234, 0.1);
            border-left-color: #667eea;
        }
        code {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 8px;
            border-radius: 4px;
            color: #00ffff;
        }
        .btn {
            background: linear-gradient(135deg, #ff007f, #bf00ff);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255, 0, 127, 0.6);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        th {
            background: rgba(0, 255, 255, 0.1);
            color: #00ffff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌐 AnimeGon - Test Conexión Alwaysdata</h1>
        
        <div class="test-section info">
            <h3>📋 Configuración Alwaysdata</h3>
            <table>
                <tr><th>Parámetro</th><th>Valor</th></tr>
                <tr><td>Host</td><td><code><?= DB_HOST ?></code></td></tr>
                <tr><td>Puerto</td><td><code><?= DB_PORT ?></code></td></tr>
                <tr><td>Base de datos</td><td><code><?= DB_NAME ?></code></td></tr>
                <tr><td>Usuario</td><td><code><?= DB_USER ?></code></td></tr>
                <tr><td>Contraseña</td><td><code><?= str_repeat('*', strlen(DB_PASS)) ?></code></td></tr>
            </table>
        </div>

        <div class="test-section">
            <h3>🔌 Test de Conexión</h3>
            <?php
            $resultado_conexion = verificarConexion();
            
            if ($resultado_conexion['estado'] === 'exitoso') {
                echo "<div class='success'>";
                echo "<h4>✅ ¡Conexión exitosa a Alwaysdata!</h4>";
                echo "<p>" . htmlspecialchars($resultado_conexion['mensaje']) . "</p>";
                echo "<p><strong>Info del servidor:</strong> <code>" . htmlspecialchars($resultado_conexion['servidor_info']) . "</code></p>";
                echo "</div>";
                
                // Verificar tablas
                echo "<h4>📊 Verificación de Tablas</h4>";
                $verificacion_tablas = verificarTablas();
                
                if (isset($verificacion_tablas['error'])) {
                    echo "<div class='error'>";
                    echo "<p><strong>Error al verificar tablas:</strong> " . htmlspecialchars($verificacion_tablas['error']) . "</p>";
                    echo "</div>";
                } else {
                    if ($verificacion_tablas['todas_creadas']) {
                        echo "<div class='success'>";
                        echo "<p>✅ <strong>Todas las tablas necesarias están creadas.</strong></p>";
                    } else {
                        echo "<div class='warning'>";
                        echo "<p>⚠️ <strong>Faltan algunas tablas.</strong></p>";
                        echo "<p><strong>Tablas faltantes:</strong> " . implode(', ', $verificacion_tablas['tablas_faltantes']) . "</p>";
                    }
                    
                    if (!empty($verificacion_tablas['tablas_existentes'])) {
                        echo "<p><strong>Tablas existentes:</strong></p>";
                        echo "<ul>";
                        foreach ($verificacion_tablas['tablas_existentes'] as $tabla) {
                            echo "<li><code>" . htmlspecialchars($tabla) . "</code></li>";
                        }
                        echo "</ul>";
                    }
                    echo "</div>";
                }
                
            } else {
                echo "<div class='error'>";
                echo "<h4>❌ Error de Conexión</h4>";
                echo "<p><strong>Error:</strong> " . htmlspecialchars($resultado_conexion['mensaje']) . "</p>";
                echo "</div>";
            }
            ?>
        </div>

        <div class="test-section info">
            <h3>� Pasos siguientes</h3>
            <?php if ($resultado_conexion['estado'] === 'exitoso'): ?>
                <ol>
                    <li>✅ <strong>Conexión establecida</strong> - Tu configuración es correcta</li>
                    <?php if (isset($verificacion_tablas['todas_creadas']) && $verificacion_tablas['todas_creadas']): ?>
                        <li>✅ <strong>Base de datos completa</strong> - Todas las tablas están creadas</li>
                        <li>🎯 <strong>¡Listo para usar!</strong> - Tu aplicación AnimeGon está lista</li>
                    <?php else: ?>
                        <li>📋 <strong>Crear tablas</strong> - Ejecuta el script database.sql en phpMyAdmin de Alwaysdata</li>
                        <li>🔄 <strong>Verificar nuevamente</strong> - Recarga esta página después de crear las tablas</li>
                    <?php endif; ?>
                </ol>
            <?php else: ?>
                <ol>
                    <li>🔍 <strong>Verificar credenciales</strong> - Revisa usuario y contraseña en Alwaysdata</li>
                    <li>🗄️ <strong>Crear base de datos</strong> - Asegúrate de que 'animegon_db' existe</li>
                    <li>🌐 <strong>Verificar conectividad</strong> - Comprueba que el host sea accesible</li>
                    <li>📞 <strong>Contactar soporte</strong> - Si persiste, contacta a Alwaysdata</li>
                </ol>
            <?php endif; ?>
        </div>

        <div class="test-section info">
            <h3>📚 Enlaces útiles de Alwaysdata</h3>
            <ul>
                <li><a href="https://admin.alwaysdata.com" target="_blank" style="color: #00ffff;">Panel de administración Alwaysdata</a></li>
                <li><a href="https://help.alwaysdata.com/en/databases/mysql/" target="_blank" style="color: #00ffff;">Documentación MySQL Alwaysdata</a></li>
                <li><strong>phpMyAdmin:</strong> Accesible desde tu panel de Alwaysdata</li>
            </ul>
        </div>

        <?php if ($resultado_conexion['estado'] === 'exitoso' && isset($verificacion_tablas['todas_creadas']) && !$verificacion_tablas['todas_creadas']): ?>
        <div class="test-section warning">
            <h3>⚠️ Script SQL para ejecutar</h3>
            <p>Copia este enlace y ejecuta el script completo en phpMyAdmin de Alwaysdata:</p>
            <p><strong>Archivo:</strong> <code>database.sql</code></p>
            <p>O ejecuta tabla por tabla empezando con la tabla usuarios:</p>
            <pre style="background: rgba(0,0,0,0.5); padding: 15px; border-radius: 8px; overflow-x: auto;"><code>CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    activo BOOLEAN DEFAULT TRUE
);</code></pre>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn">🏠 Volver al Inicio</a>
            <button onclick="location.reload()" class="btn">🔄 Recargar Test</button>
        </div>
    </div>
</body>
</html>