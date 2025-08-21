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

// Obtener equipo específico si se pasa por parámetro
$equipo_seleccionado = null;
if (isset($_GET['equipo'])) {
    $query = "SELECT e.*, c.nombre as categoria 
              FROM equipos e 
              JOIN categorias c ON e.categoria_id = c.id 
              WHERE e.id = ? AND e.club_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$_GET['equipo'], $club['id']]);
    $equipo_seleccionado = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener todos los equipos del club
$query = "SELECT e.*, c.nombre as categoria 
          FROM equipos e 
          JOIN categorias c ON e.categoria_id = c.id 
          WHERE e.club_id = ?
          ORDER BY c.nombre, e.nombre";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías disponibles
$query = "SELECT * FROM categorias ORDER BY nombre";
$stmt = $conn->prepare($query);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para obtener estadísticas de un equipo
function getEstadisticasEquipo($conn, $equipo_id) {
    $stats = [];
    
    // Jugadores
    $query = "SELECT COUNT(*) FROM jugadores WHERE equipo_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$equipo_id]);
    $stats['jugadores'] = $stmt->fetchColumn();
    
    // Técnicos
    $query = "SELECT COUNT(*) FROM tecnicos WHERE equipo_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$equipo_id]);
    $stats['tecnicos'] = $stmt->fetchColumn();
    
    // Partidos jugados
    $query = "SELECT COUNT(*) FROM partidos 
              WHERE (equipo_local_id = ? OR equipo_visitante_id = ?) 
              AND finalizado = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([$equipo_id, $equipo_id]);
    $stats['partidos_jugados'] = $stmt->fetchColumn();
    
    // Próximos partidos
    $query = "SELECT COUNT(*) FROM partidos 
              WHERE (equipo_local_id = ? OR equipo_visitante_id = ?) 
              AND fecha >= NOW() AND finalizado = 0";
    $stmt = $conn->prepare($query);
    $stmt->execute([$equipo_id, $equipo_id]);
    $stats['proximos_partidos'] = $stmt->fetchColumn();
    
    return $stats;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Equipos - FEDEXVB</title>
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
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="equipos.php" class="active"><i class="fas fa-users-cog"></i> Mis Equipos</a></li>
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
            <h1><i class="fas fa-users-cog"></i> Gestión de Equipos</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Equipos
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($equipo_seleccionado): ?>
            <!-- Vista detallada de un equipo -->
            <div class="card">
                <div class="card-header" style="background: var(--primary-green);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <i class="fas fa-users"></i> <?php echo $equipo_seleccionado['nombre']; ?>
                            <span class="badge" style="background: var(--info); margin-left: 10px;">
                                <?php echo $equipo_seleccionado['categoria']; ?>
                            </span>
                        </div>
                        <a href="equipos.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver a equipos
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php 
                    $stats = getEstadisticasEquipo($conn, $equipo_seleccionado['id']);
                    ?>
                    
                    <!-- Estadísticas del equipo -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-running" style="font-size: 2rem; color: var(--info);"></i>
                                <h3 style="color: var(--info); margin: 10px 0;"><?php echo $stats['jugadores']; ?></h3>
                                <p class="text-muted">Jugadores</p>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-chalkboard-teacher" style="font-size: 2rem; color: var(--warning);"></i>
                                <h3 style="color: var(--warning); margin: 10px 0;"><?php echo $stats['tecnicos']; ?></h3>
                                <p class="text-muted">Técnicos</p>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check" style="font-size: 2rem; color: var(--success);"></i>
                                <h3 style="color: var(--success); margin: 10px 0;"><?php echo $stats['partidos_jugados']; ?></h3>
                                <p class="text-muted">Partidos Jugados</p>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt" style="font-size: 2rem; color: var(--primary-green);"></i>
                                <h3 style="color: var(--primary-green); margin: 10px 0;"><?php echo $stats['proximos_partidos']; ?></h3>
                                <p class="text-muted">Próximos Partidos</p>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones del equipo -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <a href="jugadores.php?equipo=<?php echo $equipo_seleccionado['id']; ?>" class="btn btn-info btn-lg">
                            <i class="fas fa-running"></i> Ver Jugadores (<?php echo $stats['jugadores']; ?>)
                        </a>
                        
                        <a href="tecnicos.php?equipo=<?php echo $equipo_seleccionado['id']; ?>" class="btn btn-warning btn-lg">
                            <i class="fas fa-chalkboard-teacher"></i> Ver Técnicos (<?php echo $stats['tecnicos']; ?>)
                        </a>
                        
                        <a href="partidos.php?equipo=<?php echo $equipo_seleccionado['id']; ?>" class="btn btn-success btn-lg">
                            <i class="fas fa-calendar-alt"></i> Ver Partidos
                        </a>
                        
                        <button class="btn btn-primary btn-lg" onclick="openModal('modalContactoAdmin')">
                            <i class="fas fa-edit"></i> Solicitar Modificación
                        </button>
                    </div>

                    <!-- Información adicional -->
                    <div class="alert alert-info mt-4">
                        <h5><i class="fas fa-info-circle"></i> Información del Equipo</h5>
                        <p><strong>Categoría:</strong> <?php echo $equipo_seleccionado['categoria']; ?></p>
                        <p><strong>Temporada:</strong> <?php echo $equipo_seleccionado['temporada'] ?? '2025-2026'; ?></p>
                        <p class="mb-0"><strong>Estado:</strong> 
                            <span class="badge" style="background: var(--success);">Activo</span>
                        </p>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Vista general de equipos -->
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <i class="fas fa-users-cog"></i> Mis Equipos
                        </div>
                        <button class="btn btn-primary" onclick="openModal('modalContactoAdmin')">
                            <i class="fas fa-plus"></i> Solicitar Nuevo Equipo
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($equipos) > 0): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
                            <?php foreach ($equipos as $equipo): ?>
                            <?php $stats = getEstadisticasEquipo($conn, $equipo['id']); ?>
                            <div class="card">
                                <div class="card-header" style="background: var(--info);">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <i class="fas fa-users"></i> <?php echo $equipo['nombre']; ?>
                                        </div>
                                        <span class="badge" style="background: var(--primary-green);">
                                            <?php echo $equipo['categoria']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div style="display: flex; justify-content: space-between; margin: 15px 0;">
                                        <div class="text-center">
                                            <i class="fas fa-running" style="color: var(--info);"></i>
                                            <p style="margin: 5px 0 0 0; font-size: 0.9em;">
                                                <?php echo $stats['jugadores']; ?> jugadores
                                            </p>
                                        </div>
                                        <div class="text-center">
                                            <i class="fas fa-chalkboard-teacher" style="color: var(--warning);"></i>
                                            <p style="margin: 5px 0 0 0; font-size: 0.9em;">
                                                <?php echo $stats['tecnicos']; ?> técnicos
                                            </p>
                                        </div>
                                        <div class="text-center">
                                            <i class="fas fa-calendar-alt" style="color: var(--success);"></i>
                                            <p style="margin: 5px 0 0 0; font-size: 0.9em;">
                                                <?php echo $stats['proximos_partidos']; ?> próximos
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <a href="equipos.php?equipo=<?php echo $equipo['id']; ?>" 
                                           class="btn btn-primary btn-sm" style="flex: 1;">
                                            <i class="fas fa-eye"></i> Ver Detalles
                                        </a>
                                        <a href="jugadores.php?equipo=<?php echo $equipo['id']; ?>" 
                                           class="btn btn-info btn-sm" style="flex: 1;">
                                            <i class="fas fa-users"></i> Jugadores
                                        </a>
                                        <a href="partidos.php?equipo=<?php echo $equipo['id']; ?>" 
                                           class="btn btn-success btn-sm" style="flex: 1;">
                                            <i class="fas fa-calendar"></i> Partidos
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <i class="fas fa-users-slash" style="font-size: 4rem; color: var(--medium-gray);"></i>
                            <h3 class="mt-3">No tienes equipos registrados</h3>
                            <p class="text-muted mb-4">
                                Contacta con el administrador para registrar tus equipos en las diferentes categorías.
                            </p>
                            <button class="btn btn-primary btn-lg" onclick="openModal('modalContactoAdmin')">
                                <i class="fas fa-plus"></i> Solicitar Equipo
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información sobre categorías 
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i> Categorías Disponibles
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($categorias as $categoria): ?>
                        <div class="alert alert-light text-center">
                            <h6 style="margin: 0; color: var(--primary-green);">
                                <?php echo $categoria['nombre']; ?>
                            </h6>
                            <?php if ($categoria['descripcion']): ?>
                                <small class="text-muted"><?php echo $categoria['descripcion']; ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            -->
        <?php endif; ?>
    </main>

    <!-- Modal para contactar administrador -->
    <div id="modalContactoAdmin" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-envelope"></i> Contactar Administrador</h3>
                <button class="modal-close" onclick="closeModal('modalContactoAdmin')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Solicitud de Equipo/Modificación</h5>
                    <p>Para registrar un nuevo equipo o modificar la información de equipos existentes, contacta con el administrador:</p>
                    <ul>
                        <li><strong>Email:</strong> admin@fedexvb.es</li>
                        <li><strong>Teléfono:</strong> [Número de contacto]</li>
                    </ul>
                    
                    <h6>Información necesaria:</h6>
                    <ul>
                        <li>Nombre del equipo</li>
                        <li>Categoría</li>
                        <li>Lista de jugadores y técnicos</li>
                        <li>Documentación requerida</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalContactoAdmin')">Cerrar</button>
                <a href="mailto:admin@fedexvb.es?subject=Solicitud de Equipo - <?php echo $club['nombre_club']; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Enviar Email
                </a>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
</body>
</html>
