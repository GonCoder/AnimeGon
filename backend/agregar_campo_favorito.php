<?php
// agregar_campo_favorito.php - Script para agregar el campo favorito a la tabla lista_usuario

require_once 'config/config.php';

try {
    $conexion = obtenerConexion();
    
    echo "<h2>🔧 Agregando campo 'favorito' a la tabla lista_usuario</h2>";
    
    // Verificar si el campo favorito ya existe
    $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_usuario' AND COLUMN_NAME = 'favorito'");
    $stmt_check->execute();
    $tiene_favorito = $stmt_check->fetchColumn() > 0;
    
    if ($tiene_favorito) {
        echo "<p style='color: green;'>✅ El campo 'favorito' ya existe en la tabla lista_usuario.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ El campo 'favorito' no existe. Agregando...</p>";
        
        // Agregar el campo favorito
        $sql_add_favorito = "ALTER TABLE lista_usuario ADD COLUMN favorito BOOLEAN DEFAULT FALSE";
        $conexion->exec($sql_add_favorito);
        
        echo "<p style='color: green;'>✅ Campo 'favorito' agregado exitosamente a la tabla lista_usuario.</p>";
    }
    
    // Verificar si los campos titulo_original y titulo_ingles existen en animes
    echo "<h3>🔧 Verificando campos adicionales en tabla animes</h3>";
    
    $campos_animes = ['titulo_original', 'titulo_ingles'];
    foreach ($campos_animes as $campo) {
        $stmt_check_anime = $conexion->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'animes' AND COLUMN_NAME = ?");
        $stmt_check_anime->execute([$campo]);
        $tiene_campo = $stmt_check_anime->fetchColumn() > 0;
        
        if ($tiene_campo) {
            echo "<p style='color: green;'>✅ El campo '$campo' ya existe en la tabla animes.</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ El campo '$campo' no existe. Agregando...</p>";
            
            $sql_add_campo = "ALTER TABLE animes ADD COLUMN $campo VARCHAR(255) DEFAULT NULL";
            $conexion->exec($sql_add_campo);
            
            echo "<p style='color: green;'>✅ Campo '$campo' agregado exitosamente a la tabla animes.</p>";
        }
    }
    
    echo "<h3>🎯 ¡Actualización completada!</h3>";
    echo "<p><a href='../views/mis_animes.php' style='color: #00ffff;'>📺 Ir a Mis Animes</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>