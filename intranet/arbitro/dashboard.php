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
$query = "SELECT p.*, el.nombre as equipo_local, ev.nombre as equipo_visitante, 
                 pab.nombre as pabellon, pab.ciudad, cat.nombre as categoria,
                 ap.nombre as arbitro_principal, as2.nombre as arbitro_segundo, an.nombre as anotador
          FROM partidos p
          JOIN equipos el ON p.equipo_local_id = el.id
          JOIN equipos ev ON p.equipo_visitante_id = ev.id
          JOIN pabellones pab ON p.pabellon_id = pab.id
          JOIN categorias cat ON p.categoria_id = cat.id
          LEFT JOIN arbitros ap ON p.arbitro_principal_id = ap.id
          LEFT JOIN arbitros as2 ON p.arbitro_segundo_id = as2.id
          LEFT JOIN arbitros an ON p.anotador_id = an.id
          WHERE p.arbitro_principal_id = ? OR p.arbitro_segundo_id = ? OR p.anotador_id = ?
          ORDER BY p.fecha ASC";
$stmt = $conn->prepare($query);
$stmt->execute([$arbitro_id, $arbitro_id, $arbitro_id]);
$partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del árbitro
$stats = [];
$stats['partidos_total'] = count($partidos);
$stats['partidos_completados'] = count(array_filter($partidos, function($p) { return $p['finalizado']; }));
$stats['partidos_pendientes'] = $stats['partidos_total'] - $stats['partidos_completados'];

// Próximos partidos
$proximosPartidos = array_filter($partidos, function($p) {
    return strtotime($p['fecha']) >= time();
});
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Árbitro - FEDEXVB</title>
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="disponibilidad.php"><i class="fas fa-calendar-check"></i> Mi Disponibilidad</a></li>
            <li><a href="partidos.php"><i class="fa-solid fa-globe"></i> Mis Partidos</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Mis Liquidaciones</a></li>
            <li><a href="arbitros.php"><i class="fas fa-users"></i> Lista de Árbitros</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard del Árbitro</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Dashboard
            </div>
        </div>

        <!-- Información importante -->
        <div class="card">
            <div class="card-header" style="background: var(--warning);">
                <i class="fas fa-info-circle"></i> Información Importante
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb"></i> Recordatorios:</h5>
                    <ul class="mb-0">
                        <li>Mantén actualizada tu disponibilidad mensualmente</li>
                        <li>Revisa regularmente tus partidos asignados</li>
                        <li>Consulta tus liquidaciones para verificar los pagos</li>
                        <li>Si tienes alguna rectificación, comunícala a través del sistema</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tarjetas de estadísticas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header" style="background: var(--primary-green);">
                    <i class="fa-solid fa-globe"></i> Total Partidos
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--primary-green); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['partidos_total']; ?>
                    </h2>
                    <p class="text-muted">Partidos asignados</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--success);">
                    <i class="fas fa-check-circle"></i> Completados
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--success); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['partidos_completados']; ?>
                    </h2>
                    <p class="text-muted">Partidos finalizados</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--warning);">
                    <i class="fas fa-clock"></i> Pendientes
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--warning); font-size: 2.5rem; margin: 0;">
                        <?php echo $stats['partidos_pendientes']; ?>
                    </h2>
                    <p class="text-muted">Partidos por disputar</p>
                    <a href="partidos.php" class="btn btn-warning btn-sm">Ver calendario</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--info);">
                    <i class="fas fa-calendar-alt"></i> Disponibilidad
                </div>
                <div class="card-body text-center">
                    <div style="margin: 20px 0;">
                        <i class="fas fa-calendar-check" style="font-size: 2rem; color: var(--info);"></i>
                    </div>
                    <p class="text-muted">Gestiona tu disponibilidad</p>
                    <a href="disponibilidad.php" class="btn btn-info btn-sm">Actualizar</a>
                </div>
            </div>
        </div>

        <!-- Próximos partidos -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-calendar-check"></i> Próximos Partidos Asignados
            </div>
            <div class="card-body">
                <?php if (count($proximosPartidos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Categoría</th>
                                    <th>Partido</th>
                                    <th>Pabellón</th>
                                    <th>Mi Función</th>
                                    <th>Compañeros</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($proximosPartidos, 0, 5) as $partido): ?>
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
                                        <strong><?php echo $partido['pabellon']; ?></strong><br>
                                        <small><?php echo $partido['ciudad']; ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $funcion = '';
                                        if ($partido['arbitro_principal_id'] == $arbitro_id) $funcion = '1º Árbitro';
                                        elseif ($partido['arbitro_segundo_id'] == $arbitro_id) $funcion = '2º Árbitro';
                                        elseif ($partido['anotador_id'] == $arbitro_id) $funcion = 'Anotador';
                                        ?>
                                        <span class="badge" style="background: var(--info);">
                                            <?php echo $funcion; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($partido['arbitro_principal'] && $partido['arbitro_principal_id'] != $arbitro_id): ?>
                                                1º: <?php echo $partido['arbitro_principal']; ?><br>
                                            <?php endif; ?>
                                            <?php if ($partido['arbitro_segundo'] && $partido['arbitro_segundo_id'] != $arbitro_id): ?>
                                                2º: <?php echo $partido['arbitro_segundo']; ?><br>
                                            <?php endif; ?>
                                            <?php if ($partido['anotador'] && $partido['anotador_id'] != $arbitro_id): ?>
                                                Anot: <?php echo $partido['anotador']; ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($proximosPartidos) > 5): ?>
                        <div class="text-center mt-3">
                            <a href="partidos.php" class="btn btn-primary">
                                Ver todos los partidos (<?php echo count($proximosPartidos); ?>)
                            </a>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No tienes partidos asignados</h4>
                        <p class="text-muted">
                            Asegúrate de mantener actualizada tu disponibilidad para recibir asignaciones
                        </p>
                        <a href="disponibilidad.php" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Actualizar Disponibilidad
                        </a>
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
                    <a href="disponibilidad.php" class="btn btn-primary" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-calendar-check" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Actualizar Disponibilidad
                    </a>
                    
                    <a href="partidos.php" class="btn btn-info" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fa-solid fa-globe" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Ver Mis Partidos
                    </a>
                    
                    <a href="liquidaciones.php" class="btn btn-success" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-file-invoice-dollar" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Mis Liquidaciones
                    </a>
                    
                    <a href="arbitros.php" class="btn" style="background: var(--primary-green); color: white; height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-users" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Lista de Árbitros
                    </a>
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
