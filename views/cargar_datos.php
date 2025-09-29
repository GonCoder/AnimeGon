<?php
session_start();
require_once '../backend/config/config.php';
require_once '../backend/config/funciones.php';

// Verificar si el usuario está logueado y es admin (opcional)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

echo "<h2>🛠️ Cargar Datos de Ejemplo - AnimeGon Hub</h2>";

try {
    $conexion = obtenerConexion();
    
    // Verificar si ya hay animes
    $query = "SELECT COUNT(*) as total FROM animes";
    $stmt = $conexion->prepare($query);
    $stmt->execute();
    $total_animes = $stmt->fetchColumn();
    
    if ($total_animes > 0) {
        echo "<p style='color: orange;'>⚠️ Ya hay $total_animes animes en la base de datos.</p>";
        echo "<p><a href='hub.php'>🔙 Volver al Hub</a></p>";
        exit();
    }
    
    echo "<p>📥 Insertando animes de ejemplo...</p>";
    
    // Animes de ejemplo para el hub
    $animes_ejemplo = [
        [
            'titulo' => 'Demon Slayer',
            'titulo_original' => 'Kimetsu no Yaiba',
            'titulo_ingles' => 'Demon Slayer: Kimetsu no Yaiba',
            'sinopsis' => 'La historia de Tanjiro Kamado, un joven que se convierte en cazador de demonios para salvar a su hermana.',
            'tipo' => 'TV',
            'estado' => 'Finalizado',
            'episodios_total' => 26,
            'duracion_episodio' => 24,
            'año' => 2019,
            'clasificacion' => 'PG-13',
            'imagen_portada' => 'img/demon_slayer.jpg'
        ],
        [
            'titulo' => 'Attack on Titan',
            'titulo_original' => 'Shingeki no Kyojin',
            'titulo_ingles' => 'Attack on Titan',
            'sinopsis' => 'La humanidad lucha por sobrevivir contra gigantes devoradores de humanos.',
            'tipo' => 'TV',
            'estado' => 'Finalizado',
            'episodios_total' => 87,
            'duracion_episodio' => 24,
            'año' => 2013,
            'clasificacion' => 'R',
            'imagen_portada' => 'img/attack_on_titan.jpg'
        ],
        [
            'titulo' => 'Your Name',
            'titulo_original' => 'Kimi no Na wa',
            'titulo_ingles' => 'Your Name',
            'sinopsis' => 'Dos adolescentes intercambian cuerpos misteriosamente.',
            'tipo' => 'Película',
            'estado' => 'Finalizado',
            'episodios_total' => 1,
            'duracion_episodio' => 106,
            'año' => 2016,
            'clasificacion' => 'PG',
            'imagen_portada' => 'img/your_name.jpg'
        ],
        [
            'titulo' => 'Spirited Away',
            'titulo_original' => 'Sen to Chihiro no Kamikakushi',
            'titulo_ingles' => 'Spirited Away',
            'sinopsis' => 'Una niña debe trabajar en un mundo espiritual para salvar a sus padres.',
            'tipo' => 'Película',
            'estado' => 'Finalizado',
            'episodios_total' => 1,
            'duracion_episodio' => 125,
            'año' => 2001,
            'clasificacion' => 'G',
            'imagen_portada' => 'img/spirited_away.jpg'
        ],
        [
            'titulo' => 'One Piece',
            'titulo_original' => 'One Piece',
            'titulo_ingles' => 'One Piece',
            'sinopsis' => 'Las aventuras de Monkey D. Luffy en busca del tesoro más grande del mundo.',
            'tipo' => 'TV',
            'estado' => 'Emitiendo',
            'episodios_total' => null,
            'duracion_episodio' => 24,
            'año' => 1999,
            'clasificacion' => 'PG-13',
            'imagen_portada' => 'img/one_piece.jpg'
        ],
        [
            'titulo' => 'Naruto',
            'titulo_original' => 'Naruto',
            'titulo_ingles' => 'Naruto',
            'sinopsis' => 'Un joven ninja busca reconocimiento y sueña con convertirse en el Hokage.',
            'tipo' => 'TV',
            'estado' => 'Finalizado',
            'episodios_total' => 720,
            'duracion_episodio' => 24,
            'año' => 2002,
            'clasificacion' => 'PG-13',
            'imagen_portada' => 'img/naruto.jpg'
        ],
        [
            'titulo' => 'Death Note',
            'titulo_original' => 'Death Note',
            'titulo_ingles' => 'Death Note',
            'sinopsis' => 'Un estudiante encuentra un cuaderno que puede matar a cualquier persona.',
            'tipo' => 'TV',
            'estado' => 'Finalizado',
            'episodios_total' => 37,
            'duracion_episodio' => 24,
            'año' => 2006,
            'clasificacion' => 'R',
            'imagen_portada' => 'img/death_note.jpg'
        ],
        [
            'titulo' => 'My Hero Academia',
            'titulo_original' => 'Boku no Hero Academia',
            'titulo_ingles' => 'My Hero Academia',
            'sinopsis' => 'En un mundo donde la mayoría tiene superpoderes, un chico sin poderes sueña con ser héroe.',
            'tipo' => 'TV',
            'estado' => 'Emitiendo',
            'episodios_total' => null,
            'duracion_episodio' => 24,
            'año' => 2016,
            'clasificacion' => 'PG-13',
            'imagen_portada' => 'img/my_hero_academia.jpg'
        ]
    ];
    
    $conexion->beginTransaction();
    
    $query = "INSERT INTO animes (titulo, titulo_original, titulo_ingles, sinopsis, tipo, estado, episodios_total, duracion_episodio, año, clasificacion, imagen_portada, fecha_creacion) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conexion->prepare($query);
    
    $insertados = 0;
    foreach ($animes_ejemplo as $anime) {
        try {
            $stmt->execute([
                $anime['titulo'],
                $anime['titulo_original'],
                $anime['titulo_ingles'],
                $anime['sinopsis'],
                $anime['tipo'],
                $anime['estado'],
                $anime['episodios_total'],
                $anime['duracion_episodio'],
                $anime['año'],
                $anime['clasificacion'],
                $anime['imagen_portada']
            ]);
            $insertados++;
            echo "<p style='color: green;'>✅ Insertado: {$anime['titulo']}</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error al insertar {$anime['titulo']}: " . $e->getMessage() . "</p>";
        }
    }
    
    $conexion->commit();
    echo "<h3 style='color: green;'>🎉 Proceso completado!</h3>";
    echo "<p>Se insertaron <strong>$insertados</strong> animes de ejemplo.</p>";
    
} catch (Exception $e) {
    if (isset($conexion)) {
        $conexion->rollback();
    }
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . $e->getMessage() . "</p>";
}

echo "<br><br>";
echo "<a href='hub.php' style='background: #00ff00; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🌐 Ir al Hub</a>";
echo "<a href='debug_hub.php' style='background: #ffc107; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔍 Ver Diagnóstico</a>";
echo "<a href='dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Dashboard</a>";
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    background: linear-gradient(135deg, #0a0a0a, #1a2e1a, #16213e); 
    color: white; 
    min-height: 100vh;
}
a { 
    text-decoration: none; 
    display: inline-block; 
    margin: 5px; 
}
a:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
}
</style>