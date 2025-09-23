-- ========================================
-- AnimeGon_db - Base de Datos Completa
-- Sistema de Seguimiento de Anime
-- ========================================

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS animegon_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE animegon_db;

-- ========================================
-- TABLA USUARIOS
-- ========================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100),
    fecha_nacimiento DATE,
    genero ENUM('Masculino', 'Femenino', 'Otro', 'Prefiero no decir') DEFAULT NULL,
    pais VARCHAR(100),
    bio TEXT,
    avatar VARCHAR(255) DEFAULT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    activo BOOLEAN DEFAULT TRUE,
    verificado BOOLEAN DEFAULT FALSE,
    rol ENUM('usuario', 'moderador', 'admin') DEFAULT 'usuario',
    configuracion_privacidad JSON,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_activo (activo),
    INDEX idx_fecha_registro (fecha_registro)
) ENGINE=InnoDB;

-- ========================================
-- TABLA GÉNEROS DE ANIME
-- ========================================
CREATE TABLE generos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT,
    color_hex VARCHAR(7) DEFAULT '#667eea',
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_activo (activo)
) ENGINE=InnoDB;

-- ========================================
-- TABLA ESTUDIOS DE ANIMACIÓN
-- ========================================
CREATE TABLE estudios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) UNIQUE NOT NULL,
    descripcion TEXT,
    fundacion YEAR,
    pais VARCHAR(100),
    sitio_web VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_pais (pais)
) ENGINE=InnoDB;

-- ========================================
-- TABLA ANIMES
-- ========================================
CREATE TABLE animes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    titulo_original VARCHAR(255),
    titulo_ingles VARCHAR(255),
    sinopsis TEXT,
    tipo ENUM('TV', 'OVA', 'Película', 'Especial', 'ONA') NOT NULL,
    estado ENUM('Emitiendo', 'Finalizado', 'Próximamente', 'Cancelado') NOT NULL,
    episodios_total INT DEFAULT NULL,
    episodios_emitidos INT DEFAULT 0,
    duracion_episodio INT, -- en minutos
    fecha_inicio DATE,
    fecha_fin DATE,
    temporada ENUM('Primavera', 'Verano', 'Otoño', 'Invierno'),
    año YEAR,
    clasificacion ENUM('G', 'PG', 'PG-13', 'R', 'R+') DEFAULT 'PG-13',
    puntuacion_promedio DECIMAL(3,2) DEFAULT 0.00,
    total_votos INT DEFAULT 0,
    popularidad INT DEFAULT 0,
    imagen_portada VARCHAR(255),
    trailer_url VARCHAR(255),
    sitio_oficial VARCHAR(255),
    estudio_id INT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE SET NULL,
    INDEX idx_titulo (titulo),
    INDEX idx_tipo (tipo),
    INDEX idx_estado (estado),
    INDEX idx_año (año),
    INDEX idx_puntuacion (puntuacion_promedio),
    INDEX idx_popularidad (popularidad),
    INDEX idx_estudio (estudio_id),
    FULLTEXT(titulo, titulo_original, titulo_ingles, sinopsis)
) ENGINE=InnoDB;

-- ========================================
-- TABLA RELACIÓN ANIME-GÉNEROS
-- ========================================
CREATE TABLE anime_generos (
    anime_id INT,
    genero_id INT,
    PRIMARY KEY (anime_id, genero_id),
    FOREIGN KEY (anime_id) REFERENCES animes(id) ON DELETE CASCADE,
    FOREIGN KEY (genero_id) REFERENCES generos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================
-- TABLA LISTA DE USUARIO (Mi Lista)
-- ========================================
CREATE TABLE lista_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    anime_id INT NOT NULL,
    estado ENUM('Viendo', 'Completado', 'En Pausa', 'Abandonado', 'Plan de Ver') NOT NULL,
    episodios_vistos INT DEFAULT 0,
    puntuacion TINYINT CHECK (puntuacion BETWEEN 1 AND 10),
    fecha_inicio DATE,
    fecha_finalizacion DATE,
    rewatching BOOLEAN DEFAULT FALSE,
    veces_visto INT DEFAULT 1,
    notas TEXT,
    privado BOOLEAN DEFAULT FALSE,
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_usuario_anime (usuario_id, anime_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (anime_id) REFERENCES animes(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_anime (anime_id),
    INDEX idx_estado (estado),
    INDEX idx_puntuacion (puntuacion),
    INDEX idx_fecha_agregado (fecha_agregado)
) ENGINE=InnoDB;

-- ========================================
-- TABLA FAVORITOS
-- ========================================
CREATE TABLE favoritos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    anime_id INT NOT NULL,
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_usuario_anime_fav (usuario_id, anime_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (anime_id) REFERENCES animes(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_anime (anime_id)
) ENGINE=InnoDB;

-- ========================================
-- TABLA RESEÑAS
-- ========================================
CREATE TABLE reseñas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    anime_id INT NOT NULL,
    titulo VARCHAR(255),
    contenido TEXT NOT NULL,
    puntuacion TINYINT CHECK (puntuacion BETWEEN 1 AND 10),
    contiene_spoilers BOOLEAN DEFAULT FALSE,
    util_positivo INT DEFAULT 0,
    util_negativo INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_usuario_anime_reseña (usuario_id, anime_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (anime_id) REFERENCES animes(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_anime (anime_id),
    INDEX idx_puntuacion (puntuacion),
    INDEX idx_fecha (fecha_creacion),
    FULLTEXT(titulo, contenido)
) ENGINE=InnoDB;

-- ========================================
-- TABLA COMENTARIOS EN RESEÑAS
-- ========================================
CREATE TABLE comentarios_reseña (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseña_id INT NOT NULL,
    usuario_id INT NOT NULL,
    contenido TEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reseña_id) REFERENCES reseñas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_reseña (reseña_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB;

-- ========================================
-- TABLA SEGUIMIENTO DE EPISODIOS
-- ========================================
CREATE TABLE episodios_vistos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    anime_id INT NOT NULL,
    episodio_numero INT NOT NULL,
    fecha_visto TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    puntuacion_episodio TINYINT CHECK (puntuacion_episodio BETWEEN 1 AND 10),
    comentario TEXT,
    UNIQUE KEY unique_usuario_anime_episodio (usuario_id, anime_id, episodio_numero),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (anime_id) REFERENCES animes(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_anime (anime_id),
    INDEX idx_fecha (fecha_visto)
) ENGINE=InnoDB;

-- ========================================
-- TABLA NOTIFICACIONES
-- ========================================
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('nuevo_episodio', 'anime_finalizado', 'recomendacion', 'sistema') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    anime_id INT DEFAULT NULL,
    leida BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (anime_id) REFERENCES animes(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_leida (leida),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB;

-- ========================================
-- TABLA CONFIGURACIÓN DE USUARIO
-- ========================================
CREATE TABLE configuracion_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    tema ENUM('oscuro', 'claro', 'auto') DEFAULT 'oscuro',
    idioma VARCHAR(10) DEFAULT 'es',
    notificaciones_email BOOLEAN DEFAULT TRUE,
    notificaciones_web BOOLEAN DEFAULT TRUE,
    lista_publica BOOLEAN DEFAULT TRUE,
    mostrar_puntuaciones BOOLEAN DEFAULT TRUE,
    formato_fecha ENUM('dd/mm/yyyy', 'mm/dd/yyyy', 'yyyy-mm-dd') DEFAULT 'dd/mm/yyyy',
    zona_horaria VARCHAR(50) DEFAULT 'Europe/Madrid',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================
-- INSERTAR DATOS INICIALES
-- ========================================

-- Géneros de anime
INSERT INTO generos (nombre, descripcion, color_hex) VALUES
('Acción', 'Animes con secuencias de combate y aventura', '#ff6b6b'),
('Aventura', 'Historias de exploración y descubrimiento', '#4ecdc4'),
('Comedia', 'Animes enfocados en el humor', '#45b7d1'),
('Drama', 'Historias emotivas y profundas', '#96ceb4'),
('Fantasía', 'Mundos mágicos y fantásticos', '#ffeaa7'),
('Romance', 'Historias de amor', '#fd79a8'),
('Ciencia Ficción', 'Futuros alternativos y tecnología', '#6c5ce7'),
('Slice of Life', 'Vida cotidiana realista', '#a29bfe'),
('Sobrenatural', 'Elementos paranormales y místicos', '#e17055'),
('Misterio', 'Historias de suspense e investigación', '#636e72'),
('Horror', 'Animes de terror y suspenso', '#2d3436'),
('Deportes', 'Competiciones deportivas', '#00b894'),
('Mecha', 'Robots gigantes y tecnología militar', '#fdcb6e'),
('Escolar', 'Ambientado en escuelas', '#e84393'),
('Militar', 'Conflictos bélicos y estrategia', '#636e72'),
('Música', 'Centrado en la música y el arte', '#fd79a8'),
('Psicológico', 'Exploración de la mente humana', '#74b9ff'),
('Thriller', 'Tensión y suspense constante', '#2d3436');

-- Estudios de animación famosos
INSERT INTO estudios (nombre, descripcion, fundacion, pais, sitio_web) VALUES
('Studio Ghibli', 'Famoso por películas como El Viaje de Chihiro', 1985, 'Japón', 'https://www.ghibli.jp/'),
('Madhouse', 'Conocido por One Punch Man, Death Note', 1972, 'Japón', 'https://www.madhouse.co.jp/'),
('Toei Animation', 'Dragon Ball, One Piece, Sailor Moon', 1948, 'Japón', 'https://www.toei-anim.co.jp/'),
('Studio Pierrot', 'Naruto, Bleach, Tokyo Ghoul', 1979, 'Japón', 'http://pierrot.jp/'),
('Wit Studio', 'Attack on Titan, Vinland Saga', 2012, 'Japón', 'https://wit-studio.com/'),
('MAPPA', 'Jujutsu Kaisen, Chainsaw Man', 2011, 'Japón', 'https://mappa.co.jp/'),
('Bones', 'My Hero Academia, Fullmetal Alchemist', 1998, 'Japón', 'http://www.bones.co.jp/'),
('A-1 Pictures', 'Sword Art Online, Your Name', 2005, 'Japón', 'https://www.a1p.jp/'),
('Studio Trigger', 'Kill la Kill, Little Witch Academia', 2011, 'Japón', 'https://trigger.co.jp/'),
('Kyoto Animation', 'Violet Evergarden, K-On!', 1981, 'Japón', 'http://www.kyotoanimation.co.jp/');

-- Animes de ejemplo
INSERT INTO animes (titulo, titulo_original, titulo_ingles, sinopsis, tipo, estado, episodios_total, duracion_episodio, fecha_inicio, año, clasificacion, estudio_id) VALUES
('Demon Slayer', 'Kimetsu no Yaiba', 'Demon Slayer: Kimetsu no Yaiba', 'La historia de Tanjiro Kamado, un joven que se convierte en cazador de demonios para salvar a su hermana.', 'TV', 'Finalizado', 26, 24, '2019-04-06', 2019, 'PG-13', 8),
('Attack on Titan', 'Shingeki no Kyojin', 'Attack on Titan', 'La humanidad lucha por sobrevivir contra gigantes devoradores de humanos.', 'TV', 'Finalizado', 87, 24, '2013-04-07', 2013, 'R', 5),
('Your Name', 'Kimi no Na wa', 'Your Name', 'Dos adolescentes intercambian cuerpos misteriosamente.', 'Película', 'Finalizado', 1, 106, '2016-08-26', 2016, 'PG', 9),
('Spirited Away', 'Sen to Chihiro no Kamikakushi', 'Spirited Away', 'Una niña debe trabajar en un mundo espiritual para salvar a sus padres.', 'Película', 'Finalizado', 1, 125, '2001-07-20', 2001, 'G', 1),
('One Piece', 'One Piece', 'One Piece', 'Las aventuras de Monkey D. Luffy en busca del tesoro más grande del mundo.', 'TV', 'Emitiendo', NULL, 24, '1999-10-20', 1999, 'PG-13', 3);

-- Configuración inicial para usuarios de ejemplo
INSERT INTO configuracion_usuario (usuario_id, tema, idioma, notificaciones_email, lista_publica) 
SELECT id, 'oscuro', 'es', TRUE, TRUE FROM usuarios;

-- ========================================
-- VISTAS ÚTILES
-- ========================================

-- Vista de estadísticas de usuario
CREATE VIEW vista_estadisticas_usuario AS
SELECT 
    u.id,
    u.username,
    u.nombre,
    COUNT(DISTINCT l.anime_id) as total_animes,
    COUNT(DISTINCT CASE WHEN l.estado = 'Completado' THEN l.anime_id END) as completados,
    COUNT(DISTINCT CASE WHEN l.estado = 'Viendo' THEN l.anime_id END) as viendo,
    COUNT(DISTINCT CASE WHEN l.estado = 'Plan de Ver' THEN l.anime_id END) as plan_ver,
    COUNT(DISTINCT f.anime_id) as favoritos,
    ROUND(AVG(CASE WHEN l.puntuacion > 0 THEN l.puntuacion END), 2) as puntuacion_promedio,
    SUM(CASE WHEN l.estado = 'Completado' AND a.episodios_total IS NOT NULL 
             THEN a.episodios_total * a.duracion_episodio 
             ELSE l.episodios_vistos * a.duracion_episodio END) / 60 as horas_vistas
FROM usuarios u
LEFT JOIN lista_usuario l ON u.id = l.usuario_id
LEFT JOIN animes a ON l.anime_id = a.id
LEFT JOIN favoritos f ON u.id = f.usuario_id
GROUP BY u.id, u.username, u.nombre;

-- Vista de animes populares
CREATE VIEW vista_animes_populares AS
SELECT 
    a.*,
    e.nombre as estudio_nombre,
    COUNT(DISTINCT l.usuario_id) as usuarios_siguiendo,
    COUNT(DISTINCT f.usuario_id) as usuarios_favoritos,
    GROUP_CONCAT(DISTINCT g.nombre SEPARATOR ', ') as generos
FROM animes a
LEFT JOIN estudios e ON a.estudio_id = e.id
LEFT JOIN lista_usuario l ON a.id = l.anime_id
LEFT JOIN favoritos f ON a.id = f.anime_id
LEFT JOIN anime_generos ag ON a.id = ag.anime_id
LEFT JOIN generos g ON ag.genero_id = g.id
WHERE a.activo = TRUE
GROUP BY a.id
ORDER BY usuarios_siguiendo DESC, a.puntuacion_promedio DESC;

-- ========================================
-- PROCEDIMIENTOS ALMACENADOS
-- ========================================

DELIMITER //

-- Procedimiento para actualizar estadísticas de anime
CREATE PROCEDURE ActualizarEstadisticasAnime(IN anime_id INT)
BEGIN
    DECLARE puntuacion_prom DECIMAL(3,2);
    DECLARE total_votos_count INT;
    
    SELECT AVG(puntuacion), COUNT(*)
    INTO puntuacion_prom, total_votos_count
    FROM lista_usuario 
    WHERE anime_id = anime_id AND puntuacion > 0;
    
    UPDATE animes 
    SET puntuacion_promedio = COALESCE(puntuacion_prom, 0),
        total_votos = total_votos_count
    WHERE id = anime_id;
END//

-- Procedimiento para agregar anime a lista
CREATE PROCEDURE AgregarAnimeALista(
    IN p_usuario_id INT,
    IN p_anime_id INT,
    IN p_estado VARCHAR(20),
    IN p_episodios_vistos INT,
    IN p_puntuacion TINYINT
)
BEGIN
    INSERT INTO lista_usuario (usuario_id, anime_id, estado, episodios_vistos, puntuacion)
    VALUES (p_usuario_id, p_anime_id, p_estado, p_episodios_vistos, p_puntuacion)
    ON DUPLICATE KEY UPDATE
        estado = p_estado,
        episodios_vistos = p_episodios_vistos,
        puntuacion = p_puntuacion,
        fecha_actualizacion = CURRENT_TIMESTAMP;
    
    CALL ActualizarEstadisticasAnime(p_anime_id);
END//

DELIMITER ;

-- ========================================
-- TRIGGERS
-- ========================================

DELIMITER //

-- Trigger para crear configuración de usuario automáticamente
CREATE TRIGGER crear_configuracion_usuario 
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO configuracion_usuario (usuario_id)
    VALUES (NEW.id);
END//

-- Trigger para actualizar estadísticas cuando se modifica puntuación
CREATE TRIGGER actualizar_estadisticas_anime
AFTER UPDATE ON lista_usuario
FOR EACH ROW
BEGIN
    IF OLD.puntuacion != NEW.puntuacion THEN
        CALL ActualizarEstadisticasAnime(NEW.anime_id);
    END IF;
END//

DELIMITER ;

-- ========================================
-- ÍNDICES ADICIONALES PARA RENDIMIENTO
-- ========================================

-- Índices compuestos para consultas frecuentes
CREATE INDEX idx_lista_usuario_estado_fecha ON lista_usuario(estado, fecha_actualizacion);
CREATE INDEX idx_animes_tipo_estado ON animes(tipo, estado);
CREATE INDEX idx_episodios_usuario_fecha ON episodios_vistos(usuario_id, fecha_visto);

-- ========================================
-- CONFIGURACIONES DE OPTIMIZACIÓN
-- ========================================

-- Configuraciones recomendadas para MySQL
-- SET GLOBAL innodb_buffer_pool_size = 1024M;
-- SET GLOBAL query_cache_size = 64M;
-- SET GLOBAL max_connections = 200;

-- ========================================
-- SCRIPT COMPLETADO
-- ========================================

-- Para verificar la creación exitosa
SELECT 'Base de datos AnimeGon creada exitosamente' as status;
SHOW TABLES;