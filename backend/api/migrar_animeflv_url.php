<?php
/**
 * Script de migración: Mover animeflv_url_name de lista_usuario a animes
 * Esto hace que el campo sea global para todos los usuarios
 */

require_once '../config/config.php';

try {
    $conexion = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔄 Iniciando migración de animeflv_url_name</h2>";
    
    // Paso 1: Agregar campo a la tabla animes si no existe
    echo "<p>Paso 1: Agregando campo animeflv_url_name a tabla animes...</p>";
    $sql_add_field = "ALTER TABLE animes 
                      ADD COLUMN IF NOT EXISTS animeflv_url_name VARCHAR(255) DEFAULT NULL";
    $conexion->exec($sql_add_field);
    echo "✅ Campo agregado a tabla animes<br>";
    
    // Paso 2: Crear índice para el nuevo campo
    echo "<p>Paso 2: Creando índice para animeflv_url_name...</p>";
    $sql_add_index = "ALTER TABLE animes 
                      ADD INDEX IF NOT EXISTS idx_animeflv_url_name (animeflv_url_name)";
    $conexion->exec($sql_add_index);
    echo "✅ Índice creado<br>";
    
    // Paso 3: Migrar datos existentes
    echo "<p>Paso 3: Migrando datos existentes...</p>";
    
    // Obtener todos los animeflv_url_name únicos de lista_usuario
    $sql_obtener_urls = "SELECT DISTINCT anime_id, animeflv_url_name 
                         FROM lista_usuario 
                         WHERE animeflv_url_name IS NOT NULL AND animeflv_url_name != ''";
    $stmt = $conexion->prepare($sql_obtener_urls);
    $stmt->execute();
    $urls_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $migrados = 0;
    $conflictos = 0;
    
    foreach ($urls_existentes as $url_data) {
        $anime_id = $url_data['anime_id'];
        $animeflv_url = $url_data['animeflv_url_name'];
        
        // Verificar si el anime ya tiene una URL diferente
        $sql_verificar = "SELECT animeflv_url_name FROM animes WHERE id = ?";
        $stmt_verificar = $conexion->prepare($sql_verificar);
        $stmt_verificar->execute([$anime_id]);
        $url_actual = $stmt_verificar->fetchColumn();
        
        if ($url_actual && $url_actual != $animeflv_url) {
            echo "⚠️ Conflicto en anime ID $anime_id: URL actual '$url_actual' vs nueva '$animeflv_url'<br>";
            $conflictos++;
            continue;
        }
        
        // Actualizar la URL en la tabla animes
        if (!$url_actual) {
            $sql_actualizar = "UPDATE animes SET animeflv_url_name = ? WHERE id = ?";
            $stmt_actualizar = $conexion->prepare($sql_actualizar);
            $stmt_actualizar->execute([$animeflv_url, $anime_id]);
            $migrados++;
            echo "✅ Migrado anime ID $anime_id: '$animeflv_url'<br>";
        }
    }
    
    echo "<p><strong>Resumen de migración:</strong></p>";
    echo "• URLs migradas: $migrados<br>";
    echo "• Conflictos detectados: $conflictos<br>";
    
    // Paso 4: Verificar la migración
    echo "<p>Paso 4: Verificando migración...</p>";
    $sql_contar_animes = "SELECT COUNT(*) FROM animes WHERE animeflv_url_name IS NOT NULL";
    $stmt_contar = $conexion->prepare($sql_contar_animes);
    $stmt_contar->execute();
    $total_animes_con_url = $stmt_contar->fetchColumn();
    
    $sql_contar_lista = "SELECT COUNT(DISTINCT anime_id) FROM lista_usuario WHERE animeflv_url_name IS NOT NULL";
    $stmt_contar_lista = $conexion->prepare($sql_contar_lista);
    $stmt_contar_lista->execute();
    $total_lista_con_url = $stmt_contar_lista->fetchColumn();
    
    echo "• Animes con URL en tabla animes: $total_animes_con_url<br>";
    echo "• Animes con URL en lista_usuario: $total_lista_con_url<br>";
    
    if ($total_animes_con_url >= $total_lista_con_url) {
        echo "✅ <strong>Migración completada exitosamente</strong><br>";
        
        // Mostrar algunos ejemplos
        echo "<p>Ejemplos de datos migrados:</p>";
        $sql_ejemplos = "SELECT id, titulo, animeflv_url_name FROM animes WHERE animeflv_url_name IS NOT NULL LIMIT 5";
        $stmt_ejemplos = $conexion->prepare($sql_ejemplos);
        $stmt_ejemplos->execute();
        $ejemplos = $stmt_ejemplos->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<ul>";
        foreach ($ejemplos as $ejemplo) {
            echo "<li>ID {$ejemplo['id']}: {$ejemplo['titulo']} → {$ejemplo['animeflv_url_name']}</li>";
        }
        echo "</ul>";
        
        echo "<p><strong>⚠️ Importante:</strong> Después de verificar que todo funciona correctamente, podrás eliminar el campo animeflv_url_name de la tabla lista_usuario.</p>";
        
    } else {
        echo "❌ <strong>Error en la migración</strong>. Revisar datos manualmente.<br>";
    }
    
} catch (PDOException $e) {
    echo "<p><strong>❌ Error de base de datos:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>