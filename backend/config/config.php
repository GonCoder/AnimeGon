<?php
// config.php - Configuración de la base de datos

// Configuración de la base de datos para Alwaysdata
define('DB_HOST', 'mysql-animegon.alwaysdata.net');
define('DB_NAME', 'animegon_db');
define('DB_USER', 'animegon');
define('DB_PASS', 'Wazsecret12');
define('DB_PORT', '3306');

// Función para obtener conexión a la base de datos
function obtenerConexion() {
    try {
        // Configuración optimizada para hosting remoto
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false, // Mejor para hosting compartido
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_TIMEOUT => 30 // Timeout de 30 segundos para conexiones remotas
        ];
        
        $conexion = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        
        return $conexion;
        
    } catch (PDOException $e) {
        // Error más detallado para debugging
        $error_msg = "Error de conexión a Alwaysdata: " . $e->getMessage();
        error_log($error_msg); // Log del error
        
        // Mostrar error amigable
        die("
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ff6b6b; border-radius: 8px; background: #fff5f5; color: #721c24;'>
            <h2 style='color: #d63384;'>🔴 Error de Conexión</h2>
            <p><strong>No se pudo conectar a la base de datos de Alwaysdata.</strong></p>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <h3>💡 Posibles causas:</h3>
            <ul>
                <li>Credenciales incorrectas</li>
                <li>Base de datos no creada en Alwaysdata</li>
                <li>Problemas de conectividad de red</li>
                <li>Configuración de firewall</li>
            </ul>
            <p><strong>Host:</strong> " . DB_HOST . "</p>
            <p><strong>Base de datos:</strong> " . DB_NAME . "</p>
            <p><strong>Usuario:</strong> " . DB_USER . "</p>
        </div>
        ");
    }
}

// Función para verificar la conexión
function verificarConexion() {
    try {
        $conexion = obtenerConexion();
        
        // Verificar que podemos hacer consultas
        $stmt = $conexion->query("SELECT 1 as test");
        $resultado = $stmt->fetch();
        
        if ($resultado['test'] == 1) {
            return [
                'estado' => 'exitoso',
                'mensaje' => 'Conexión exitosa a Alwaysdata',
                'servidor_info' => $conexion->getAttribute(PDO::ATTR_SERVER_INFO)
            ];
        }
        
    } catch (Exception $e) {
        return [
            'estado' => 'error',
            'mensaje' => $e->getMessage()
        ];
    }
}

// Función para verificar si las tablas existen
function verificarTablas() {
    try {
        $conexion = obtenerConexion();
        $stmt = $conexion->query("SHOW TABLES");
        $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $tablas_necesarias = ['usuarios', 'animes', 'generos', 'lista_usuario'];
        $tablas_faltantes = array_diff($tablas_necesarias, $tablas);
        
        return [
            'tablas_existentes' => $tablas,
            'tablas_faltantes' => $tablas_faltantes,
            'todas_creadas' => empty($tablas_faltantes)
        ];
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

?>