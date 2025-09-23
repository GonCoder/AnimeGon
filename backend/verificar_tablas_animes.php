<?php
// verificar_tablas_animes.php - Script para verificar y crear tablas necesarias

require_once 'config/config.php';

$tablas_necesarias = [
    'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ultimo_acceso TIMESTAMP NULL,
        activo BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB",
    
    'animes' => "CREATE TABLE IF NOT EXISTS animes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        titulo_original VARCHAR(255) DEFAULT NULL,
        titulo_ingles VARCHAR(255) DEFAULT NULL,
        episodios_total INT DEFAULT NULL,
        imagen_portada VARCHAR(255),
        tipo ENUM('TV', 'OVA', 'Película', 'Especial', 'ONA') DEFAULT 'TV',
        estado ENUM('Emitiendo', 'Finalizado', 'Próximamente', 'Cancelado') DEFAULT 'Finalizado',
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    'lista_usuario' => "CREATE TABLE IF NOT EXISTS lista_usuario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        anime_id INT NOT NULL,
        estado ENUM('Viendo', 'Completado', 'En Pausa', 'Abandonado', 'Plan de Ver') NOT NULL,
        episodios_vistos INT DEFAULT 0,
        puntuacion DECIMAL(3,1) DEFAULT NULL CHECK (puntuacion >= 0 AND puntuacion <= 10),
        fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_usuario_anime (usuario_id, anime_id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (anime_id) REFERENCES animes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    'favoritos' => "CREATE TABLE IF NOT EXISTS favoritos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        anime_id INT NOT NULL,
        fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_favorito (usuario_id, anime_id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (anime_id) REFERENCES animes(id) ON DELETE CASCADE,
        INDEX idx_usuario_fecha (usuario_id, fecha_agregado)
    ) ENGINE=InnoDB"
];

try {
    $conexion = obtenerConexion();
    
    echo "<h2>🔧 Verificación y Creación de Tablas</h2>";
    
    foreach ($tablas_necesarias as $nombre_tabla => $sql_crear) {
        echo "<p><strong>Verificando tabla: $nombre_tabla</strong></p>";
        
        // Verificar si la tabla existe usando INFORMATION_SCHEMA
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$nombre_tabla]);
        $existe = $stmt->fetchColumn() > 0;
        
        if ($existe) {
            echo "<span style='color: green;'>✅ La tabla '$nombre_tabla' ya existe.</span><br>";
            
            // Si es la tabla animes, verificar si tiene los nuevos campos
            if ($nombre_tabla === 'animes') {
                echo "<p><strong>Verificando campos adicionales en tabla animes...</strong></p>";
                
                // Verificar si existen los campos titulo_original y titulo_ingles
                $campos_adicionales = ['titulo_original', 'titulo_ingles'];
                foreach ($campos_adicionales as $campo) {
                    $stmt_campo = $conexion->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'animes' AND COLUMN_NAME = ?");
                    $stmt_campo->execute([$campo]);
                    $campo_existe = $stmt_campo->fetchColumn() > 0;
                    
                    if (!$campo_existe) {
                        echo "<span style='color: orange;'>⚠️ Agregando campo '$campo' a la tabla animes...</span><br>";
                        try {
                            $conexion->exec("ALTER TABLE animes ADD COLUMN $campo VARCHAR(255) DEFAULT NULL");
                            echo "<span style='color: green;'>✅ Campo '$campo' agregado exitosamente.</span><br>";
                        } catch (Exception $e) {
                            echo "<span style='color: red;'>❌ Error al agregar campo '$campo': " . $e->getMessage() . "</span><br>";
                        }
                    } else {
                        echo "<span style='color: green;'>✅ Campo '$campo' ya existe.</span><br>";
                    }
                }
            }
        } else {
            echo "<span style='color: orange;'>⚠️ La tabla '$nombre_tabla' no existe. Creando...</span><br>";
            
            try {
                $conexion->exec($sql_crear);
                echo "<span style='color: green;'>✅ Tabla '$nombre_tabla' creada exitosamente.</span><br>";
            } catch (Exception $e) {
                echo "<span style='color: red;'>❌ Error al crear tabla '$nombre_tabla': " . $e->getMessage() . "</span><br>";
            }
        }
        echo "<br>";
    }
    
    echo "<p><strong>🎯 Verificación completada. Ahora puedes usar la funcionalidad de Mis Animes.</strong></p>";
    echo "<p><a href='../views/mis_animes.php' style='color: #00ffff;'>📺 Ir a Mis Animes</a></p>";
    
} catch (Exception $e) {
    echo "<span style='color: red;'>❌ Error de conexión: " . $e->getMessage() . "</span>";
}
?>