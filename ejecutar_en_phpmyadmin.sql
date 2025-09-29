-- Ejecutar en phpMyAdmin para agregar el campo animeflv_url_name
-- Ve a tu base de datos animegon_db y ejecuta este comando en la pestaña SQL

ALTER TABLE lista_usuario 
ADD COLUMN animeflv_url_name VARCHAR(255) DEFAULT NULL COMMENT 'Nombre del anime en AnimeFLV para generar URLs (ej: jujutsu-kaisen-tv)';

-- Agregar índice para mejor rendimiento (opcional)
ALTER TABLE lista_usuario 
ADD INDEX idx_animeflv_url (animeflv_url_name);