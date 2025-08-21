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

// Construir query para técnicos
$tecnicos_query = "SELECT t.*, e.nombre as equipo_nombre, c.nombre as categoria
                   FROM tecnicos t 
                   JOIN equipos e ON t.equipo_id = e.id 
                   JOIN categorias c ON e.categoria_id = c.id 
                   WHERE e.club_id = ?";
$params = [$club['id']];

if ($equipo_filtro) {
    $tecnicos_query .= " AND e.id = ?";
    $params[] = $equipo_filtro;
}

$tecnicos_query .= " ORDER BY e.nombre, t.apellidos, t.nombre";

$stmt = $conn->prepare($tecnicos_query);
$stmt->execute($params);
$tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario de búsqueda
$busqueda = '';
if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
    $busqueda = sanitize_input($_GET['buscar']);
    $tecnicos = array_filter($tecnicos, function($tecnico) use ($busqueda) {
        return stripos($tecnico['nombre'], $busqueda) !== false || 
               stripos($tecnico['apellidos'], $busqueda) !== false ||
               stripos($tecnico['nivel'], $busqueda) !== false;
    });
}

// Estadísticas
$total_tecnicos = count($tecnicos);
$tecnicos_por_equipo = [];
$niveles_count = [];

foreach ($equipos as $equipo) {
    $count = count(array_filter($tecnicos, function($t) use ($equipo) {
        return $t['equipo_nombre'] === $equipo['nombre'];
    }));
    $tecnicos_por_equipo[$equipo['nombre']] = $count;
}

foreach ($tecnicos as $tecnico) {
    $nivel = $tecnico['nivel'] ?: 'Sin especificar';
    $niveles_count[$nivel] = ($niveles_count[$nivel] ?? 0) + 1;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Técnicos - FEDEXVB</title>
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
            <li><a href="tecnicos.php" class="active"><i class="fas fa-chalkboard-teacher"></i> Gestión de Técnicos</a></li>
            <li><a href="partidos.php"><i class="fas fa-calendar-alt"></i> Mis Partidos</a></li>
            <li><a href="inscripciones.php"><i class="fas fa-file-signature"></i> Inscripciones</a></li>
            <li><a href="documentos.php"><i class="fas fa-folder-open"></i> Documentos</a></li>
            <li><a href="perfil.php"><i class="fas fa-building"></i> Perfil del Club</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-clipboard-user"></i> Gestión de Técnicos</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Técnicos
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
                    <i class="fas fa-chalkboard-teacher" style="font-size: 2rem; color: var(--warning);"></i>
                    <h3 style="color: var(--warning); margin: 10px 0;"><span id="total-count"><?php echo $total_tecnicos; ?></span></h3>
                    <p class="text-muted">Total Técnicos</p>
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
                    <i class="fas fa-chart-pie" style="font-size: 2rem; color: var(--success);"></i>
                    <h3 style="color: var(--success); margin: 10px 0;">
                        <?php echo $total_tecnicos > 0 && count($equipos) > 0 ? round($total_tecnicos / count($equipos), 1) : 0; ?>
                    </h3>
                    <p class="text-muted">Promedio por Equipo</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body text-center">
                    <button class="btn btn-primary btn-lg" onclick="openModal('modalContactoAdmin')" style="width: 100%;">
                        <i class="fas fa-plus"></i><br>
                        <small>Añadir Técnico</small>
                    </button>
                </div>
            </div>
        </div>

        <!-- Distribución por niveles -->
        <?php if (count($niveles_count) > 0): ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar"></i> Distribución por Niveles
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <?php foreach ($niveles_count as $nivel => $count): ?>
                    <div class="text-center p-3" style="background: var(--light-gray); border-radius: 8px;">
                        <h4 style="color: var(--warning); margin: 0;"><?php echo $count; ?></h4>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em;"><?php echo $nivel; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                        <label for="buscar">Buscar técnico:</label>
                        <input type="text" name="buscar" id="buscar" class="form-control" 
                               placeholder="Nombre, apellidos o nivel" value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    
                    <a href="tecnicos.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </form>
            </div>
        </div>

        <!-- Lista de técnicos -->
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <i class="fas fa-list"></i> Lista de Técnicos
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
                        <span class="badge" style="background: var(--warning);">
                            <?php echo count($tecnicos); ?> técnicos
                        </span>
                    </div>
                    <div>
                        <button class="btn btn-success btn-sm" onclick="exportarTecnicos()">
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
                                   placeholder="Buscar por nombre, nivel, equipo..." 
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
                        <span id="searchResults">0</span> técnico(s) encontrado(s)
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Busca por nombre, apellidos, nivel o equipo. Usa ESC para limpiar.</em>
                    </div>
                </div>
                
                <?php if (count($tecnicos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table searchable-table" id="tablaTecnicos">
                            <thead>
                                <tr>
                                    <th onclick="sortTable(0)">
                                        Apellidos y Nombre <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable(1)">
                                        Nivel <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable(2)">
                                        Equipo <i class="fas fa-sort"></i>
                                    </th>
                                    <th>Contacto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-user-tie" style="color: var(--warning); font-size: 1.5rem;"></i>
                                            <div>
                                                <strong><?php echo $tecnico['apellidos'] . ', ' . $tecnico['nombre']; ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($tecnico['nivel']): ?>
                                            <span class="badge" style="background: var(--success);">
                                                <?php echo $tecnico['nivel']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Sin especificar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo $tecnico['equipo_nombre']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $tecnico['categoria']; ?></small>
                                    </td>
                                    <td>
                                        <?php if ($tecnico['telefono']): ?>
                                            <i class="fas fa-phone"></i> <?php echo $tecnico['telefono']; ?><br>
                                        <?php endif; ?>
                                        <?php if ($tecnico['email']): ?>
                                            <i class="fas fa-envelope"></i> 
                                            <a href="mailto:<?php echo $tecnico['email']; ?>">
                                                <?php echo $tecnico['email']; ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$tecnico['telefono'] && !$tecnico['email']): ?>
                                            <span class="text-muted">Sin datos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-sm" 
                                                onclick="verDetallesTecnico(<?php echo $tecnico['id']; ?>)"
                                                title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-warning btn-sm" 
                                                onclick="editarTecnico(<?php echo $tecnico['id']; ?>)"
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
                        <i class="fas fa-user-tie" style="font-size: 4rem; color: var(--medium-gray);"></i>
                        <h3 class="mt-3">No se encontraron técnicos</h3>
                        <p class="text-muted mb-4">
                            <?php if ($busqueda): ?>
                                No hay técnicos que coincidan con la búsqueda "<?php echo htmlspecialchars($busqueda); ?>"
                            <?php elseif ($equipo_filtro): ?>
                                Este equipo no tiene técnicos registrados
                            <?php else: ?>
                                Aún no tienes técnicos registrados en tus equipos
                            <?php endif; ?>
                        </p>
                        <button class="btn btn-primary btn-lg" onclick="openModal('modalContactoAdmin')">
                            <i class="fas fa-plus"></i> Registrar Primer Técnico
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Distribución por equipos -->
        <?php if (count($equipos) > 1 && $total_tecnicos > 0): ?>
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
                            <h3 style="color: var(--warning);">
                                <?php echo $tecnicos_por_equipo[$equipo['nombre']]; ?>
                            </h3>
                            <p class="text-muted"><?php echo $equipo['categoria']; ?></p>
                            <div class="progress" style="height: 10px; margin: 10px 0;">
                                <div class="progress-bar" style="width: <?php echo ($total_tecnicos > 0) ? ($tecnicos_por_equipo[$equipo['nombre']] / $total_tecnicos * 100) : 0; ?>%; background: var(--warning);"></div>
                            </div>
                            <a href="tecnicos.php?equipo=<?php echo $equipo['id']; ?>" class="btn btn-primary btn-sm">
                                Ver técnicos
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Información sobre niveles de técnicos 
        <div class="card">
            <div class="card-header">
                <i class="fas fa-graduation-cap"></i> Niveles de Técnicos
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Niveles de Formación</h5>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                        <div>
                            <strong>Técnico Nivel 1:</strong>
                            <p class="text-muted mb-0">Formación básica de entrenador</p>
                        </div>
                        <div>
                            <strong>Técnico Nivel 2:</strong>
                            <p class="text-muted mb-0">Formación intermedia</p>
                        </div>
                        <div>
                            <strong>Técnico Nivel 3:</strong>
                            <p class="text-muted mb-0">Formación avanzada</p>
                        </div>
                        <div>
                            <strong>Técnico Nacional:</strong>
                            <p class="text-muted mb-0">Máximo nivel de formación</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        -->
    </main>

    <!-- Modal para contactar administrador -->
    <div id="modalContactoAdmin" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Gestión de Técnicos</h3>
                <button class="modal-close" onclick="closeModal('modalContactoAdmin')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Información Importante</h5>
                    <p>Para añadir, modificar o dar de baja técnicos, es necesario contactar con el administrador:</p>
                    
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
                            <li>DNI del técnico</li>
                            <li>Título o certificado de nivel</li>
                            <li>Curriculum vitae</li>
                            <li>Certificado de antecedentes penales</li>
                            <li>Fotografía tamaño carnet</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Nota:</strong> Los técnicos deben estar habilitados por la federación correspondiente.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalContactoAdmin')">Cerrar</button>
                <a href="mailto:admin@fedexvb.es?subject=Gestión de Técnicos - <?php echo $club['nombre_club']; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Contactar
                </a>
            </div>
        </div>
    </div>

    <!-- Modal detalles técnico -->
    <div id="modalDetallesTecnico" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-tie"></i> Detalles del Técnico</h3>
                <button class="modal-close" onclick="closeModal('modalDetallesTecnico')">&times;</button>
            </div>
            <div class="modal-body" id="detallesTecnicoContent">
                <!-- Se llenará dinámicamente -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalDetallesTecnico')">Cerrar</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function verDetallesTecnico(tecnicoId) {
            const tecnicos = <?php echo json_encode($tecnicos); ?>;
            const tecnico = tecnicos.find(t => t.id == tecnicoId);
            
            if (tecnico) {
                const content = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h5>Información Personal</h5>
                            <p><strong>Nombre:</strong> ${tecnico.nombre}</p>
                            <p><strong>Apellidos:</strong> ${tecnico.apellidos}</p>
                            <p><strong>Nivel:</strong> ${tecnico.nivel || 'Sin especificar'}</p>
                        </div>
                        <div>
                            <h5>Información del Equipo</h5>
                            <p><strong>Equipo:</strong> ${tecnico.equipo_nombre}</p>
                            <p><strong>Categoría:</strong> ${tecnico.categoria}</p>
                            <p><strong>Estado:</strong> <span class="badge" style="background: var(--success);">Activo</span></p>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <h5>Contacto</h5>
                        <p><strong>Teléfono:</strong> ${tecnico.telefono || 'No registrado'}</p>
                        <p><strong>Email:</strong> ${tecnico.email || 'No registrado'}</p>
                    </div>
                `;
                document.getElementById('detallesTecnicoContent').innerHTML = content;
                openModal('modalDetallesTecnico');
            }
        }

        function editarTecnico(tecnicoId) {
            if (confirm('¿Desea solicitar una modificación de este técnico al administrador?')) {
                const tecnicos = <?php echo json_encode($tecnicos); ?>;
                const tecnico = tecnicos.find(t => t.id == tecnicoId);
                
                if (tecnico) {
                    const subject = `Modificación de Técnico - ${tecnico.nombre} ${tecnico.apellidos}`;
                    const body = `Hola,\n\nSolicito modificar los datos del siguiente técnico:\n\nNombre: ${tecnico.nombre}\nApellidos: ${tecnico.apellidos}\nNivel: ${tecnico.nivel || 'Sin especificar'}\nEquipo: ${tecnico.equipo_nombre}\n\nMotivo de la modificación:\n[Escribir aquí el motivo]\n\nNuevos datos:\n[Escribir aquí los nuevos datos]\n\nGracias.`;
                    
                    window.location.href = `mailto:admin@fedexvb.es?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
                }
            }
        }

        function exportarTecnicos() {
            const tecnicos = <?php echo json_encode($tecnicos); ?>;
            let csv = 'Nombre,Apellidos,Nivel,Equipo,Categoria,Telefono,Email\n';
            
            tecnicos.forEach(t => {
                csv += `"${t.nombre}","${t.apellidos}","${t.nivel || ''}","${t.equipo_nombre}","${t.categoria}","${t.telefono || ''}","${t.email || ''}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tecnicos_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function sortTable(columnIndex) {
            const table = document.getElementById('tablaTecnicos');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const isAscending = table.getAttribute('data-sort-direction') !== 'asc';
            table.setAttribute('data-sort-direction', isAscending ? 'asc' : 'desc');
            
            rows.sort((a, b) => {
                const aValue = a.cells[columnIndex].textContent.trim();
                const bValue = b.cells[columnIndex].textContent.trim();
                
                return isAscending ? 
                    aValue.localeCompare(bValue) : 
                    bValue.localeCompare(aValue);
            });
            
            rows.forEach(row => tbody.appendChild(row));
        }
    </script>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda para la tabla de técnicos
        document.addEventListener('DOMContentLoaded', function() {
            new TableSearchBar({
                searchInputId: 'searchInput',
                clearBtnId: 'searchClear',
                searchInfoId: 'searchInfo',
                searchResultsId: 'searchResults',
                totalCountId: 'total-count',
                tableSelector: 'tbody tr',
                columnsCount: 6,
                noResultsId: 'noResultsRow'
            });
        });
    </script>
</body>
</html>
