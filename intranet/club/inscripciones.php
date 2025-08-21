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

if (!$club) {
    header('Location: ../unauthorized.php');
    exit();
}

// Obtener categorías
$query = "SELECT * FROM categorias ORDER BY nombre";
$stmt = $conn->prepare($query);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener equipos actuales del club
$query = "SELECT e.*, c.nombre as categoria 
          FROM equipos e 
          JOIN categorias c ON e.categoria_id = c.id 
          WHERE e.club_id = ?
          ORDER BY c.nombre, e.nombre";
$stmt = $conn->prepare($query);
$stmt->execute([$club['id']]);
$equipos_actuales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripciones - FEDEXVB</title>
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
            <li><a href="equipos.php"><i class="fas fa-users-cog"></i> Mis Equipos</a></li>
            <li><a href="jugadores.php"><i class="fas fa-running"></i> Gestión de Jugadores</a></li>
            <li><a href="tecnicos.php"><i class="fas fa-chalkboard-teacher"></i> Gestión de Técnicos</a></li>
            <li><a href="partidos.php"><i class="fas fa-calendar-alt"></i> Mis Partidos</a></li>
            <li><a href="inscripciones.php" class="active"><i class="fas fa-file-signature"></i> Inscripciones</a></li>
            <li><a href="documentos.php"><i class="fas fa-folder-open"></i> Documentos</a></li>
            <li><a href="perfil.php"><i class="fas fa-building"></i> Perfil del Club</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-file-signature"></i> Guía de Inscripciones</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Inscripciones
            </div>
        </div>

        <!-- Información de temporada -->
        <div class="card">
            <div class="card-header" style="background: var(--primary-green);">
                <i class="fas fa-calendar"></i> Temporada 2025-2026
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <h5><i class="fas fa-info-circle"></i> Información de la Temporada Actual</h5>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
                        <div>
                            <h6>Fechas Importantes:</h6>
                            <ul>
                                <li><strong>Inicio inscripciones:</strong> 1 de Junio 2025</li>
                                <li><strong>Fin inscripciones:</strong> 31 de Agosto 2025</li>
                                <li><strong>Inicio competición:</strong> 15 de Septiembre 2025</li>
                                <li><strong>Fin competición:</strong> 30 de Mayo 2026</li>
                            </ul>
                        </div>
                        <div>
                            <h6>Estado Actual:</h6>
                            <p><strong>Temporada:</strong> <span class="badge" style="background: var(--success);">En Curso</span></p>
                            <p><strong>Inscripciones:</strong> <span class="badge" style="background: var(--danger);">Cerradas</span></p>
                            <p><strong>Próxima temporada:</strong> Junio 2026</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado actual del club -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clipboard-check"></i> Estado Actual de Inscripciones
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-users-cog" style="font-size: 2rem; color: var(--success);"></i>
                            <h3 style="color: var(--success); margin: 10px 0;"><?php echo count($equipos_actuales); ?></h3>
                            <p class="text-muted">Equipos Inscritos</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--primary-green);"></i>
                            <h3 style="color: var(--primary-green); margin: 10px 0;">Activo</h3>
                            <p class="text-muted">Estado del Club</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check" style="font-size: 2rem; color: var(--info);"></i>
                            <h3 style="color: var(--info); margin: 10px 0;">2025-26</h3>
                            <p class="text-muted">Temporada Actual</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body text-center">
                            <button class="btn btn-primary btn-lg" onclick="openModal('modalSolicitarEquipo')" style="width: 100%;">
                                <i class="fas fa-plus"></i><br>
                                <small>Solicitar Equipo</small>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Equipos actuales -->
        <?php if (count($equipos_actuales) > 0): ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Equipos Inscritos en la Temporada Actual
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($equipos_actuales as $equipo): ?>
                    <div class="card">
                        <div class="card-header" style="background: var(--success);">
                            <i class="fas fa-check-circle"></i> <?php echo $equipo['nombre']; ?>
                        </div>
                        <div class="card-body">
                            <p><strong>Categoría:</strong> <?php echo $equipo['categoria']; ?></p>
                            <p><strong>Temporada:</strong> <?php echo $equipo['temporada'] ?? '2025-2026'; ?></p>
                            <p><strong>Estado:</strong> <span class="badge" style="background: var(--success);">Activo</span></p>
                            
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <a href="equipos.php?equipo=<?php echo $equipo['id']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> Ver Equipo
                                </a>
                                <a href="jugadores.php?equipo=<?php echo $equipo['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-users"></i> Jugadores
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Proceso de inscripción -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-route"></i> Proceso de Inscripción de Equipos
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <h5><i class="fas fa-info-circle"></i> Información General</h5>
                    <p>El proceso de inscripción de equipos debe realizarse a través del administrador de la federación. A continuación se detalla el proceso completo.</p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Paso 1 -->
                    <div class="card">
                        <div class="card-header" style="background: var(--info);">
                            <h6><i class="fas fa-file-alt"></i> Paso 1: Documentación</h6>
                        </div>
                        <div class="card-body">
                            <h6>Documentos necesarios:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Solicitud de inscripción</li>
                                <li><i class="fas fa-check text-success"></i> Licencia del club</li>
                                <li><i class="fas fa-check text-success"></i> Seguro de responsabilidad civil</li>
                                <li><i class="fas fa-check text-success"></i> Lista provisional de jugadores</li>
                                <li><i class="fas fa-check text-success"></i> Lista de técnicos</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Paso 2 -->
                    <div class="card">
                        <div class="card-header" style="background: var(--warning);">
                            <h6><i class="fas fa-users"></i> Paso 2: Jugadores y Técnicos</h6>
                        </div>
                        <div class="card-body">
                            <h6>Por cada jugador:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Ficha federativa</li>
                                <li><i class="fas fa-check text-success"></i> DNI / Pasaporte</li>
                                <li><i class="fas fa-check text-success"></i> Certificado médico</li>
                                <li><i class="fas fa-check text-success"></i> Autorización parental (menores)</li>
                                <li><i class="fas fa-check text-success"></i> Fotografía carnet</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Paso 3 -->
                    <div class="card">
                        <div class="card-header" style="background: var(--success);">
                            <h6><i class="fas fa-euro-sign"></i> Paso 3: Pagos</h6>
                        </div>
                        <div class="card-body">
                            <h6>Tasas de inscripción:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Inscripción del equipo</li>
                                <li><i class="fas fa-check text-success"></i> Licencias de jugadores</li>
                                <li><i class="fas fa-check text-success"></i> Licencias de técnicos</li>
                                <li><i class="fas fa-check text-success"></i> Seguro deportivo</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Paso 4 -->
                    <div class="card">
                        <div class="card-header" style="background: var(--primary-green);">
                            <h6><i class="fas fa-check-circle"></i> Paso 4: Confirmación</h6>
                        </div>
                        <div class="card-body">
                            <h6>Finalización:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Revisión de documentación</li>
                                <li><i class="fas fa-check text-success"></i> Confirmación de pagos</li>
                                <li><i class="fas fa-check text-success"></i> Asignación de calendario</li>
                                <li><i class="fas fa-check text-success"></i> Activación en sistema</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categorías disponibles -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Categorías Disponibles
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Información sobre Categorías</h5>
                    <p>La federación organiza competiciones en las siguientes categorías. Cada categoría tiene sus propios requisitos de edad y formación.</p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <?php foreach ($categorias as $categoria): ?>
                    <div class="card">
                        <div class="card-header" style="background: var(--light-green);">
                            <h6><?php echo $categoria['nombre']; ?></h6>
                        </div>
                        <div class="card-body">
                            <?php if ($categoria['descripcion']): ?>
                                <p class="text-muted"><?php echo $categoria['descripcion']; ?></p>
                            <?php endif; ?>
                            
                            <?php
                            // Verificar si el club tiene equipo en esta categoría
                            $tiene_equipo = false;
                            foreach ($equipos_actuales as $equipo) {
                                if ($equipo['categoria'] === $categoria['nombre']) {
                                    $tiene_equipo = true;
                                    break;
                                }
                            }
                            ?>
                            
                            <?php if ($tiene_equipo): ?>
                                <span class="badge" style="background: var(--success);">
                                    <i class="fas fa-check"></i> Inscrito
                                </span>
                            <?php else: ?>
                                <button class="btn btn-primary btn-sm" onclick="solicitarCategoria('<?php echo $categoria['nombre']; ?>')">
                                    <i class="fas fa-plus"></i> Solicitar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Requisitos y normativa -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-gavel"></i> Requisitos y Normativa
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <h5><i class="fas fa-users"></i> Requisitos de Jugadores</h5>
                        <ul>
                            <li>Edad mínima según categoría</li>
                            <li>Certificado médico de aptitud deportiva</li>
                            <li>Licencia federativa válida</li>
                            <li>Seguro deportivo obligatorio</li>
                            <li>Autorización parental (menores de edad)</li>
                        </ul>
                    </div>

                    <div>
                        <h5><i class="fas fa-clipboard-user"></i> Requisitos de Técnicos</h5>
                        <ul>
                            <li>Titulación mínima de Técnico Nivel 1</li>
                            <li>Licencia federativa de técnico</li>
                            <li>Certificado de antecedentes penales</li>
                            <li>Seguro de responsabilidad civil</li>
                            <li>Formación en primeros auxilios (recomendada)</li>
                        </ul>
                    </div>

                    <div>
                        <h5><i class="fas fa-building"></i> Requisitos del Club</h5>
                        <ul>
                            <li>Licencia de club federativo vigente</li>
                            <li>Seguro de responsabilidad civil</li>
                            <li>Instalaciones deportivas homologadas</li>
                            <li>Estatutos y reglamento interno</li>
                            <li>Solvencia económica acreditada</li>
                        </ul>
                    </div>
                </div>

                <div class="alert alert-warning mt-4">
                    <h5><i class="fas fa-exclamation-triangle"></i> Importante</h5>
                    <ul class="mb-0">
                        <li>Todas las inscripciones deben realizarse dentro del plazo establecido</li>
                        <li>La documentación incompleta puede resultar en la no admisión del equipo</li>
                        <li>Los pagos deben estar al día antes del inicio de la competición</li>
                        <li>Cualquier cambio en las listas debe ser comunicado al administrador</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Contacto para inscripciones -->
        <div class="card">
            <div class="card-header" style="background: var(--primary-green);">
                <i class="fas fa-phone"></i> Contacto para Inscripciones
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; align-items: center;">
                    <div>
                        <h5>Información de Contacto</h5>
                        <p><i class="fas fa-envelope"></i> <strong>Email:</strong> admin@fedexvb.es</p>
                        <p><i class="fas fa-phone"></i> <strong>Teléfono:</strong> [Número de contacto]</p>
                        <p><i class="fas fa-clock"></i> <strong>Horario:</strong> Lunes a Viernes, 9:00 - 17:00</p>
                    </div>
                    
                    <div class="text-center">
                        <button class="btn btn-primary btn-lg" onclick="openModal('modalContactoInscripcion')">
                            <i class="fas fa-envelope"></i> Contactar para Inscripción
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal solicitar equipo -->
    <div id="modalSolicitarEquipo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Solicitar Nuevo Equipo</h3>
                <button class="modal-close" onclick="closeModal('modalSolicitarEquipo')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Solicitud de Equipo</h5>
                    <p>Para solicitar la inscripción de un nuevo equipo, contacta con el administrador proporcionando la siguiente información:</p>
                    
                    <h6>Información Necesaria:</h6>
                    <ul>
                        <li>Categoría del equipo</li>
                        <li>Nombre del equipo</li>
                        <li>Lista provisional de jugadores</li>
                        <li>Técnicos disponibles</li>
                        <li>Disponibilidad de instalaciones</li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <strong>Nota:</strong> Las inscripciones para la temporada 2025-2026 están cerradas. La próxima apertura será en Junio 2026.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalSolicitarEquipo')">Cerrar</button>
                <a href="mailto:admin@fedexvb.es?subject=Solicitud de Nuevo Equipo - <?php echo $club['nombre_club']; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Enviar Solicitud
                </a>
            </div>
        </div>
    </div>

    <!-- Modal contacto inscripción -->
    <div id="modalContactoInscripcion" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-envelope"></i> Contacto para Inscripciones</h3>
                <button class="modal-close" onclick="closeModal('modalContactoInscripcion')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Información de Contacto</h5>
                    <p>Para cualquier consulta sobre inscripciones, documentación o procesos administrativos:</p>
                    
                    <div style="margin: 20px 0;">
                        <h6><i class="fas fa-user"></i> Administrador de la Federación</h6>
                        <p><strong>Email:</strong> admin@fedexvb.es</p>
                        <p><strong>Teléfono:</strong> [Número de contacto]</p>
                        <p><strong>Horario:</strong> Lunes a Viernes, 9:00 - 17:00 h</p>
                    </div>
                    
                    <div class="alert alert-success">
                        <strong>Tip:</strong> Para una respuesta más rápida, incluye en tu email el nombre del club y el motivo específico de la consulta.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalContactoInscripcion')">Cerrar</button>
                <a href="mailto:admin@fedexvb.es?subject=Consulta Inscripciones - <?php echo $club['nombre_club']; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Enviar Email
                </a>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function solicitarCategoria(categoria) {
            const subject = `Solicitud de Inscripción - Categoría ${categoria}`;
            const body = `Hola,\n\nSolicito información para inscribir un equipo en la categoría ${categoria}.\n\nDatos del club:\n- Nombre: <?php echo $club['nombre_club']; ?>\n- Categoría solicitada: ${categoria}\n\nPor favor, proporcionen información sobre:\n- Documentación necesaria\n- Plazos de inscripción\n- Costes asociados\n- Proceso a seguir\n\nGracias.`;
            
            window.location.href = `mailto:admin@fedexvb.es?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        }

        // Destacar las fechas importantes con colores
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const inscripcionesAbiertas = today >= new Date('2025-06-01') && today <= new Date('2025-08-31');
            
            if (inscripcionesAbiertas) {
                // Cambiar el badge de estado si las inscripciones están abiertas
                const estadoBadge = document.querySelector('.badge:contains("Cerradas")');
                if (estadoBadge) {
                    estadoBadge.style.background = 'var(--success)';
                    estadoBadge.innerHTML = 'Abiertas';
                }
            }
        });
    </script>
</body>
</html>
