# 🎌 AnimeGon - Sistema de Gestión de Animes

## 📁 Nueva Estructura Organizada

```
AnimeGon/
├── 📂 backend/                 # 🔧 Lógica del servidor
│   ├── 📂 api/                # 🌐 Endpoints REST
│   │   ├── procesar_anime.php  # ➕ Agregar animes
│   │   ├── editar_anime.php    # ✏️ Editar animes
│   │   └── eliminar_anime.php  # 🗑️ Eliminar animes
│   ├── 📂 config/             # ⚙️ Configuración
│   │   ├── config.php         # 🗄️ Configuración BD
│   │   └── funciones.php      # 🛠️ Funciones auxiliares
│   ├── database.sql           # 📊 Script de BD
│   ├── test_conexion.php      # 🔍 Test conexión
│   └── verificar_tablas_animes.php # ✅ Verificar tablas
├── 📂 frontend/               # 🎨 Interfaz de usuario
│   └── 📂 assets/            # 📦 Recursos estáticos
│       ├── 📂 css/           # 🎨 Estilos
│       │   └── style.css     # 🌙 Tema oscuro anime
│       └── 📂 js/            # ⚡ JavaScript
│           └── animes.js     # 🎯 Lógica de interfaz
├── 📂 views/                 # 📄 Páginas de interfaz
│   ├── dashboard.php         # 📊 Dashboard principal
│   ├── login.php            # 🔐 Iniciar sesión
│   ├── registro.php         # 📝 Registrarse
│   ├── logout.php           # 🚪 Cerrar sesión
│   ├── mis_animes.php       # 📺 Lista animes (antigua)
│   └── mis_animes_new.php   # 📺 Lista animes (nueva)
├── 📂 img/                   # �️ Imágenes de animes
│   └── .htaccess            # 🛡️ Seguridad
├── index.php                # 🏠 Página de inicio
└── README.md               # 📚 Documentación
```

## ✨ Mejoras en la Organización

### 🎯 **Separación de Responsabilidades:**
- **Backend**: Toda la lógica del servidor y APIs
- **Frontend**: Recursos estáticos (CSS, JS)
- **Views**: Páginas de interfaz de usuario
- **Img**: Imágenes con seguridad y optimización

### 🔗 **Rutas Actualizadas:**

#### 🌐 **URLs Principales:**
- **Inicio**: `index.php`
- **Login**: `views/login.php`
- **Registro**: `views/registro.php`
- **Dashboard**: `views/dashboard.php`
- **Mis Animes**: `views/mis_animes_new.php`

#### 🔧 **APIs Backend:**
- **Agregar**: `backend/api/procesar_anime.php`
- **Editar**: `backend/api/editar_anime.php`
- **Eliminar**: `backend/api/eliminar_anime.php`

### 🛡️ **Seguridad Mejorada:**
- Archivos de configuración protegidos en `backend/`
- APIs organizadas y validadas
- Imágenes con protección `.htaccess`
- Sanitización de rutas y datos

## 🚀 Nuevas Funcionalidades

### ✨ Gestión Completa de Animes
- **➕ Agregar**: Nuevos animes con imagen
- **✏️ Editar**: Modificar información y progreso
- **🗑️ Eliminar**: Remover animes de la lista
- **🔍 Filtrar**: Búsqueda en tiempo real
- **📊 Progreso**: Seguimiento visual de episodios

### 🎨 Interfaz Mejorada
- **🌙 Tema oscuro** con efectos neon
- **📱 Responsive design** para móviles
- **⚡ Modales interactivos** para formularios
- **🎯 Animaciones** suaves y transiciones

### 🔒 Seguridad
- **🛡️ Validación** de archivos de imagen
- **📝 Sanitización** de datos de entrada
- **🔐 Protección** de directorios de imágenes
- **🚫 Prevención** de inyecciones SQL

## 🗄️ Base de Datos

### Tablas Principales:
- **`usuarios`**: Información de usuarios registrados
- **`animes`**: Catálogo de animes disponibles
- **`lista_usuario`**: Lista personal de cada usuario
- **`generos`**: Géneros de anime
- **`estudios`**: Estudios de animación

### Estados de Anime:
- **⏳ Plan de Ver**: Pendiente de ver
- **👀 Viendo**: Actualmente viendo
- **✅ Completado**: Terminado
- **⏸️ En Pausa**: Pausado temporalmente
- **❌ Abandonado**: Abandonado

## 🔧 Configuración

### 1. Base de Datos
```sql
-- Ejecutar en phpMyAdmin o consola MySQL
source backend/database.sql
```

### 2. Configuración de Conexión
Editar `backend/config/config.php`:
```php
define('DB_HOST', 'tu-host');
define('DB_NAME', 'tu-base-datos');
define('DB_USER', 'tu-usuario');
define('DB_PASS', 'tu-contraseña');
```

### 3. Permisos de Directorio
```bash
chmod 755 img/
```

## 🎯 Uso de la API

### Agregar Anime
```
POST backend/api/procesar_anime.php
- nombre: string (requerido)
- total_episodios: int
- episodios_vistos: int
- estado: string
- imagen: file (JPG/PNG, máx 1MB)
```

### Editar Anime
```
POST backend/api/editar_anime.php
- anime_id: int (requerido)
- nombre: string
- total_episodios: int
- episodios_vistos: int
- estado: string
- imagen: file (opcional)
```

### Eliminar Anime
```
POST backend/api/eliminar_anime.php
- anime_id: int (requerido)
```

## 🎨 Personalización CSS

### Variables de Color:
```css
:root {
  --neon-cyan: #00ffff;
  --neon-pink: #ff007f;
  --neon-purple: #bf00ff;
  --neon-green: #00ff88;
  --bg-dark: #0a0a0a;
  --bg-medium: #1a1a1a;
}
```

## 📱 JavaScript

### Clase Principal:
```javascript
class AnimeManager {
  // Gestión completa de animes
  // Validaciones y filtros
  // Modales y formularios
  // Comunicación con API
}
```

## 🔍 Testing

### Test de Conexión:
```
GET backend/test_conexion.php
```

### Verificar Tablas:
Ejecutar `verificar_tablas_animes.php` para validar estructura de BD.

## 🚧 Próximas Mejoras

- [ ] Sistema de favoritos
- [ ] Recomendaciones personalizadas
- [ ] Estadísticas de visualización
- [ ] Integración con APIs externas (MyAnimeList)
- [ ] Sistema de calificaciones
- [ ] Compartir listas públicas

## 📞 Soporte

Para reportar bugs o sugerir mejoras, contacta al desarrollador.

---

**🎌 AnimeGon v2.0** - Tu compañero personal para el seguimiento de anime