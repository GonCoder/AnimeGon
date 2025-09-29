-- ========================================
-- SQL PARA AGREGAR SEGUIMIENTO DE EPISODIOS
-- Sistema de seguimiento con integración AnimeFLV
-- ========================================

-- Agregar campo para nombre URL de AnimeFLV en la tabla lista_usuario
ALTER TABLE lista_usuario 
ADD COLUMN animeflv_url_name VARCHAR(255) DEFAULT NULL COMMENT 'Nombre del anime en AnimeFLV para generar URLs (ej: jujutsu-kaisen-tv)',
ADD INDEX idx_animeflv_url (animeflv_url_name);

-- ========================================
-- COMENTARIOS Y DOCUMENTACIÓN
-- ========================================

/*
EXPLICACIÓN DEL NUEVO CAMPO:

1. animeflv_url_name: 
   - Campo VARCHAR(255) que almacena el nombre exacto que usa AnimeFLV
   - Ejemplo: Para "Jujutsu Kaisen" sería "jujutsu-kaisen-tv"
   - Se usa para construir URLs de seguimiento de episodios

2. CONSTRUCCIÓN DE URLs:
   - URL base del anime: https://www3.animeflv.net/anime/{animeflv_url_name}
   - URL de episodio específico: https://www3.animeflv.net/ver/{animeflv_url_name}-{numero_episodio}
   
3. EJEMPLOS DE USO:
   - Si animeflv_url_name = "jujutsu-kaisen-tv" y episodios_vistos = 17
   - Botón "+": incrementa episodios_vistos a 18 y abre https://www3.animeflv.net/ver/jujutsu-kaisen-tv-18
   - Botón "-": decrementa episodios_vistos a 16

4. FUNCIONALIDAD:
   - Los botones + y - modificarán el campo episodios_vistos existente
   - El botón + además abrirá automáticamente la URL del siguiente episodio en AnimeFLV
   - Se agregará validación para no permitir episodios negativos o superiores al total

5. INTERFAZ:
   - Se agregará un campo en el formulario de edición: "Nombre URL AnimeFLV para seguimiento"
   - Placeholder: "ej: jujutsu-kaisen-tv (sin espacios, guiones medios)"
   - Campo opcional pero recomendado para usar el seguimiento de episodios
*/