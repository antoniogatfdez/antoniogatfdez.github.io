<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('club');

$database = new Database();
$conn = $database->getConnection();

$message = '';

// Obtener ID del club
$query = "SELECT id, nombre_club FROM clubes WHERE usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header('Location: ../unauthorized.php');
    exit();
}

// Filtros
$equipo_filtro = isset($_GET['equipo']) ? (int)$_GET['equipo'] : null;
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Obtener equipos del club
$query = "SELECT e.*, c.nombre as categoria 
          FROM equipos e 
          JOIN categorias c ON e.categoria_id = c.id 
          WHERE e.club_id = ?
          ORDER BY c.nombre, e.nombre";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir query para partidos
$partidos_query = "SELECT p.*, 
                          el.nombre as equipo_local, ev.nombre as equipo_visitante,
                          pab.nombre as pabellon, pab.ciudad as ciudad_pabellon,
                          cat.nombre as categoria,
                          ar1.nombre as arbitro_principal_nombre, ar1.apellidos as arbitro_principal_apellidos,
                          ar2.nombre as arbitro_segundo_nombre, ar2.apellidos as arbitro_segundo_apellidos,
                          an.nombre as anotador_nombre, an.apellidos as anotador_apellidos,
                          CASE 
                              WHEN p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) THEN 'LOCAL'
                              ELSE 'VISITANTE'
                          END as condicion
                   FROM partidos p
                   JOIN equipos el ON p.equipo_local_id = el.id
                   JOIN equipos ev ON p.equipo_visitante_id = ev.id
                   JOIN pabellones pab ON p.pabellon_id = pab.id
                   JOIN categorias cat ON p.categoria_id = cat.id
                   LEFT JOIN arbitros ar1 ON p.arbitro_principal_id = ar1.id
                   LEFT JOIN arbitros ar2 ON p.arbitro_segundo_id = ar2.id
                   LEFT JOIN arbitros an ON p.anotador_id = an.id
                   WHERE (p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) 
                         OR p.equipo_visitante_id IN (SELECT id FROM equipos WHERE club_id = ?))";

$params = [$club['id'], $club['id'], $club['id']];

if ($equipo_filtro) {
    $partidos_query .= " AND (p.equipo_local_id = ? OR p.equipo_visitante_id = ?)";
    $params[] = $equipo_filtro;
    $params[] = $equipo_filtro;
}

if ($estado_filtro === 'pendientes') {
    $partidos_query .= " AND p.finalizado = 0 AND p.fecha >= NOW()";
} elseif ($estado_filtro === 'finalizados') {
    $partidos_query .= " AND p.finalizado = 1";
} elseif ($estado_filtro === 'proximos') {
    $partidos_query .= " AND p.finalizado = 0 AND p.fecha >= NOW()";
} elseif ($estado_filtro === 'pasados') {
    $partidos_query .= " AND p.fecha < NOW()";
}

if ($fecha_desde) {
    $partidos_query .= " AND DATE(p.fecha) >= ?";
    $params[] = $fecha_desde;
}

if ($fecha_hasta) {
    $partidos_query .= " AND DATE(p.fecha) <= ?";
    $params[] = $fecha_hasta;
}

$partidos_query .= " ORDER BY p.fecha DESC";

$stmt = $conn->prepare($partidos_query);
$stmt->execute($params);
$partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats = [
    'total' => count($partidos),
    'proximos' => count(array_filter($partidos, function($p) { 
        return !$p['finalizado'] && strtotime($p['fecha']) >= time(); 
    })),
    'finalizados' => count(array_filter($partidos, function($p) { 
        return $p['finalizado']; 
    })),
    'local' => count(array_filter($partidos, function($p) { 
        return $p['condicion'] === 'LOCAL'; 
    })),
    'visitante' => count(array_filter($partidos, function($p) { 
        return $p['condicion'] === 'VISITANTE'; 
    }))
];
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
            <li><a href="equipos.php"><i class="fas fa-users-cog"></i> Mis Equipos</a></li>
            <li><a href="jugadores.php"><i class="fas fa-running"></i> Gestión de Jugadores</a></li>
            <li><a href="tecnicos.php"><i class="fas fa-chalkboard-teacher"></i> Gestión de Técnicos</a></li>
            <li><a href="partidos.php" class="active"><i class="fas fa-calendar-alt"></i> Mis Partidos</a></li>
            <li><a href="inscripciones.php"><i class="fas fa-file-signature"></i> Inscripciones</a></li>
            <li><a href="documentos.php"><i class="fas fa-folder-open"></i> Documentos</a></li>
            <li><a href="perfil.php"><i class="fas fa-building"></i> Perfil del Club</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-calendar-alt"></i> Mis Partidos</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Partidos
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-alt" style="font-size: 2rem; color: var(--primary-green);"></i>
                    <h3 style="color: var(--primary-green); margin: 10px 0;"><?php echo $stats['total']; ?></h3>
                    <p class="text-muted">Total Partidos</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-clock" style="font-size: 2rem; color: var(--info);"></i>
                    <h3 style="color: var(--info); margin: 10px 0;"><?php echo $stats['proximos']; ?></h3>
                    <p class="text-muted">Próximos</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success);"></i>
                    <h3 style="color: var(--success); margin: 10px 0;"><?php echo $stats['finalizados']; ?></h3>
                    <p class="text-muted">Finalizados</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-home" style="font-size: 2rem; color: var(--warning);"></i>
                    <h3 style="color: var(--warning); margin: 10px 0;"><?php echo $stats['local']; ?></h3>
                    <p class="text-muted">Como Local</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-plane" style="font-size: 2rem; color: var(--danger);"></i>
                    <h3 style="color: var(--danger); margin: 10px 0;"><?php echo $stats['visitante']; ?></h3>
                    <p class="text-muted">Como Visitante</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-filter"></i> Filtros de Búsqueda
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                        <div>
                            <label for="equipo">Equipo:</label>
                            <select name="equipo" id="equipo" class="form-control">
                                <option value="">Todos los equipos</option>
                                <?php foreach ($equipos as $equipo): ?>
                                    <option value="<?php echo $equipo['id']; ?>" 
                                            <?php echo ($equipo_filtro == $equipo['id']) ? 'selected' : ''; ?>>
                                        <?php echo $equipo['nombre'] . ' (' . $equipo['categoria'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="estado">Estado:</label>
                            <select name="estado" id="estado" class="form-control">
                                <option value="todos" <?php echo ($estado_filtro === 'todos') ? 'selected' : ''; ?>>Todos</option>
                                <option value="proximos" <?php echo ($estado_filtro === 'proximos') ? 'selected' : ''; ?>>Próximos</option>
                                <option value="finalizados" <?php echo ($estado_filtro === 'finalizados') ? 'selected' : ''; ?>>Finalizados</option>
                                <option value="pasados" <?php echo ($estado_filtro === 'pasados') ? 'selected' : ''; ?>>Pasados</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="fecha_desde">Desde:</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" 
                                   value="<?php echo $fecha_desde; ?>">
                        </div>
                        
                        <div>
                            <label for="fecha_hasta">Hasta:</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" 
                                   value="<?php echo $fecha_hasta; ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        
                        <a href="partidos.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de partidos -->
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <i class="fas fa-list"></i> Lista de Partidos
                        <span class="badge" style="background: var(--primary-green);">
                            <span id="total-count"><?php echo count($partidos); ?></span> partidos
                        </span>
                    </div>
                    <div>
                        <button class="btn btn-success btn-sm" onclick="exportarPartidos()">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <button class="btn btn-info btn-sm" onclick="verCalendario()">
                            <i class="fas fa-calendar"></i> Vista Calendario
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($partidos) > 0): ?>
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
                            <em>Busca entre tus partidos por cualquier criterio. Usa ESC para limpiar.</em>
                        </div>
                    </div>
                    
                    <!-- Vista de tarjetas para dispositivos móviles y lista para escritorio -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table searchable-table">
                                <thead>
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <th>Partido</th>
                                        <th>Resultado</th>
                                        <th>Categoría</th>
                                        <th>Pabellón</th>
                                        <th>Árbitros</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($partidos as $partido): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo format_datetime($partido['fecha'], 'd/m/Y'); ?></strong><br>
                                            <small><?php echo format_datetime($partido['fecha'], 'H:i'); ?></small>
                                        </td>
                                        <td>
                                            <div style="text-align: center;">
                                                <strong style="color: <?php echo ($partido['condicion'] === 'LOCAL') ? 'var(--success)' : 'var(--info)'; ?>;">
                                                    <?php echo $partido['equipo_local']; ?>
                                                </strong>
                                                <div style="margin: 5px 0; font-size: 0.8em; color: var(--medium-gray);">
                                                    VS
                                                </div>
                                                <strong style="color: <?php echo ($partido['condicion'] === 'VISITANTE') ? 'var(--success)' : 'var(--info)'; ?>;">
                                                    <?php echo $partido['equipo_visitante']; ?>
                                                </strong>
                                            </div>
                                            <div class="text-center mt-2">
                                                <span class="badge" style="background: <?php echo ($partido['condicion'] === 'LOCAL') ? 'var(--success)' : 'var(--info)'; ?>;">
                                                    <?php echo $partido['condicion']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($partido['sets_local'] !== null && $partido['sets_visitante'] !== null): ?>
                                                <?php 
                                                // Determinar resultado desde la perspectiva del club
                                                $mis_sets = ($partido['condicion'] === 'LOCAL') ? $partido['sets_local'] : $partido['sets_visitante'];
                                                $sets_rival = ($partido['condicion'] === 'LOCAL') ? $partido['sets_visitante'] : $partido['sets_local'];
                                                $gano = $mis_sets > $sets_rival;
                                                ?>
                                                <div class="text-center">
                                                    <span class="badge" style="background: <?php echo $gano ? 'var(--success)' : 'var(--danger)'; ?>;">
                                                        <?php echo $mis_sets; ?> - <?php echo $sets_rival; ?>
                                                    </span>
                                                    <br>
                                                    <small style="color: <?php echo $gano ? 'var(--success)' : 'var(--danger)'; ?>; font-weight: bold;">
                                                        <?php echo $gano ? 'GANADO' : 'PERDIDO'; ?>
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center">
                                                    <span class="badge" style="background: var(--warning);">
                                                        Sin resultado
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: var(--primary-green);">
                                                <?php echo $partido['categoria']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo $partido['pabellon']; ?></strong><br>
                                            <small class="text-muted"><?php echo $partido['ciudad_pabellon']; ?></small>
                                        </td>
                                        <td>
                                            <?php if ($partido['arbitro_principal_nombre']): ?>
                                                <div><strong>Principal:</strong> <?php echo $partido['arbitro_principal_apellidos'] . ', ' . $partido['arbitro_principal_nombre']; ?></div>
                                            <?php endif; ?>
                                            <?php if ($partido['arbitro_segundo_nombre']): ?>
                                                <div><strong>Segundo:</strong> <?php echo $partido['arbitro_segundo_apellidos'] . ', ' . $partido['arbitro_segundo_nombre']; ?></div>
                                            <?php endif; ?>
                                            <?php if ($partido['anotador_nombre']): ?>
                                                <div><strong>Anotador:</strong> <?php echo $partido['anotador_apellidos'] . ', ' . $partido['anotador_nombre']; ?></div>
                                            <?php endif; ?>
                                            <?php if (!$partido['arbitro_principal_nombre'] && !$partido['arbitro_segundo_nombre'] && !$partido['anotador_nombre']): ?>
                                                <span class="text-muted">Por asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($partido['finalizado']): ?>
                                                <span class="badge" style="background: var(--success);">
                                                    <i class="fas fa-check-circle"></i> Finalizado
                                                </span>
                                            <?php elseif (strtotime($partido['fecha']) >= time()): ?>
                                                <span class="badge" style="background: var(--info);">
                                                    <i class="fas fa-clock"></i> Programado
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: var(--warning);">
                                                    <i class="fas fa-exclamation-triangle"></i> Pendiente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="verDetalles(<?php echo $partido['id']; ?>)"
                                                    title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($partido['condicion'] === 'LOCAL' && !$partido['finalizado']): ?>
                                                <button class="btn btn-warning btn-sm" 
                                                        onclick="gestionarPabellon(<?php echo $partido['id']; ?>)"
                                                        title="Gestionar pabellón">
                                                    <i class="fas fa-building"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Vista móvil -->
                    <div class="d-block d-md-none">
                        <?php foreach ($partidos as $partido): ?>
                        <div class="card mb-3">
                            <div class="card-header" style="background: <?php echo ($partido['condicion'] === 'LOCAL') ? 'var(--success)' : 'var(--info)'; ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-weight: bold;">
                                        <?php echo format_datetime($partido['fecha'], 'd/m/Y H:i'); ?>
                                    </span>
                                    <span class="badge" style="background: white; color: <?php echo ($partido['condicion'] === 'LOCAL') ? 'var(--success)' : 'var(--info)'; ?>;">
                                        <?php echo $partido['condicion']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h5><?php echo $partido['equipo_local']; ?></h5>
                                    <small class="text-muted">VS</small>
                                    <h5><?php echo $partido['equipo_visitante']; ?></h5>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.9em;">
                                    <div><strong>Categoría:</strong> <?php echo $partido['categoria']; ?></div>
                                    <div><strong>Pabellón:</strong> <?php echo $partido['pabellon']; ?></div>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button class="btn btn-info btn-sm" onclick="verDetalles(<?php echo $partido['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver Detalles
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <div class="text-center p-5">
                        <i class="fas fa-calendar-times" style="font-size: 4rem; color: var(--medium-gray);"></i>
                        <h3 class="mt-3">No hay partidos</h3>
                        <p class="text-muted mb-4">
                            <?php if ($equipo_filtro || $estado_filtro !== 'todos' || $fecha_desde || $fecha_hasta): ?>
                                No se encontraron partidos con los filtros aplicados
                            <?php else: ?>
                                Aún no tienes partidos programados
                            <?php endif; ?>
                        </p>
                        <a href="partidos.php" class="btn btn-primary">
                            <i class="fas fa-refresh"></i> Ver Todos los Partidos
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Próximos partidos destacados -->
        <?php 
        $proximos_destacados = array_filter($partidos, function($p) { 
            return !$p['finalizado'] && strtotime($p['fecha']) >= time() && strtotime($p['fecha']) <= strtotime('+7 days'); 
        });
        if (count($proximos_destacados) > 0): 
        ?>
        <div class="card">
            <div class="card-header" style="background: var(--info);">
                <i class="fas fa-star"></i> Próximos Partidos (Esta Semana)
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach (array_slice($proximos_destacados, 0, 4) as $partido): ?>
                    <div class="card">
                        <div class="card-header" style="background: <?php echo ($partido['condicion'] === 'LOCAL') ? 'var(--success)' : 'var(--info)'; ?>;">
                            <div class="text-center">
                                <strong><?php echo format_datetime($partido['fecha'], 'd/m/Y'); ?></strong><br>
                                <span style="font-size: 1.2em;"><?php echo format_datetime($partido['fecha'], 'H:i'); ?></span>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5><?php echo $partido['equipo_local']; ?></h5>
                            <div style="margin: 10px 0; color: var(--medium-gray);">VS</div>
                            <h5><?php echo $partido['equipo_visitante']; ?></h5>
                            
                            <div style="margin: 15px 0;">
                                <span class="badge" style="background: var(--primary-green);">
                                    <?php echo $partido['categoria']; ?>
                                </span>
                                <span class="badge" style="background: <?php echo ($partido['condicion'] === 'LOCAL') ? 'var(--success)' : 'var(--info)'; ?>;">
                                    <?php echo $partido['condicion']; ?>
                                </span>
                            </div>
                            
                            <p class="text-muted mb-2">
                                <i class="fas fa-map-marker-alt"></i> <?php echo $partido['pabellon']; ?>
                            </p>
                            
                            <button class="btn btn-primary btn-sm" onclick="verDetallesPartido(<?php echo $partido['id']; ?>)">
                                <i class="fas fa-eye"></i> Ver Detalles
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Modal detalles partido -->
    <div id="modalDetallesPartido" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-alt"></i> Detalles del Partido</h3>
                <button class="modal-close" onclick="closeModal('modalDetallesPartido')">&times;</button>
            </div>
            <div class="modal-body" id="detallesPartidoContent">
                <!-- Se llenará dinámicamente -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalDetallesPartido')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal gestión pabellón -->
    <div id="modalGestionPabellon" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-building"></i> Gestión de Pabellón</h3>
                <button class="modal-close" onclick="closeModal('modalGestionPabellon')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Información sobre el Pabellón</h5>
                    <p>Como equipo local, es tu responsabilidad:</p>
                    <ul>
                        <li>Asegurar que el pabellón esté disponible</li>
                        <li>Verificar las condiciones de la instalación</li>
                        <li>Coordinar con el personal del pabellón</li>
                        <li>Informar cualquier problema al administrador</li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <strong>Importante:</strong> Si hay algún problema con el pabellón, contacta inmediatamente con el administrador.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalGestionPabellon')">Cerrar</button>
                <a href="mailto:admin@fedexvb.es?subject=Problema con Pabellón - <?php echo $club['nombre_club']; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Contactar Admin
                </a>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function verDetalles(partidoId) {
            fetch(`api/partidos.php?id=${partidoId}`)
                .then(response => response.json())
                .then(partido => {
                    if (partido && !partido.error) {
                        let content = `
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <h5>Información del Partido</h5>
                                    <p><strong>Fecha:</strong> ${partido.fecha}</p>
                                    <p><strong>Hora:</strong> ${partido.hora}</p>
                                    <p><strong>Categoría:</strong> ${partido.categoria}</p>
                                    <p><strong>Condición:</strong> 
                                        <span class="badge" style="background: ${partido.condicion === 'LOCAL' ? 'var(--success)' : 'var(--info)'};">
                                            ${partido.condicion}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <h5>Equipos</h5>
                                    <div style="text-align: center; background: var(--light-gray); padding: 20px; border-radius: 8px;">
                                        <h4 style="color: ${partido.condicion === 'LOCAL' ? 'var(--success)' : 'var(--info)'};">
                                            ${partido.equipo_local}
                                        </h4>
                                        <div style="margin: 10px 0; color: var(--medium-gray);">VS</div>
                                        <h4 style="color: ${partido.condicion === 'VISITANTE' ? 'var(--success)' : 'var(--info)'};">
                                            ${partido.equipo_visitante}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Agregar resultado si existe
                        if (partido.mis_sets !== null && partido.sets_rival !== null) {
                            const gano = partido.resultado === 'GANADO';
                            content += `
                                <h5 style="margin-top: 20px;">Resultado Final</h5>
                                <div class="card" style="background: ${gano ? 'var(--success)' : 'var(--danger)'}; color: white; margin-bottom: 15px;">
                                    <div class="card-body text-center">
                                        <h3 style="margin: 0;">
                                            ${partido.mi_equipo} ${partido.mis_sets} - ${partido.sets_rival} ${partido.equipo_rival}
                                        </h3>
                                        <p style="margin: 5px 0 0 0; font-size: 1.1em; font-weight: bold;">
                                            ${partido.resultado}
                                        </p>
                                    </div>
                                </div>
                            `;
                            
                            if (partido.sets_detalle && partido.sets_detalle.length > 0) {
                                content += `
                                    <h5>Detalle por Sets</h5>
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
                                    const ganadorLocal = parseInt(set.puntos_local) > parseInt(set.puntos_visitante);
                                    const ganadorVisitante = parseInt(set.puntos_visitante) > parseInt(set.puntos_local);
                                    
                                    // Resaltar el set ganado por mi equipo
                                    const resaltarLocal = ganadorLocal && partido.condicion === 'LOCAL';
                                    const resaltarVisitante = ganadorVisitante && partido.condicion === 'VISITANTE';
                                    
                                    content += `
                                        <tr>
                                            <td><strong>Set ${set.numero_set}</strong></td>
                                            <td class="${resaltarLocal ? 'text-success' : ''}" style="font-weight: ${ganadorLocal ? 'bold' : 'normal'};">
                                                ${set.puntos_local}
                                            </td>
                                            <td class="${resaltarVisitante ? 'text-success' : ''}" style="font-weight: ${ganadorVisitante ? 'bold' : 'normal'};">
                                                ${set.puntos_visitante}
                                            </td>
                                        </tr>
                                    `;
                                });
                                
                                content += `
                                            </tbody>
                                        </table>
                                    </div>
                                `;
                            }
                        } else {
                            content += `
                                <div style="margin-top: 20px; padding: 15px; background: var(--warning); color: white; border-radius: 5px; text-align: center;">
                                    <i class="fas fa-clock"></i> Partido sin resultado registrado
                                </div>
                            `;
                        }
                        
                        content += `
                            <div style="margin-top: 20px;">
                                <h5>Pabellón</h5>
                                <p><strong>Nombre:</strong> ${partido.pabellon}</p>
                                <p><strong>Ciudad:</strong> ${partido.ciudad}</p>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <h5>Equipo Arbitral</h5>
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
                            </div>
                        `;
                        
                        document.getElementById('detallesPartidoContent').innerHTML = content;
                        openModal('modalDetallesPartido');
                    } else {
                        showNotification('Error al cargar los detalles del partido', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al cargar los detalles del partido', 'error');
                });
        }

        function gestionarPabellon(partidoId) {
            openModal('modalGestionPabellon');
        }

        function exportarPartidos() {
            const partidos = <?php echo json_encode($partidos); ?>;
            let csv = 'Fecha,Hora,Equipo Local,Equipo Visitante,Categoria,Pabellon,Ciudad,Condicion,Estado\n';
            
            partidos.forEach(p => {
                const fecha = new Date(p.fecha);
                const fechaStr = fecha.toLocaleDateString('es-ES');
                const horaStr = fecha.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
                const estado = p.finalizado ? 'Finalizado' : (new Date(p.fecha) >= new Date() ? 'Programado' : 'Pendiente');
                
                csv += `"${fechaStr}","${horaStr}","${p.equipo_local}","${p.equipo_visitante}","${p.categoria}","${p.pabellon}","${p.ciudad_pabellon}","${p.condicion}","${estado}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `partidos_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function verCalendario() {
            alert('Función de vista calendario en desarrollo. Próximamente disponible.');
        }
    </script>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda
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
