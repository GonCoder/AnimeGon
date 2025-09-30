<?php
/**
 * Script de limpieza: Eliminar el campo animeflv_url_name de lista_usuario
 * Solo ejecutar después de verificar que todo funciona correctamente
 */

require_once '../config/config.php';

try {
    $conexion = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🧹 Limpieza final de migración animeflv_url_name</h2>";
    
    // Verificar que el campo existe en ambas tablas
    echo "<p>Verificando estructura actual...</p>";
    
    $sql_check_animes = "SHOW COLUMNS FROM animes LIKE 'animeflv_url_name'";
    $stmt_check_animes = $conexion->prepare($sql_check_animes);
    $stmt_check_animes->execute();
    $field_animes = $stmt_check_animes->fetch();
    
    $sql_check_lista = "SHOW COLUMNS FROM lista_usuario LIKE 'animeflv_url_name'";
    $stmt_check_lista = $conexion->prepare($sql_check_lista);
    $stmt_check_lista->execute();
    $field_lista = $stmt_check_lista->fetch();
    
    if (!$field_animes) {
        echo "❌ <strong>Error:</strong> El campo animeflv_url_name no existe en la tabla animes<br>";
        exit;
    }
    
    if (!$field_lista) {
        echo "ℹ️ El campo animeflv_url_name ya fue eliminado de lista_usuario<br>";
        exit;
    }
    
    echo "✅ Campo existe en tabla animes<br>";
    echo "✅ Campo existe en tabla lista_usuario<br>";
    
    // Verificar que los datos están correctamente migrados
    echo "<p>Verificando migración de datos...</p>";
    
    $sql_count_animes = "SELECT COUNT(*) FROM animes WHERE animeflv_url_name IS NOT NULL AND animeflv_url_name != ''";
    $stmt_count_animes = $conexion->prepare($sql_count_animes);
    $stmt_count_animes->execute();
    $count_animes = $stmt_count_animes->fetchColumn();
    
    $sql_count_lista = "SELECT COUNT(DISTINCT anime_id) FROM lista_usuario WHERE animeflv_url_name IS NOT NULL AND animeflv_url_name != ''";
    $stmt_count_lista = $conexion->prepare($sql_count_lista);
    $stmt_count_lista->execute();
    $count_lista = $stmt_count_lista->fetchColumn();
    
    echo "• Animes con URL en tabla animes: $count_animes<br>";
    echo "• Animes únicos con URL en lista_usuario: $count_lista<br>";
    
    if ($count_animes < $count_lista) {
        echo "⚠️ <strong>Advertencia:</strong> Parece que algunos datos no se migraron correctamente. Revisar antes de continuar.<br>";
        echo "<p><strong>Sugerencia:</strong> Ejecutar primero el script migrar_animeflv_url.php</p>";
        exit;
    }
    
    // Mostrar algunos ejemplos de datos migrados
    echo "<p>Ejemplos de datos en tabla animes:</p>";
    $sql_ejemplos = "SELECT id, titulo, animeflv_url_name FROM animes WHERE animeflv_url_name IS NOT NULL LIMIT 5";
    $stmt_ejemplos = $conexion->prepare($sql_ejemplos);
    $stmt_ejemplos->execute();
    $ejemplos = $stmt_ejemplos->fetchAll(PDO::FETCH_ASSOC);
    
    if ($ejemplos) {
        echo "<ul>";
        foreach ($ejemplos as $ejemplo) {
            echo "<li>{$ejemplo['titulo']} → {$ejemplo['animeflv_url_name']}</li>";
        }
        echo "</ul>";
    }
    
    // Confirmar antes de eliminar
    echo "<p><strong>⚠️ ATENCIÓN:</strong> Vas a eliminar el campo animeflv_url_name de la tabla lista_usuario.</p>";
    echo "<p>Esta acción NO se puede deshacer. Asegúrate de que:</p>";
    echo "<ul>";
    echo "<li>✅ Las exportaciones funcionan correctamente (usando datos de tabla animes)</li>";
    echo "<li>✅ Las importaciones funcionan correctamente (guardando en tabla animes)</li>";
    echo "<li>✅ La edición de animes actualiza la tabla animes</li>";
    echo "<li>✅ La vista de mis_animes muestra los datos correctamente</li>";
    echo "</ul>";
    
    echo '<form method="post" style="margin: 20px 0;">';
    echo '<input type="hidden" name="confirmar_limpieza" value="1">';
    echo '<button type="submit" style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">';
    echo '🗑️ CONFIRMAR ELIMINACIÓN DEL CAMPO';
    echo '</button>';
    echo '</form>';
    
    // Procesar la eliminación si se confirma
    if (isset($_POST['confirmar_limpieza']) && $_POST['confirmar_limpieza'] == '1') {
        echo "<hr>";
        echo "<h3>🗑️ Eliminando campo animeflv_url_name de lista_usuario...</h3>";
        
        // Eliminar índice primero
        try {
            $sql_drop_index = "ALTER TABLE lista_usuario DROP INDEX idx_animeflv_url";
            $conexion->exec($sql_drop_index);
            echo "✅ Índice eliminado<br>";
        } catch (Exception $e) {
            echo "ℹ️ Índice no existía o ya fue eliminado<br>";
        }
        
        // Eliminar campo
        $sql_drop_field = "ALTER TABLE lista_usuario DROP COLUMN animeflv_url_name";
        $conexion->exec($sql_drop_field);
        echo "✅ Campo animeflv_url_name eliminado de tabla lista_usuario<br>";
        
        echo "<h3>🎉 Limpieza completada exitosamente</h3>";
        echo "<p>El campo animeflv_url_name ahora existe únicamente en la tabla animes y es compartido por todos los usuarios.</p>";
        
        // Verificar estructura final
        $sql_final_check = "SHOW COLUMNS FROM lista_usuario LIKE 'animeflv_url_name'";
        $stmt_final_check = $conexion->prepare($sql_final_check);
        $stmt_final_check->execute();
        $field_final = $stmt_final_check->fetch();
        
        if (!$field_final) {
            echo "✅ Verificación: Campo eliminado correctamente de lista_usuario<br>";
        } else {
            echo "❌ Error: El campo aún existe en lista_usuario<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p><strong>❌ Error de base de datos:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>