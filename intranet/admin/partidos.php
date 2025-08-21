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
            $message = createPartido($conn, $_POST);
            break;
        case 'edit':
            $message = editPartido($conn, $_POST);
            break;
        case 'delete':
            $message = deletePartido($conn, $_POST['partido_id']);
            break;
    }
}

// Obtener partidos
$query = "SELECT p.*, 
                 el.nombre as equipo_local_nombre,
                 ev.nombre as equipo_visitante_nombre,
                 c.nombre as categoria_nombre,
                 pab.nombre as pabellon_nombre,
                 pab.ciudad as pabellon_ciudad,
                 CONCAT(a1.nombre, ' ', a1.apellidos) as arbitro1_nombre,
                 CONCAT(a2.nombre, ' ', a2.apellidos) as arbitro2_nombre,
                 CONCAT(an.nombre, ' ', an.apellidos) as anotador_nombre
          FROM partidos p
          LEFT JOIN equipos el ON p.equipo_local_id = el.id
          LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
          LEFT JOIN categorias c ON p.categoria_id = c.id
          LEFT JOIN pabellones pab ON p.pabellon_id = pab.id
          LEFT JOIN arbitros a1 ON p.arbitro_principal_id = a1.id
          LEFT JOIN arbitros a2 ON p.arbitro_segundo_id = a2.id
          LEFT JOIN arbitros an ON p.anotador_id = an.id
          ORDER BY p.fecha DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener datos para los formularios
$query = "SELECT * FROM equipos ORDER BY nombre";
$stmt = $conn->prepare($query);
$stmt->execute();
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT * FROM categorias ORDER BY nombre";
$stmt = $conn->prepare($query);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT * FROM arbitros ORDER BY nombre, apellidos";
$stmt = $conn->prepare($query);
$stmt->execute();
$arbitros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT * FROM pabellones ORDER BY ciudad, nombre";
$stmt = $conn->prepare($query);
$stmt->execute();
$pabellones = $stmt->fetchAll(PDO::FETCH_ASSOC);

function createPartido($conn, $data) {
    try {
        $query = "INSERT INTO partidos (equipo_local_id, equipo_visitante_id, categoria_id, fecha, pabellon_id, arbitro_principal_id, arbitro_segundo_id, anotador_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            sanitize_input($data['equipo_local_id']),
            sanitize_input($data['equipo_visitante_id']),
            sanitize_input($data['categoria_id']),
            sanitize_input($data['fecha'] . ' ' . $data['hora']),
            sanitize_input($data['pabellon_id']),
            $data['arbitro_principal_id'] ?: null,
            $data['arbitro_segundo_id'] ?: null,
            $data['anotador_id'] ?: null
        ]);
        
        return success_message('Partido creado correctamente');
    } catch (Exception $e) {
        return error_message('Error al crear el partido: ' . $e->getMessage());
    }
}

function editPartido($conn, $data) {
    try {
        $query = "UPDATE partidos SET 
                    equipo_local_id = ?, equipo_visitante_id = ?, categoria_id = ?, 
                    fecha = ?, pabellon_id = ?, 
                    arbitro_principal_id = ?, arbitro_segundo_id = ?, anotador_id = ?
                  WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            sanitize_input($data['equipo_local_id']),
            sanitize_input($data['equipo_visitante_id']),
            sanitize_input($data['categoria_id']),
            sanitize_input($data['fecha'] . ' ' . $data['hora']),
            sanitize_input($data['pabellon_id']),
            $data['arbitro_principal_id'] ?: null,
            $data['arbitro_segundo_id'] ?: null,
            $data['anotador_id'] ?: null,
            $data['partido_id']
        ]);
        
        return success_message('Partido actualizado correctamente');
    } catch (Exception $e) {
        return error_message('Error al actualizar el partido: ' . $e->getMessage());
    }
}

function deletePartido($conn, $partido_id) {
    try {
        $query = "DELETE FROM partidos WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$partido_id]);
        
        return success_message('Partido eliminado correctamente');
    } catch (Exception $e) {
        return error_message('Error al eliminar el partido');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Partidos - FEDEXVB</title>
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
            <li><a href="partidos.php" class="active"><i class="fas fa-calendar-alt"></i> Gestión de Partidos</a></li>
            <li><a href="arbitros.php"><i class="fa-solid fa-person"></i> Gestión de Árbitros</a></li>
            <li><a href="clubes.php"><i class="fas fa-building"></i> Gestión de Clubes</a></li>
            <li><a href="licencias.php"><i class="fas fa-id-card"></i> Gestión de Licencias</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Liquidaciones</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-calendar-alt"></i> Gestión de Partidos</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> <a href="dashboard.php">Inicio</a> / Gestión de Partidos
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Botón crear partido -->
        <div class="mb-3">
            <button onclick="openModal('createPartidoModal')" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Crear Partido
            </button>
        </div>

        <!-- Lista de partidos -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Lista de Partidos
                <span class="badge" style="background: var(--primary-green); color: white; margin-left: 10px;">
                    <span id="total-count"><?php echo count($partidos); ?></span> partidos
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
                                   placeholder="Buscar por equipos, categoría, pabellón, árbitros, fecha..." 
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
                        <span id="searchResults">0</span> partido(s) encontrado(s)
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Busca por equipos, categoría, pabellón, árbitros o fecha. Usa ESC para limpiar.</em>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table data-table searchable-table" id="partidosTable">
                        <thead>
                            <tr>
                                <th data-sortable>Fecha</th>
                                <th data-sortable>Hora</th>
                                <th data-sortable>Equipos</th>
                                <th data-sortable>Resultado</th>
                                <th data-sortable>Categoría</th>
                                <th data-sortable>Pabellón</th>
                                <th>Árbitros</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidos as $partido): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($partido['fecha'])); ?></td>
                                <td><?php echo date('H:i', strtotime($partido['fecha'])); ?></td>
                                <td>
                                    <strong><?php echo $partido['equipo_local_nombre']; ?></strong>
                                    <br>vs<br>
                                    <strong><?php echo $partido['equipo_visitante_nombre']; ?></strong>
                                </td>
                                <td>
                                    <?php if ($partido['sets_local'] !== null && $partido['sets_visitante'] !== null): ?>
                                        <span class="badge" style="background: var(--success);">
                                            <?php echo $partido['sets_local']; ?> - <?php echo $partido['sets_visitante']; ?>
                                        </span>
                                        <br>
                                        <small style="color: var(--success);">Finalizado</small>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--warning);">
                                            Sin resultado
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--info);">
                                        <?php echo $partido['categoria_nombre']; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo $partido['pabellon_nombre']; ?></strong><br>
                                    <small style="color: var(--medium-gray);"><?php echo $partido['pabellon_ciudad']; ?></small>
                                </td>
                                <td>
                                    <small>
                                        <strong>1º:</strong> <?php echo $partido['arbitro1_nombre'] ?: '-'; ?><br>
                                        <strong>2º:</strong> <?php echo $partido['arbitro2_nombre'] ?: '-'; ?><br>
                                        <strong>Anot:</strong> <?php echo $partido['anotador_nombre'] ?: '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <button onclick="verDetalles(<?php echo $partido['id']; ?>)" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($partido['sets_local'] === null): ?>
                                        <button onclick="abrirResultados(<?php echo $partido['id']; ?>)" class="btn btn-success btn-sm">
                                            <i class="fas fa-trophy"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="editarResultados(<?php echo $partido['id']; ?>)" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="editPartido(<?php echo $partido['id']; ?>)" class="btn btn-info btn-sm">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <button onclick="deletePartido(<?php echo $partido['id']; ?>)" class="btn btn-danger btn-sm btn-delete" 
                                            data-message="¿Está seguro de eliminar este partido?">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Crear Partido -->
    <div id="createPartidoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-plus"></i> Crear Nuevo Partido</h2>
                <span class="close" onclick="closeModal('createPartidoModal')">&times;</span>
            </div>
            <form method="POST" class="validate-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Equipo Local</label>
                            <select name="equipo_local_id" class="form-control" required>
                                <option value="">Seleccione equipo local</option>
                                <?php foreach ($equipos as $equipo): ?>
                                <option value="<?php echo $equipo['id']; ?>"><?php echo $equipo['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Equipo Visitante</label>
                            <select name="equipo_visitante_id" class="form-control" required>
                                <option value="">Seleccione equipo visitante</option>
                                <?php foreach ($equipos as $equipo): ?>
                                <option value="<?php echo $equipo['id']; ?>"><?php echo $equipo['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" class="form-control" required>
                                <option value="">Seleccione categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo $categoria['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Hora</label>
                            <input type="time" name="hora" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pabellón</label>
                        <select name="pabellon_id" class="form-control" required>
                            <option value="">Seleccione pabellón</option>
                            <?php foreach ($pabellones as $pabellon): ?>
                            <option value="<?php echo $pabellon['id']; ?>">
                                <?php echo $pabellon['nombre'] . ' - ' . $pabellon['ciudad']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">1º Árbitro</label>
                            <select name="arbitro_principal_id" class="form-control">
                                <option value="">Seleccione árbitro</option>
                                <?php foreach ($arbitros as $arbitro): ?>
                                <option value="<?php echo $arbitro['id']; ?>"><?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">2º Árbitro</label>
                            <select name="arbitro_segundo_id" class="form-control">
                                <option value="">Seleccione árbitro</option>
                                <?php foreach ($arbitros as $arbitro): ?>
                                <option value="<?php echo $arbitro['id']; ?>"><?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Anotador</label>
                            <select name="anotador_id" class="form-control">
                                <option value="">Seleccione anotador</option>
                                <?php foreach ($arbitros as $arbitro): ?>
                                <option value="<?php echo $arbitro['id']; ?>"><?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createPartidoModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Partido
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Partido -->
    <div id="editPartidoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Editar Partido</h2>
                <span class="close" onclick="closeModal('editPartidoModal')">&times;</span>
            </div>
            <form method="POST" class="validate-form" id="editPartidoForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="partido_id" id="edit_partido_id">
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Equipo Local</label>
                            <select name="equipo_local_id" id="edit_equipo_local_id" class="form-control" required>
                                <option value="">Seleccione equipo local</option>
                                <?php foreach ($equipos as $equipo): ?>
                                <option value="<?php echo $equipo['id']; ?>"><?php echo $equipo['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Equipo Visitante</label>
                            <select name="equipo_visitante_id" id="edit_equipo_visitante_id" class="form-control" required>
                                <option value="">Seleccione equipo visitante</option>
                                <?php foreach ($equipos as $equipo): ?>
                                <option value="<?php echo $equipo['id']; ?>"><?php echo $equipo['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" id="edit_categoria_id" class="form-control" required>
                                <option value="">Seleccione categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo $categoria['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha" id="edit_fecha" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Hora</label>
                            <input type="time" name="hora" id="edit_hora" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pabellón</label>
                        <select name="pabellon_id" id="edit_pabellon_id" class="form-control" required>
                            <option value="">Seleccione pabellón</option>
                            <?php foreach ($pabellones as $pabellon): ?>
                            <option value="<?php echo $pabellon['id']; ?>">
                                <?php echo $pabellon['nombre'] . ' - ' . $pabellon['ciudad']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">1º Árbitro</label>
                            <select name="arbitro_principal_id" id="edit_arbitro_principal_id" class="form-control">
                                <option value="">Seleccione árbitro</option>
                                <?php foreach ($arbitros as $arbitro): ?>
                                <option value="<?php echo $arbitro['id']; ?>"><?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">2º Árbitro</label>
                            <select name="arbitro_segundo_id" id="edit_arbitro_segundo_id" class="form-control">
                                <option value="">Seleccione árbitro</option>
                                <?php foreach ($arbitros as $arbitro): ?>
                                <option value="<?php echo $arbitro['id']; ?>"><?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Anotador</label>
                            <select name="anotador_id" id="edit_anotador_id" class="form-control">
                                <option value="">Seleccione anotador</option>
                                <?php foreach ($arbitros as $arbitro): ?>
                                <option value="<?php echo $arbitro['id']; ?>"><?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editPartidoModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Partido
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalles Partido -->
    <div id="detallesPartidoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-info-circle"></i> Detalles del Partido</h2>
                <span class="close" onclick="closeModal('detallesPartidoModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="detallesPartidoContent">
                    <!-- Se cargará dinámicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('detallesPartidoModal')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal Resultados Partido -->
    <div id="resultadosModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 id="resultadosModalTitle"><i class="fas fa-trophy"></i> Registrar Resultado</h2>
                <span class="close" onclick="closeModal('resultadosModal')">&times;</span>
            </div>
            <form id="formResultado" onsubmit="event.preventDefault(); guardarResultado();">
                <div class="modal-body">
                    <input type="hidden" id="resultadoPartidoId" name="partido_id">
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h4 class="text-center mb-3">
                                <span id="resultadoEquipoLocal"></span> vs <span id="resultadoEquipoVisitante"></span>
                            </h4>
                            <p class="text-center text-muted" id="resultadoFecha"></p>
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label">Sets ganados - <span id="equipoLocalLabel">Local</span></label>
                            <select id="setsLocal" name="sets_local" class="form-control" onchange="generarSets()" required>
                                <option value="">Seleccionar...</option>
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sets ganados - <span id="equipoVisitanteLabel">Visitante</span></label>
                            <select id="setsVisitante" name="sets_visitante" class="form-control" onchange="generarSets()" required>
                                <option value="">Seleccionar...</option>
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                    </div>

                    <div id="setsContainer">
                        <!-- Se generarán dinámicamente los campos de sets -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resultadosModal')">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar Resultado
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function editPartido(partidoId) {
            // Obtener datos del partido
            fetch(`api/partidos.php?id=${partidoId}`)
                .then(response => response.json())
                .then(partido => {
                    if (partido) {
                        document.getElementById('edit_partido_id').value = partido.id;
                        document.getElementById('edit_equipo_local_id').value = partido.equipo_local_id;
                        document.getElementById('edit_equipo_visitante_id').value = partido.equipo_visitante_id;
                        document.getElementById('edit_categoria_id').value = partido.categoria_id;
                        document.getElementById('edit_fecha').value = partido.fecha_original;
                        document.getElementById('edit_hora').value = partido.hora_original;
                        document.getElementById('edit_pabellon_id').value = partido.pabellon_id;
                        document.getElementById('edit_arbitro_principal_id').value = partido.arbitro_principal_id || '';
                        document.getElementById('edit_arbitro_segundo_id').value = partido.arbitro_segundo_id || '';
                        document.getElementById('edit_anotador_id').value = partido.anotador_id || '';
                        
                        openModal('editPartidoModal');
                    }
                })
                .catch(error => {
                    showNotification('Error al cargar los datos del partido', 'error');
                });
        }

        function deletePartido(partidoId) {
            if (confirm('¿Está seguro de eliminar este partido?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="partido_id" value="${partidoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Validación para evitar que un equipo juegue contra sí mismo
        document.addEventListener('DOMContentLoaded', function() {
            const equipoLocalSelects = document.querySelectorAll('[name="equipo_local_id"]');
            const equipoVisitanteSelects = document.querySelectorAll('[name="equipo_visitante_id"]');

            function validateEquipos(localSelect, visitanteSelect) {
                localSelect.addEventListener('change', function() {
                    if (this.value && this.value === visitanteSelect.value) {
                        showNotification('Un equipo no puede jugar contra sí mismo', 'error');
                        visitanteSelect.value = '';
                    }
                });

                visitanteSelect.addEventListener('change', function() {
                    if (this.value && this.value === localSelect.value) {
                        showNotification('Un equipo no puede jugar contra sí mismo', 'error');
                        localSelect.value = '';
                    }
                });
            }

            // Aplicar validación a todos los formularios
            for (let i = 0; i < equipoLocalSelects.length; i++) {
                validateEquipos(equipoLocalSelects[i], equipoVisitanteSelects[i]);
            }
        });

        // Funciones para gestión de resultados
        function verDetalles(partidoId) {
            fetch(`api/partidos.php?id=${partidoId}`)
                .then(response => response.json())
                .then(partido => {
                    if (partido && !partido.error) {
                        let html = `
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <h4>Información del Partido</h4>
                                    <p><strong>Fecha:</strong> ${partido.fecha}</p>
                                    <p><strong>Hora:</strong> ${partido.hora}</p>
                                    <p><strong>Ciudad:</strong> ${partido.ciudad}</p>
                                    <p><strong>Pabellón:</strong> ${partido.pabellon}</p>
                                </div>
                                <div>
                                    <h4>Equipos</h4>
                                    <p><strong>Local:</strong> ${partido.equipo_local || 'No definido'}</p>
                                    <p><strong>Visitante:</strong> ${partido.equipo_visitante || 'No definido'}</p>
                                    <p><strong>Categoría:</strong> ${partido.categoria || 'No definida'}</p>
                                </div>
                            </div>
                            
                            <h4>Equipo Arbitral</h4>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                <div class="text-center">
                                    <strong>1º Árbitro</strong><br>
                                    ${partido.arbitro1_nombre || 'Sin asignar'}
                                </div>
                                <div class="text-center">
                                    <strong>2º Árbitro</strong><br>
                                    ${partido.arbitro2_nombre || 'Sin asignar'}
                                </div>
                                <div class="text-center">
                                    <strong>Anotador</strong><br>
                                    ${partido.anotador_nombre || 'Sin asignar'}
                                </div>
                            </div>
                        `;
                        
                        // Agregar resultados si existen
                        if (partido.sets_local !== null && partido.sets_visitante !== null) {
                            html += `
                                <h4 style="margin-top: 20px;">Resultado Final</h4>
                                <div class="card" style="background: var(--light-gray); margin-bottom: 15px;">
                                    <div class="card-body text-center">
                                        <h3 style="margin: 0; color: var(--primary-green);">
                                            ${partido.equipo_local} ${partido.sets_local} - ${partido.sets_visitante} ${partido.equipo_visitante}
                                        </h3>
                                        <p style="margin: 5px 0 0 0; color: var(--medium-gray);">
                                            Estado: <span style="color: var(--success);">${partido.estado}</span>
                                        </p>
                                    </div>
                                </div>
                            `;
                            
                            if (partido.sets_detalle && partido.sets_detalle.length > 0) {
                                html += `
                                    <h4>Detalle por Sets</h4>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Set</th>
                                                    <th>${partido.equipo_local}</th>
                                                    <th>${partido.equipo_visitante}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                `;
                                
                                partido.sets_detalle.forEach(set => {
                                    const ganador = parseInt(set.puntos_local) > parseInt(set.puntos_visitante) ? 'local' : 'visitante';
                                    html += `
                                        <tr>
                                            <td><strong>Set ${set.numero_set}</strong></td>
                                            <td class="${ganador === 'local' ? 'text-success' : ''}" style="font-weight: ${ganador === 'local' ? 'bold' : 'normal'};">
                                                ${set.puntos_local}
                                            </td>
                                            <td class="${ganador === 'visitante' ? 'text-success' : ''}" style="font-weight: ${ganador === 'visitante' ? 'bold' : 'normal'};">
                                                ${set.puntos_visitante}
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
                        } else {
                            html += `
                                <div style="margin-top: 20px; padding: 15px; background: var(--warning); color: white; border-radius: 5px; text-align: center;">
                                    <i class="fas fa-clock"></i> Partido sin resultado registrado
                                </div>
                            `;
                        }
                        
                        document.getElementById('detallesPartidoContent').innerHTML = html;
                        openModal('detallesPartidoModal');
                    } else {
                        showNotification('Error al cargar los detalles del partido', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al cargar los detalles del partido', 'error');
                });
        }

        function abrirResultados(partidoId) {
            cargarFormularioResultados(partidoId, false);
        }

        function editarResultados(partidoId) {
            cargarFormularioResultados(partidoId, true);
        }

        function cargarFormularioResultados(partidoId, esEdicion) {
            fetch(`api/partidos.php?id=${partidoId}`)
                .then(response => response.json())
                .then(partido => {
                    if (partido && !partido.error) {
                        document.getElementById('resultadoPartidoId').value = partidoId;
                        document.getElementById('resultadoEquipoLocal').textContent = partido.equipo_local;
                        document.getElementById('resultadoEquipoVisitante').textContent = partido.equipo_visitante;
                        document.getElementById('resultadoFecha').textContent = `${partido.fecha} ${partido.hora}`;
                        document.getElementById('equipoLocalLabel').textContent = partido.equipo_local;
                        document.getElementById('equipoVisitanteLabel').textContent = partido.equipo_visitante;
                        
                        // Reset form
                        document.getElementById('formResultado').reset();
                        document.getElementById('resultadoPartidoId').value = partidoId;
                        document.getElementById('setsContainer').innerHTML = '';
                        
                        // Si es edición, cargar datos existentes
                        if (esEdicion && partido.sets_local !== null && partido.sets_visitante !== null) {
                            document.getElementById('setsLocal').value = partido.sets_local;
                            document.getElementById('setsVisitante').value = partido.sets_visitante;
                            
                            // Cargar detalles de sets si existen
                            if (partido.sets_detalle && partido.sets_detalle.length > 0) {
                                generarSets();
                                // Llenar los datos de los sets
                                partido.sets_detalle.forEach((set, index) => {
                                    const setNum = index + 1;
                                    const localInput = document.querySelector(`input[name="set${setNum}_local"]`);
                                    const visitanteInput = document.querySelector(`input[name="set${setNum}_visitante"]`);
                                    if (localInput) localInput.value = set.puntos_local;
                                    if (visitanteInput) visitanteInput.value = set.puntos_visitante;
                                });
                            }
                        }
                        
                        document.getElementById('resultadosModalTitle').innerHTML = esEdicion ? 
                            '<i class="fas fa-edit"></i> Editar Resultado' : 
                            '<i class="fas fa-trophy"></i> Registrar Resultado';
                        
                        openModal('resultadosModal');
                    } else {
                        showNotification('Error al cargar los datos del partido', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al cargar los datos del partido', 'error');
                });
        }

        function generarSets() {
            const setsLocal = parseInt(document.getElementById('setsLocal').value) || 0;
            const setsVisitante = parseInt(document.getElementById('setsVisitante').value) || 0;
            const totalSets = setsLocal + setsVisitante;
            
            if (totalSets < 3 || totalSets > 5) {
                showNotification('Un partido de voleibol debe tener entre 3 y 5 sets', 'error');
                return;
            }
            
            if (setsLocal === setsVisitante) {
                showNotification('No puede haber empate en voleibol', 'error');
                return;
            }
            
            if ((setsLocal > 3 || setsVisitante > 3) || (setsLocal < 2 && setsVisitante < 2)) {
                showNotification('Resultado inválido para voleibol', 'error');
                return;
            }
            
            const container = document.getElementById('setsContainer');
            const equipoLocal = document.getElementById('equipoLocalLabel').textContent;
            const equipoVisitante = document.getElementById('equipoVisitanteLabel').textContent;
            container.innerHTML = '';
            
            for (let i = 1; i <= totalSets; i++) {
                const setDiv = document.createElement('div');
                setDiv.className = 'form-row';
                setDiv.innerHTML = `
                    <h4>Set ${i}</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">${equipoLocal}</label>
                            <input type="number" name="set${i}_local" class="form-control" min="0" max="50" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">${equipoVisitante}</label>
                            <input type="number" name="set${i}_visitante" class="form-control" min="0" max="50" required>
                        </div>
                    </div>
                `;
                container.appendChild(setDiv);
            }
        }

        function guardarResultado() {
            const formData = new FormData(document.getElementById('formResultado'));
            formData.append('action', 'guardar_resultado');
            
            fetch('api/partidos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Resultado guardado correctamente', 'success');
                    closeModal('resultadosModal');
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Error al guardar el resultado', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al guardar el resultado', 'error');
            });
        }
    </script>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda para la tabla de partidos
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
        });
    </script>
</body>
</html>
