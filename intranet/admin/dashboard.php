<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

// Obtener estadísticas para el dashboard
$stats = [];

// Total de usuarios por tipo
$query = "SELECT tipo_usuario, COUNT(*) as total FROM usuarios WHERE activo = 1 GROUP BY tipo_usuario";
$stmt = $conn->prepare($query);
$stmt->execute();
$userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($userStats as $stat) {
    $stats[$stat['tipo_usuario']] = $stat['total'];
}

// Total de partidos
$query = "SELECT COUNT(*) as total FROM partidos";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['partidos'] = $stmt->fetchColumn();

// Total de equipos
$query = "SELECT COUNT(*) as total FROM equipos";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['equipos'] = $stmt->fetchColumn();

// Estadísticas de licencias
$query = "SELECT COUNT(*) as total FROM licencias_arbitros WHERE activa = 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['licencias_activas'] = $stmt->fetchColumn();

// Licencias próximas a vencer (30 días)
$query = "SELECT COUNT(*) as total FROM licencias_arbitros 
          WHERE activa = 1 AND fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['licencias_por_vencer'] = $stmt->fetchColumn();

// Licencias vencidas
$query = "SELECT COUNT(*) as total FROM licencias_arbitros 
          WHERE activa = 1 AND fecha_vencimiento < NOW()";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['licencias_vencidas'] = $stmt->fetchColumn();

// Próximos partidos
$query = "SELECT p.*, el.nombre as equipo_local, ev.nombre as equipo_visitante, 
                 pab.nombre as pabellon, cat.nombre as categoria
          FROM partidos p
          JOIN equipos el ON p.equipo_local_id = el.id
          JOIN equipos ev ON p.equipo_visitante_id = ev.id
          JOIN pabellones pab ON p.pabellon_id = pab.id
          JOIN categorias cat ON p.categoria_id = cat.id
          WHERE p.fecha >= NOW()
          ORDER BY p.fecha ASC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$proximosPartidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Licencias próximas a vencer o vencidas
$query = "SELECT l.*, a.nombre, a.apellidos 
          FROM licencias_arbitros l
          JOIN arbitros a ON l.arbitro_id = a.id
          WHERE l.activa = 1 AND l.fecha_vencimiento <= DATE_ADD(NOW(), INTERVAL 60 DAY)
          ORDER BY l.fecha_vencimiento ASC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$licenciasAlertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrador - FEDEXVB</title>
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="usuarios.php"><i class="fas fa-users"></i> Gestión de Usuarios</a></li>
            <li><a href="partidos.php"><i class="fas fa-calendar-alt"></i> Gestión de Partidos</a></li>
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
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard del Administrador</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Dashboard
            </div>
        </div>

        <!-- Accesos rápidos -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-rocket"></i> Accesos Rápidos
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <a href="usuarios.php?action=create" class="btn btn-primary" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-user-plus" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Crear Usuario
                    </a>
                    
                    <a href="partidos.php?action=create" class="btn btn-info" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-calendar-plus" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Nuevo Partido
                    </a>
                    
                    <a href="liquidaciones.php?action=create" class="btn btn-warning" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-file-invoice-dollar" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Nueva Liquidación
                    </a>

                </div>
            </div>
        </div>

        <!-- Tarjetas de estadísticas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header" style="background: var(--primary-green);">
                    <i class="fas fa-users"></i> Total Usuarios
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--primary-green); font-size: 2.5rem; margin: 0;">
                        <?php echo array_sum($stats); ?>
                    </h2>
                    <p class="text-muted">Usuarios registrados</p>
                    <small>
                        Admins: <?php echo $stats['administrador'] ?? 0; ?> | 
                        Árbitros: <?php echo $stats['arbitro'] ?? 0; ?> | 
                        Clubes: <?php echo $stats['club'] ?? 0; ?>
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--info);">
                    <i class="fas fa-calendar-alt"></i> Partidos
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--info); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['partidos']; ?>
                    </h2>
                    <p class="text-muted">Partidos programados</p>
                    <a href="partidos.php" class="btn btn-info btn-sm">Ver todos</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--warning);">
                    <i class="fas fa-users-cog"></i> Equipos
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--warning); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['equipos']; ?>
                    </h2>
                    <p class="text-muted">Equipos registrados</p>
                    <a href="clubes.php" class="btn btn-warning btn-sm">Gestionar</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--success);">
                    <i class="fa-solid fa-person"></i> Árbitros
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--success); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['arbitro'] ?? 0; ?>
                    </h2>
                    <p class="text-muted">Árbitros activos</p>
                    <a href="arbitros.php" class="btn btn-success btn-sm">Ver disponibilidad</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--primary-green);">
                    <i class="fas fa-id-card"></i> Licencias
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--primary-green); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['licencias_activas'] ?? 0; ?>
                    </h2>
                    <p class="text-muted">Licencias activas</p>
                    <small>
                        <?php if (($stats['licencias_vencidas'] ?? 0) > 0): ?>
                            <span style="color: var(--danger);">Vencidas: <?php echo $stats['licencias_vencidas']; ?></span>
                        <?php endif; ?>
                        <?php if (($stats['licencias_por_vencer'] ?? 0) > 0): ?>
                            <br><span style="color: var(--warning);">Por vencer: <?php echo $stats['licencias_por_vencer']; ?></span>
                        <?php endif; ?>
                    </small>
                    <br>
                    <a href="licencias.php" class="btn btn-primary btn-sm mt-2">Gestionar</a>
                </div>
            </div>
        </div>

        <!-- Próximos partidos -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-calendar-check"></i> Próximos Partidos
            </div>
            <div class="card-body">
                <?php if (count($proximosPartidos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Categoría</th>
                                    <th>Partido</th>
                                    <th>Pabellón</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proximosPartidos as $partido): ?>
                                <tr>
                                    <td><?php echo format_datetime($partido['fecha']); ?></td>
                                    <td><span class="badge" style="background: var(--primary-green);"><?php echo $partido['categoria']; ?></span></td>
                                    <td>
                                        <strong><?php echo $partido['equipo_local']; ?></strong>
                                        vs
                                        <strong><?php echo $partido['equipo_visitante']; ?></strong>
                                    </td>
                                    <td><?php echo $partido['pabellon']; ?></td>
                                    <td>
                                        <span class="badge" style="background: var(--info);">Programado</span>
                                    </td>
                                    <td>
                                        <a href="partidos.php?edit=<?php echo $partido['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No hay partidos programados</h4>
                        <p class="text-muted">Comience creando partidos desde la gestión de partidos</p>
                        <a href="partidos.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Crear Partido
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alertas de Licencias 
        <div class="card">
            <div class="card-header" style="background: <?php echo (count($licenciasAlertas) > 0) ? 'var(--warning)' : 'var(--primary-green)'; ?>;">
                <i class="fas fa-exclamation-triangle"></i> Estado de Licencias
            </div>
            <div class="card-body">
                <?php if (count($licenciasAlertas) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Árbitro</th>
                                    <th>Nivel</th>
                                    <th>Fecha Vencimiento</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($licenciasAlertas as $licencia): ?>
                                    <?php
                                    $fechaVencimiento = new DateTime($licencia['fecha_vencimiento']);
                                    $hoy = new DateTime();
                                    $diasRestantes = $hoy->diff($fechaVencimiento)->days;
                                    $vencida = $fechaVencimiento < $hoy;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $licencia['nombre'] . ' ' . $licencia['apellidos']; ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: var(--primary-green);">
                                                <?php echo strtoupper($licencia['nivel_licencia']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_date($licencia['fecha_vencimiento']); ?></td>
                                        <td>
                                            <?php if ($vencida): ?>
                                                <span class="badge" style="background: var(--danger);">
                                                    <i class="fas fa-times"></i> Vencida
                                                </span>
                                            <?php elseif ($diasRestantes <= 7): ?>
                                                <span class="badge" style="background: var(--danger);">
                                                    <i class="fas fa-exclamation"></i> Vence en <?php echo $diasRestantes; ?> días
                                                </span>
                                            <?php elseif ($diasRestantes <= 30): ?>
                                                <span class="badge" style="background: var(--warning);">
                                                    <i class="fas fa-clock"></i> Vence en <?php echo $diasRestantes; ?> días
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: var(--info);">
                                                    <i class="fas fa-info"></i> Vence en <?php echo $diasRestantes; ?> días
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="licencias.php?edit=<?php echo $licencia['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Renovar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="licencias.php" class="btn btn-warning">
                            <i class="fas fa-eye"></i> Ver Todas las Licencias
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success);"></i>
                        <h4 class="mt-3" style="color: var(--success);">Todas las licencias están vigentes</h4>
                        <p class="text-muted">No hay licencias vencidas o próximas a vencer</p>
                        <a href="licencias.php" class="btn btn-primary">
                            <i class="fas fa-id-card"></i> Gestionar Licencias
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div> --->

        
    </main>

    <script src="../assets/js/app.js"></script>
</body>
</html>
