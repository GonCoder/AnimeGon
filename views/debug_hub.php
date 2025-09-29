<?php
session_start();
require_once '../backend/config/config.php';
require_once '../backend/config/funciones.php';

echo "<h2>🔍 Diagnóstico del Hub - AnimeGon</h2>";

// Mostrar configuración de conexión
echo "<h3>🌐 Configuración de Conexión:</h3>";
echo "<p><strong>Host:</strong> " . DB_HOST . "</p>";
echo "<p><strong>Base de datos:</strong> " . DB_NAME . "</p>";
echo "<p><strong>Usuario:</strong> " . DB_USER . "</p>";

try {
    $conexion = obtenerConexion();
    echo "<p style='color: green;'><strong>✅ Conexión exitosa</strong></p>";
    
    echo "<h3>📊 Estado de las tablas:</h3>";
    
    // Listar todas las tablas
    $query = "SHOW TABLES";
    $stmt = $conexion->prepare($query);
    $stmt->execute();
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p><strong>📋 Tablas disponibles:</strong> " . implode(", ", $tablas) . "</p>";
    
    // Verificar tabla animes
    if (in_array('animes', $tablas)) {
        $query = "SELECT COUNT(*) as total FROM animes";
        $stmt = $conexion->prepare($query);
        $stmt->execute();
        $total_animes = $stmt->fetchColumn();
        echo "<p><strong>📺 Total animes en tabla 'animes':</strong> $total_animes</p>";
        
        if ($total_animes > 0) {
            echo "<h4>📋 Primeros 5 animes en la tabla:</h4>";
            $query = "SELECT id, titulo, tipo, estado FROM animes LIMIT 5";
            $stmt = $conexion->prepare($query);
            $stmt->execute();
            $animes_muestra = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Título</th><th>Tipo</th><th>Estado</th></tr>";
            foreach ($animes_muestra as $anime) {
                echo "<tr>";
                echo "<td>{$anime['id']}</td>";
                echo "<td>{$anime['titulo']}</td>";
                echo "<td>{$anime['tipo']}</td>";
                echo "<td>{$anime['estado']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'><strong>⚠️ La tabla 'animes' está vacía</strong></p>";
            echo "<h4>🛠️ Solución sugerida:</h4>";
            echo "<p>Necesitas cargar los datos de ejemplo. Ejecuta el script database.sql en tu base de datos remota.</p>";
        }
    } else {
        echo "<p style='color: red;'><strong>❌ La tabla 'animes' no existe</strong></p>";
    }
    
    // Verificar tabla lista_usuario
    if (in_array('lista_usuario', $tablas)) {
        $query = "SELECT COUNT(*) as total FROM lista_usuario";
        $stmt = $conexion->prepare($query);
        $stmt->execute();
        $total_lista = $stmt->fetchColumn();
        echo "<p><strong>📝 Total registros en 'lista_usuario':</strong> $total_lista</p>";
    }
    
    // Verificar usuarios
    if (in_array('usuarios', $tablas)) {
        $query = "SELECT COUNT(*) as total FROM usuarios";
        $stmt = $conexion->prepare($query);
        $stmt->execute();
        $total_usuarios = $stmt->fetchColumn();
        echo "<p><strong>👥 Total usuarios:</strong> $total_usuarios</p>";
    }
    
    // Si hay usuario logueado, probar la consulta del hub
    if (isset($_SESSION['usuario_id'])) {
        $usuario_id = $_SESSION['usuario_id'];
        echo "<h3>🧪 Prueba de consulta del Hub para usuario ID: $usuario_id</h3>";
        
        // Consulta simple para debug
        $query_debug = "SELECT a.*, 'Sistema' as subido_por, 0 as usuarios_que_lo_tienen, 0 as puntuacion_promedio
                        FROM animes a
                        ORDER BY a.titulo ASC
                        LIMIT 10";
        
        $stmt = $conexion->prepare($query_debug);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>🎯 Total animes para mostrar:</strong> " . count($resultados) . "</p>";
        
        if (count($resultados) > 0) {
            echo "<h4>📋 Animes que deberían aparecer en el hub:</h4>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Título</th><th>Tipo</th><th>Estado</th></tr>";
            foreach ($resultados as $anime) {
                echo "<tr>";
                echo "<td>{$anime['id']}</td>";
                echo "<td>{$anime['titulo']}</td>";
                echo "<td>{$anime['tipo']}</td>";
                echo "<td>{$anime['estado']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Verificar qué animes ya tiene el usuario
        $query_usuario = "SELECT COUNT(*) as total FROM lista_usuario WHERE usuario_id = ?";
        $stmt = $conexion->prepare($query_usuario);
        $stmt->execute([$usuario_id]);
        $animes_usuario = $stmt->fetchColumn();
        echo "<p><strong>📚 Animes que ya tienes en tu lista:</strong> $animes_usuario</p>";
        
    } else {
        echo "<p><em>⚠️ No hay usuario logueado para probar la consulta del hub</em></p>";
        echo "<p><a href='login.php'>🔐 Ir a Login</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error de conexión:</strong> " . $e->getMessage() . "</p>";
    echo "<h4>🛠️ Posibles soluciones:</h4>";
    echo "<ul>";
    echo "<li>Verificar que la base de datos remota esté funcionando</li>";
    echo "<li>Comprobar las credenciales de conexión</li>";
    echo "<li>Verificar que las tablas existan en la base de datos</li>";
    echo "</ul>";
}

echo "<br><br>";
echo "<a href='hub.php' style='background: #00ff00; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔙 Volver al Hub</a>";
echo "<a href='dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Ir al Dashboard</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: white; }
table { background: #2a2a2a; }
th, td { padding: 8px; text-align: left; border: 1px solid #555; }
th { background: #333; }
a { color: #00ff00; text-decoration: none; padding: 10px; display: inline-block; margin: 5px; }
a:hover { text-decoration: underline; }
</style>