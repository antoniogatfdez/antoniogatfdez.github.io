<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();
$message = '';

// Obtener estadísticas de árbitros
$query = "SELECT a.*, 
                 COALESCE(stats.partidos_count, 0) as partidos_arbitrados,
                 COALESCE(disponibilidad_count, 0) as dias_disponibles
          FROM arbitros a
          LEFT JOIN (
              SELECT arbitro_principal_id as arbitro_id, COUNT(*) as partidos_count
              FROM partidos 
              WHERE arbitro_principal_id IS NOT NULL
              UNION ALL
              SELECT arbitro_segundo_id as arbitro_id, COUNT(*) as partidos_count
              FROM partidos 
              WHERE arbitro_segundo_id IS NOT NULL
              UNION ALL
              SELECT anotador_id as arbitro_id, COUNT(*) as partidos_count
              FROM partidos 
              WHERE anotador_id IS NOT NULL
          ) stats ON a.id = stats.arbitro_id
          LEFT JOIN (
              SELECT arbitro_id, COUNT(*) as disponibilidad_count
              FROM disponibilidad_arbitros
              WHERE disponible = 1 AND fecha >= CURDATE()
              GROUP BY arbitro_id
          ) disp ON a.id = disp.arbitro_id
          GROUP BY a.id
          ORDER BY a.nombre, a.apellidos";
$stmt = $conn->prepare($query);
$stmt->execute();
$arbitros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener disponibilidad de árbitros para el mes actual
$currentMonth = date('Y-m');
$firstDay = $currentMonth . '-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

$query = "SELECT a.id, a.nombre, a.apellidos, 
                 GROUP_CONCAT(CASE WHEN da.disponible = 1 THEN da.fecha END ORDER BY da.fecha) as fechas_disponibles,
                 COUNT(CASE WHEN da.disponible = 1 THEN 1 END) as dias_disponibles_mes
          FROM arbitros a
          LEFT JOIN disponibilidad_arbitros da ON a.id = da.arbitro_id 
              AND da.fecha BETWEEN ? AND ?
          GROUP BY a.id
          ORDER BY a.nombre, a.apellidos";
$stmt = $conn->prepare($query);
$stmt->execute([$firstDay, $lastDay]);
$disponibilidad_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Árbitros - FEDEXVB</title>
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
            <li><a href="arbitros.php" class="active"><i class="fa-solid fa-person"></i> Gestión de Árbitros</a></li>
            <li><a href="clubes.php"><i class="fas fa-building"></i> Gestión de Clubes</a></li>
            <li><a href="licencias.php"><i class="fas fa-id-card"></i> Gestión de Licencias</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Liquidaciones</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fa-solid fa-person"></i> Gestión de Árbitros</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> <a href="dashboard.php">Inicio</a> / Gestión de Árbitros
            </div>
        </div>

        <!-- Estadísticas generales -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header" style="background: var(--info);">
                    <i class="fas fa-users"></i> Total Árbitros
                </div>
                <div class="card-body text-center">
                    <h2 style="margin: 0; color: var(--info);"><?php echo count($arbitros); ?></h2>
                    <p style="margin: 0; color: var(--medium-gray);">Árbitros registrados</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--success);">
                    <i class="fas fa-calendar-check"></i> Disponibles Hoy
                </div>
                <div class="card-body text-center">
                    <?php
                    $today = date('Y-m-d');
                    $query = "SELECT COUNT(*) FROM disponibilidad_arbitros WHERE fecha = ? AND disponible = 1";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$today]);
                    $disponibles_hoy = $stmt->fetchColumn();
                    ?>
                    <h2 style="margin: 0; color: var(--success);"><?php echo $disponibles_hoy; ?></h2>
                    <p style="margin: 0; color: var(--medium-gray);">Disponibles hoy</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--warning);">
                    <i class="fas fa-calendar-week"></i> Fin de Semana
                </div>
                <div class="card-body text-center">
                    <?php
                    $nextSaturday = date('Y-m-d', strtotime('next saturday'));
                    $nextSunday = date('Y-m-d', strtotime('next sunday'));
                    $query = "SELECT COUNT(DISTINCT arbitro_id) FROM disponibilidad_arbitros 
                              WHERE fecha IN (?, ?) AND disponible = 1";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$nextSaturday, $nextSunday]);
                    $disponibles_finde = $stmt->fetchColumn();
                    ?>
                    <h2 style="margin: 0; color: var(--warning);"><?php echo $disponibles_finde; ?></h2>
                    <p style="margin: 0; color: var(--medium-gray);">Fin de semana próximo</p>
                </div>
            </div>
        </div>

        <!-- Lista de árbitros y estadísticas -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Lista de Árbitros y Estadísticas
                <span class="badge" style="background: var(--primary-green); color: white; margin-left: 10px;">
                    <span id="total-count"><?php echo count($arbitros); ?></span> árbitros
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
                                   placeholder="Buscar por nombre, ciudad, licencia..." 
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
                        <span id="searchResults">0</span> árbitro(s) encontrado(s)
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Busca por nombre, ciudad o tipo de licencia. Usa ESC para limpiar.</em>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table data-table searchable-table" id="arbitrosTable">
                        <thead>
                            <tr>
                                <th data-sortable>Nombre</th>
                                <th data-sortable>Ciudad</th>
                                <th data-sortable>Licencia</th>
                                <th data-sortable>Partidos Temporada</th>
                                <th data-sortable>Días Disponibles</th>
                                <th>Disponibilidad Mes</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($arbitros as $arbitro): ?>
                            <tr data-licencia="<?php echo $arbitro['licencia']; ?>" data-ciudad="<?php echo $arbitro['ciudad']; ?>">
                                <td>
                                    <strong><?php echo $arbitro['nombre'] . ' ' . $arbitro['apellidos']; ?></strong>
                                </td>
                                <td><?php echo $arbitro['ciudad']; ?></td>
                                <td>
                                    <span class="badge" style="background: 
                                        <?php 
                                        echo $arbitro['licencia'] == 'n3' ? 'var(--success)' : 
                                             ($arbitro['licencia'] == 'n2' ? 'var(--info)' : 
                                             ($arbitro['licencia'] == 'n1' ? 'var(--warning)' : 'var(--medium-gray)')); 
                                        ?>">
                                        <?php echo strtoupper($arbitro['licencia']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--primary-green);">
                                        <?php echo $arbitro['partidos_arbitrados']; ?> partidos
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--info);">
                                        <?php echo $arbitro['dias_disponibles']; ?> días
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $disponibilidad_arbitro = array_filter($disponibilidad_mes, function($d) use ($arbitro) {
                                        return $d['id'] == $arbitro['id'];
                                    });
                                    $disp = reset($disponibilidad_arbitro);
                                    ?>
                                    <small><?php echo $disp['dias_disponibles_mes'] ?? 0; ?> días este mes</small>
                                </td>
                                <td>
                                    <button onclick="verDisponibilidad(<?php echo $arbitro['id']; ?>)" class="btn btn-info btn-sm">
                                        <i class="fas fa-calendar-check"></i> Ver Disponibilidad
                                    </button>
                                    <button onclick="verEstadisticas(<?php echo $arbitro['id']; ?>)" class="btn btn-success btn-sm">
                                        <i class="fas fa-chart-bar"></i> Estadísticas
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Disponibilidad del mes actual -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-calendar-alt"></i> Disponibilidad General - <?php echo date('F Y'); ?>
            </div>
            <div class="card-body">
                <div id="disponibilidadChart" style="min-height: 300px;">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Árbitro</th>
                                    <th>Días Disponibles</th>
                                    <th>Fechas Disponibles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($disponibilidad_mes as $disp): ?>
                                <tr>
                                    <td><?php echo $disp['nombre'] . ' ' . $disp['apellidos']; ?></td>
                                    <td>
                                        <span class="badge" style="background: var(--success);">
                                            <?php echo $disp['dias_disponibles_mes']; ?> días
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php 
                                            if ($disp['fechas_disponibles']) {
                                                $fechas = explode(',', $disp['fechas_disponibles']);
                                                echo implode(', ', array_slice($fechas, 0, 5));
                                                if (count($fechas) > 5) echo '...';
                                            } else {
                                                echo 'Sin disponibilidad';
                                            }
                                            ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div> 
    </main>

    <!-- Modal Ver Disponibilidad -->
    <div id="disponibilidadModal" class="modal">
        <div class="modal-content" style="max-width: 800px; width: 90%;">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-check"></i> Disponibilidad del Árbitro</h2>
                <span class="close" onclick="closeModal('disponibilidadModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="calendarioDisponibilidad">
                    <!-- Se cargará dinámicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('disponibilidadModal')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal Estadísticas -->
    <div id="estadisticasModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-chart-bar"></i> Estadísticas del Árbitro</h2>
                <span class="close" onclick="closeModal('estadisticasModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="estadisticasContent">
                    <!-- Se cargará dinámicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('estadisticasModal')">Cerrar</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        // Función auxiliar para formatear fecha en formato YYYY-MM-DD sin problemas de zona horaria
        function formatDateString(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function verDisponibilidad(arbitroId) {
            // Empezar siempre con el mes actual usando método robusto
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear();
            const currentMonth = String(currentDate.getMonth() + 1).padStart(2, '0');
            const currentMonthStr = `${currentYear}-${currentMonth}`;
            
            loadDisponibilidadCalendar(arbitroId, currentMonthStr);
        }
        
        function loadDisponibilidadCalendar(arbitroId, monthStr) {
            // Mostrar loading mientras se carga
            document.getElementById('calendarioDisponibilidad').innerHTML = `
                <div style="text-align: center; padding: 50px;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Cargando disponibilidad...</p>
                </div>
            `;
            
            fetch(`api/disponibilidad.php?arbitro_id=${arbitroId}&month=${monthStr}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('=== ADMIN: Datos de disponibilidad recibidos ===');
                    console.log('Datos recibidos:', data);
                    console.log('Mes solicitado:', monthStr);
                    console.log('====================================');
                    
                    const [year, month] = monthStr.split('-');
                    const yearNum = parseInt(year);
                    const monthNum = parseInt(month) - 1; // JavaScript months are 0-based
                    
                    console.log(`Procesando: año ${yearNum}, mes ${monthNum} (${monthNum + 1})`);
                    
                    const monthNames = [
                        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                    ];
                    
                    // Obtener nombre del árbitro
                    const arbitroRow = document.querySelector(`button[onclick="verDisponibilidad(${arbitroId})"]`).closest('tr');
                    const arbitroNombre = arbitroRow.querySelector('td:first-child strong').textContent;
                    
                    let html = `
                        <div class="calendar-disponibilidad">
                            <div class="calendar-header-admin">
                                <div class="calendar-navigation-admin">
                                    <button onclick="navigateMonth(${arbitroId}, '${monthStr}', -1)" class="btn-nav-admin" title="Mes anterior">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <h4>${arbitroNombre} - ${monthNames[monthNum]} ${yearNum}</h4>
                                    <button onclick="navigateMonth(${arbitroId}, '${monthStr}', 1)" class="btn-nav-admin" title="Mes siguiente">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                                <div class="calendar-actions-admin">
                                    <button onclick="goToCurrentMonth(${arbitroId})" class="btn-today-admin" title="Ir al mes actual">
                                        <i class="fas fa-calendar-day"></i> Mes Actual (${new Date().toLocaleDateString('es-ES', {month: 'long', year: 'numeric'})})
                                    </button>
                                </div>
                            </div>
                            
                            <div class="calendar-grid-admin">
                                <!-- Headers de días -->
                                <div class="calendar-day-header-admin">Lun</div>
                                <div class="calendar-day-header-admin">Mar</div>
                                <div class="calendar-day-header-admin">Mié</div>
                                <div class="calendar-day-header-admin">Jue</div>
                                <div class="calendar-day-header-admin">Vie</div>
                                <div class="calendar-day-header-admin">Sáb</div>
                                <div class="calendar-day-header-admin">Dom</div>
                    `;
                    
                    // Calcular primer día del mes y ajustar para empezar en Lunes
                    const firstDay = new Date(yearNum, monthNum, 1);
                    let dayOfWeek = firstDay.getDay();
                    dayOfWeek = dayOfWeek === 0 ? 6 : dayOfWeek - 1; // Convertir domingo (0) a 6, resto -1
                    
                    const startDate = new Date(firstDay);
                    startDate.setDate(startDate.getDate() - dayOfWeek);
                    
                    const currentDateObj = new Date(startDate);
                    const today = new Date();
                    
                    // Generar 42 días (6 semanas completas)
                    for (let i = 0; i < 42; i++) {
                        const dayNumber = currentDateObj.getDate();
                        const isCurrentMonth = currentDateObj.getMonth() === monthNum;
                        const dateStr = formatDateString(currentDateObj);
                        const isToday = currentDateObj.toDateString() === today.toDateString();
                        
                        // Buscar disponibilidad para esta fecha
                        const dataForDate = data.find(d => d.fecha === dateStr);
                        const disponible = dataForDate && dataForDate.disponible == 1;
                        const tieneInfo = dataForDate !== undefined;
                        const observaciones = dataForDate ? dataForDate.observaciones : '';
                        
                        // Debug para fechas del mes actual
                        if (isCurrentMonth && i < 10) { // Solo los primeros 10 días para no saturar
                            console.log(`Día ${dayNumber}: dateStr=${dateStr}, tieneInfo=${tieneInfo}, disponible=${disponible}`);
                        }
                        
                        let dayClass = 'calendar-day-admin';
                        if (!isCurrentMonth) dayClass += ' other-month';
                        if (isToday) dayClass += ' today';
                        if (isCurrentMonth && tieneInfo && disponible) dayClass += ' available';
                        if (isCurrentMonth && tieneInfo && !disponible) dayClass += ' unavailable';
                        
                        let iconHtml = '';
                        if (isCurrentMonth) {
                            if (tieneInfo) {
                                iconHtml = disponible ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>';
                                // Agregar icono de observaciones si las hay
                                if (disponible && observaciones && observaciones.trim() !== '') {
                                    iconHtml += '<i class="fas fa-comment" style="margin-left: 4px; font-size: 0.7em; opacity: 0.8;" title="Tiene observaciones"></i>';
                                }
                            } else {
                                // Si no hay información específica, asumir no disponible por defecto
                                iconHtml = '<i class="fas fa-times" style="opacity: 0.3;"></i>';
                            }
                        } else {
                            iconHtml = '';
                        }
                        
                        // Crear tooltip con observaciones si las hay
                        let title = '';
                        if (isCurrentMonth && observaciones && observaciones.trim() !== '') {
                            title = `title="Observaciones: ${observaciones.replace(/"/g, '&quot;')}"`;
                        }
                        
                        html += `
                            <div class="${dayClass}" ${title} ${observaciones && observaciones.trim() !== '' ? `onclick="showObservacionesInfo('${dateStr}', '${observaciones.replace(/'/g, "\\'")}', '${dayNumber}')"` : ''} style="${observaciones && observaciones.trim() !== '' ? 'cursor: pointer;' : ''}">
                                <div class="day-number-admin">${dayNumber}</div>
                                <div class="availability-icon-admin">${iconHtml}</div>
                            </div>
                        `;
                        
                        currentDateObj.setDate(currentDateObj.getDate() + 1);
                    }
                    
                    html += `
                            </div>
                            
                            <div class="legend-admin">
                                <div class="legend-item-admin">
                                    <div class="legend-color-admin available"></div>
                                    <span><i class="fas fa-check" style="color: var(--light-green);"></i> Disponible</span>
                                </div>
                                <div class="legend-item-admin">
                                    <div class="legend-color-admin unavailable"></div>
                                    <span><i class="fas fa-times" style="color: #f44336;"></i> No disponible</span>
                                </div>
                                <div class="legend-item-admin">
                                    <div class="legend-color-admin default"></div>
                                    <span><i class="fas fa-times" style="color: #ccc;"></i> No disponible por defecto</span>
                                </div>
                                <div class="legend-item-admin">
                                    <div style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-comment" style="color: var(--primary-green); font-size: 0.8em;"></i>
                                    </div>
                                    <span><i class="fas fa-comment-dots" style="color: var(--primary-green);"></i> Con observaciones (click para ver)</span>
                                </div>
                                <div class="info-text-admin">
                                    <small><i class="fas fa-info-circle"></i> Mostrando ${monthNames[monthNum]} ${yearNum} - Los días sin configuración específica se consideran no disponibles</small>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('calendarioDisponibilidad').innerHTML = html;
                    
                    // Solo abrir el modal si no está ya abierto
                    if (!document.getElementById('disponibilidadModal').style.display || 
                        document.getElementById('disponibilidadModal').style.display === 'none') {
                        openModal('disponibilidadModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('calendarioDisponibilidad').innerHTML = `
                        <div style="text-align: center; padding: 50px; color: #d32f2f;">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                            <p>Error al cargar la disponibilidad</p>
                            <p><small>${error.message}</small></p>
                        </div>
                    `;
                    showNotification('Error al cargar disponibilidad', 'error');
                });
        }
        
        function navigateMonth(arbitroId, currentMonth, direction) {
            console.log(`Navegando desde ${currentMonth} en dirección ${direction}`);
            
            const [year, month] = currentMonth.split('-');
            const date = new Date(parseInt(year), parseInt(month) - 1, 1);
            date.setMonth(date.getMonth() + direction);
            
            // Usar método más robusto para evitar problemas de zona horaria
            const newYear = date.getFullYear();
            const newMonth = String(date.getMonth() + 1).padStart(2, '0');
            const newMonthStr = `${newYear}-${newMonth}`;
            
            console.log(`Nuevo mes: ${newMonthStr}`);
            
            loadDisponibilidadCalendar(arbitroId, newMonthStr);
        }
        
        function goToCurrentMonth(arbitroId) {
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear();
            const currentMonth = String(currentDate.getMonth() + 1).padStart(2, '0');
            const currentMonthStr = `${currentYear}-${currentMonth}`;
            
            console.log(`Yendo al mes actual: ${currentMonthStr}`);
            loadDisponibilidadCalendar(arbitroId, currentMonthStr);
        }

        function verEstadisticas(arbitroId) {
            fetch(`api/estadisticas.php?arbitro_id=${arbitroId}`)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div class="stat-card">
                                <h4>Partidos Arbitrados</h4>
                                <div class="stat-number">${data.total_partidos || 0}</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">${data.como_principal || 0}</div>
                                <div class="stat-label">Como Principal</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">${data.como_segundo || 0}</div>
                                <div class="stat-label">Como Segundo</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">${data.como_anotador || 0}</div>
                                <div class="stat-label">Como Anotador</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h5>Últimos Partidos</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Equipos</th>
                                            <th>Rol</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    
                    if (data.ultimos_partidos) {
                        data.ultimos_partidos.forEach(partido => {
                            html += `
                                <tr>
                                    <td>${partido.fecha}</td>
                                    <td>${partido.equipos}</td>
                                    <td>${partido.rol}</td>
                                </tr>
                            `;
                        });
                    }
                    
                    html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('estadisticasContent').innerHTML = html;
                    openModal('estadisticasModal');
                })
                .catch(error => {
                    showNotification('Error al cargar estadísticas', 'error');
                });
        }
        
        function showObservacionesInfo(fecha, observaciones, dia) {
            if (!observaciones || observaciones.trim() === '') return;
            
            // Usar formateo más robusto para evitar problemas de zona horaria
            const fechaObj = new Date(fecha + 'T12:00:00'); // Usar mediodía para evitar problemas
            const fechaFormateada = fechaObj.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Crear modal temporal para mostrar observaciones
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'block';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-comment"></i> Observaciones del Árbitro</h3>
                        <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <strong><i class="fas fa-calendar-alt"></i> ${fechaFormateada}</strong>
                        </div>
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid var(--primary-green);">
                            <div style="margin-bottom: 8px;"><strong><i class="fas fa-check-circle" style="color: var(--primary-green);"></i> Estado: Disponible</strong></div>
                            <div><strong><i class="fas fa-comment-dots"></i> Observaciones:</strong></div>
                            <div style="margin-top: 8px; padding: 10px; background: white; border-radius: 4px; font-style: italic;">
                                "${observaciones}"
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cerrar</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
    </script>

    <style>
        .calendar-day-mini {
            background: white;
            border: 1px solid var(--light-gray);
            padding: 5px;
            text-align: center;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .calendar-day-mini.available {
            background: #e8f5e8;
            color: var(--dark-green);
        }
        
        .calendar-day-mini.unavailable {
            background: #ffebee;
            color: #c62828;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-green);
        }
        
        .stat-label {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }
        
        /* Estilos para el calendario de disponibilidad del modal */
        .calendar-disponibilidad {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .calendar-header-admin {
            background: linear-gradient(135deg, var(--primary-green), var(--light-green));
            color: white;
            padding: 20px;
        }
        
        .calendar-navigation-admin {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .calendar-actions-admin {
            display: flex;
            justify-content: center;
        }
        
        .calendar-header-admin h4 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            flex: 1;
            text-align: center;
        }
        
        .btn-nav-admin {
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .btn-nav-admin:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
        }
        
        .btn-today-admin {
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-today-admin:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.5);
        }
        
        .calendar-grid-admin {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border-top: 1px solid #e0e0e0;
        }
        
        .calendar-day-header-admin {
            background: var(--primary-green);
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        
        .calendar-day-header-admin:last-child {
            border-right: none;
        }
        
        .calendar-day-admin {
            min-height: 60px;
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 4px;
            background: white;
            transition: all 0.2s ease;
        }
        
        .calendar-day-admin:nth-child(7n) {
            border-right: none;
        }
        
        .calendar-day-admin.other-month {
            color: #bbb;
            background: #fafafa;
        }
        
        .calendar-day-admin.available {
            background: linear-gradient(135deg, #e8f5e8, #f1f8e9);
            color: var(--dark-green);
            border-left: 3px solid var(--light-green);
        }
        
        .calendar-day-admin.unavailable {
            background: linear-gradient(135deg, #ffebee, #fce4ec);
            color: #c62828;
            border-left: 3px solid #f44336;
        }
        
        .calendar-day-admin.today {
            border: 2px solid var(--primary-green);
            font-weight: bold;
        }
        
        .day-number-admin {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
            line-height: 1;
        }
        
        .availability-icon-admin {
            font-size: 1rem;
            opacity: 0.8;
        }
        
        .calendar-day-admin.available .availability-icon-admin {
            color: var(--light-green);
        }
        
        .calendar-day-admin.unavailable .availability-icon-admin {
            color: #f44336;
        }
        
        /* Estilo especial para días con observaciones */
        .calendar-day-admin[title*="Observaciones"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(46, 125, 50, 0.3);
            cursor: pointer;
        }
        
        .calendar-day-admin .fas.fa-comment {
            color: var(--primary-green);
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .legend-admin {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 15px;
            background: linear-gradient(to right, #f8f9fa, #e9ecef, #f8f9fa);
            border-top: 1px solid #e0e0e0;
        }
        
        .legend-item-admin {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .legend-color-admin {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid rgba(0,0,0,0.1);
            background: white;
        }
        
        .legend-color-admin.available {
            background: linear-gradient(135deg, #e8f5e8, #f1f8e9);
            border-left: 3px solid var(--light-green);
        }
        
        .legend-color-admin.unavailable {
            background: linear-gradient(135deg, #ffebee, #fce4ec);
            border-left: 3px solid #f44336;
        }
        
        .legend-color-admin.default {
            background: white;
            border: 1px solid #e0e0e0;
        }
        
        .info-text-admin {
            grid-column: 1 / -1;
            text-align: center;
            color: var(--medium-gray);
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        /* Responsive para el modal */
        @media (max-width: 768px) {
            .calendar-navigation-admin {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .calendar-navigation-admin h4 {
                order: -1;
                margin-bottom: 10px;
            }
            
            .calendar-actions-admin {
                order: 1;
                margin-top: 10px;
            }
            
            .calendar-day-admin {
                min-height: 45px;
                padding: 4px 2px;
            }
            
            .day-number-admin {
                font-size: 0.9rem;
            }
            
            .availability-icon-admin {
                font-size: 0.8rem;
            }
            
            .legend-admin {
                flex-direction: column;
                gap: 10px;
                align-items: center;
            }
            
            .btn-nav-admin, .btn-today-admin {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .calendar-actions-admin {
                flex-direction: column;
                gap: 5px;
            }
        }
        
        /* Estilos para filtros mejorados */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary-black);
            margin-bottom: 5px;
            display: block;
        }
        
        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
        }
        
        /* Estilos para mensajes de resultados */
        #mensajeResultados {
            border-left: 4px solid var(--primary-green);
            background: linear-gradient(135deg, #f8f9fa, #e8f5e8);
            animation: slideInDown 0.3s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Estilos para filas filtradas */
        #arbitrosTable tbody tr[style*="background: #e8f5e8"] {
            border-left: 3px solid var(--light-green);
            transition: all 0.3s ease;
        }
        
        #arbitrosTable tbody tr[style*="background: #e8f5e8"]:hover {
            background: #d4edda !important;
            transform: translateX(5px);
        }
        
        /* Mejorar botones de filtros */
        .btn-secondary:hover {
            background-color: var(--medium-gray);
            border-color: var(--medium-gray);
        }
        
        .btn-info:hover {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        /* Estilos para la información de ayuda */
        .alert-info ul {
            margin-bottom: 0;
        }
        
        .alert-info ul li {
            margin-bottom: 3px;
        }
        
        /* Responsive para filtros */
        @media (max-width: 768px) {
            .card-body > div {
                grid-template-columns: 1fr !important;
            }
            
            .form-group div {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda para la tabla de árbitros
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
