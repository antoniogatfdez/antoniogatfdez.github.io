<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('arbitro');

$database = new Database();
$conn = $database->getConnection();

// Obtener ID del árbitro
$query = "SELECT id FROM arbitros WHERE usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$arbitro_id = $stmt->fetchColumn();

// Obtener partidos asignados
$query = "SELECT p.*, 
                 el.nombre as equipo_local,
                 ev.nombre as equipo_visitante,
                 pab.nombre as pabellon,
                 pab.ciudad,
                 c.nombre as categoria,
                 CASE 
                     WHEN p.arbitro_principal_id = ? THEN '1º Árbitro'
                     WHEN p.arbitro_segundo_id = ? THEN '2º Árbitro'
                     WHEN p.anotador_id = ? THEN 'Anotador'
                 END as mi_rol,
                 CONCAT(a1.nombre, ' ', a1.apellidos) as arbitro1_nombre,
                 CONCAT(a2.nombre, ' ', a2.apellidos) as arbitro2_nombre,
                 CONCAT(an.nombre, ' ', an.apellidos) as anotador_nombre
          FROM partidos p
          LEFT JOIN equipos el ON p.equipo_local_id = el.id
          LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
          LEFT JOIN pabellones pab ON p.pabellon_id = pab.id
          LEFT JOIN categorias c ON p.categoria_id = c.id
          LEFT JOIN arbitros a1 ON p.arbitro_principal_id = a1.id
          LEFT JOIN arbitros a2 ON p.arbitro_segundo_id = a2.id
          LEFT JOIN arbitros an ON p.anotador_id = an.id
          WHERE p.arbitro_principal_id = ? OR p.arbitro_segundo_id = ? OR p.anotador_id = ?
          ORDER BY p.fecha DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id]);
$partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separar partidos por estado
$partidos_futuros = [];
$partidos_pasados = [];
$hoy = date('Y-m-d');

foreach ($partidos as $partido) {
    if ($partido['fecha'] >= $hoy) {
        $partidos_futuros[] = $partido;
    } else {
        $partidos_pasados[] = $partido;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Partidos - FEDEXVB</title>
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
            <li><a href="partidos.php" class="active"><i class="fa-solid fa-globe"></i> Mis Partidos</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Mis Liquidaciones</a></li>
            <li><a href="arbitros.php"><i class="fas fa-users"></i> Lista de Árbitros</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fa-solid fa-globe"></i> Mis Partidos Asignados</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> <a href="dashboard.php">Inicio</a> / Mis Partidos
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header" style="background: var(--info);">
                    <i class="fas fa-calendar-day"></i> Próximos Partidos
                </div>
                <div class="card-body text-center">
                    <h2 style="margin: 0; color: var(--info);"><?php echo count($partidos_futuros); ?></h2>
                    <p style="margin: 0; color: var(--medium-gray);">Partidos por arbitrar</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--success);">
                    <i class="fas fa-check-circle"></i> Partidos Arbitrados
                </div>
                <div class="card-body text-center">
                    <h2 style="margin: 0; color: var(--success);"><?php echo count($partidos_pasados); ?></h2>
                    <p style="margin: 0; color: var(--medium-gray);">Esta temporada</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--warning);">
                    <i class="fas fa-clock"></i> Próximo Partido
                </div>
                <div class="card-body">
                    <?php if (!empty($partidos_futuros)): ?>
                        <?php $proximo = $partidos_futuros[0]; ?>
                        <p style="margin: 0; font-weight: bold;"><?php echo format_date($proximo['fecha']); ?></p>
                        <p style="margin: 0; color: var(--medium-gray); font-size: 0.9rem;">
                            <?php echo format_datetime($proximo['fecha'], 'H:i'); ?> - <?php echo $proximo['ciudad']; ?>
                        </p>
                    <?php else: ?>
                        <p style="margin: 0; color: var(--medium-gray);">Sin partidos próximos</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-filter"></i> Filtros
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Filtrar por período</label>
                        <select id="filtroPeriodo" class="form-control" onchange="filtrarPartidos()">
                            <option value="todos">Todos los partidos</option>
                            <option value="futuros">Solo próximos</option>
                            <option value="pasados">Solo pasados</option>
                            <option value="mes">Este mes</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Filtrar por rol</label>
                        <select id="filtroRol" class="form-control" onchange="filtrarPartidos()">
                            <option value="">Todos los roles</option>
                            <option value="1º Árbitro">1º Árbitro</option>
                            <option value="2º Árbitro">2º Árbitro</option>
                            <option value="Anotador">Anotador</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Buscar equipo</label>
                        <input type="text" id="buscarEquipo" class="form-control" placeholder="Nombre del equipo..." onkeyup="filtrarPartidos()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Próximos partidos -->
        <?php if (!empty($partidos_futuros)): ?>
        <div class="card mb-4" id="seccion-futuros">
            <div class="card-header" style="background: var(--info);">
                <i class="fas fa-calendar-day"></i> Próximos Partidos 
                <span class="badge" style="background: white; color: var(--info); margin-left: 10px;">
                    <span id="futuros-count"><?php echo count($partidos_futuros); ?></span> partidos
                </span>
            </div>
            <div class="card-body">
                <!-- Barra de búsqueda para próximos partidos -->
                <div class="search-container">
                    <div class="search-input-group">
                        <div class="search-input-icon">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   id="searchFuturosInput" 
                                   placeholder="Buscar por equipos, categoría, ubicación, árbitros..." 
                                   class="search-input-field">
                        </div>
                        <button type="button" 
                                id="searchFuturosClear" 
                                class="btn search-clear-btn" 
                                style="display: none;"
                                title="Limpiar búsqueda">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                    </div>
                    <div id="searchFuturosInfo" class="search-info" style="display: none;">
                        <i class="fas fa-info-circle"></i> 
                        <span id="searchFuturosResults">0</span> partido(s) encontrado(s)
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Busca por equipos, categoría, ubicación o nombres de árbitros. Usa ESC para limpiar.</em>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table searchable-table" id="tabla-futuros">
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Equipos</th>
                                <th>Categoría</th>
                                <th>Ubicación</th>
                                <th>Mi Rol</th>
                                <th>Otros Árbitros</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidos_futuros as $partido): ?>
                            <tr data-periodo="futuro" data-rol="<?php echo $partido['mi_rol']; ?>" data-equipos="<?php echo strtolower($partido['equipo_local'] . ' ' . $partido['equipo_visitante']); ?>">
                                <td>
                                    <strong><?php echo format_datetime($partido['fecha'], 'd/m/Y'); ?></strong><br>
                                    <span style="color: var(--medium-gray);"><?php echo format_datetime($partido['fecha'], 'H:i'); ?></span>
                                </td>
                                <td>
                                    <div style="text-align: center;">
                                        <strong style="color: var(--primary-green);"><?php echo $partido['equipo_local']; ?></strong>
                                        <div style="margin: 5px 0; color: var(--medium-gray);">vs</div>
                                        <strong style="color: var(--primary-black);"><?php echo $partido['equipo_visitante']; ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--info);">
                                        <?php echo $partido['categoria']; ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-map-marker-alt"></i> <?php echo $partido['ciudad']; ?><br>
                                    <small style="color: var(--medium-gray);"><?php echo $partido['pabellon']; ?></small>
                                </td>
                                <td>
                                    <span class="badge" style="background: 
                                        <?php 
                                        echo $partido['mi_rol'] == '1º Árbitro' ? 'var(--success)' : 
                                             ($partido['mi_rol'] == '2º Árbitro' ? 'var(--warning)' : 'var(--info)'); 
                                        ?>">
                                        <?php echo $partido['mi_rol']; ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php if ($partido['arbitro1_nombre'] && $partido['mi_rol'] != '1º Árbitro'): ?>
                                            <strong>1º:</strong> <?php echo $partido['arbitro1_nombre']; ?><br>
                                        <?php endif; ?>
                                        <?php if ($partido['arbitro2_nombre'] && $partido['mi_rol'] != '2º Árbitro'): ?>
                                            <strong>2º:</strong> <?php echo $partido['arbitro2_nombre']; ?><br>
                                        <?php endif; ?>
                                        <?php if ($partido['anotador_nombre'] && $partido['mi_rol'] != 'Anotador'): ?>
                                            <strong>Anot:</strong> <?php echo $partido['anotador_nombre']; ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <button onclick="verDetalles(<?php echo $partido['id']; ?>)" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                    <?php if ($partido['fecha'] < date('Y-m-d H:i:s') && $partido['sets_local'] === null): ?>
                                        <button onclick="abrirResultados(<?php echo $partido['id']; ?>)" class="btn btn-success btn-sm">
                                            <i class="fas fa-trophy"></i> Resultado
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
        <?php endif; ?>

        <!-- Partidos anteriores -->
        <?php if (!empty($partidos_pasados)): ?>
        <div class="card" id="seccion-pasados">
            <div class="card-header" style="background: var(--success);">
                <i class="fas fa-history"></i> Partidos Arbitrados 
                <span class="badge" style="background: white; color: var(--success); margin-left: 10px;">
                    <span id="pasados-count"><?php echo count($partidos_pasados); ?></span> partidos
                </span>
            </div>
            <div class="card-body">
                <!-- Barra de búsqueda para partidos pasados -->
                <div class="search-container">
                    <div class="search-input-group">
                        <div class="search-input-icon">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   id="searchPasadosInput" 
                                   placeholder="Buscar en historial por equipos, categoría, ubicación..." 
                                   class="search-input-field">
                        </div>
                        <button type="button" 
                                id="searchPasadosClear" 
                                class="btn search-clear-btn" 
                                style="display: none;"
                                title="Limpiar búsqueda">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                    </div>
                    <div id="searchPasadosInfo" class="search-info" style="display: none;">
                        <i class="fas fa-info-circle"></i> 
                        <span id="searchPasadosResults">0</span> partido(s) encontrado(s) en el historial
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Filtra tu historial de partidos por cualquier criterio.</em>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table searchable-table" id="tabla-pasados">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Equipos</th>
                                <th>Resultado</th>
                                <th>Categoría</th>
                                <th>Ubicación</th>
                                <th>Mi Rol</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidos_pasados as $partido): ?>
                            <tr data-periodo="pasado" data-rol="<?php echo $partido['mi_rol']; ?>" data-equipos="<?php echo strtolower($partido['equipo_local'] . ' ' . $partido['equipo_visitante']); ?>">
                                <td><?php echo format_datetime($partido['fecha'], 'd/m/Y H:i'); ?></td>
                                <td>
                                    <strong><?php echo $partido['equipo_local']; ?></strong> vs 
                                    <strong><?php echo $partido['equipo_visitante']; ?></strong>
                                </td>
                                <td>
                                    <?php if ($partido['sets_local'] !== null && $partido['sets_visitante'] !== null): ?>
                                        <span class="badge" style="background: var(--success);">
                                            <?php echo $partido['sets_local']; ?> - <?php echo $partido['sets_visitante']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--warning);">
                                            Sin resultado
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--medium-gray);">
                                        <?php echo $partido['categoria']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $partido['ciudad']; ?><br>
                                    <small style="color: var(--medium-gray);"><?php echo $partido['pabellon']; ?></small>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--medium-gray);">
                                        <?php echo $partido['mi_rol']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick="verDetalles(<?php echo $partido['id']; ?>)" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                    <?php if ($partido['sets_local'] === null): ?>
                                        <button onclick="abrirResultados(<?php echo $partido['id']; ?>)" class="btn btn-success btn-sm">
                                            <i class="fas fa-trophy"></i> Resultado
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
        <?php endif; ?>

        <?php if (empty($partidos)): ?>
        <div class="card">
            <div class="card-body text-center p-5">
                <i class="fas fa-calendar-times" style="font-size: 4rem; color: var(--medium-gray); margin-bottom: 20px;"></i>
                <h3>No tienes partidos asignados</h3>
                <p class="text-muted">Cuando se te asignen partidos, aparecerán aquí.</p>
                <a href="disponibilidad.php" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i> Gestionar mi disponibilidad
                </a>
            </div>
        </div>
        <?php endif; ?>
    </main>

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
                <h2><i class="fas fa-trophy"></i> Registrar Resultado</h2>
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
        function filtrarPartidos() {
            const periodo = document.getElementById('filtroPeriodo').value;
            const rol = document.getElementById('filtroRol').value;
            const equipoBuscar = document.getElementById('buscarEquipo').value.toLowerCase();
            
            const rows = document.querySelectorAll('tbody tr[data-periodo]');
            
            rows.forEach(row => {
                const rowPeriodo = row.getAttribute('data-periodo');
                const rowRol = row.getAttribute('data-rol');
                const rowEquipos = row.getAttribute('data-equipos');
                
                let mostrar = true;
                
                // Filtro por período
                if (periodo === 'futuros' && rowPeriodo !== 'futuro') mostrar = false;
                if (periodo === 'pasados' && rowPeriodo !== 'pasado') mostrar = false;
                
                // Filtro por rol
                if (rol && rowRol !== rol) mostrar = false;
                
                // Filtro por equipo
                if (equipoBuscar && !rowEquipos.includes(equipoBuscar)) mostrar = false;
                
                row.style.display = mostrar ? '' : 'none';
            });

            // Mostrar/ocultar secciones
            if (periodo === 'futuros') {
                document.getElementById('seccion-pasados').style.display = 'none';
                document.getElementById('seccion-futuros').style.display = 'block';
            } else if (periodo === 'pasados') {
                document.getElementById('seccion-futuros').style.display = 'none';
                document.getElementById('seccion-pasados').style.display = 'block';
            } else {
                document.getElementById('seccion-futuros').style.display = 'block';
                document.getElementById('seccion-pasados').style.display = 'block';
            }
        }

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
            // Cargar datos del partido
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
        // Inicializar búsquedas para ambas tablas
        document.addEventListener('DOMContentLoaded', function() {
            // Búsqueda para partidos futuros
            if (document.getElementById('searchFuturosInput')) {
                new TableSearchBar({
                    searchInputId: 'searchFuturosInput',
                    clearBtnId: 'searchFuturosClear',
                    searchInfoId: 'searchFuturosInfo',
                    searchResultsId: 'searchFuturosResults',
                    totalCountId: 'futuros-count',
                    tableSelector: '#tabla-futuros tbody tr',
                    noResultsId: 'noResultsFuturos',
                    columnsCount: 7
                });
            }
            
            // Búsqueda para partidos pasados
            if (document.getElementById('searchPasadosInput')) {
                new TableSearchBar({
                    searchInputId: 'searchPasadosInput',
                    clearBtnId: 'searchPasadosClear',
                    searchInfoId: 'searchPasadosInfo',
                    searchResultsId: 'searchPasadosResults',
                    totalCountId: 'pasados-count',
                    tableSelector: '#tabla-pasados tbody tr',
                    noResultsId: 'noResultsPasados',
                    columnsCount: 7
                });
            }
        });
    </script>
</body>
</html>
