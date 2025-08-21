<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();
$message = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $message = createLiquidacion($conn, $_POST);
            break;
        case 'edit':
            $message = editLiquidacion($conn, $_POST);
            break;
        case 'delete':
            $message = deleteLiquidacion($conn, $_POST['liquidacion_id']);
            break;
        case 'generar_partidos':
            $message = generarPartidosLiquidacion($conn, $_POST);
            break;
        case 'gestionar_rectificacion':
            $message = gestionarRectificacion($conn, $_POST);
            break;
    }
}

// Obtener liquidaciones
$query = "SELECT l.*, 
                 CONCAT(a.nombre, ' ', a.apellidos) as arbitro_nombre,
                 l.numero_partidos as partidos_contabilizados,
                 COUNT(lp.id) as total_partidos_registrados,
                 SUM(lp.importe_partido + lp.importe_dieta + lp.importe_kilometraje) as total_importe_calculado,
                 COUNT(r.id) as total_rectificaciones,
                 SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as rectificaciones_pendientes,
                 GROUP_CONCAT(CASE WHEN r.estado = 'pendiente' THEN r.id END) as rectificacion_pendiente_id,
                 GROUP_CONCAT(CASE WHEN r.estado = 'pendiente' THEN r.motivo END SEPARATOR '|||') as rectificacion_motivo,
                 GROUP_CONCAT(CASE WHEN r.estado = 'pendiente' THEN r.observaciones END SEPARATOR '|||') as rectificacion_observaciones
          FROM liquidaciones l
          LEFT JOIN arbitros a ON l.arbitro_id = a.id
          LEFT JOIN liquidaciones_partidos lp ON l.id = lp.liquidacion_id
          LEFT JOIN rectificaciones_liquidaciones r ON l.id = r.liquidacion_id
          GROUP BY l.id
          ORDER BY l.fecha_creacion DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener árbitros para el formulario
$query = "SELECT * FROM arbitros ORDER BY nombre, apellidos";
$stmt = $conn->prepare($query);
$stmt->execute();
$arbitros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener total de rectificaciones pendientes para mostrar en el botón
$query = "SELECT COUNT(*) as total_pendientes FROM rectificaciones_liquidaciones WHERE estado = 'pendiente'";
$stmt = $conn->prepare($query);
$stmt->execute();
$rectificaciones_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total_pendientes'];

function createLiquidacion($conn, $data) {
    try {
        $conn->beginTransaction();
        
        $query = "INSERT INTO liquidaciones (arbitro_id, fecha_inicio, fecha_fin, numero_partidos, estado, observaciones) 
                  VALUES (?, ?, ?, 0, 'pendiente', ?)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $data['arbitro_id'],
            $data['fecha_inicio'],
            $data['fecha_fin'],
            sanitize_input($data['observaciones'])
        ]);
        
        $liquidacion_id = $conn->lastInsertId();
        
        $conn->commit();
        return success_message("Liquidación creada correctamente. ID: $liquidacion_id");
    } catch (Exception $e) {
        $conn->rollback();
        return error_message('Error al crear la liquidación: ' . $e->getMessage());
    }
}

function generarPartidosLiquidacion($conn, $data) {
    try {
        $conn->beginTransaction();
        
        $liquidacion_id = $data['liquidacion_id'];
        
        // Obtener datos de la liquidación
        $query = "SELECT arbitro_id, fecha_inicio, fecha_fin FROM liquidaciones WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$liquidacion_id]);
        $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$liquidacion) {
            throw new Exception("Liquidación no encontrada");
        }
        
        $arbitro_id = $liquidacion['arbitro_id'];
        $fecha_inicio = $liquidacion['fecha_inicio'];
        $fecha_fin = $liquidacion['fecha_fin'];
        
        // Limpiar partidos existentes de esta liquidación
        $query = "DELETE FROM liquidaciones_partidos WHERE liquidacion_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$liquidacion_id]);
        
        // Obtener partidos del árbitro en el rango de fechas
        $query = "SELECT p.*, 
                         el.nombre as equipo_local,
                         ev.nombre as equipo_visitante,
                         CASE 
                             WHEN p.arbitro_principal_id = ? THEN '1º Árbitro'
                             WHEN p.arbitro_segundo_id = ? THEN '2º Árbitro'
                             WHEN p.anotador_id = ? THEN 'Anotador'
                         END as rol
                  FROM partidos p
                  LEFT JOIN equipos el ON p.equipo_local_id = el.id
                  LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
                  WHERE (p.arbitro_principal_id = ? OR p.arbitro_segundo_id = ? OR p.anotador_id = ?)
                    AND p.fecha BETWEEN ? AND ?
                  ORDER BY p.fecha";
        $stmt = $conn->prepare($query);
        $stmt->execute([$arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id, $fecha_inicio, $fecha_fin]);
        $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insertar partidos en la liquidación
        foreach ($partidos as $partido) {
            $query = "INSERT INTO liquidaciones_partidos 
                      (liquidacion_id, partido_id, rol_arbitro, importe_partido, importe_dieta, importe_kilometraje) 
                      VALUES (?, ?, ?, 0, 0, 0)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$liquidacion_id, $partido['id'], $partido['rol']]);
        }
        
        $conn->commit();
        return success_message("Se cargaron " . count($partidos) . " partidos en la liquidación");
    } catch (Exception $e) {
        $conn->rollback();
        return error_message('Error al generar partidos: ' . $e->getMessage());
    }
}

function editLiquidacion($conn, $data) {
    try {
        $query = "UPDATE liquidaciones SET 
                    estado = ?, observaciones = ?
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $data['estado'],
            sanitize_input($data['observaciones']),
            $data['liquidacion_id']
        ]);
        
        return success_message('Liquidación actualizada correctamente');
    } catch (Exception $e) {
        return error_message('Error al actualizar la liquidación');
    }
}

function deleteLiquidacion($conn, $liquidacion_id) {
    try {
        $conn->beginTransaction();
        
        // Eliminar partidos de la liquidación
        $query = "DELETE FROM liquidaciones_partidos WHERE liquidacion_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$liquidacion_id]);
        
        // Eliminar liquidación
        $query = "DELETE FROM liquidaciones WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$liquidacion_id]);
        
        $conn->commit();
        return success_message('Liquidación eliminada correctamente');
    } catch (Exception $e) {
        $conn->rollback();
        return error_message('Error al eliminar la liquidación');
    }
}

function gestionarRectificacion($conn, $data) {
    try {
        $query = "UPDATE rectificaciones_liquidaciones SET 
                    estado = ?, respuesta_admin = ?, fecha_respuesta = NOW()
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $data['estado'],
            sanitize_input($data['respuesta_admin']),
            $data['rectificacion_id']
        ]);
        
        $accion = $data['estado'] == 'aprobada' ? 'aprobada' : 'rechazada';
        return success_message("Rectificación $accion correctamente");
    } catch (Exception $e) {
        return error_message('Error al gestionar la rectificación: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Liquidaciones - FEDEXVB</title>
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
                    <span>FEDEXVB - Administrador</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <div class="user-name"><?php echo $_SESSION['user_name'] . ' ' . $_SESSION['user_lastname']; ?></div>
                        <div class="user-role">Administrador</div>
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
            <li><a href="usuarios.php"><i class="fas fa-users"></i> Gestión de Usuarios</a></li>
            <li><a href="partidos.php"><i class="fas fa-calendar-alt"></i> Gestión de Partidos</a></li>
            <li><a href="arbitros.php"><i class="fa-solid fa-person"></i> Gestión de Árbitros</a></li>
            <li><a href="clubes.php"><i class="fas fa-building"></i> Gestión de Clubes</a></li>
            <li><a href="licencias.php"><i class="fas fa-id-card"></i> Gestión de Licencias</a></li>
            <li><a href="liquidaciones.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Liquidaciones</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Gestión de Liquidaciones</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> <a href="dashboard.php">Inicio</a> / Liquidaciones
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Botón crear liquidación -->
        <div class="mb-3">
            <button onclick="openModal('createLiquidacionModal')" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nueva Liquidación
            </button>
        </div>

        <!-- Lista de liquidaciones -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Lista de Liquidaciones
                <span class="badge" style="background: var(--primary-green); color: white; margin-left: 10px;">
                    <span id="total-count"><?php echo count($liquidaciones); ?></span> liquidaciones
                </span>
            </div>
            <div class="card-body">
                <!-- Barra de búsqueda -->
                <div class="search-container">
                    <div class="search-input-group">
                        <div class="search-input-icon">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   id="searchInput" 
                                   placeholder="Buscar por árbitro, período, estado, total..." 
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
                        <em>Busca por nombre del árbitro, período, estado o total. Usa ESC para limpiar.</em>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table data-table searchable-table" id="liquidacionesTable">
                        <thead>
                            <tr>
                                <th data-sortable>Árbitro</th>
                                <th data-sortable>Período</th>
                                <th data-sortable>Partidos</th>
                                <th data-sortable>Total</th>
                                <th data-sortable>Estado</th>
                                <th>Rectificaciones</th>
                                <th data-sortable>Fecha Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liquidaciones as $liquidacion): ?>
                            <tr>
                                <td><strong><?php echo $liquidacion['arbitro_nombre']; ?></strong></td>
                                <td>
                                    <?php echo format_date($liquidacion['fecha_inicio']); ?>
                                    <br>hasta<br>
                                    <?php echo format_date($liquidacion['fecha_fin']); ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--info);">
                                        <?php echo $liquidacion['partidos_contabilizados']; ?> partidos
                                    </span>
                                    <?php if ($liquidacion['total_partidos_registrados'] > 0): ?>
                                        <br><small style="color: var(--medium-gray);">
                                            (<?php echo $liquidacion['total_partidos_registrados']; ?> registrados)
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: var(--success);">
                                        €<?php echo number_format($liquidacion['total_importe_calculado'], 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge" style="background: 
                                        <?php 
                                        echo $liquidacion['estado'] == 'pagada' ? 'var(--success)' : 
                                             ($liquidacion['estado'] == 'pendiente' ? 'var(--warning)' : 'var(--error)'); 
                                        ?>">
                                        <?php echo ucfirst($liquidacion['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($liquidacion['total_rectificaciones'] > 0): ?>
                                        <span class="badge" style="background: 
                                            <?php echo $liquidacion['rectificaciones_pendientes'] > 0 ? 'var(--warning)' : 'var(--info)'; ?>">
                                            <?php 
                                            if ($liquidacion['rectificaciones_pendientes'] > 0) {
                                                echo $liquidacion['rectificaciones_pendientes'] . ' pendiente' . ($liquidacion['rectificaciones_pendientes'] > 1 ? 's' : '');
                                            } else {
                                                echo $liquidacion['total_rectificaciones'] . ' gestionada' . ($liquidacion['total_rectificaciones'] > 1 ? 's' : '');
                                            }
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--medium-gray); font-size: 0.9em;">Sin rectificaciones</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo format_date($liquidacion['fecha_creacion']); ?></td>
                                <td>
                                    <button onclick="verDetalles(<?php echo $liquidacion['id']; ?>)" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                    <button onclick="editLiquidacion(<?php echo $liquidacion['id']; ?>)" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteLiquidacion(<?php echo $liquidacion['id']; ?>)" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                
                                    
                                    <?php if ($liquidacion['rectificaciones_pendientes'] > 0): ?>
                                    <br style="margin-bottom: 5px;">
                                    <?php 
                                    // Procesar los datos concatenados para obtener solo el primer registro
                                    $rectificacion_id = explode(',', $liquidacion['rectificacion_pendiente_id'])[0];
                                    $motivo = explode('|||', $liquidacion['rectificacion_motivo'])[0];
                                    $observaciones = explode('|||', $liquidacion['rectificacion_observaciones'])[0];
                                    ?>
                                    <button class="btn btn-warning btn-sm rectificacion-btn" 
                                            style="width: 100%; margin-top: 5px;"
                                            data-rectificacion-id="<?php echo $rectificacion_id; ?>"
                                            data-arbitro-nombre="<?php echo htmlspecialchars($liquidacion['arbitro_nombre']); ?>"
                                            data-periodo="Del <?php echo format_date($liquidacion['fecha_inicio']); ?> al <?php echo format_date($liquidacion['fecha_fin']); ?>"
                                            data-motivo="<?php echo htmlspecialchars($motivo); ?>"
                                            data-observaciones="<?php echo htmlspecialchars($observaciones); ?>">
                                        <i class="fas fa-exclamation-triangle"></i> Gestionar Rectificación
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Crear Liquidación -->
    <div id="createLiquidacionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Nueva Liquidación</h2>
                <span class="close" onclick="closeModal('createLiquidacionModal')">&times;</span>
            </div>
            <form method="POST" class="validate-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label class="form-label">Árbitro</label>
                        <select name="arbitro_id" class="form-control" required>
                            <option value="">Seleccione un árbitro</option>
                            <?php foreach ($arbitros as $arbitro): ?>
                            <option value="<?php echo $arbitro['id']; ?>">
                                <?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" name="fecha_fin" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Observaciones adicionales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createLiquidacionModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Liquidación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalles Liquidación -->
    <div id="detallesLiquidacionModal" class="modal">
        <div class="modal-content" style="max-width: 90%; width: 1000px;">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> Detalles de Liquidación</h2>
                <span class="close" onclick="closeModal('detallesLiquidacionModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="detallesContent">
                    <!-- Se cargará dinámicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('detallesLiquidacionModal')">Cerrar</button>
                <button type="button" class="btn btn-success" onclick="generarPartidosActual()">
                    <i class="fas fa-sync"></i> Cargar Partidos
                </button>
                <button type="button" class="btn btn-primary" onclick="guardarImportes()">
                    <i class="fas fa-save"></i> Guardar Importes
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Editar Liquidación -->
    <div id="editLiquidacionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Editar Liquidación</h2>
                <span class="close" onclick="closeModal('editLiquidacionModal')">&times;</span>
            </div>
            <form method="POST" class="validate-form" id="editLiquidacionForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="liquidacion_id" id="edit_liquidacion_id">
                    
                    <div class="form-group">
                        <label class="form-label">Árbitro</label>
                        <input type="text" id="edit_arbitro_nombre" class="form-control" readonly style="background-color: var(--light-gray);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="estado" id="edit_estado" class="form-control" required>
                            <option value="pendiente">Pendiente</option>
                            <option value="pagada">Pagada</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="edit_observaciones" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editLiquidacionModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Rectificaciones -->
    <div id="rectificacionesModal" class="modal">
        <div class="modal-content" style="max-width: 95%; width: 1200px;">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Gestión de Rectificaciones</h2>
                <span class="close" onclick="closeModal('rectificacionesModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="rectificacionesContent">
                    <!-- Se cargará dinámicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rectificacionesModal')">Cerrar</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        let liquidacionActual = null;

        // Debug - verificar que el JavaScript se carga
        console.log('Script de liquidaciones cargado correctamente');
        
        // Función de prueba
        window.testFunction = function() {
            alert('¡La función de JavaScript funciona!');
        };

        // Validación del formulario de nueva liquidación
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#createLiquidacionModal form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const fechaInicio = document.querySelector('input[name="fecha_inicio"]').value;
                    const fechaFin = document.querySelector('input[name="fecha_fin"]').value;
                    
                    if (new Date(fechaInicio) > new Date(fechaFin)) {
                        e.preventDefault();
                        showNotification('La fecha de inicio no puede ser posterior a la fecha fin', 'error');
                        return;
                    }
                });
            }
        });

        function verDetalles(liquidacionId) {
            console.log('Función verDetalles llamada con ID:', liquidacionId);
            liquidacionActual = liquidacionId;
            
            fetch(`api/liquidaciones.php?id=${liquidacionId}`)
                .then(response => {
                    console.log('Response recibida:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Data recibida:', data);
                    if (!data) {
                        alert('No se encontraron datos de la liquidación');
                        return;
                    }
                    
                    let html = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h4>Información General</h4>
                                <p><strong>Árbitro:</strong> ${data.arbitro_nombre || 'N/A'}</p>
                                <p><strong>Período:</strong> ${data.fecha_inicio || 'N/A'} al ${data.fecha_fin || 'N/A'}</p>
                                <p><strong>Estado:</strong> ${data.estado || 'N/A'}</p>
                                <p><strong>Observaciones:</strong> ${data.observaciones || 'Sin observaciones'}</p>
                            </div>
                            <div class="col-md-6">
                                <h4>Resumen de Liquidación</h4>
                                <p><strong>Partidos Contabilizados:</strong> ${data.numero_partidos || 0}</p>
                                <p><strong>Partidos Registrados:</strong> ${(data.partidos && data.partidos.length) || 0}</p>
                                <p><strong>Total Calculado:</strong> €${data.total_importe || '0.00'}</p>
                            </div>
                        </div>
                        
                        <h5>Partidos y Conceptos Detallados</h5>
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
                    
                    if (data.partidos && data.partidos.length > 0) {
                        data.partidos.forEach((partido, index) => {
                            html += `
                                <tr>
                                    <td>${partido.fecha || 'N/A'}</td>
                                    <td>${partido.equipos || 'N/A'}</td>
                                    <td>${partido.rol_arbitro || 'N/A'}</td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               value="${partido.importe_partido || 0}" 
                                               onchange="calcularTotal(${index})"
                                               data-index="${index}" data-field="partido" step="0.01">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               value="${partido.importe_dieta || 0}" 
                                               onchange="calcularTotal(${index})"
                                               data-index="${index}" data-field="dieta" step="0.01">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               value="${partido.importe_kilometraje || 0}" 
                                               onchange="calcularTotal(${index})"
                                               data-index="${index}" data-field="kilometraje" step="0.01">
                                    </td>
                                    <td id="total-${index}" class="font-weight-bold">
                                        €${((parseFloat(partido.importe_partido) || 0) + 
                                            (parseFloat(partido.importe_dieta) || 0) + 
                                            (parseFloat(partido.importe_kilometraje) || 0)).toFixed(2)}
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html += '<tr><td colspan="7" class="text-center">No hay partidos registrados</td></tr>';
                    }
                    
                    html += `
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td colspan="6">TOTAL GENERAL</td>
                                        <td id="total-general">€${data.total_importe || '0.00'}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    `;
                    
                    document.getElementById('detallesContent').innerHTML = html;
                    openModal('detallesLiquidacionModal');
                })
                .catch(error => {
                    console.error('Error completo:', error);
                    alert('Error al cargar detalles: ' + error.message);
                });
        }

        function calcularTotal(index) {
            const partido = parseFloat(document.querySelector(`[data-index="${index}"][data-field="partido"]`).value) || 0;
            const dieta = parseFloat(document.querySelector(`[data-index="${index}"][data-field="dieta"]`).value) || 0;
            const kilometraje = parseFloat(document.querySelector(`[data-index="${index}"][data-field="kilometraje"]`).value) || 0;
            
            const total = partido + dieta + kilometraje;
            document.getElementById(`total-${index}`).textContent = `€${total.toFixed(2)}`;
            
            // Calcular total general
            const todosLosTotales = document.querySelectorAll('[id^="total-"]:not(#total-general)');
            let totalGeneral = 0;
            todosLosTotales.forEach(elemento => {
                const valor = parseFloat(elemento.textContent.replace('€', '')) || 0;
                totalGeneral += valor;
            });
            
            document.getElementById('total-general').textContent = `€${totalGeneral.toFixed(2)}`;
        }

        function generarPartidosActual() {
            if (!liquidacionActual) return;
            
            if (confirm('¿Cargar partidos para esta liquidación? Esto eliminará los datos existentes.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="generar_partidos">
                    <input type="hidden" name="liquidacion_id" value="${liquidacionActual}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function guardarImportes() {
            if (!liquidacionActual) return;
            
            const importes = [];
            const inputs = document.querySelectorAll('[data-index]');
            
            inputs.forEach(input => {
                const index = input.getAttribute('data-index');
                const field = input.getAttribute('data-field');
                const value = parseFloat(input.value) || 0;
                
                if (!importes[index]) {
                    importes[index] = {};
                }
                importes[index][field] = value;
            });
            
            fetch('api/liquidaciones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'actualizar_importes',
                    liquidacion_id: liquidacionActual,
                    importes: importes
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Importes actualizados correctamente', 'success');
                } else {
                    showNotification('Error al actualizar importes', 'error');
                }
            })
            .catch(error => {
                showNotification('Error de conexión', 'error');
            });
        }

        function editLiquidacion(liquidacionId) {
            console.log('Función editLiquidacion llamada con ID:', liquidacionId);
            fetch(`api/liquidaciones.php?id=${liquidacionId}`)
                .then(response => {
                    console.log('Response recibida:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Data recibida:', data);
                    if (data && data.id) {
                        document.getElementById('edit_liquidacion_id').value = data.id;
                        document.getElementById('edit_arbitro_nombre').value = data.arbitro_nombre || 'N/A';
                        document.getElementById('edit_estado').value = data.estado || 'pendiente';
                        document.getElementById('edit_observaciones').value = data.observaciones || '';
                        
                        openModal('editLiquidacionModal');
                    } else {
                        alert('No se encontraron datos de la liquidación');
                    }
                })
                .catch(error => {
                    console.error('Error completo:', error);
                    alert('Error al cargar datos de la liquidación: ' + error.message);
                });
        }

        function deleteLiquidacion(liquidacionId) {
            console.log('Función deleteLiquidacion llamada con ID:', liquidacionId);
            if (confirm('¿Está seguro de eliminar esta liquidación? Esta acción no se puede deshacer.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="liquidacion_id" value="${liquidacionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function generarPDF(liquidacionId) {
            window.open(`pdf/liquidacion.php?id=${liquidacionId}`, '_blank');
        }

        function gestionarRectificacionDirecta(rectificacionId, arbitroNombre, periodoLiquidacion, motivo, observaciones) {
            console.log('Gestionando rectificación directa:', rectificacionId);
            
            const data = {
                id: rectificacionId,
                motivo: motivo || 'Sin motivo especificado',
                observaciones: observaciones || 'Sin observaciones adicionales', 
                arbitro_nombre: arbitroNombre || 'Árbitro desconocido',
                periodo_liquidacion: periodoLiquidacion || 'Periodo no especificado'
            };
            
            openModal('rectificacionesModal');
            mostrarModalRectificacion(data);
        }

        function verRectificaciones() {
            alert('Botón Ver Rectificaciones clickeado');
            console.log('Función verRectificaciones llamada');
            cargarRectificaciones();
            openModal('rectificacionesModal');
        }

        function cargarRectificaciones() {
            console.log('Cargando rectificaciones...');
            document.getElementById('rectificacionesContent').innerHTML = '<div style="text-align: center; padding: 20px;">Cargando rectificaciones...</div>';
            
            fetch('api/liquidaciones.php?action=rectificaciones')
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    let html = '';
                    
                    if (!data || data.length === 0) {
                        html = `
                            <div class="text-center" style="padding: 40px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: var(--success); margin-bottom: 15px;"></i>
                                <h4>No hay rectificaciones</h4>
                                <p style="color: var(--medium-gray);">No se encontraron rectificaciones en el sistema</p>
                                <button onclick="testGestionarRectificacion()" class="btn btn-primary">Test Gestionar</button>
                            </div>
                        `;
                    } else {
                        html = `
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Fecha Solicitud</th>
                                            <th>Árbitro</th>
                                            <th>Liquidación</th>
                                            <th>Motivo</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        data.forEach(rectificacion => {
                            console.log('Procesando rectificación:', rectificacion);
                            const estadoColor = rectificacion.estado === 'aprobada' ? 'var(--success)' : 
                                              (rectificacion.estado === 'rechazada' ? 'var(--error)' : 'var(--warning)');
                            
                            html += `
                                <tr>
                                    <td>${rectificacion.fecha_solicitud || 'N/A'}</td>
                                    <td><strong>${rectificacion.arbitro_nombre || 'N/A'}</strong></td>
                                    <td>
                                        <small>${rectificacion.periodo_liquidacion || 'N/A'}</small><br>
                                        <small style="color: var(--medium-gray);">ID: ${rectificacion.liquidacion_id}</small>
                                    </td>
                                    <td>
                                        <strong>${rectificacion.motivo}</strong>
                                        ${rectificacion.observaciones ? `<br><small style="color: var(--medium-gray);">${rectificacion.observaciones.substring(0, 50)}${rectificacion.observaciones.length > 50 ? '...' : ''}</small>` : ''}
                                    </td>
                                    <td>
                                        <span class="badge" style="background: ${estadoColor}">
                                            ${rectificacion.estado.charAt(0).toUpperCase() + rectificacion.estado.slice(1)}
                                        </span>
                                        ${rectificacion.fecha_respuesta ? `<br><small style="color: var(--medium-gray);">Resp: ${rectificacion.fecha_respuesta}</small>` : ''}
                                    </td>
                                    <td>
                                        ${rectificacion.estado === 'pendiente' ? 
                                            `<button onclick="gestionarRectificacion(${rectificacion.id})" class="btn btn-primary btn-sm">
                                                <i class="fas fa-cog"></i> Gestionar
                                            </button>` : 
                                            `<button onclick="verDetalleRectificacion(${rectificacion.id})" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i> Ver Detalle
                                            </button>`
                                        }
                                    </td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                    
                    document.getElementById('rectificacionesContent').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error al cargar rectificaciones:', error);
                    document.getElementById('rectificacionesContent').innerHTML = `
                        <div class="text-center" style="padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: var(--error); margin-bottom: 15px;"></i>
                            <h4>Error al cargar rectificaciones</h4>
                            <p style="color: var(--medium-gray);">Error: ${error.message}</p>
                            <button onclick="testGestionarRectificacion()" class="btn btn-primary">Test Gestionar</button>
                        </div>
                    `;
                });
        }

        // Función de test
        function testGestionarRectificacion() {
            alert('Test function called');
            gestionarRectificacion(1);
        }

        function mostrarModalRectificacion(data) {
            if (!data || !data.id) {
                showNotification('Error: Datos de rectificación inválidos', 'error');
                return;
            }
            
            const contentElement = document.getElementById('rectificacionesContent');
            if (!contentElement) {
                showNotification('Error: No se pudo encontrar el contenedor del modal', 'error');
                return;
            }
            
            const motivo = data.motivo || 'Sin motivo especificado';
            const observaciones = data.observaciones || 'Sin observaciones adicionales';
            const arbitro = data.arbitro_nombre || 'Árbitro desconocido';
            const periodo = data.periodo_liquidacion || 'Periodo no especificado';

            const modalContent = `
                <div style="max-width: 600px; margin: 0 auto; text-align: left; background: white; padding: 20px; border-radius: 8px;">
                    <h3 style="color: #2E7D32; margin-bottom: 20px; text-align: center;">
                        <i class="fas fa-exclamation-triangle"></i> Rectificación Solicitada
                    </h3>
                    
                    <div style="background: #F5F5F5; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <p><strong>Árbitro:</strong> ${arbitro}</p>
                        <p><strong>Liquidación:</strong> ${periodo}</p>
                    </div>

                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <h4 style="color: #8b7300; margin: 0 0 10px 0;">
                            <i class="fas fa-info-circle"></i> Motivo de la rectificación:
                        </h4>
                        <p style="color: #8b7300; margin: 0; font-weight: 500;">${motivo}</p>
                    </div>

                    <div style="background: #e3f2fd; border: 1px solid #bbdefb; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
                        <h4 style="color: #1565c0; margin: 0 0 10px 0;">
                            <i class="fas fa-comment"></i> Observaciones del árbitro:
                        </h4>
                        <p style="color: #1565c0; margin: 0; line-height: 1.5;">${observaciones}</p>
                    </div>

                    <div style="text-align: center;">
                        <button onclick="procesarRectificacion(${data.id}, 'aprobada')" 
                                class="btn btn-success" style="margin-right: 15px; padding: 12px 25px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-check"></i> Solucionar
                        </button>
                        <button onclick="procesarRectificacion(${data.id}, 'rechazada')" 
                                class="btn btn-danger" style="padding: 12px 25px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-times"></i> Denegar
                        </button>
                    </div>
                </div>
            `;

            contentElement.innerHTML = modalContent;
        }

        function procesarRectificacion(rectificacionId, decision) {
            const respuesta = prompt(
                decision === 'aprobada' 
                    ? 'Escriba una respuesta explicando cómo se solucionará la rectificación:' 
                    : 'Escriba el motivo por el cual se deniega la rectificación:'
            );

            if (respuesta === null) {
                return; // Usuario canceló
            }

            if (respuesta.trim() === '') {
                alert('Debe escribir una respuesta.');
                return;
            }

            // Mostrar mensaje de procesando
            document.getElementById('rectificacionesContent').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: var(--primary-green); margin-bottom: 15px;"></i>
                    <h4>Procesando rectificación...</h4>
                </div>
            `;

            // Enviar la decisión
            const formData = new FormData();
            formData.append('action', 'gestionar_rectificacion');
            formData.append('rectificacion_id', rectificacionId);
            formData.append('estado', decision);
            formData.append('respuesta_admin', respuesta);

            fetch('liquidaciones.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Recargar la página para mostrar el mensaje de éxito
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al procesar la rectificación', 'error');
                cargarRectificaciones(); // Volver a cargar la lista
            });
        }

        function gestionarRectificacion(rectificacionId) {
            console.log('Gestionando rectificación ID:', rectificacionId);
            
            fetch(`api/liquidaciones.php?action=rectificacion_detalle&id=${rectificacionId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    if (data && data.id) {
                        // Mostrar información de la rectificación con botones de acción
                        mostrarModalRectificacion(data);
                    } else {
                        console.error('Datos inválidos recibidos:', data);
                        showNotification('No se encontraron datos válidos de la rectificación', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al cargar datos de la rectificación: ' + error.message, 'error');
                });
        }

        function verDetalleRectificacion(rectificacionId) {
            fetch(`api/liquidaciones.php?action=rectificacion_detalle&id=${rectificacionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        let detalleHtml = `
                            <h4>Detalle de Rectificación</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Árbitro:</strong> ${data.arbitro_nombre}</p>
                                    <p><strong>Liquidación:</strong> ${data.periodo_liquidacion}</p>
                                    <p><strong>Fecha Solicitud:</strong> ${data.fecha_solicitud}</p>
                                    <p><strong>Motivo:</strong> ${data.motivo}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Estado:</strong> 
                                        <span class="badge" style="background: ${data.estado === 'aprobada' ? 'var(--success)' : 'var(--error)'}">
                                            ${data.estado.charAt(0).toUpperCase() + data.estado.slice(1)}
                                        </span>
                                    </p>
                                    <p><strong>Fecha Respuesta:</strong> ${data.fecha_respuesta || 'N/A'}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <p><strong>Observaciones del Árbitro:</strong></p>
                                    <div style="background: var(--light-gray); padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                                        ${data.observaciones || 'Sin observaciones'}
                                    </div>
                                    <p><strong>Respuesta del Administrador:</strong></p>
                                    <div style="background: var(--light-gray); padding: 10px; border-radius: 5px;">
                                        ${data.respuesta_admin || 'Sin respuesta'}
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('rectificacionesContent').innerHTML = detalleHtml;
                    }
                })
                .catch(error => {
                    showNotification('Error al cargar detalle de la rectificación', 'error');
                });
        }

        function testDirecto() {
            alert('Test directo funcionando');
            
            // Crear datos de prueba directamente
            const dataPrueba = {
                id: 1,
                motivo: 'Error en dietas de kilometraje',
                observaciones: 'Las dietas del partido del 15/01 no se calcularon correctamente. Debería ser 25€ en lugar de 15€. He revisado la documentación y confirmo que el importe está mal calculado.',
                arbitro_nombre: 'Juan Pérez García',
                periodo_liquidacion: 'Del 01/01/2024 al 31/01/2024'
            };
            
            openModal('rectificacionesModal');
            mostrarModalRectificacion(dataPrueba);
        }
    </script>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda para la tabla de liquidaciones
        document.addEventListener('DOMContentLoaded', function() {
            new TableSearchBar({
                searchInputId: 'searchInput',
                clearBtnId: 'searchClear',
                searchInfoId: 'searchInfo',
                searchResultsId: 'searchResults',
                totalCountId: 'total-count',
                tableSelector: 'tbody tr',
                columnsCount: 8,
                noResultsId: 'noResultsRow'
            });

            // Event listener para botones de rectificación
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('rectificacion-btn') || e.target.closest('.rectificacion-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const btn = e.target.classList.contains('rectificacion-btn') ? e.target : e.target.closest('.rectificacion-btn');
                    
                    if (btn) {
                        const data = {
                            id: btn.getAttribute('data-rectificacion-id'),
                            arbitro_nombre: btn.getAttribute('data-arbitro-nombre'),
                            periodo_liquidacion: btn.getAttribute('data-periodo'),
                            motivo: btn.getAttribute('data-motivo'),
                            observaciones: btn.getAttribute('data-observaciones')
                        };
                        
                        openModal('rectificacionesModal');
                        mostrarModalRectificacion(data);
                    }
                }
            });
        });
    </script>
</body>
</html>
