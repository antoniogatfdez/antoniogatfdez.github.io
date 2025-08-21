<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireUserType('club');

$database = new Database();
$conn = $database->getConnection();

// Obtener información del club
$stmt = $conn->prepare("SELECT id, nombre_club FROM clubes WHERE usuario_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header('Location: ../unauthorized.php');
    exit();
}

// Obtener estadísticas de documentos
$stmt = $conn->prepare("SELECT COUNT(*) FROM documentos_clubes WHERE club_id = ? AND activo = 1");
$stmt->execute([$club['id']]);
$total_documentos = $stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT tipo_archivo, COUNT(*) as cantidad 
    FROM documentos_clubes 
    WHERE club_id = ? AND activo = 1 
    GROUP BY tipo_archivo 
    ORDER BY cantidad DESC
");
$stmt->execute([$club['id']]);
$tipos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos - <?php echo htmlspecialchars($club['nombre_club']); ?> - FEDEXVB</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-volleyball-ball"></i>
                    <span>FEDEXVB - <?php echo htmlspecialchars($club['nombre_club']); ?></span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($auth->getUsername()); ?></div>
                        <div class="user-role">Club</div>
                    </div>
                    <a href="../includes/logout.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="equipos.php"><i class="fas fa-users"></i> Mis Equipos</a></li>
            <li><a href="jugadores.php"><i class="fas fa-user-friends"></i> Mis Jugadores</a></li>
            <li><a href="tecnicos.php"><i class="fas fa-chalkboard-teacher"></i> Mis Técnicos</a></li>
            <li><a href="partidos.php"><i class="fas fa-calendar-alt"></i> Mis Partidos</a></li>
            <li><a href="inscripciones.php"><i class="fas fa-file-signature"></i> Inscripciones</a></li>
            <li><a href="documentos.php" class="active"><i class="fas fa-folder-open"></i> Documentos</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-folder-open"></i> Documentos del Club</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Documentos
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-folder-open" style="font-size: 2.5rem; color: var(--primary-green);"></i>
                    <h3 style="color: var(--primary-green); margin: 15px 0;"><?php echo $total_documentos; ?></h3>
                    <p class="text-muted">Total Documentos</p>
                </div>
            </div>
            
            <?php foreach ($tipos_documentos as $tipo): ?>
            <div class="card">
                <div class="card-body text-center">
                    <i class="<?php echo getFileIconByType($tipo['tipo_archivo']); ?>" style="font-size: 2.5rem; color: var(--info);"></i>
                    <h3 style="color: var(--info); margin: 15px 0;"><?php echo $tipo['cantidad']; ?></h3>
                    <p class="text-muted">Archivos <?php echo strtoupper($tipo['tipo_archivo']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtros y búsqueda -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-filter"></i> Filtros y Búsqueda
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 200px 150px; gap: 15px; align-items: end;">
                    <div class="form-group">
                        <label for="busqueda">Buscar documentos</label>
                        <input type="text" id="busqueda" placeholder="Buscar por nombre..." 
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                               onkeyup="filtrarDocumentos()">
                    </div>
                    <div class="form-group">
                        <label for="filtroTipo">Tipo de archivo</label>
                        <select id="filtroTipo" onchange="filtrarDocumentos()" 
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                            <option value="">Todos los tipos</option>
                            <option value="pdf">PDF</option>
                            <option value="doc">Word</option>
                            <option value="docx">Word</option>
                            <option value="xls">Excel</option>
                            <option value="xlsx">Excel</option>
                            <option value="jpg">Imagen</option>
                            <option value="jpeg">Imagen</option>
                            <option value="png">Imagen</option>
                            <option value="txt">Texto</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-secondary" onclick="limpiarFiltros()" style="width: 100%;">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenedor principal con lista y visor -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; min-height: 600px;" id="contenedorPrincipal">
            <!-- Lista de documentos -->
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <i class="fas fa-file-alt"></i> Mis Documentos
                        </div>
                        <div>
                            <button class="btn btn-primary" onclick="cargarDocumentos()">
                                <i class="fas fa-sync"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <div id="listaDocumentos">
                        <div class="text-center p-4">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--medium-gray);"></i>
                            <p class="text-muted mt-2">Cargando documentos...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visor de documentos -->
            <div class="card" id="visorDocumentos">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <i class="fas fa-eye"></i> <span id="tituloVisor">Visor de Documentos</span>
                        </div>
                        <div id="accionesVisor" style="display: none;">
                            <button class="btn btn-primary btn-sm" onclick="descargarDocumentoVisor()" id="btnDescargarVisor">
                                <i class="fas fa-download"></i> Descargar
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="cerrarVisor()">
                                <i class="fas fa-times"></i> Cerrar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="padding: 0; height: 600px; position: relative;">
                    <div id="contenidoVisor" style="height: 100%; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                        <div class="text-center" style="color: var(--medium-gray);">
                            <i class="fas fa-mouse-pointer" style="font-size: 4rem; margin-bottom: 20px;"></i>
                            <h4>Selecciona un documento</h4>
                            <p>Haz clic en cualquier documento de la lista para visualizarlo aquí</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSS responsivo -->
        <style>
            @media (max-width: 1024px) {
                #contenedorPrincipal {
                    grid-template-columns: 1fr !important;
                    grid-template-rows: auto auto;
                }
                
                #visorDocumentos .card-body {
                    height: 400px !important;
                }
            }
            
            @media (max-width: 768px) {
                .card-header div {
                    flex-direction: column !important;
                    gap: 10px;
                }
                
                #accionesVisor {
                    display: flex !important;
                    gap: 5px;
                }
                
                #accionesVisor button {
                    font-size: 0.8rem !important;
                    padding: 5px 10px !important;
                }
                
                .documento-item .list-group-item div {
                    flex-direction: column !important;
                    align-items: flex-start !important;
                }
                
                .documento-item h6 {
                    font-size: 0.9rem !important;
                }
            }
        </style>
    </main>

    <script src="../assets/js/app.js"></script>
    <script>
        let documentosOriginales = [];
        let documentosFiltrados = [];

        // Cargar documentos al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            cargarDocumentos();
        });

        async function cargarDocumentos() {
            try {
                const response = await fetch('api/documentos.php?action=list_documentos');
                const data = await response.json();
                
                if (data.success) {
                    documentosOriginales = data.documentos;
                    documentosFiltrados = [...documentosOriginales];
                    mostrarDocumentos(documentosFiltrados);
                } else {
                    mostrarError('Error al cargar documentos: ' + data.message);
                }
            } catch (error) {
                mostrarError('Error de conexión al cargar documentos');
            }
        }

        function mostrarDocumentos(documentos) {
            const container = document.getElementById('listaDocumentos');
            
            if (documentos.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No hay documentos disponibles</h4>
                        <p class="text-muted">
                            ${documentosOriginales.length === 0 
                                ? 'El administrador aún no ha subido documentos para este club' 
                                : 'No se encontraron documentos que coincidan con los filtros aplicados'}
                        </p>
                        ${documentosOriginales.length > 0 ? '<button class="btn btn-primary" onclick="limpiarFiltros()"><i class="fas fa-times"></i> Limpiar Filtros</button>' : ''}
                    </div>
                `;
                return;
            }
            
            let html = '<div class="list-group" style="border: none;">';
            
            documentos.forEach((doc, index) => {
                const fechaSubida = new Date(doc.fecha_subida).toLocaleDateString('es-ES', {
                    day: '2-digit',
                    month: '2-digit', 
                    year: 'numeric'
                });
                const tamaño = formatFileSize(doc.tamaño_archivo);
                const icono = getFileIcon(doc.tipo_archivo);
                
                html += `
                    <div class="list-group-item documento-item" 
                         style="border: 1px solid #dee2e6; margin-bottom: 8px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; background: white;"
                         onclick="seleccionarDocumento(${doc.id}, '${doc.nombre_documento.replace(/'/g, "\\'")}', '${doc.nombre_archivo.replace(/'/g, "\\'")}', '${doc.tipo_archivo}', ${doc.tamaño_archivo}, '${doc.fecha_subida}')"
                         onmouseover="this.style.background='#f8f9fa'; this.style.borderColor='var(--primary-green)'"
                         onmouseout="this.style.background='white'; this.style.borderColor='#dee2e6'"
                         id="documento-${doc.id}">
                        <div style="display: flex; align-items: center; padding: 12px;">
                            <div style="margin-right: 15px; font-size: 1.5rem; color: var(--primary-green);">
                                <i class="${icono}"></i>
                            </div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <h6 style="margin: 0 0 5px 0; font-weight: 600; font-size: 0.95rem; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    ${doc.nombre_documento}
                                </h6>
                                <p style="margin: 0 0 3px 0; font-size: 0.8rem; color: #666; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    ${doc.nombre_archivo}
                                </p>
                                <div style="display: flex; gap: 15px; font-size: 0.75rem; color: #999;">
                                    <span><i class="fas fa-weight" style="margin-right: 3px;"></i>${tamaño}</span>
                                    <span><i class="fas fa-calendar" style="margin-right: 3px;"></i>${fechaSubida}</span>
                                </div>
                            </div>
                            <div style="margin-left: 10px;">
                                <i class="fas fa-chevron-right" style="color: #ccc; font-size: 0.8rem;"></i>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        function mostrarError(mensaje) {
            const container = document.getElementById('listaDocumentos');
            container.innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--warning);"></i>
                    <h4 class="mt-3">Error al cargar documentos</h4>
                    <p class="text-muted">${mensaje}</p>
                    <button class="btn btn-primary" onclick="cargarDocumentos()">
                        <i class="fas fa-sync"></i> Intentar de nuevo
                    </button>
                </div>
            `;
        }

        function filtrarDocumentos() {
            const busqueda = document.getElementById('busqueda').value.toLowerCase();
            const tipoFiltro = document.getElementById('filtroTipo').value.toLowerCase();
            
            documentosFiltrados = documentosOriginales.filter(doc => {
                const coincideBusqueda = doc.nombre_documento.toLowerCase().includes(busqueda) || 
                                       doc.nombre_archivo.toLowerCase().includes(busqueda);
                const coincideTipo = tipoFiltro === '' || doc.tipo_archivo.toLowerCase() === tipoFiltro;
                
                return coincideBusqueda && coincideTipo;
            });
            
            mostrarDocumentos(documentosFiltrados);
        }

        function limpiarFiltros() {
            document.getElementById('busqueda').value = '';
            document.getElementById('filtroTipo').value = '';
            documentosFiltrados = [...documentosOriginales];
            mostrarDocumentos(documentosFiltrados);
        }

        function getFileIcon(tipoArchivo) {
            const iconos = {
                'pdf': 'fas fa-file-pdf',
                'doc': 'fas fa-file-word',
                'docx': 'fas fa-file-word',
                'xls': 'fas fa-file-excel',
                'xlsx': 'fas fa-file-excel',
                'jpg': 'fas fa-file-image',
                'jpeg': 'fas fa-file-image',
                'png': 'fas fa-file-image',
                'txt': 'fas fa-file-alt'
            };
            return iconos[tipoArchivo.toLowerCase()] || 'fas fa-file';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function descargarDocumento(documentoId) {
            window.open(`api/documentos.php?action=download&documento_id=${documentoId}`, '_blank');
        }

        // Variables para el visor
        let documentoSeleccionado = null;

        function seleccionarDocumento(id, nombre, archivo, tipo, tamaño, fecha) {
            // Quitar selección anterior
            document.querySelectorAll('.documento-item').forEach(item => {
                item.style.background = 'white';
                item.style.borderColor = '#dee2e6';
                item.style.boxShadow = 'none';
            });
            
            // Marcar como seleccionado
            const elemento = document.getElementById(`documento-${id}`);
            if (elemento) {
                elemento.style.background = '#e3f2fd';
                elemento.style.borderColor = 'var(--primary-green)';
                elemento.style.boxShadow = '0 2px 8px rgba(46, 125, 50, 0.2)';
            }
            
            // Guardar documento seleccionado
            documentoSeleccionado = { id, nombre, archivo, tipo, tamaño, fecha };
            
            // Actualizar el visor
            mostrarDocumentoEnVisor(id, nombre, archivo, tipo, tamaño, fecha);
        }

        function mostrarDocumentoEnVisor(id, nombre, archivo, tipo, tamaño, fecha) {
            const tituloVisor = document.getElementById('tituloVisor');
            const contenidoVisor = document.getElementById('contenidoVisor');
            const accionesVisor = document.getElementById('accionesVisor');
            
            // Actualizar título
            tituloVisor.textContent = nombre;
            
            // Mostrar acciones
            accionesVisor.style.display = 'block';
            
            // Formatear información
            const fechaFormateada = new Date(fecha).toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const tamañoFormateado = formatFileSize(tamaño);
            const icono = getFileIcon(tipo);
            
            let contenido = '';
            
            // Crear contenido según el tipo de archivo
            if (tipo.toLowerCase() === 'pdf') {
                contenido = `
                    <div style="width: 100%; height: 100%; display: flex; flex-direction: column;">
                        <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center;">
                                    <i class="${icono}" style="font-size: 1.5rem; color: #dc3545; margin-right: 10px;"></i>
                                    <div>
                                        <h6 style="margin: 0; font-weight: 600;">${nombre}</h6>
                                        <small style="color: #666;">${archivo} • ${tamañoFormateado} • ${fechaFormateada}</small>
                                    </div>
                                </div>
                                <button class="btn btn-outline-primary btn-sm" onclick="abrirEnNuevaVentana(${id})">
                                    <i class="fas fa-external-link-alt"></i> Abrir en nueva ventana
                                </button>
                            </div>
                        </div>
                        <iframe src="api/documentos.php?action=view&documento_id=${id}" 
                                style="flex: 1; border: none; width: 100%;" 
                                onload="this.style.height = '100%'">
                            <p>Tu navegador no soporta iframes. <a href="api/documentos.php?action=download&documento_id=${id}" target="_blank">Descargar el archivo</a></p>
                        </iframe>
                    </div>
                `;
            } else if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(tipo.toLowerCase())) {
                contenido = `
                    <div style="width: 100%; height: 100%; display: flex; flex-direction: column;">
                        <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                            <div style="display: flex; align-items: center;">
                                <i class="${icono}" style="font-size: 1.5rem; color: #28a745; margin-right: 10px;"></i>
                                <div>
                                    <h6 style="margin: 0; font-weight: 600;">${nombre}</h6>
                                    <small style="color: #666;">${archivo} • ${tamañoFormateado} • ${fechaFormateada}</small>
                                </div>
                            </div>
                        </div>
                        <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; background: #f8f9fa;">
                            <img src="api/documentos.php?action=view&documento_id=${id}" 
                                 style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                                 alt="${nombre}"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div style="display: none; text-center; color: #666;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>No se pudo cargar la imagen</p>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                contenido = `
                    <div style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px;">
                        <div style="background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); max-width: 400px;">
                            <div style="font-size: 4rem; color: var(--primary-green); margin-bottom: 20px;">
                                <i class="${icono}"></i>
                            </div>
                            <h4 style="margin-bottom: 15px; color: #333;">${nombre}</h4>
                            <p style="color: #666; margin-bottom: 20px; font-size: 0.9rem;">
                                <strong>Archivo:</strong> ${archivo}<br>
                                <strong>Tamaño:</strong> ${tamañoFormateado}<br>
                                <strong>Subido:</strong> ${fechaFormateada}
                            </p>
                            <div style="display: flex; gap: 10px; justify-content: center;">
                                <button class="btn btn-primary" onclick="descargarDocumentoVisor()">
                                    <i class="fas fa-download"></i> Descargar
                                </button>
                                <button class="btn btn-outline-primary" onclick="abrirEnNuevaVentana(${id})">
                                    <i class="fas fa-external-link-alt"></i> Abrir
                                </button>
                            </div>
                            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; font-size: 0.85rem; color: #666;">
                                <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                                Vista previa no disponible para este tipo de archivo
                            </div>
                        </div>
                    </div>
                `;
            }
            
            contenidoVisor.innerHTML = contenido;
        }

        function cerrarVisor() {
            const tituloVisor = document.getElementById('tituloVisor');
            const contenidoVisor = document.getElementById('contenidoVisor');
            const accionesVisor = document.getElementById('accionesVisor');
            
            // Quitar selección
            document.querySelectorAll('.documento-item').forEach(item => {
                item.style.background = 'white';
                item.style.borderColor = '#dee2e6';
                item.style.boxShadow = 'none';
            });
            
            // Resetear visor
            tituloVisor.textContent = 'Visor de Documentos';
            accionesVisor.style.display = 'none';
            documentoSeleccionado = null;
            
            contenidoVisor.innerHTML = `
                <div class="text-center" style="color: var(--medium-gray);">
                    <i class="fas fa-mouse-pointer" style="font-size: 4rem; margin-bottom: 20px;"></i>
                    <h4>Selecciona un documento</h4>
                    <p>Haz clic en cualquier documento de la lista para visualizarlo aquí</p>
                </div>
            `;
        }

        function descargarDocumentoVisor() {
            if (documentoSeleccionado) {
                descargarDocumento(documentoSeleccionado.id);
            }
        }

        function abrirEnNuevaVentana(documentoId) {
            window.open(`api/documentos.php?action=view&documento_id=${documentoId}`, '_blank');
        }
    </script>
</body>
</html>

<?php
function getFileIconByType($tipo) {
    $iconos = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'txt' => 'fas fa-file-alt'
    ];
    return $iconos[strtolower($tipo)] ?? 'fas fa-file';
}
?>
