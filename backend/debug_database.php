<?php
// debug_database.php - Script para verificar el estado de la base de datos

require_once 'config/config.php';

try {
    $conexion = obtenerConexion();
    echo "<h2>🔍 Verificación de Base de Datos</h2>";
    
    // Verificar si las tablas existen
    $tablas = ['usuarios', 'animes', 'lista_usuario'];
    
    foreach ($tablas as $tabla) {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$tabla]);
        $existe = $stmt->fetchColumn() > 0;
        
        if ($existe) {
            echo "<p>✅ Tabla '$tabla' existe</p>";
            
            // Contar registros
            $stmt_count = $conexion->prepare("SELECT COUNT(*) FROM $tabla");
            $stmt_count->execute();
            $count = $stmt_count->fetchColumn();
            echo "<p>📊 Registros en '$tabla': $count</p>";
            
            // Si es lista_usuario, verificar campos
            if ($tabla === 'lista_usuario') {
                echo "<p><strong>Estructura de la tabla lista_usuario:</strong></p>";
                $stmt_desc = $conexion->prepare("DESCRIBE lista_usuario");
                $stmt_desc->execute();
                $campos = $stmt_desc->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($campos as $campo) {
                    echo "<p>- {$campo['Field']} ({$campo['Type']})</p>";
                }
                
                // Verificar campo favorito específicamente
                $stmt_fav = $conexion->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_usuario' AND COLUMN_NAME = 'favorito'");
                $stmt_fav->execute();
                $tiene_favorito = $stmt_fav->fetchColumn() > 0;
                
                if ($tiene_favorito) {
                    echo "<p>✅ Campo 'favorito' existe en lista_usuario</p>";
                } else {
                    echo "<p>❌ Campo 'favorito' NO existe en lista_usuario</p>";
                }
            }
            
            // Si es animes, verificar nuevos campos
            if ($tabla === 'animes') {
                echo "<p><strong>Verificando nuevos campos en animes:</strong></p>";
                $campos_nuevos = ['titulo_original', 'titulo_ingles'];
                foreach ($campos_nuevos as $campo_nuevo) {
                    $stmt_campo = $conexion->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'animes' AND COLUMN_NAME = ?");
                    $stmt_campo->execute([$campo_nuevo]);
                    $tiene_campo = $stmt_campo->fetchColumn() > 0;
                    
                    if ($tiene_campo) {
                        echo "<p>✅ Campo '$campo_nuevo' existe en animes</p>";
                    } else {
                        echo "<p>❌ Campo '$campo_nuevo' NO existe en animes</p>";
                    }
                }
            }
        } else {
            echo "<p>❌ Tabla '$tabla' NO existe</p>";
        }
        echo "<hr>";
    }
    
    // Verificar usuario actual de sesión si está disponible
    session_start();
    if (isset($_SESSION['usuario_id'])) {
        $usuario_id = $_SESSION['usuario_id'];
        echo "<p><strong>Usuario actual en sesión: $usuario_id</strong></p>";
        
        // Verificar animes del usuario
        $stmt_user_animes = $conexion->prepare("SELECT COUNT(*) FROM lista_usuario WHERE usuario_id = ?");
        $stmt_user_animes->execute([$usuario_id]);
        $user_animes_count = $stmt_user_animes->fetchColumn();
        echo "<p>📺 Animes del usuario $usuario_id: $user_animes_count</p>";
        
        if ($user_animes_count > 0) {
            echo "<p><strong>Últimos animes del usuario:</strong></p>";
            $stmt_recent = $conexion->prepare("SELECT lu.*, a.titulo FROM lista_usuario lu LEFT JOIN animes a ON lu.anime_id = a.id WHERE lu.usuario_id = ? ORDER BY lu.fecha_agregado DESC LIMIT 3");
            $stmt_recent->execute([$usuario_id]);
            $recent_animes = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($recent_animes as $anime) {
                echo "<p>- {$anime['titulo']} (Estado: {$anime['estado']}, Episodios: {$anime['episodios_vistos']})</p>";
            }
        }
    } else {
        echo "<p>⚠️ No hay usuario en sesión</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>