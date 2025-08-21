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

// Filtro por equipo
$equipo_filtro = isset($_GET['equipo']) ? (int)$_GET['equipo'] : null;

// Obtener equipos del club
$query = "SELECT e.*, c.nombre as categoria 
          FROM equipos e 
          JOIN categorias c ON e.categoria_id = c.id 
          WHERE e.club_id = ?
          ORDER BY c.nombre, e.nombre";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir query para jugadores
$jugadores_query = "SELECT j.*, e.nombre as equipo_nombre, c.nombre as categoria,
                           TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) as edad
                    FROM jugadores j 
                    JOIN equipos e ON j.equipo_id = e.id 
                    JOIN categorias c ON e.categoria_id = c.id 
                    WHERE e.club_id = ?";
$params = [$club['id']];

if ($equipo_filtro) {
    $jugadores_query .= " AND e.id = ?";
    $params[] = $equipo_filtro;
}

$jugadores_query .= " ORDER BY e.nombre, j.apellidos, j.nombre";

$stmt = $conn->prepare($jugadores_query);
$stmt->execute($params);
$jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario de búsqueda
$busqueda = '';
if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
    $busqueda = sanitize_input($_GET['buscar']);
    $jugadores = array_filter($jugadores, function($jugador) use ($busqueda) {
        return stripos($jugador['nombre'], $busqueda) !== false || 
               stripos($jugador['apellidos'], $busqueda) !== false ||
               stripos($jugador['dni'], $busqueda) !== false;
    });
}

// Estadísticas
$total_jugadores = count($jugadores);
$jugadores_por_equipo = [];
foreach ($equipos as $equipo) {
    $count = count(array_filter($jugadores, function($j) use ($equipo) {
        return $j['equipo_nombre'] === $equipo['nombre'];
    }));
    $jugadores_por_equipo[$equipo['nombre']] = $count;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Jugadores - FEDEXVB</title>
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
            <li><a href="jugadores.php" class="active"><i class="fas fa-running"></i> Gestión de Jugadores</a></li>
            <li><a href="tecnicos.php"><i class="fas fa-chalkboard-teacher"></i> Gestión de Técnicos</a></li>
            <li><a href="partidos.php"><i class="fas fa-calendar-alt"></i> Mis Partidos</a></li>
            <li><a href="inscripciones.php"><i class="fas fa-file-signature"></i> Inscripciones</a></li>
            <li><a href="documentos.php"><i class="fas fa-folder-open"></i> Documentos</a></li>
            <li><a href="perfil.php"><i class="fas fa-building"></i> Perfil del Club</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-running"></i> Gestión de Jugadores</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Jugadores
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-running" style="font-size: 2rem; color: var(--primary-green);"></i>
                    <h3 style="color: var(--primary-green); margin: 10px 0;"><span id="total-count"><?php echo $total_jugadores; ?></span></h3>
                    <p class="text-muted">Total Jugadores</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-users-cog" style="font-size: 2rem; color: var(--info);"></i>
                    <h3 style="color: var(--info); margin: 10px 0;"><?php echo count($equipos); ?></h3>
                    <p class="text-muted">Equipos</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-chart-pie" style="font-size: 2rem; color: var(--warning);"></i>
                    <h3 style="color: var(--warning); margin: 10px 0;">
                        <?php echo $total_jugadores > 0 ? round($total_jugadores / count($equipos), 1) : 0; ?>
                    </h3>
                    <p class="text-muted">Promedio por Equipo</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body text-center">
                    <button class="btn btn-primary btn-lg" onclick="openModal('modalContactoAdmin')" style="width: 100%;">
                        <i class="fas fa-plus"></i><br>
                        <small>Añadir Jugador</small>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros y búsqueda -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-filter"></i> Filtros y Búsqueda
            </div>
            <div class="card-body">
                <form method="GET" action="" style="display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 15px; align-items: end;">
                    <div>
                        <label for="equipo">Filtrar por equipo:</label>
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
                        <label for="buscar">Buscar jugador:</label>
                        <input type="text" name="buscar" id="buscar" class="form-control" 
                               placeholder="Nombre, apellidos o DNI" value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    
                    <a href="jugadores.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </form>
            </div>
        </div>

        <!-- Lista de jugadores -->
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <i class="fas fa-list"></i> Lista de Jugadores
                        <?php if ($equipo_filtro): ?>
                            <?php 
                            $equipo_nombre = '';
                            foreach ($equipos as $e) {
                                if ($e['id'] == $equipo_filtro) {
                                    $equipo_nombre = $e['nombre'];
                                    break;
                                }
                            }
                            ?>
                            <span class="badge" style="background: var(--info);">
                                <?php echo $equipo_nombre; ?>
                            </span>
                        <?php endif; ?>
                        <span class="badge" style="background: var(--primary-green);">
                            <?php echo count($jugadores); ?> jugadores
                        </span>
                    </div>
                    <div>
                        <button class="btn btn-success btn-sm" onclick="exportarJugadores()">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="openModal('modalContactoAdmin')">
                            <i class="fas fa-plus"></i> Añadir
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Barra de búsqueda -->
                <div class="search-container">
                    <div class="search-input-group">
                        <div class="search-input-icon">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   id="searchInput" 
                                   placeholder="Buscar por nombre, apellidos, posición..." 
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
                        <span id="searchResults">0</span> jugador(es) encontrado(s)
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Busca por nombre, apellidos, posición o equipo. Usa ESC para limpiar.</em>
                    </div>
                </div>
                
                <?php if (count($jugadores) > 0): ?>
                    <div class="table-responsive">
                        <table class="table searchable-table" id="tablaJugadores">
                            <thead>
                                <tr>
                                    <th onclick="sortTable(0)">
                                        Apellidos y Nombre <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable(1)">
                                        DNI <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable(2)">
                                        Edad <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable(3)">
                                        Equipo <i class="fas fa-sort"></i>
                                    </th>
                                    <th>Contacto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jugadores as $jugador): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $jugador['apellidos'] . ', ' . $jugador['nombre']; ?></strong>
                                    </td>
                                    <td>
                                        <code><?php echo $jugador['dni']; ?></code>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--info);">
                                            <?php echo $jugador['edad']; ?> años
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo format_date($jugador['fecha_nacimiento']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo $jugador['equipo_nombre']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $jugador['categoria']; ?></small>
                                    </td>
                                    <td>
                                        <?php if ($jugador['telefono']): ?>
                                            <i class="fas fa-phone"></i> <?php echo $jugador['telefono']; ?><br>
                                        <?php endif; ?>
                                        <?php if ($jugador['email']): ?>
                                            <i class="fas fa-envelope"></i> 
                                            <a href="mailto:<?php echo $jugador['email']; ?>">
                                                <?php echo $jugador['email']; ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$jugador['telefono'] && !$jugador['email']): ?>
                                            <span class="text-muted">Sin datos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-sm" 
                                                onclick="verDetallesJugador(<?php echo $jugador['id']; ?>)"
                                                title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-warning btn-sm" 
                                                onclick="editarJugador(<?php echo $jugador['id']; ?>)"
                                                title="Solicitar modificación">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-5">
                        <i class="fas fa-user-slash" style="font-size: 4rem; color: var(--medium-gray);"></i>
                        <h3 class="mt-3">No se encontraron jugadores</h3>
                        <p class="text-muted mb-4">
                            <?php if ($busqueda): ?>
                                No hay jugadores que coincidan con la búsqueda "<?php echo htmlspecialchars($busqueda); ?>"
                            <?php elseif ($equipo_filtro): ?>
                                Este equipo no tiene jugadores registrados
                            <?php else: ?>
                                Aún no tienes jugadores registrados en tus equipos
                            <?php endif; ?>
                        </p>
                        <button class="btn btn-primary btn-lg" onclick="openModal('modalContactoAdmin')">
                            <i class="fas fa-plus"></i> Registrar Primer Jugador
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Distribución por equipos -->
        <?php if (count($equipos) > 1 && $total_jugadores > 0): ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar"></i> Distribución por Equipos
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <?php foreach ($equipos as $equipo): ?>
                    <div class="card">
                        <div class="card-header" style="background: var(--light-green);">
                            <?php echo $equipo['nombre']; ?>
                        </div>
                        <div class="card-body text-center">
                            <h3 style="color: var(--primary-green);">
                                <?php echo $jugadores_por_equipo[$equipo['nombre']]; ?>
                            </h3>
                            <p class="text-muted"><?php echo $equipo['categoria']; ?></p>
                            <div class="progress" style="height: 10px; margin: 10px 0;">
                                <div class="progress-bar" style="width: <?php echo ($total_jugadores > 0) ? ($jugadores_por_equipo[$equipo['nombre']] / $total_jugadores * 100) : 0; ?>%; background: var(--primary-green);"></div>
                            </div>
                            <a href="jugadores.php?equipo=<?php echo $equipo['id']; ?>" class="btn btn-primary btn-sm">
                                Ver jugadores
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Modal para contactar administrador -->
    <div id="modalContactoAdmin" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Gestión de Jugadores</h3>
                <button class="modal-close" onclick="closeModal('modalContactoAdmin')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Información Importante</h5>
                    <p>Para añadir, modificar o dar de baja jugadores, es necesario contactar con el administrador:</p>
                    
                    <div style="margin: 20px 0;">
                        <h6><i class="fas fa-envelope"></i> Contacto:</h6>
                        <ul>
                            <li><strong>Email:</strong> admin@fedexvb.es</li>
                            <li><strong>Teléfono:</strong> [Número de contacto]</li>
                        </ul>
                    </div>

                    <div style="margin: 20px 0;">
                        <h6><i class="fas fa-file-alt"></i> Documentación necesaria:</h6>
                        <ul>
                            <li>DNI del jugador</li>
                            <li>Fecha de nacimiento</li>
                            <li>Autorización parental (menores)</li>
                            <li>Certificado médico de aptitud</li>
                            <li>Fotografía tamaño carnet</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Nota:</strong> Los cambios pueden tardar 24-48 horas en aparecer en el sistema.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalContactoAdmin')">Cerrar</button>
                <a href="mailto:admin@fedexvb.es?subject=Gestión de Jugadores - <?php echo $club['nombre_club']; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Contactar
                </a>
            </div>
        </div>
    </div>

    <!-- Modal detalles jugador -->
    <div id="modalDetallesJugador" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Detalles del Jugador</h3>
                <button class="modal-close" onclick="closeModal('modalDetallesJugador')">&times;</button>
            </div>
            <div class="modal-body" id="detallesJugadorContent">
                <!-- Se llenará dinámicamente -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalDetallesJugador')">Cerrar</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function verDetallesJugador(jugadorId) {
            // Buscar los datos del jugador en el array PHP
            const jugadores = <?php echo json_encode($jugadores); ?>;
            const jugador = jugadores.find(j => j.id == jugadorId);
            
            if (jugador) {
                const content = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h5>Información Personal</h5>
                            <p><strong>Nombre:</strong> ${jugador.nombre}</p>
                            <p><strong>Apellidos:</strong> ${jugador.apellidos}</p>
                            <p><strong>DNI:</strong> ${jugador.dni}</p>
                            <p><strong>Fecha de nacimiento:</strong> ${jugador.fecha_nacimiento}</p>
                            <p><strong>Edad:</strong> ${jugador.edad} años</p>
                        </div>
                        <div>
                            <h5>Información del Equipo</h5>
                            <p><strong>Equipo:</strong> ${jugador.equipo_nombre}</p>
                            <p><strong>Categoría:</strong> ${jugador.categoria}</p>
                            <p><strong>Estado:</strong> <span class="badge" style="background: var(--success);">Activo</span></p>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <h5>Contacto</h5>
                        <p><strong>Teléfono:</strong> ${jugador.telefono || 'No registrado'}</p>
                        <p><strong>Email:</strong> ${jugador.email || 'No registrado'}</p>
                    </div>
                `;
                document.getElementById('detallesJugadorContent').innerHTML = content;
                openModal('modalDetallesJugador');
            }
        }

        function editarJugador(jugadorId) {
            if (confirm('¿Desea solicitar una modificación de este jugador al administrador?')) {
                const jugadores = <?php echo json_encode($jugadores); ?>;
                const jugador = jugadores.find(j => j.id == jugadorId);
                
                if (jugador) {
                    const subject = `Modificación de Jugador - ${jugador.nombre} ${jugador.apellidos}`;
                    const body = `Hola,\n\nSolicito modificar los datos del siguiente jugador:\n\nNombre: ${jugador.nombre}\nApellidos: ${jugador.apellidos}\nDNI: ${jugador.dni}\nEquipo: ${jugador.equipo_nombre}\n\nMotivo de la modificación:\n[Escribir aquí el motivo]\n\nNuevos datos:\n[Escribir aquí los nuevos datos]\n\nGracias.`;
                    
                    window.location.href = `mailto:admin@fedexvb.es?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
                }
            }
        }

        function exportarJugadores() {
            // Crear CSV simple con los datos de jugadores
            const jugadores = <?php echo json_encode($jugadores); ?>;
            let csv = 'Nombre,Apellidos,DNI,Edad,Equipo,Categoria,Telefono,Email\n';
            
            jugadores.forEach(j => {
                csv += `"${j.nombre}","${j.apellidos}","${j.dni}","${j.edad}","${j.equipo_nombre}","${j.categoria}","${j.telefono || ''}","${j.email || ''}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `jugadores_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function sortTable(columnIndex) {
            const table = document.getElementById('tablaJugadores');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Determinar dirección de ordenamiento
            const isAscending = table.getAttribute('data-sort-direction') !== 'asc';
            table.setAttribute('data-sort-direction', isAscending ? 'asc' : 'desc');
            
            rows.sort((a, b) => {
                const aValue = a.cells[columnIndex].textContent.trim();
                const bValue = b.cells[columnIndex].textContent.trim();
                
                if (columnIndex === 2) { // Edad - ordenamiento numérico
                    return isAscending ? 
                        parseInt(aValue) - parseInt(bValue) : 
                        parseInt(bValue) - parseInt(aValue);
                } else { // Texto
                    return isAscending ? 
                        aValue.localeCompare(bValue) : 
                        bValue.localeCompare(aValue);
                }
            });
            
            // Reordenar las filas
            rows.forEach(row => tbody.appendChild(row));
        }
    </script>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda para la tabla de jugadores
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
