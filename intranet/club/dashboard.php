<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('club');

$database = new Database();
$conn = $database->getConnection();

// Obtener ID del club
$query = "SELECT id, nombre_club FROM clubes WHERE usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener equipos del club
$query = "SELECT e.*, c.nombre as categoria 
          FROM equipos e 
          JOIN categorias c ON e.categoria_id = c.id 
          WHERE e.club_id = ?
          ORDER BY e.nombre";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del club
$stats = [];
$stats['equipos'] = count($equipos);

// Total de jugadores
$query = "SELECT COUNT(*) FROM jugadores j 
          JOIN equipos e ON j.equipo_id = e.id 
          WHERE e.club_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$stats['jugadores'] = $stmt->fetchColumn();

// Total de técnicos
$query = "SELECT COUNT(*) FROM tecnicos t 
          JOIN equipos e ON t.equipo_id = e.id 
          WHERE e.club_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$stats['tecnicos'] = $stmt->fetchColumn();

// Próximos partidos del club
$query = "SELECT p.*, el.nombre as equipo_local, ev.nombre as equipo_visitante, 
                 pab.nombre as pabellon, cat.nombre as categoria,
                 CASE 
                     WHEN p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) THEN 'LOCAL'
                     ELSE 'VISITANTE'
                 END as condicion
          FROM partidos p
          JOIN equipos el ON p.equipo_local_id = el.id
          JOIN equipos ev ON p.equipo_visitante_id = ev.id
          JOIN pabellones pab ON p.pabellon_id = pab.id
          JOIN categorias cat ON p.categoria_id = cat.id
          WHERE (p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) 
                OR p.equipo_visitante_id IN (SELECT id FROM equipos WHERE club_id = ?))
                AND p.fecha >= NOW()
          ORDER BY p.fecha ASC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id'], $club['id'], $club['id']]);
$proximosPartidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Club - FEDEXVB</title>
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="equipos.php"><i class="fas fa-users-cog"></i> Mis Equipos</a></li>
            <li><a href="jugadores.php"><i class="fas fa-running"></i> Gestión de Jugadores</a></li>
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
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard del Club</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Dashboard
            </div>
        </div>

        <!-- Información del club 
        <div class="card">
            <div class="card-header" style="background: var(--primary-green);">
                <i class="fas fa-building"></i> <?php echo $club['nombre_club']; ?>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Bienvenido al panel de gestión de tu club</h5>
                    <p class="mb-0">
                        Desde aquí puedes gestionar todos los aspectos de tu club: equipos, jugadores, técnicos y consultar información sobre partidos.
                    </p>
                </div>
            </div>
        </div>
        -->

        <!-- Tarjetas de estadísticas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header" style="background: var(--primary-green);">
                    <i class="fas fa-users-cog"></i> Equipos
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--primary-green); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['equipos']; ?>
                    </h2>
                    <p class="text-muted">Equipos registrados</p>
                    <a href="equipos.php" class="btn btn-primary btn-sm">Gestionar equipos</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--info);">
                    <i class="fas fa-running"></i> Jugadores
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--info); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['jugadores']; ?>
                    </h2>
                    <p class="text-muted">Jugadores en total</p>
                    <a href="jugadores.php" class="btn btn-info btn-sm">Ver jugadores</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--warning);">
                    <i class="fas fa-chalkboard-teacher"></i> Técnicos
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--warning); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['tecnicos']; ?>
                    </h2>
                    <p class="text-muted">Técnicos registrados</p>
                    <a href="tecnicos.php" class="btn btn-warning btn-sm">Gestionar técnicos</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--success);">
                    <i class="fas fa-calendar-check"></i> Partidos
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--success); font-size: 2.5rem; margin: 0;">
                        <?php echo count($proximosPartidos); ?>
                    </h2>
                    <p class="text-muted">Próximos partidos</p>
                    <a href="partidos.php" class="btn btn-success btn-sm">Ver calendario</a>
                </div>
            </div>
        </div>

        <!-- Mis equipos -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users-cog"></i> Mis Equipos
            </div>
            <div class="card-body">
                <?php if (count($equipos) > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <?php foreach ($equipos as $equipo): ?>
                        <div class="card">
                            <div class="card-header" style="background: var(--info);">
                                <i class="fas fa-users"></i> <?php echo $equipo['nombre']; ?>
                            </div>
                            <div class="card-body">
                                <p><strong>Categoría:</strong> <?php echo $equipo['categoria']; ?></p>
                                
                                <?php
                                // Obtener estadísticas del equipo
                                $query = "SELECT COUNT(*) FROM jugadores WHERE equipo_id = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([$equipo['id']]);
                                $jugadores_equipo = $stmt->fetchColumn();
                                
                                $query = "SELECT COUNT(*) FROM tecnicos WHERE equipo_id = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([$equipo['id']]);
                                $tecnicos_equipo = $stmt->fetchColumn();
                                ?>
                                
                                <div style="display: flex; justify-content: space-between; margin: 15px 0;">
                                    <span><i class="fas fa-running"></i> <?php echo $jugadores_equipo; ?> jugadores</span>
                                    <span><i class="fas fa-clipboard-user"></i> <?php echo $tecnicos_equipo; ?> técnicos</span>
                                </div>
                                
                                <div style="display: flex; gap: 10px;">
                                    <a href="equipos.php?equipo=<?php echo $equipo['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Ver detalles
                                    </a>
                                    <a href="jugadores.php?equipo=<?php echo $equipo['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-users"></i> Jugadores
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-users-slash" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No tienes equipos registrados</h4>
                        <p class="text-muted">Contacta con el administrador para registrar tus equipos</p>
                        <a href="inscripciones.php" class="btn btn-primary">
                            <i class="fas fa-file-signature"></i> Guía de Inscripción
                        </a>
                    </div>
                <?php endif; ?>
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
                                    <th>Condición</th>
                                    <th>Pabellón</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proximosPartidos as $partido): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo format_datetime($partido['fecha'], 'd/m/Y'); ?></strong><br>
                                        <small><?php echo format_datetime($partido['fecha'], 'H:i'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--primary-green);">
                                            <?php echo $partido['categoria']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo $partido['equipo_local']; ?></strong><br>
                                        <small>vs</small><br>
                                        <strong><?php echo $partido['equipo_visitante']; ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: <?php echo $partido['condicion'] == 'LOCAL' ? 'var(--success)' : 'var(--info)'; ?>;">
                                            <?php echo $partido['condicion']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $partido['pabellon']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="partidos.php" class="btn btn-primary">
                            Ver todos los partidos
                        </a>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No hay partidos programados</h4>
                        <p class="text-muted">Los partidos aparecerán cuando sean programados por el administrador</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-rocket"></i> Acciones Rápidas
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <a href="equipos.php" class="btn btn-primary" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-users-cog" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Gestionar Equipos
                    </a>
                    
                    <a href="jugadores.php" class="btn btn-info" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-running" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Ver Jugadores
                    </a>
                    
                    <a href="partidos.php" class="btn btn-success" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-calendar-alt" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Mis Partidos
                    </a>
                    
                    <a href="inscripciones.php" class="btn btn-warning" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-file-signature" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Guía Inscripción
                    </a>
                </div>
            </div>
        </div>

        <!-- Información importante -->
        <div class="card">
            <div class="card-header" style="background: var(--warning);">
                <i class="fas fa-info-circle"></i> Información Importante
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb"></i> Recordatorios para clubes:</h5>
                    <ul class="mb-0">
                        <li>Mantén actualizada la información de jugadores y técnicos</li>
                        <li>Revisa regularmente el calendario de partidos</li>
                        <li>Para partidos como local, gestiona la disponibilidad del pabellón</li>
                        <li>Consulta las guías de inscripción para nuevas temporadas</li>
                        <li>Contacta con el administrador para cualquier cambio importante</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/app.js"></script>
    
    <?php if ($_SESSION['password_temporal']): ?>
    <script>
        // Mostrar modal para cambio de contraseña si es temporal
        document.addEventListener('DOMContentLoaded', function() {
            if (confirm('Debe cambiar su contraseña temporal. ¿Desea hacerlo ahora?')) {
                window.location.href = 'cambiar-password.php';
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
