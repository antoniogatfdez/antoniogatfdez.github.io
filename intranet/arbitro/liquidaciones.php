<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('arbitro');

$database = new Database();
$conn = $database->getConnection();
$message = '';

// Obtener ID del árbitro
$query = "SELECT id FROM arbitros WHERE usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$arbitro_id = $stmt->fetchColumn();

// Procesar rectificaciones
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'solicitar_rectificacion') {
        $liquidacion_id = $_POST['liquidacion_id'];
        $motivo = sanitize_input($_POST['motivo']);
        $observaciones = sanitize_input($_POST['observaciones']);
        
        try {
            $query = "INSERT INTO rectificaciones_liquidaciones (liquidacion_id, arbitro_id, motivo, observaciones, estado) 
                      VALUES (?, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($query);
            $stmt->execute([$liquidacion_id, $arbitro_id, $motivo, $observaciones]);
            
            $message = success_message('Solicitud de rectificación enviada correctamente');
        } catch (Exception $e) {
            $message = error_message('Error al enviar la solicitud de rectificación');
        }
    }
}

// Obtener liquidaciones del árbitro
$query = "SELECT l.*, 
                 COUNT(lp.id) as total_partidos,
                 SUM(lp.importe_partido + lp.importe_dieta + lp.importe_kilometraje) as total_importe,
                 r.id as rectificacion_id,
                 r.estado as rectificacion_estado,
                 r.motivo as rectificacion_motivo,
                 r.respuesta_admin
          FROM liquidaciones l
          LEFT JOIN liquidaciones_partidos lp ON l.id = lp.liquidacion_id
          LEFT JOIN rectificaciones_liquidaciones r ON l.id = r.liquidacion_id
          WHERE l.arbitro_id = ?
          GROUP BY l.id
          ORDER BY l.fecha_creacion DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$arbitro_id]);
$liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Liquidaciones - FEDEXVB</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/search-bar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-volleyball-ball"></i>
                    <span>FEDEXVB - Árbitro</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <div class="user-name"><?php echo $_SESSION['user_name'] . ' ' . $_SESSION['user_lastname']; ?></div>
                        <div class="user-role">Árbitro</div>
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
            <li><a href="disponibilidad.php"><i class="fas fa-calendar-check"></i> Mi Disponibilidad</a></li>
            <li><a href="partidos.php"><i class="fa-solid fa-globe"></i> Mis Partidos</a></li>
            <li><a href="liquidaciones.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Mis Liquidaciones</a></li>
            <li><a href="arbitros.php"><i class="fas fa-users"></i> Lista de Árbitros</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Mis Liquidaciones</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> <a href="dashboard.php">Inicio</a> / Mis Liquidaciones
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Estadísticas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header" style="background: var(--info);">
                    <i class="fas fa-file-invoice"></i> Total Liquidaciones
                </div>
                <div class="card-body text-center">
                    <h2 style="margin: 0; color: var(--info);"><?php echo count($liquidaciones); ?></h2>
                    <p style="margin: 0; color: var(--medium-gray);">Liquidaciones generadas</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--success);">
                    <i class="fas fa-euro-sign"></i> Total Cobrado
                </div>
                <div class="card-body text-center">
                    <?php
                    $total_cobrado = 0;
                    foreach ($liquidaciones as $liq) {
                        if ($liq['estado'] == 'pagada') {
                            $total_cobrado += $liq['total_importe'];
                        }
                    }
                    ?>
                    <h2 style="margin: 0; color: var(--success);">€<?php echo number_format($total_cobrado, 2); ?></h2>
                    <p style="margin: 0; color: var(--medium-gray);">Importe pagado</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--warning);">
                    <i class="fas fa-clock"></i> Pendientes
                </div>
                <div class="card-body text-center">
                    <?php
                    $pendientes = array_filter($liquidaciones, function($liq) {
                        return $liq['estado'] == 'pendiente';
                    });
                    $total_pendiente = array_sum(array_column($pendientes, 'total_importe'));
                    ?>
                    <h2 style="margin: 0; color: var(--warning);"><?php echo count($pendientes); ?></h2>
                    <p style="margin: 0; color: var(--medium-gray);">€<?php echo number_format($total_pendiente, 2); ?> pendientes</p>
                </div>
            </div>
        </div>

        <!-- Información importante -->
        <div class="card mb-4">
            <div class="card-header" style="background: var(--info);">
                <i class="fas fa-info-circle"></i> Información sobre Liquidaciones
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb"></i> Información importante:</h5>
                    <ul class="mb-0">
                        <li><strong>Revisión:</strong> Revisa cuidadosamente cada liquidación antes de que sea procesada</li>
                        <li><strong>Rectificaciones:</strong> Si encuentras algún error, puedes solicitar una rectificación</li>
                        <li><strong>Pagos:</strong> Los pagos se procesan mensualmente una vez aprobadas las liquidaciones</li>
                        <li><strong>Documentos:</strong> Puedes descargar el PDF de cada liquidación para tus registros</li>
                        <li><strong>Consultas:</strong> Para cualquier duda, contacta con el administrador</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Lista de liquidaciones -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Mis Liquidaciones
                <span class="badge" style="background: var(--primary-green); color: white; margin-left: 10px;">
                    <span id="total-count"><?php echo count($liquidaciones); ?></span> liquidaciones
                </span>
            </div>
            <div class="card-body">
                <?php if (!empty($liquidaciones)): ?>
                
                <!-- Barra de búsqueda -->
                <div class="search-container">
                    <div class="search-input-group">
                        <div class="search-input-icon">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   id="searchInput" 
                                   placeholder="Buscar por período, estado, número de partidos, importe..." 
                                   class="search-input-field">
                        </div>
                        <button type="button" 
                                id="searchClear" 
                                class="btn search-clear-btn" 
                                style="display: none;"
                                title="Limpiar búsqueda">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                    </div>
                    <div id="searchInfo" class="search-info" style="display: none;">
                        <i class="fas fa-info-circle"></i> 
                        <span id="searchResults">0</span> liquidación(es) encontrada(s)
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Busca por período, estado, número de partidos o importe. Usa ESC para limpiar.</em>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table data-table searchable-table">
                        <thead>
                            <tr>
                                <th data-sortable>Período</th>
                                <th data-sortable>Partidos</th>
                                <th data-sortable>Total</th>
                                <th data-sortable>Estado</th>
                                <th>Rectificación</th>
                                <th data-sortable>Fecha Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liquidaciones as $liquidacion): ?>
                            <tr>
                                <td>
                                    <?php echo format_date($liquidacion['fecha_inicio']); ?>
                                    <br>hasta<br>
                                    <?php echo format_date($liquidacion['fecha_fin']); ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--info);">
                                        <?php echo $liquidacion['total_partidos']; ?> partidos
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: var(--success); font-size: 1.1rem;">
                                        €<?php echo number_format($liquidacion['total_importe'], 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge" style="background: 
                                        <?php 
                                        echo $liquidacion['estado'] == 'pagada' ? 'var(--success)' : 
                                             ($liquidacion['estado'] == 'pendiente' ? 'var(--warning)' : 'var(--error)'); 
                                        ?>">
                                        <i class="fas fa-<?php echo $liquidacion['estado'] == 'pagada' ? 'check' : ($liquidacion['estado'] == 'pendiente' ? 'clock' : 'times'); ?>"></i>
                                        <?php echo ucfirst($liquidacion['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($liquidacion['rectificacion_id']): ?>
                                        <span class="badge" style="background: 
                                            <?php 
                                            echo $liquidacion['rectificacion_estado'] == 'aprobada' ? 'var(--success)' : 
                                                 ($liquidacion['rectificacion_estado'] == 'rechazada' ? 'var(--error)' : 'var(--warning)'); 
                                            ?>">
                                            <?php echo ucfirst($liquidacion['rectificacion_estado']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--medium-gray);">Sin rectificación</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo format_date($liquidacion['fecha_creacion']); ?></td>
                                <td>
                                    <button onclick="verDetalles(<?php echo $liquidacion['id']; ?>)" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                    <?php if (!$liquidacion['rectificacion_id'] && $liquidacion['estado'] != 'pagada'): ?>
                                    <button onclick="solicitarRectificacion(<?php echo $liquidacion['id']; ?>)" class="btn btn-warning btn-sm">
                                        <i class="fas fa-exclamation-triangle"></i> Rectificar
                                    </button>
                                    <?php endif; ?>
                                    <!--<button onclick="descargarPDF(<?php echo $liquidacion['id']; ?>)" class="btn btn-success btn-sm">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </button>-->
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-file-invoice-dollar" style="font-size: 4rem; color: var(--medium-gray); margin-bottom: 20px;"></i>
                    <h3>No tienes liquidaciones</h3>
                    <p class="text-muted">Cuando se generen liquidaciones de tus partidos arbitrados, aparecerán aquí.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Detalles Liquidación -->
    <div id="detallesLiquidacionModal" class="modal">
        <div class="modal-content" style="max-width: 90%; width: 1000px;">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> Detalles de Liquidación</h2>
                <span class="close" onclick="closeModal('detallesLiquidacionModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="detallesLiquidacionContent">
                    <!-- Se cargará dinámicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('detallesLiquidacionModal')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal Solicitar Rectificación -->
    <div id="rectificacionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Solicitar Rectificación</h2>
                <span class="close" onclick="closeModal('rectificacionModal')">&times;</span>
            </div>
            <form method="POST" class="validate-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="solicitar_rectificacion">
                    <input type="hidden" name="liquidacion_id" id="rectificacion_liquidacion_id">
                    
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-info-circle"></i> Información importante:</h5>
                        <p>Use este formulario para solicitar una rectificación de su liquidación. El administrador revisará su solicitud y le responderá.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Motivo de la rectificación</label>
                        <select name="motivo" class="form-control" required>
                            <option value="">Seleccione un motivo</option>
                            <option value="error_importe">Error en el importe</option>
                            <option value="partido_faltante">Partido no incluido</option>
                            <option value="partido_incorrecto">Partido incluido incorrectamente</option>
                            <option value="error_dieta">Error en dietas</option>
                            <option value="error_kilometraje">Error en kilometraje</option>
                            <option value="otro">Otro motivo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observaciones detalladas</label>
                        <textarea name="observaciones" class="form-control" rows="5" required 
                                  placeholder="Describe detalladamente el error encontrado y la corrección que solicitas..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rectificacionModal')">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-paper-plane"></i> Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function verDetalles(liquidacionId) {
            fetch(`api/liquidaciones.php?id=${liquidacionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showNotification(data.error, 'error');
                        return;
                    }
                    
                    let html = `
                        <div class="grid-info" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <h4>Información General</h4>
                                <p><strong>Período:</strong> ${data.fecha_inicio} al ${data.fecha_fin}</p>
                                <p><strong>Estado:</strong> 
                                    <span class="badge" style="background: ${data.estado == 'pagada' ? 'var(--success)' : (data.estado == 'pendiente' ? 'var(--warning)' : 'var(--error)')}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9rem;">
                                        ${data.estado.charAt(0).toUpperCase() + data.estado.slice(1)}
                                    </span>
                                </p>
                                <p><strong>Árbitro:</strong> ${data.arbitro_nombre || 'No especificado'}</p>
                                <p><strong>Observaciones:</strong> ${data.observaciones || 'Sin observaciones'}</p>
                            </div>
                            <div>
                                <h4>Resumen</h4>
                                <p><strong>Total Partidos:</strong> ${data.partidos ? data.partidos.length : 0}</p>
                                <p><strong>Total Importe:</strong> <span style="color: var(--success); font-size: 1.2rem; font-weight: bold;">€${data.total_importe || '0.00'}</span></p>
                                <p><strong>Fecha Creación:</strong> ${data.fecha_creacion ? new Date(data.fecha_creacion).toLocaleDateString('es-ES') : 'No especificada'}</p>
                            </div>
                        </div>
                        
                        <style>
                            @media (max-width: 768px) {
                                .grid-info {
                                    grid-template-columns: 1fr !important;
                                }
                            }
                        </style>
                        
                        <h5>Detalle de Partidos</h5>
                    `;
                    
                    if (data.partidos && data.partidos.length > 0) {
                        html += `
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Equipos</th>
                                            <th>Rol</th>
                                            <th>Importe Partido</th>
                                            <th>Dieta</th>
                                            <th>Kilometraje</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        let totalGeneral = 0;
                        data.partidos.forEach(partido => {
                            const importePartido = parseFloat(partido.importe_partido) || 0;
                            const importeDieta = parseFloat(partido.importe_dieta) || 0;
                            const importeKilometraje = parseFloat(partido.importe_kilometraje) || 0;
                            const totalPartido = importePartido + importeDieta + importeKilometraje;
                            totalGeneral += totalPartido;
                            
                            html += `
                                <tr>
                                    <td>${partido.fecha || 'No especificada'}</td>
                                    <td>${partido.equipos || 'No especificados'}</td>
                                    <td>${partido.rol_arbitro || 'No especificado'}</td>
                                    <td>€${importePartido.toFixed(2)}</td>
                                    <td>€${importeDieta.toFixed(2)}</td>
                                    <td>€${importeKilometraje.toFixed(2)}</td>
                                    <td class="font-weight-bold">€${totalPartido.toFixed(2)}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-weight-bold" style="background: var(--light-gray);">
                                            <td colspan="6">TOTAL GENERAL</td>
                                            <td style="color: var(--success); font-size: 1.1rem;">€${totalGeneral.toFixed(2)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="text-center p-4">
                                <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--medium-gray); margin-bottom: 10px;"></i>
                                <p>No hay partidos asociados a esta liquidación.</p>
                            </div>
                        `;
                    }
                    
                    document.getElementById('detallesLiquidacionContent').innerHTML = html;
                    openModal('detallesLiquidacionModal');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al cargar detalles de la liquidación', 'error');
                });
        }

        function solicitarRectificacion(liquidacionId) {
            document.getElementById('rectificacion_liquidacion_id').value = liquidacionId;
            openModal('rectificacionModal');
        }

        function descargarPDF(liquidacionId) {
            window.open(`../admin/pdf/liquidacion.php?id=${liquidacionId}`, '_blank');
        }
    </script>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda para liquidaciones
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('searchInput')) {
                new TableSearchBar({
                    searchInputId: 'searchInput',
                    clearBtnId: 'searchClear',
                    searchInfoId: 'searchInfo',
                    searchResultsId: 'searchResults',
                    totalCountId: 'total-count',
                    tableSelector: 'tbody tr',
                    columnsCount: 7,
                    noResultsId: 'noResultsRow'
                });
            }
        });
    </script>
</body>
</html>
