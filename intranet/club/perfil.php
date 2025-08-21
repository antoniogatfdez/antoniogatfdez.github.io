<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('club');

$database = new Database();
$conn = $database->getConnection();

$message = '';

// Obtener información completa del club
$query = "SELECT c.*, u.email, u.fecha_creacion
          FROM clubes c 
          JOIN usuarios u ON c.usuario_id = u.id 
          WHERE c.usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header('Location: ../unauthorized.php');
    exit();
}

// Obtener estadísticas del club
$stats = [];

// Número de equipos
$query = "SELECT COUNT(*) FROM equipos WHERE club_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$stats['equipos'] = $stmt->fetchColumn();

// Número de jugadores
$query = "SELECT COUNT(*) FROM jugadores j 
          JOIN equipos e ON j.equipo_id = e.id 
          WHERE e.club_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$stats['jugadores'] = $stmt->fetchColumn();

// Número de técnicos
$query = "SELECT COUNT(*) FROM tecnicos t 
          JOIN equipos e ON t.equipo_id = e.id 
          WHERE e.club_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$stats['tecnicos'] = $stmt->fetchColumn();

// Partidos jugados
$query = "SELECT COUNT(*) FROM partidos p
          WHERE (p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) 
                OR p.equipo_visitante_id IN (SELECT id FROM equipos WHERE club_id = ?))
                AND p.finalizado = 1";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id'], $club['id']]);
$stats['partidos_jugados'] = $stmt->fetchColumn();

// Partidos próximos
$query = "SELECT COUNT(*) FROM partidos p
          WHERE (p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) 
                OR p.equipo_visitante_id IN (SELECT id FROM equipos WHERE club_id = ?))
                AND p.fecha >= NOW() AND p.finalizado = 0";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id'], $club['id']]);
$stats['partidos_proximos'] = $stmt->fetchColumn();

// Obtener equipos por categoría
$query = "SELECT c.nombre as categoria, COUNT(e.id) as cantidad
          FROM categorias c
          LEFT JOIN equipos e ON c.id = e.categoria_id AND e.club_id = ?
          GROUP BY c.id, c.nombre
          ORDER BY c.nombre";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$equipos_por_categoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener historial reciente (últimos partidos)
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
          ORDER BY p.fecha DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id'], $club['id'], $club['id']]);
$historial_reciente = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil del Club - FEDEXVB</title>
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
                    <span>FEDEXVB - Club</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="user-name"><?php echo $club['nombre_club']; ?></div>
                        <div class="user-role">Club</div>
                    </div>
                    <div class="user-actions">
                        <a href="../cambiar-password.php" class="btn btn-secondary btn-sm" title="Cambiar Contraseña">
                            <i class="fas fa-key"></i>
                        </a>
                        <a href="../includes/logout.php" class="btn btn-secondary btn-sm" title="Cerrar Sesión">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
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
            <li><a href="partidos.php"><i class="fas fa-calendar-alt"></i> Mis Partidos</a></li>
            <li><a href="inscripciones.php"><i class="fas fa-file-signature"></i> Inscripciones</a></li>
            <li><a href="documentos.php"><i class="fas fa-folder-open"></i> Documentos</a></li>
            <li><a href="perfil.php" class="active"><i class="fas fa-building"></i> Perfil del Club</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-building"></i> Perfil del Club</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Perfil
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Información principal del club -->
        <div class="card">
            <div class="card-header" style="background: var(--primary-green);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <i class="fas fa-building"></i> Información del Club
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="openModal('modalEditarClub')">
                        <i class="fas fa-edit"></i> Solicitar Modificación
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <h5>Datos Básicos</h5>
                                <p><strong>Nombre del Club:</strong><br><?php echo $club['nombre_club']; ?></p>
                                <p><strong>Razón Social:</strong><br><?php echo $club['razon_social'] ?: 'No especificada'; ?></p>
                                <p><strong>Responsable:</strong><br><?php echo $club['nombre_responsable']; ?></p>
                            </div>
                            
                            <div>
                                <h5>Datos de Contacto</h5>
                                <p><strong>Email:</strong><br><?php echo $club['email']; ?></p>
                                <p><strong>IBAN:</strong><br>
                                    <?php if ($club['iban']): ?>
                                        <code><?php echo $club['iban']; ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">No registrado</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Fecha de registro:</strong><br><?php echo format_datetime($club['fecha_creacion'], 'd/m/Y'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-center p-4" style="background: var(--light-gray); border-radius: 8px;">
                            <i class="fas fa-building" style="font-size: 4rem; color: var(--primary-green); margin-bottom: 20px;"></i>
                            <h4 style="color: var(--primary-green);"><?php echo $club['nombre_club']; ?></h4>
                            <span class="badge" style="background: var(--success); font-size: 1rem; padding: 8px 15px;">
                                <i class="fas fa-check-circle"></i> Club Activo
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas del club -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar"></i> Estadísticas del Club
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-users-cog" style="font-size: 2.5rem; color: var(--primary-green);"></i>
                            <h3 style="color: var(--primary-green); margin: 15px 0;"><?php echo $stats['equipos']; ?></h3>
                            <p class="text-muted">Equipos Activos</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-running" style="font-size: 2.5rem; color: var(--info);"></i>
                            <h3 style="color: var(--info); margin: 15px 0;"><?php echo $stats['jugadores']; ?></h3>
                            <p class="text-muted">Jugadores</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-chalkboard-teacher" style="font-size: 2.5rem; color: var(--warning);"></i>
                            <h3 style="color: var(--warning); margin: 15px 0;"><?php echo $stats['tecnicos']; ?></h3>
                            <p class="text-muted">Técnicos</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check" style="font-size: 2.5rem; color: var(--success);"></i>
                            <h3 style="color: var(--success); margin: 15px 0;"><?php echo $stats['partidos_jugados']; ?></h3>
                            <p class="text-muted">Partidos Jugados</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-clock" style="font-size: 2.5rem; color: var(--danger);"></i>
                            <h3 style="color: var(--danger); margin: 15px 0;"><?php echo $stats['partidos_proximos']; ?></h3>
                            <p class="text-muted">Próximos Partidos</p>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de equipos por categoría -->
                <h5>Distribución de Equipos por Categoría</h5>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <?php foreach ($equipos_por_categoria as $categoria): ?>
                        <?php if ($categoria['cantidad'] > 0): ?>
                        <div class="card">
                            <div class="card-body text-center">
                                <h4 style="color: var(--primary-green);"><?php echo $categoria['cantidad']; ?></h4>
                                <p class="text-muted mb-0"><?php echo $categoria['categoria']; ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Historial reciente -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i> Actividad Reciente
            </div>
            <div class="card-body">
                <?php if (count($historial_reciente) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Partido</th>
                                    <th>Categoría</th>
                                    <th>Pabellón</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial_reciente as $partido): ?>
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
                                            <div style="margin: 5px 0; font-size: 0.8em;">VS</div>
                                            <strong style="color: <?php echo ($partido['condicion'] === 'VISITANTE') ? 'var(--success)' : 'var(--info)'; ?>;">
                                                <?php echo $partido['equipo_visitante']; ?>
                                            </strong>
                                        </div>
                                        <div class="text-center mt-1">
                                            <span class="badge" style="background: <?php echo ($partido['condicion'] === 'LOCAL') ? 'var(--success)' : 'var(--info)'; ?>; font-size: 0.7em;">
                                                <?php echo $partido['condicion']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--primary-green);">
                                            <?php echo $partido['categoria']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $partido['pabellon']; ?></td>
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
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="partidos.php" class="btn btn-primary">
                            <i class="fas fa-calendar-alt"></i> Ver Todos los Partidos
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">Sin actividad reciente</h4>
                        <p class="text-muted">No hay partidos registrados para mostrar</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información adicional -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Accesos rápidos -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-rocket"></i> Accesos Rápidos
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <a href="equipos.php" class="btn btn-primary" style="height: 60px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                            <i class="fas fa-users-cog" style="margin-right: 8px;"></i>
                            Gestionar Equipos
                        </a>
                        
                        <a href="jugadores.php" class="btn btn-info" style="height: 60px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                            <i class="fas fa-running" style="margin-right: 8px;"></i>
                            Ver Jugadores
                        </a>
                        
                        <a href="partidos.php" class="btn btn-success" style="height: 60px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                            <i class="fas fa-calendar-alt" style="margin-right: 8px;"></i>
                            Calendario
                        </a>
                        
                        <a href="inscripciones.php" class="btn btn-warning" style="height: 60px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                            <i class="fas fa-file-signature" style="margin-right: 8px;"></i>
                            Inscripciones
                        </a>
                    </div>
                </div>
            </div>

            <!-- Información de seguridad -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-shield-alt"></i> Información de Seguridad
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Estado de la Cuenta</h6>
                        <p><strong>Último acceso:</strong> Ahora</p>
                        <p><strong>Contraseña:</strong> 
                            <?php if ($_SESSION['password_temporal']): ?>
                                <span class="badge" style="background: var(--warning);">Temporal</span>
                            <?php else: ?>
                                <span class="badge" style="background: var(--success);">Actualizada</span>
                            <?php endif; ?>
                        </p>
                        <p class="mb-0"><strong>Estado:</strong> <span class="badge" style="background: var(--success);">Activa</span></p>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <a href="../cambiar-password.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </a>
                        <button class="btn btn-info btn-sm" onclick="openModal('modalSeguridadInfo')">
                            <i class="fas fa-info"></i> Consejos
                        </button>
                    </div>
                </div>
            </div>
        </div>

        
    </main>

    <!-- Modal editar club -->
    <div id="modalEditarClub" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Solicitar Modificación de Datos</h3>
                <button class="modal-close" onclick="closeModal('modalEditarClub')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Modificación de Datos del Club</h5>
                    <p>Para modificar los datos del club, contacta con el administrador proporcionando la siguiente información:</p>
                    
                    <h6>Datos Actuales:</h6>
                    <ul>
                        <li><strong>Nombre del Club:</strong> <?php echo $club['nombre_club']; ?></li>
                        <li><strong>Razón Social:</strong> <?php echo $club['razon_social'] ?: 'No especificada'; ?></li>
                        <li><strong>Responsable:</strong> <?php echo $club['nombre_responsable']; ?></li>
                        <li><strong>Email:</strong> <?php echo $club['email']; ?></li>
                        <li><strong>IBAN:</strong> <?php echo $club['iban'] ?: 'No registrado'; ?></li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <strong>Importante:</strong> Cualquier cambio debe estar justificado y puede requerir documentación adicional.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalEditarClub')">Cerrar</button>
                <a href="mailto:admin@fedexvb.es?subject=Solicitud de Modificación de Datos - <?php echo $club['nombre_club']; ?>&body=Hola,%0A%0ASolicito modificar los siguientes datos del club:%0A%0A- Campo a modificar:%0A- Valor actual:%0A- Nuevo valor:%0A- Motivo del cambio:%0A%0AGracias." 
                   class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Enviar Solicitud
                </a>
            </div>
        </div>
    </div>

    <!-- Modal información de seguridad -->
    <div id="modalSeguridadInfo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-shield-alt"></i> Consejos de Seguridad</h3>
                <button class="modal-close" onclick="closeModal('modalSeguridadInfo')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-lock"></i> Recomendaciones de Seguridad</h5>
                    
                    <h6>Para mantener tu cuenta segura:</h6>
                    <ul>
                        <li>Cambia tu contraseña regularmente</li>
                        <li>Utiliza una contraseña fuerte (mínimo 8 caracteres, con mayúsculas, minúsculas y números)</li>
                        <li>No compartas tus credenciales con terceros</li>
                        <li>Cierra sesión cuando termines de usar el sistema</li>
                        <li>Notifica inmediatamente cualquier actividad sospechosa</li>
                    </ul>
                    
                    <h6>Si sospechas que tu cuenta ha sido comprometida:</h6>
                    <ul>
                        <li>Cambia tu contraseña inmediatamente</li>
                        <li>Contacta con el administrador</li>
                        <li>Revisa la actividad reciente de tu club</li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <strong>Nota:</strong> El administrador nunca te pedirá tu contraseña por email o teléfono.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalSeguridadInfo')">Cerrar</button>
                <a href="../cambiar-password.php" class="btn btn-primary">
                    <i class="fas fa-key"></i> Cambiar Contraseña
                </a>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        // Mostrar alerta si la contraseña es temporal
        <?php if ($_SESSION['password_temporal']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning';
            alertDiv.innerHTML = `
                <h5><i class="fas fa-exclamation-triangle"></i> Contraseña Temporal</h5>
                <p>Estás usando una contraseña temporal. Te recomendamos cambiarla por seguridad.</p>
                <a href="../cambiar-password.php" class="btn btn-warning btn-sm">
                    <i class="fas fa-key"></i> Cambiar Ahora
                </a>
            `;
            
            const mainContent = document.querySelector('.main-content');
            const firstCard = mainContent.querySelector('.card');
            mainContent.insertBefore(alertDiv, firstCard);
        });
        <?php endif; ?>

        // Función para generar reporte del club
        function generarReporte() {
            const datos = {
                club: '<?php echo $club['nombre_club']; ?>',
                equipos: <?php echo $stats['equipos']; ?>,
                jugadores: <?php echo $stats['jugadores']; ?>,
                tecnicos: <?php echo $stats['tecnicos']; ?>,
                partidos_jugados: <?php echo $stats['partidos_jugados']; ?>,
                partidos_proximos: <?php echo $stats['partidos_proximos']; ?>
            };
            
            let reporte = `REPORTE DEL CLUB - ${datos.club}\n`;
            reporte += `Fecha: ${new Date().toLocaleDateString('es-ES')}\n\n`;
            reporte += `ESTADÍSTICAS:\n`;
            reporte += `- Equipos activos: ${datos.equipos}\n`;
            reporte += `- Jugadores: ${datos.jugadores}\n`;
            reporte += `- Técnicos: ${datos.tecnicos}\n`;
            reporte += `- Partidos jugados: ${datos.partidos_jugados}\n`;
            reporte += `- Próximos partidos: ${datos.partidos_proximos}\n`;
            
            const blob = new Blob([reporte], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte_club_${new Date().toISOString().split('T')[0]}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

    </script>
</body>
</html>
