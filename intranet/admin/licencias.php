<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

$message = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $arbitro_id = sanitize_input($_POST['arbitro_id']);
                    $fecha_curso = sanitize_input($_POST['fecha_curso']);
                    $lugar_curso = sanitize_input($_POST['lugar_curso']);
                    $fecha_inicio = sanitize_input($_POST['fecha_inicio']);
                    $fecha_vencimiento = sanitize_input($_POST['fecha_vencimiento']);
                    $nivel_licencia = sanitize_input($_POST['nivel_licencia']);
                    $observaciones = sanitize_input($_POST['observaciones']);

                    // Validaciones
                    if (empty($arbitro_id) || empty($fecha_curso) || empty($lugar_curso) || 
                        empty($fecha_inicio) || empty($fecha_vencimiento) || empty($nivel_licencia)) {
                        throw new Exception('Todos los campos obligatorios deben ser completados');
                    }

                    if (strtotime($fecha_vencimiento) <= strtotime($fecha_inicio)) {
                        throw new Exception('La fecha de vencimiento debe ser posterior a la fecha de inicio');
                    }

                    // Desactivar licencias anteriores del mismo árbitro
                    $updateQuery = "UPDATE licencias_arbitros SET activa = 0 WHERE arbitro_id = ? AND activa = 1";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->execute([$arbitro_id]);

                    // Insertar nueva licencia
                    $query = "INSERT INTO licencias_arbitros 
                             (arbitro_id, fecha_curso, lugar_curso, fecha_inicio, fecha_vencimiento, 
                              nivel_licencia, observaciones, activa) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$arbitro_id, $fecha_curso, $lugar_curso, $fecha_inicio, 
                                   $fecha_vencimiento, $nivel_licencia, $observaciones]);

                    // Actualizar nivel de licencia en tabla árbitros
                    $updateArbitroQuery = "UPDATE arbitros SET licencia = ? WHERE id = ?";
                    $updateArbitroStmt = $conn->prepare($updateArbitroQuery);
                    $updateArbitroStmt->execute([$nivel_licencia, $arbitro_id]);

                    $message = success_message('Licencia registrada correctamente');
                    break;

                case 'edit':
                    $id = sanitize_input($_POST['id']);
                    $arbitro_id = sanitize_input($_POST['arbitro_id']);
                    $fecha_curso = sanitize_input($_POST['fecha_curso']);
                    $lugar_curso = sanitize_input($_POST['lugar_curso']);
                    $fecha_inicio = sanitize_input($_POST['fecha_inicio']);
                    $fecha_vencimiento = sanitize_input($_POST['fecha_vencimiento']);
                    $nivel_licencia = sanitize_input($_POST['nivel_licencia']);
                    $observaciones = sanitize_input($_POST['observaciones']);

                    if (strtotime($fecha_vencimiento) <= strtotime($fecha_inicio)) {
                        throw new Exception('La fecha de vencimiento debe ser posterior a la fecha de inicio');
                    }

                    $query = "UPDATE licencias_arbitros 
                             SET fecha_curso = ?, lugar_curso = ?, fecha_inicio = ?, 
                                 fecha_vencimiento = ?, nivel_licencia = ?, observaciones = ?
                             WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$fecha_curso, $lugar_curso, $fecha_inicio, 
                                   $fecha_vencimiento, $nivel_licencia, $observaciones, $id]);

                    // Actualizar nivel de licencia en tabla árbitros si es la licencia activa
                    $checkActiveQuery = "SELECT activa FROM licencias_arbitros WHERE id = ?";
                    $checkStmt = $conn->prepare($checkActiveQuery);
                    $checkStmt->execute([$id]);
                    $isActive = $checkStmt->fetchColumn();

                    if ($isActive) {
                        $updateArbitroQuery = "UPDATE arbitros SET licencia = ? WHERE id = ?";
                        $updateArbitroStmt = $conn->prepare($updateArbitroQuery);
                        $updateArbitroStmt->execute([$nivel_licencia, $arbitro_id]);
                    }

                    $message = success_message('Licencia actualizada correctamente');
                    break;

                case 'delete':
                    $id = sanitize_input($_POST['id']);
                    
                    $query = "DELETE FROM licencias_arbitros WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id]);

                    $message = success_message('Licencia eliminada correctamente');
                    break;
            }
        }
    } catch (Exception $e) {
        $error = error_message($e->getMessage());
    }
}

// Obtener licencias
$query = "SELECT l.*, a.nombre, a.apellidos, a.ciudad
          FROM licencias_arbitros l
          JOIN arbitros a ON l.arbitro_id = a.id
          ORDER BY l.fecha_creacion DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener árbitros para el formulario
$arbitrosQuery = "SELECT a.id, a.nombre, a.apellidos, a.ciudad, a.licencia 
                  FROM arbitros a 
                  JOIN usuarios u ON a.usuario_id = u.id 
                  WHERE u.activo = 1 
                  ORDER BY a.nombre, a.apellidos";
$arbitrosStmt = $conn->prepare($arbitrosQuery);
$arbitrosStmt->execute();
$arbitros = $arbitrosStmt->fetchAll(PDO::FETCH_ASSOC);

// Licencia para editar
$licenciaEdit = null;
if (isset($_GET['edit'])) {
    $editId = sanitize_input($_GET['edit']);
    $editQuery = "SELECT * FROM licencias_arbitros WHERE id = ?";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->execute([$editId]);
    $licenciaEdit = $editStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Licencias - FEDEXVB</title>
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
            <li><a href="licencias.php" class="active"><i class="fas fa-id-card"></i> Gestión de Licencias</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Liquidaciones</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-id-card"></i> Gestión de Licencias de Árbitros</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Gestión de Licencias
            </div>
        </div>

        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <?php echo $error; ?>
        <?php endif; ?>

        <!-- Formulario de licencia -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus"></i> <?php echo $licenciaEdit ? 'Editar Licencia' : 'Nueva Licencia'; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $licenciaEdit ? 'edit' : 'create'; ?>">
                    <?php if ($licenciaEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $licenciaEdit['id']; ?>">
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="arbitro_id">Árbitro *</label>
                            <select name="arbitro_id" id="arbitro_id" required class="form-control">
                                <option value="">Seleccionar árbitro</option>
                                <?php foreach ($arbitros as $arbitro): ?>
                                    <option value="<?php echo $arbitro['id']; ?>" 
                                            <?php echo ($licenciaEdit && $licenciaEdit['arbitro_id'] == $arbitro['id']) ? 'selected' : ''; ?>>
                                        <?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos'] . ' (' . $arbitro['ciudad'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="nivel_licencia">Nivel de Licencia *</label>
                            <select name="nivel_licencia" id="nivel_licencia" required class="form-control">
                                <option value="">Seleccionar nivel</option>
                                <option value="anotador" <?php echo ($licenciaEdit && $licenciaEdit['nivel_licencia'] == 'anotador') ? 'selected' : ''; ?>>Anotador</option>
                                <option value="n1" <?php echo ($licenciaEdit && $licenciaEdit['nivel_licencia'] == 'n1') ? 'selected' : ''; ?>>Nivel 1</option>
                                <option value="n2" <?php echo ($licenciaEdit && $licenciaEdit['nivel_licencia'] == 'n2') ? 'selected' : ''; ?>>Nivel 2</option>
                                <option value="n3" <?php echo ($licenciaEdit && $licenciaEdit['nivel_licencia'] == 'n3') ? 'selected' : ''; ?>>Nivel 3</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="fecha_curso">Fecha del Curso *</label>
                            <input type="date" name="fecha_curso" id="fecha_curso" required class="form-control"
                                   value="<?php echo $licenciaEdit ? $licenciaEdit['fecha_curso'] : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="lugar_curso">Lugar del Curso *</label>
                            <input type="text" name="lugar_curso" id="lugar_curso" required class="form-control"
                                   placeholder="Ciudad donde se realizó el curso"
                                   value="<?php echo $licenciaEdit ? htmlspecialchars($licenciaEdit['lugar_curso']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" required class="form-control"
                                   value="<?php echo $licenciaEdit ? $licenciaEdit['fecha_inicio'] : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="fecha_vencimiento">Fecha de Vencimiento *</label>
                            <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required class="form-control"
                                   value="<?php echo $licenciaEdit ? $licenciaEdit['fecha_vencimiento'] : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observaciones">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" class="form-control" rows="3"
                                  placeholder="Observaciones adicionales (opcional)"><?php echo $licenciaEdit ? htmlspecialchars($licenciaEdit['observaciones']) : ''; ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $licenciaEdit ? 'Actualizar' : 'Guardar'; ?>
                        </button>
                        <?php if ($licenciaEdit): ?>
                            <a href="licencias.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Listado de licencias -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Licencias Registradas
                <span class="badge" style="background: var(--primary-green); color: white; margin-left: 10px;">
                    <span id="total-count"><?php echo count($licencias); ?></span> licencias
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
                                   placeholder="Buscar por árbitro, nivel, lugar..." 
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
                        <span id="searchResults">0</span> licencia(s) encontrada(s)
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Busca por nombre del árbitro, nivel de licencia o lugar del curso. Usa ESC para limpiar.</em>
                    </div>
                </div>
                
                <?php if (count($licencias) > 0): ?>
                    <div class="table-responsive">
                        <table class="table data-table searchable-table" id="licenciasTable">
                            <thead>
                                <tr>
                                    <th data-sortable>Árbitro</th>
                                    <th data-sortable>Nivel</th>
                                    <th data-sortable>Fecha Curso</th>
                                    <th data-sortable>Lugar</th>
                                    <th>Vigencia</th>
                                    <th data-sortable>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($licencias as $licencia): ?>
                                    <?php
                                    $fechaVencimiento = new DateTime($licencia['fecha_vencimiento']);
                                    $hoy = new DateTime();
                                    $diasRestantes = $hoy->diff($fechaVencimiento)->days;
                                    $vencida = $fechaVencimiento < $hoy;
                                    $proximoVencimiento = !$vencida && $diasRestantes <= 30;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $licencia['nombre'] . ' ' . $licencia['apellidos']; ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $licencia['ciudad']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: var(--primary-green);">
                                                <?php echo strtoupper($licencia['nivel_licencia']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_date($licencia['fecha_curso']); ?></td>
                                        <td><?php echo htmlspecialchars($licencia['lugar_curso']); ?></td>
                                        <td>
                                            <small class="text-muted">Inicio:</small> <?php echo format_date($licencia['fecha_inicio']); ?><br>
                                            <small class="text-muted">Vence:</small> <?php echo format_date($licencia['fecha_vencimiento']); ?>
                                        </td>
                                        <td>
                                            <?php if ($vencida): ?>
                                                <span class="badge" style="background: var(--danger);">
                                                    <i class="fas fa-exclamation-triangle"></i> Vencida
                                                </span>
                                            <?php elseif ($proximoVencimiento): ?>
                                                <span class="badge" style="background: var(--warning);">
                                                    <i class="fas fa-clock"></i> Próxima a vencer
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: var(--success);">
                                                    <i class="fas fa-check"></i> Vigente
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($licencia['activa']): ?>
                                                <br><span class="badge" style="background: var(--info); font-size: 0.7rem;">Activa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="licencias.php?edit=<?php echo $licencia['id']; ?>" 
                                               class="btn btn-primary btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                    onclick="confirmDelete(<?php echo $licencia['id']; ?>)" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-id-card" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No hay licencias registradas</h4>
                        <p class="text-muted">Comience registrando la primera licencia de árbitro</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal de confirmación -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar esta licencia?</p>
                <p class="text-muted">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">
                        Cancelar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            openModal('deleteModal');
        }

        // Auto-calcular fecha de vencimiento (2 años después del curso por defecto)
        document.getElementById('fecha_curso').addEventListener('change', function() {
            const fechaCurso = new Date(this.value);
            if (fechaCurso) {
                const fechaInicio = new Date(fechaCurso);
                fechaInicio.setDate(fechaInicio.getDate() + 1); // Un día después del curso
                
                const fechaVencimiento = new Date(fechaCurso);
                fechaVencimiento.setFullYear(fechaVencimiento.getFullYear() + 2); // 2 años de vigencia
                
                document.getElementById('fecha_inicio').value = fechaInicio.toISOString().split('T')[0];
                document.getElementById('fecha_vencimiento').value = fechaVencimiento.toISOString().split('T')[0];
            }
        });
    </script>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda para la tabla de licencias
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>
