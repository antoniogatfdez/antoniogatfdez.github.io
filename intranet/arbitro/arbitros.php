<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('arbitro');

$database = new Database();
$conn = $database->getConnection();

// Obtener lista de árbitros activos con información de contacto
$query = "SELECT a.*, u.email, 
                 CASE 
                     WHEN a.nombre != '' AND a.apellidos != '' 
                     THEN CONCAT(a.nombre, ' ', a.apellidos)
                     ELSE 'Sin información'
                 END as nombre_completo,
                 CASE 
                     WHEN a.licencia = 'anotador' THEN 'Anotador'
                     WHEN a.licencia = 'n1' THEN 'Nivel 1'
                     WHEN a.licencia = 'n2' THEN 'Nivel 2'
                     WHEN a.licencia = 'n3' THEN 'Nivel 3'
                     ELSE 'Sin licencia'
                 END as licencia_texto
          FROM arbitros a
          JOIN usuarios u ON a.usuario_id = u.id
          WHERE u.activo = 1
          ORDER BY a.apellidos, a.nombre";

$stmt = $conn->prepare($query);
$stmt->execute();
$arbitros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$total_arbitros = count($arbitros);
$arbitros_con_telefono = count(array_filter($arbitros, function($a) { 
    return !empty($a['telefono']); 
}));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Árbitros - FEDEXVB</title>
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
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="disponibilidad.php"><i class="fas fa-calendar-check"></i> Mi Disponibilidad</a></li>
            <li><a href="partidos.php"><i class="fa-solid fa-globe"></i> Mis Partidos</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Mis Liquidaciones</a></li>
            <li><a href="arbitros.php" class="active"><i class="fas fa-users"></i> Lista de Árbitros</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-users"></i> Lista de Árbitros</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Lista de Árbitros
            </div>
        </div>

        <!-- Información de contacto 
        <div class="card">
            <div class="card-header" style="background: var(--info);">
                <i class="fas fa-info-circle"></i> Información de Contacto
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb"></i> Datos de Contacto:</h5>
                    <p class="mb-0">
                        Aquí puedes consultar la información de contacto de tus compañeros árbitros. 
                        Esta información está destinada exclusivamente para coordinación profesional y comunicación relacionada con la actividad arbitral.
                    </p>
                </div>
            </div>
        </div>
        -->

        <!-- Estadísticas 
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header" style="background: var(--primary-green);">
                    <i class="fas fa-users"></i> Total Árbitros
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--primary-green); font-size: 2.5rem; margin: 0;">
                        <?php echo $total_arbitros; ?>
                    </h2>
                    <p class="text-muted">Árbitros activos</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--success);">
                    <i class="fas fa-phone"></i> Con Teléfono
                </div>
                <div class="card-body text-center">
                    <h2 style="color: var(--success); font-size: 2.5rem; margin: 0;">
                        <?php echo $arbitros_con_telefono; ?>
                    </h2>
                    <p class="text-muted">Tienen teléfono registrado</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: var(--info);">
                    <i class="fas fa-envelope"></i> Contacto
                </div>
                <div class="card-body text-center">
                    <div style="margin: 20px 0;">
                        <i class="fas fa-address-book" style="font-size: 2rem; color: var(--info);"></i>
                    </div>
                    <p class="text-muted">Información de contacto actualizada</p>
                </div>
            </div>
        </div>
        -->

        <!-- Información adicional -->
        <div class="card">
            <div class="card-header" style="background: var(--warning);">
                <i class="fas fa-exclamation-triangle"></i> Uso Responsable de la Información
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h5><i class="fas fa-shield-alt"></i> Política de Privacidad:</h5>
                    <ul class="mb-0">
                        <li>Esta información es confidencial y de uso exclusivo para coordinación arbitral</li>
                        <li>No está permitido compartir estos datos con terceros ajenos a la federación</li>
                        <li>Utiliza estos contactos únicamente para asuntos relacionados con partidos y arbitraje</li>
                        <li>Si detectas información incorrecta, contacta con la administración para su corrección</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Lista de Árbitros -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Directorio de Árbitros
                <div style="float: right;">
                    <span class="badge" style="background: var(--primary-green); color: white;">
                        <span id="total-count"><?php echo $total_arbitros; ?></span> árbitros registrados
                    </span>
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
                                   placeholder="Buscar por nombre, apellidos, ciudad, email o licencia..." 
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
                        <span id="searchResults">0</span> resultado(s) encontrado(s)
                    </div>
                    <div class="search-help">
                        <i class="fas fa-lightbulb"></i> 
                        <em>Busca por nombre, apellidos, ciudad, email o tipo de licencia. Usa ESC para limpiar.</em>
                    </div>
                </div>
                <?php if (count($arbitros) > 0): ?>
                    <div class="table-responsive">
                        <table class="table searchable-table">
                            <thead>
                                <tr>
                                    <th> Nombre Completo</th>
                                    <th> Teléfono</th>
                                    <th> Correo Electrónico</th>
                                    <th> Ciudad</th>
                                    <th> Licencia</th>
                                    <th> Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($arbitros as $arbitro): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-small" style="background: var(--primary-green); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($arbitro['nombre_completo']); ?></strong>
                                                <?php if ($arbitro['usuario_id'] == $_SESSION['user_id']): ?>
                                                    <br><small style="color: var(--info); font-weight: bold;">(Tú)</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($arbitro['telefono'])): ?>
                                            <span class="phone-number">
                                                <i class="fas fa-phone text-success"></i>
                                                <a href="tel:<?php echo htmlspecialchars($arbitro['telefono']); ?>" 
                                                   style="color: var(--primary-green); text-decoration: none;">
                                                    <?php echo htmlspecialchars($arbitro['telefono']); ?>
                                                </a>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-phone-slash"></i> No disponible
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="email-address">
                                            <i class="fas fa-envelope text-info"></i>
                                            <a href="mailto:<?php echo htmlspecialchars($arbitro['email']); ?>" 
                                               style="color: var(--info); text-decoration: none;">
                                                <?php echo htmlspecialchars($arbitro['email']); ?>
                                            </a>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($arbitro['ciudad'])): ?>
                                            <span class="badge" style="background: var(--light-gray); color: var(--primary-black);">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($arbitro['ciudad']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No especificada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--primary-green); color: white;">
                                            <?php echo htmlspecialchars($arbitro['licencia_texto']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if (!empty($arbitro['telefono'])): ?>
                                                <a href="tel:<?php echo htmlspecialchars($arbitro['telefono']); ?>" 
                                                   class="btn btn-success btn-sm" title="Llamar">
                                                    <i class="fas fa-phone"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="mailto:<?php echo htmlspecialchars($arbitro['email']); ?>" 
                                               class="btn btn-info btn-sm" title="Enviar correo">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-users-slash" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No hay árbitros registrados</h4>
                        <p class="text-muted">
                            No se encontraron árbitros en el sistema
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        
    </main>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/search-bar.js"></script>
    
    <script>
        // Inicializar búsqueda para árbitros
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
    
    <style>
        .phone-number a:hover,
        .email-address a:hover {
            text-decoration: underline !important;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .user-avatar-small {
            flex-shrink: 0;
        }
        
        .d-flex {
            display: flex;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
