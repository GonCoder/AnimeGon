// animes.js - Funcionalidades para la gestión de animes

class AnimeManager {
    constructor() {
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Filtrado en tiempo real
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.filtrarAnimes(e.target.value));
        }

        // Validación de archivo de imagen
        const imagenInput = document.getElementById('imagen');
        if (imagenInput) {
            imagenInput.addEventListener('change', (e) => this.validarImagen(e.target));
        }

        // Validación de formularios
        const animeForm = document.getElementById('animeForm');
        if (animeForm) {
            animeForm.addEventListener('submit', (e) => this.validarFormulario(e));
        }

        const editForm = document.getElementById('editAnimeForm');
        if (editForm) {
            editForm.addEventListener('submit', (e) => this.validarFormularioEdicion(e));
        }

        // Eventos de botones de acción
        this.initializeActionButtons();
    }

    initializeActionButtons() {
        // Botones de editar
        document.querySelectorAll('.btn-editar').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const animeId = e.target.getAttribute('data-anime-id');
                this.abrirModalEditar(animeId);
            });
        });

        // Botones de eliminar
        document.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const animeId = e.target.getAttribute('data-anime-id');
                const animeNombre = e.target.getAttribute('data-anime-nombre');
                this.confirmarEliminar(animeId, animeNombre);
            });
        });
    }

    // Filtrado de animes
    filtrarAnimes(termino) {
        const searchTerm = termino.toLowerCase();
        const animeCards = document.querySelectorAll('.anime-card');
        
        animeCards.forEach(card => {
            const animeName = card.getAttribute('data-anime-name');
            if (animeName.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Validación de imagen
    validarImagen(input) {
        const label = document.querySelector('.file-input-label');
        
        if (input.files[0]) {
            // Verificar tamaño del archivo
            const fileSize = input.files[0].size / 1024 / 1024; // MB
            if (fileSize > 1) {
                this.mostrarAlerta('⚠️ El archivo es demasiado grande. Máximo 1MB permitido.', 'error');
                input.value = '';
                label.textContent = '📎 Seleccionar imagen (JPG, PNG - máx. 1MB)';
                return false;
            }
            
            // Verificar tipo de archivo
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/x-icon', 'image/vnd.microsoft.icon'];
            if (!allowedTypes.includes(input.files[0].type)) {
                this.mostrarAlerta('⚠️ Tipo de archivo no permitido. Solo JPG, PNG e ICO.', 'error');
                input.value = '';
                label.textContent = '📎 Seleccionar imagen (JPG, PNG, ICO - máx. 1MB)';
                return false;
            }
            
            label.innerHTML = `📎 ${input.files[0].name} <span style="color: #00ff88;">✓</span>`;
        } else {
            label.textContent = '📎 Seleccionar imagen (JPG, PNG, ICO - máx. 1MB)';
        }
        
        return true;
    }

    // Validación de formulario de agregar
    validarFormulario(event) {
        const nombre = document.getElementById('nombre').value.trim();
        const totalEpisodios = document.getElementById('total_episodios').value;
        const capitulosVistos = document.getElementById('capitulos_vistos').value;
        
        if (!nombre) {
            this.mostrarAlerta('⚠️ Por favor ingresa el nombre del anime.', 'error');
            event.preventDefault();
            return false;
        }
        
        if (totalEpisodios && capitulosVistos && parseInt(capitulosVistos) > parseInt(totalEpisodios)) {
            this.mostrarAlerta('⚠️ Los episodios vistos no pueden ser más que el total de episodios.', 'error');
            event.preventDefault();
            return false;
        }

        return true;
    }

    // Validación de formulario de edición
    validarFormularioEdicion(event) {
        const nombre = document.getElementById('edit_nombre').value.trim();
        const totalEpisodios = document.getElementById('edit_total_episodios').value;
        const capitulosVistos = document.getElementById('edit_capitulos_vistos').value;
        
        if (!nombre) {
            this.mostrarAlerta('⚠️ Por favor ingresa el nombre del anime.', 'error');
            event.preventDefault();
            return false;
        }
        
        if (totalEpisodios && capitulosVistos && parseInt(capitulosVistos) > parseInt(totalEpisodios)) {
            this.mostrarAlerta('⚠️ Los episodios vistos no pueden ser más que el total de episodios.', 'error');
            event.preventDefault();
            return false;
        }

        // Enviar formulario por AJAX
        event.preventDefault();
        this.enviarFormularioEdicion();
        return false;
    }

    // Abrir modal de edición
    async abrirModalEditar(animeId) {
        try {
            this.mostrarCargando(true);
            
            // Obtener datos del anime desde el servidor
            const response = await fetch(`../backend/api/obtener_anime.php?anime_id=${animeId}`);
            const result = await response.json();
            
            if (!result.exito) {
                throw new Error(result.error || 'Error al obtener datos del anime');
            }
            
            const anime = result.anime;
            
            // Llenar formulario de edición con todos los campos
            document.getElementById('edit_anime_id').value = animeId;
            document.getElementById('edit_nombre').value = anime.titulo || '';
            document.getElementById('edit_titulo_original').value = anime.titulo_original || '';
            document.getElementById('edit_titulo_ingles').value = anime.titulo_ingles || '';
            document.getElementById('edit_tipo').value = anime.tipo || 'TV';
            document.getElementById('edit_estado_anime').value = anime.anime_estado || 'Finalizado';
            document.getElementById('edit_total_episodios').value = anime.episodios_total || '';
            document.getElementById('edit_capitulos_vistos').value = anime.episodios_vistos || '0';
            document.getElementById('edit_estado').value = anime.lista_estado || 'Plan de Ver';
            document.getElementById('edit_puntuacion').value = anime.puntuacion || '';
            document.getElementById('edit_animeflv_url_name').value = anime.animeflv_url_name || '';
            
            // Mostrar modal
            document.getElementById('editAnimeModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            
        } catch (error) {
            console.error('Error al abrir modal de edición:', error);
            this.mostrarAlerta('❌ Error al cargar datos del anime: ' + error.message, 'error');
        } finally {
            this.mostrarCargando(false);
        }
    }

    // Enviar formulario de edición por AJAX
    async enviarFormularioEdicion() {
        const form = document.getElementById('editAnimeForm');
        const formData = new FormData(form);
        
        try {
            this.mostrarCargando(true);
            
            const response = await fetch('../backend/api/editar_anime.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.exito) {
                this.mostrarAlerta('✅ ' + result.mensaje, 'success');
                this.cerrarModalEditar();
                // Recargar página después de un breve delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                this.mostrarAlerta('❌ ' + result.mensaje, 'error');
            }
            
        } catch (error) {
            console.error('Error al editar anime:', error);
            this.mostrarAlerta('❌ Error al enviar la solicitud', 'error');
        } finally {
            this.mostrarCargando(false);
        }
    }

    // Confirmar eliminación
    confirmarEliminar(animeId, animeNombre) {
        const modal = document.getElementById('confirmDeleteModal');
        const deleteMessage = document.getElementById('deleteMessage');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        const cancelBtn = document.getElementById('cancelDeleteBtn');
        
        // Actualizar el mensaje con el título del anime
        deleteMessage.textContent = `¿Estás seguro de que quieres eliminar "${animeNombre}" de tu lista?`;
        
        // Mostrar el modal
        modal.style.display = 'flex';
        
        // Configurar los botones
        confirmBtn.onclick = () => {
            modal.style.display = 'none';
            this.eliminarAnime(animeId, animeNombre);
        };
        
        cancelBtn.onclick = () => {
            modal.style.display = 'none';
        };
        
        // Cerrar con escape
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                modal.style.display = 'none';
                document.removeEventListener('keydown', handleEscape);
            }
        };
        
        document.addEventListener('keydown', handleEscape);
        
        // Cerrar al hacer clic en el fondo
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    }

    // Eliminar anime
    async eliminarAnime(animeId, animeNombre) {
        try {
            this.mostrarCargando(true);
            
            const formData = new FormData();
            formData.append('anime_id', animeId);
            
            const response = await fetch('../backend/api/eliminar_anime.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.exito) {
                this.mostrarAlerta(`✅ "${animeNombre}" ha sido eliminado de tu lista`, 'success');
                
                // Eliminar elemento del DOM con animación
                const animeCard = document.querySelector(`[data-anime-id="${animeId}"]`).closest('.anime-card');
                animeCard.style.transition = 'all 0.3s ease';
                animeCard.style.transform = 'scale(0)';
                animeCard.style.opacity = '0';
                
                setTimeout(() => {
                    animeCard.remove();
                    this.verificarListaVacia();
                }, 300);
                
            } else {
                this.mostrarAlerta('❌ ' + result.mensaje, 'error');
            }
            
        } catch (error) {
            console.error('Error al eliminar anime:', error);
            this.mostrarAlerta('❌ Error al eliminar el anime', 'error');
        } finally {
            this.mostrarCargando(false);
        }
    }

    // Verificar si la lista está vacía después de eliminar
    verificarListaVacia() {
        const animeCards = document.querySelectorAll('.anime-card');
        if (animeCards.length === 0) {
            const animesGrid = document.getElementById('animesGrid');
            animesGrid.innerHTML = `
                <div class="no-animes" style="grid-column: 1 / -1;">
                    <h3>🎭 ¡No tienes animes en tu lista!</h3>
                    <p>Agrega tus animes favoritos para hacer seguimiento de tu progreso.</p>
                    <button class="btn-agregar" onclick="abrirModal()" style="margin-top: 20px;">
                        ➕ Agregar tu primer anime
                    </button>
                </div>
            `;
        }
    }

    // Mostrar mensaje de carga
    mostrarCargando(mostrar) {
        let loadingDiv = document.getElementById('loading-overlay');
        
        if (mostrar) {
            if (!loadingDiv) {
                loadingDiv = document.createElement('div');
                loadingDiv.id = 'loading-overlay';
                loadingDiv.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    color: #00ffff;
                    font-size: 1.2rem;
                `;
                loadingDiv.innerHTML = '🔄 Procesando...';
                document.body.appendChild(loadingDiv);
            }
            loadingDiv.style.display = 'flex';
        } else {
            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
        }
    }

    // Mostrar alertas personalizadas
    mostrarAlerta(mensaje, tipo = 'info') {
        // Remover alertas anteriores
        const alertaAnterior = document.querySelector('.alerta-personalizada');
        if (alertaAnterior) {
            alertaAnterior.remove();
        }
        
        const alerta = document.createElement('div');
        alerta.className = 'alerta-personalizada';
        
        // Colores con mayor opacidad para mejor visibilidad
        const colores = {
            'success': 'rgba(0, 255, 136, 0.95)',
            'error': 'rgba(255, 0, 127, 0.95)',
            'info': 'rgba(0, 255, 255, 0.95)'
        };
        
        const borderColors = {
            'success': '#00ff88',
            'error': '#ff007f',
            'info': '#00ffff'
        };
        
        // Colores de texto para mejor contraste
        const textColors = {
            'success': '#000000',
            'error': '#ffffff',
            'info': '#000000'
        };
        
        alerta.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${colores[tipo]};
            border: 2px solid ${borderColors[tipo]};
            border-radius: 10px;
            padding: 15px 20px;
            color: ${textColors[tipo]};
            font-weight: bold;
            font-size: 14px;
            z-index: 99999;
            min-width: 300px;
            max-width: 400px;
            word-wrap: break-word;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3), 0 0 20px ${borderColors[tipo]}40;
            backdrop-filter: blur(10px);
        `;
        
        alerta.textContent = mensaje;
        document.body.appendChild(alerta);
        
        // Remover después de 5 segundos
        setTimeout(() => {
            if (alerta.parentNode) {
                alerta.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => alerta.remove(), 300);
            }
        }, 5000);
    }

    // Cerrar modal de edición
    cerrarModalEditar() {
        document.getElementById('editAnimeModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        document.getElementById('editAnimeForm').reset();
    }
}

// Funciones globales para mantener compatibilidad
function abrirModal() {
    document.getElementById('animeModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarModal() {
    document.getElementById('animeModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('animeForm').reset();
}

function cerrarModalEditar() {
    animeManager.cerrarModalEditar();
}

// Inicializar cuando el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    window.animeManager = new AnimeManager();
    
    // Agregar estilos para animaciones
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    
    // Cerrar modales al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('animeModal');
        const editModal = document.getElementById('editAnimeModal');
        const importModal = document.getElementById('importModal');
        
        if (event.target == modal) {
            cerrarModal();
        }
        
        if (event.target == editModal) {
            cerrarModalEditar();
        }
        
        if (event.target == importModal) {
            cerrarModalImportar();
        }
    }
});

// Función para alternar favoritos
function toggleFavorito(animeId, button) {
    // Prevenir que se propague el evento al card
    event.stopPropagation();
    
    // Mostrar feedback visual inmediato
    button.style.transform = 'scale(1.2)';
    
    fetch('../backend/api/toggle_favorito.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            anime_id: animeId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar el estado visual del botón
            if (data.favorito) {
                button.classList.add('favorito');
                button.title = 'Quitar de favoritos';
            } else {
                button.classList.remove('favorito');
                button.title = 'Agregar a favoritos';
            }
            
            // Mostrar mensaje temporal
            mostrarMensajeTemporal(data.mensaje, 'success');
        } else {
            console.error('Error:', data.error);
            mostrarMensajeTemporal(data.error || 'Error al actualizar favorito', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensajeTemporal('Error de conexión', 'error');
    })
    .finally(() => {
        // Restaurar el tamaño del botón
        setTimeout(() => {
            button.style.transform = 'scale(1)';
        }, 200);
    });
}

// Función para mostrar mensajes temporales
function mostrarMensajeTemporal(mensaje, tipo = 'info') {
    // Remover mensajes anteriores
    const mensajeAnterior = document.querySelector('.mensaje-temporal');
    if (mensajeAnterior) {
        mensajeAnterior.remove();
    }
    
    // Crear elemento de mensaje
    const mensajeEl = document.createElement('div');
    mensajeEl.className = `mensaje-temporal mensaje-${tipo}`;
    mensajeEl.textContent = mensaje;
    
    // Configurar estilos base
    let backgroundColor, borderColor, textColor;
    
    switch(tipo) {
        case 'success':
            backgroundColor = 'rgba(0, 255, 136, 0.95)';
            borderColor = '#00ff88';
            textColor = '#000000';
            break;
        case 'error':
            backgroundColor = 'rgba(255, 0, 127, 0.95)';
            borderColor = '#ff007f';
            textColor = '#ffffff';
            break;
        default:
            backgroundColor = 'rgba(0, 255, 255, 0.95)';
            borderColor = '#00ffff';
            textColor = '#000000';
    }
    
    mensajeEl.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 10px;
        color: ${textColor};
        font-weight: bold;
        font-size: 14px;
        z-index: 99999;
        animation: slideIn 0.3s ease;
        background: ${backgroundColor};
        border: 2px solid ${borderColor};
        min-width: 300px;
        max-width: 400px;
        word-wrap: break-word;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3), 0 0 20px ${borderColor}40;
        backdrop-filter: blur(10px);
    `;
    
    // Agregar estilos de animación
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    if (!document.querySelector('#mensaje-temporal-styles')) {
        style.id = 'mensaje-temporal-styles';
        document.head.appendChild(style);
    }
    
    document.body.appendChild(mensajeEl);
    
    // Remover después de 3 segundos
    setTimeout(() => {
        mensajeEl.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (mensajeEl.parentNode) {
                mensajeEl.parentNode.removeChild(mensajeEl);
            }
        }, 300);
    }, 3000);
}

// Función para exportar lista - Abre el modal de opciones
function exportarLista() {
    document.getElementById('exportarModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Función para cerrar modal de exportar
function cerrarModalExportar() {
    document.getElementById('exportarModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Función para exportar en el formato seleccionado
async function exportarEnFormato(formato) {
    // Cerrar el modal primero
    cerrarModalExportar();
    
    try {
        mostrarMensajeTemporal('📤 Preparando exportación...', 'info');
        
        // Primero verificar que el endpoint responde
        const url = `../backend/api/exportar_lista.php?formato=${formato}`;
        const response = await fetch(url);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Error ${response.status}: ${errorText}`);
        }
        
        // Si la respuesta es JSON y contiene error, mostrarlo
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const jsonResponse = await response.json();
            if (jsonResponse.error) {
                throw new Error(jsonResponse.error);
            }
        }
        
        // Crear enlace de descarga
        const link = document.createElement('a');
        link.href = url;
        link.download = '';
        link.style.display = 'none';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Mostrar mensaje informativo
        const tipoFormato = formato === 'json' ? 'JSON (reimportable)' : 'TXT (solo lectura)';
        mostrarMensajeTemporal(`✅ Lista exportada en formato ${tipoFormato}`, 'success');
        
    } catch (error) {
        console.error('Error en exportación:', error);
        mostrarMensajeTemporal(`❌ Error al exportar: ${error.message}`, 'error');
    }
}

// Función para abrir modal de importar
function abrirModalImportar() {
    document.getElementById('importarModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Función para cerrar modal de importar
function cerrarModalImportar() {
    document.getElementById('importarModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('importarForm').reset();
    
    // Resetear etiqueta del archivo
    const label = document.querySelector('#importarModal .file-input-label');
    if (label) {
        label.textContent = '📎 Seleccionar archivo (.txt o .json)';
    }
}

// Manejar formulario de importación
document.addEventListener('DOMContentLoaded', function() {
    const importarForm = document.getElementById('importarForm');
    if (importarForm) {
        importarForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const archivoInput = document.getElementById('archivo_importar');
            
            if (!archivoInput.files[0]) {
                mostrarMensajeTemporal('⚠️ Por favor selecciona un archivo para importar', 'error');
                return;
            }
            
            try {
                mostrarMensajeTemporal('📥 Importando lista...', 'info');
                
                const response = await fetch('../backend/api/importar_lista.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.exito) {
                    mostrarMensajeTemporal('✅ ' + result.mensaje, 'success');
                    cerrarModalImportar();
                    
                    // Recargar la página después de 3 segundos para mostrar los nuevos animes
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    mostrarMensajeTemporal('❌ ' + result.mensaje, 'error');
                }
                
            } catch (error) {
                console.error('Error:', error);
                mostrarMensajeTemporal('❌ Error al importar la lista', 'error');
            }
        });
    }
    
    // Manejar cambio de archivo
    const archivoInput = document.getElementById('archivo_importar');
    if (archivoInput) {
        archivoInput.addEventListener('change', function(e) {
            const label = document.querySelector('#importarModal .file-input-label');
            if (e.target.files[0]) {
                label.innerHTML = `📎 ${e.target.files[0].name} <span style="color: #00ff88;">✓</span>`;
            } else {
                label.textContent = '📎 Seleccionar archivo (.txt o .json)';
            }
        });
    }
    
    // Cerrar modales con tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modales = ['animeModal', 'editAnimeModal', 'importarModal', 'exportarModal'];
            modales.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });
    
    // Cerrar modales al hacer clic fuera de ellos
    const modales = ['animeModal', 'editAnimeModal', 'importarModal', 'exportarModal'];
    modales.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });
});