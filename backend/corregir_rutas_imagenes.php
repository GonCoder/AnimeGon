<?php
// corregir_rutas_imagenes.php - Script para corregir rutas de imágenes en la base de datos

require_once 'config/config.php';
require_once 'config/funciones.php';

echo "<h2>🔧 Corrección de Rutas de Imágenes</h2>";

try {
    $conexion = obtenerConexion();
    
    // Buscar animes con rutas incorrectas (que contengan "../")
    $query_buscar = "SELECT id, titulo, imagen_portada FROM animes WHERE imagen_portada LIKE '%../%'";
    $stmt_buscar = $conexion->prepare($query_buscar);
    $stmt_buscar->execute();
    $animes_incorrectos = $stmt_buscar->fetchAll();
    
    if (empty($animes_incorrectos)) {
        echo "<p>✅ No se encontraron rutas de imágenes incorrectas.</p>";
    } else {
        echo "<p>📋 Se encontraron " . count($animes_incorrectos) . " animes con rutas incorrectas:</p>";
        echo "<ul>";
        
        foreach ($animes_incorrectos as $anime) {
            $ruta_antigua = $anime['imagen_portada'];
            
            // Extraer solo el nombre del archivo de la ruta
            $nombre_archivo = basename($ruta_antigua);
            $ruta_nueva = 'uploads/animes/' . $nombre_archivo;
            
            echo "<li>";
            echo "<strong>" . htmlspecialchars($anime['titulo']) . "</strong><br>";
            echo "Ruta antigua: " . htmlspecialchars($ruta_antigua) . "<br>";
            echo "Ruta nueva: " . htmlspecialchars($ruta_nueva) . "<br>";
            
            // Verificar si el archivo existe
            if (file_exists($ruta_nueva)) {
                // Actualizar la ruta en la base de datos
                $query_actualizar = "UPDATE animes SET imagen_portada = ? WHERE id = ?";
                $stmt_actualizar = $conexion->prepare($query_actualizar);
                
                if ($stmt_actualizar->execute([$ruta_nueva, $anime['id']])) {
                    echo "<span style='color: green;'>✅ Ruta corregida</span>";
                } else {
                    echo "<span style='color: red;'>❌ Error al actualizar la base de datos</span>";
                }
            } else {
                echo "<span style='color: orange;'>⚠️ Archivo no existe en la nueva ruta</span>";
            }
            
            echo "</li><br>";
        }
        
        echo "</ul>";
    }
    
    // Mostrar todos los animes con sus rutas actuales
    echo "<h3>📊 Estado actual de las imágenes:</h3>";
    $query_todos = "SELECT id, titulo, imagen_portada FROM animes ORDER BY id";
    $stmt_todos = $conexion->prepare($query_todos);
    $stmt_todos->execute();
    $todos_animes = $stmt_todos->fetchAll();
    
    if (empty($todos_animes)) {
        echo "<p>No hay animes en la base de datos.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<thead>";
        echo "<tr><th>ID</th><th>Título</th><th>Ruta Imagen</th><th>Estado</th></tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($todos_animes as $anime) {
            echo "<tr>";
            echo "<td>" . $anime['id'] . "</td>";
            echo "<td>" . htmlspecialchars($anime['titulo']) . "</td>";
            echo "<td>" . htmlspecialchars($anime['imagen_portada'] ?: 'Sin imagen') . "</td>";
            
            if (empty($anime['imagen_portada'])) {
                echo "<td style='color: gray;'>Sin imagen</td>";
            } elseif (file_exists($anime['imagen_portada'])) {
                echo "<td style='color: green;'>✅ Existe</td>";
            } else {
                echo "<td style='color: red;'>❌ No existe</td>";
            }
            
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='../views/mis_animes.php'>← Volver a Mis Animes</a>";
?>