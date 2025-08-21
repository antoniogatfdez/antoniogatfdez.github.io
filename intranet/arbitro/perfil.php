<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('arbitro');

$database = new Database();
$conn = $database->getConnection();

// Obtener información del árbitro
$query = "SELECT a.*, u.email, u.password_temporal 
          FROM arbitros a 
          JOIN usuarios u ON a.usuario_id = u.id 
          WHERE a.usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$arbitro = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener licencias del árbitro
$query = "SELECT * FROM licencias_arbitros 
          WHERE arbitro_id = ? 
          ORDER BY fecha_vencimiento DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$arbitro['id']]);
$licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';

// Procesar formulario de actualización de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $conn->beginTransaction();
        
        if ($_POST['action'] === 'actualizar_datos') {
            $nombre = sanitize_input($_POST['nombre']);
            $apellidos = sanitize_input($_POST['apellidos']);
            $ciudad = sanitize_input($_POST['ciudad']);
            $iban = sanitize_input($_POST['iban']);
            $email = sanitize_input($_POST['email']);
            
            // Validaciones
            if (empty($nombre) || empty($apellidos) || empty($ciudad)) {
                throw new Exception('Los campos nombre, apellidos y ciudad son obligatorios');
            }
            
            if (!validate_email($email)) {
                throw new Exception('Email no válido');
            }
            
            // Verificar si el email ya existe (excepto el actual)
            $query = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception('Este email ya está registrado por otro usuario');
            }
            
            // Validar IBAN si se proporciona
            if (!empty($iban)) {
                $iban = strtoupper(str_replace(' ', '', $iban));
                if (strlen($iban) < 15 || strlen($iban) > 34) {
                    throw new Exception('IBAN no válido');
                }
            }
            
            // Actualizar datos del árbitro
            $query = "UPDATE arbitros SET nombre = ?, apellidos = ?, ciudad = ?, iban = ? WHERE usuario_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$nombre, $apellidos, $ciudad, $iban, $_SESSION['user_id']]);
            
            // Actualizar email del usuario
            $query = "UPDATE usuarios SET email = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$email, $_SESSION['user_id']]);
            
            // Actualizar variables de sesión
            $_SESSION['user_name'] = $nombre;
            $_SESSION['user_lastname'] = $apellidos;
            
            $conn->commit();
            $message = success_message('Datos actualizados correctamente');
            
            // Recargar datos del árbitro
            $query = "SELECT a.*, u.email, u.password_temporal 
                      FROM arbitros a 
                      JOIN usuarios u ON a.usuario_id = u.id 
                      WHERE a.usuario_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            $arbitro = $stmt->fetch(PDO::FETCH_ASSOC);
            
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $message = error_message($e->getMessage());
    }
}

// Función para determinar el estado de una licencia
function getLicenciaEstado($fecha_vencimiento, $activa) {
    if (!$activa) {
        return ['estado' => 'Inactiva', 'clase' => 'danger'];
    }
    
    $fecha_venc = new DateTime($fecha_vencimiento);
    $fecha_actual = new DateTime();
    $fecha_preaviso = clone $fecha_actual;
    $fecha_preaviso->add(new DateInterval('P30D')); // 30 días
    
    if ($fecha_venc < $fecha_actual) {
        return ['estado' => 'Vencida', 'clase' => 'danger'];
    } elseif ($fecha_venc <= $fecha_preaviso) {
        return ['estado' => 'Próxima a vencer', 'clase' => 'warning'];
    } else {
        return ['estado' => 'Vigente', 'clase' => 'success'];
    }
}

// Función para formatear el nivel de licencia
function formatLicenciaNivel($nivel) {
    switch ($nivel) {
        case 'anotador':
            return 'Anotador';
        case 'n1':
            return 'Nivel 1';
        case 'n2':
            return 'Nivel 2';
        case 'n3':
            return 'Nivel 3';
        default:
            return ucfirst($nivel);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - FEDEXVB</title>
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
            <li><a href="disponibilidad.php"><i class="fas fa-calendar-check"></i> Mi Disponibilidad</a></li>
            <li><a href="partidos.php"><i class="fa-solid fa-globe"></i> Mis Partidos</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Mis Liquidaciones</a></li>
            <li><a href="arbitros.php"><i class="fas fa-users"></i> Lista de Árbitros</a></li>
            <li><a href="perfil.php" class="active"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user-cog"></i> Mi Perfil</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Mi Perfil
            </div>
        </div>

        <!-- Información adicional -->
        <div class="card">
            <div class="card-header" style="background: var(--info);">
                <i class="fas fa-info-circle"></i> Información Importante
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb"></i> Recordatorios:</h5>
                    <ul class="mb-0">
                        <li>Mantén actualizados tus datos personales para recibir comunicaciones</li>
                        <li>Es obligatorio tener configurado el IBAN para recibir liquidaciones</li>
                        <li>Revisa regularmente el estado de tus licencias</li>
                        <li>Si tu contraseña es temporal, cámbiala cuanto antes por seguridad</li>
                        <li>Para cualquier cambio en las licencias, contacta con la administración</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Información Personal -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i> Información Personal
                </div>
                <div class="card-body">
                    <form method="POST" id="formPerfil">
                        <input type="hidden" name="action" value="actualizar_datos">
                        
                        <div class="form-group">
                            <label for="nombre">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($arbitro['nombre']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="apellidos">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="apellidos" name="apellidos" 
                                   value="<?php echo htmlspecialchars($arbitro['apellidos']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($arbitro['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="ciudad">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ciudad" name="ciudad" 
                                   value="<?php echo htmlspecialchars($arbitro['ciudad']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="iban">IBAN (para liquidaciones)</label>
                            <input type="text" class="form-control" id="iban" name="iban" 
                                   value="<?php echo htmlspecialchars($arbitro['iban']); ?>" 
                                   placeholder="ES00 0000 0000 0000 0000 0000">
                            <small class="form-text text-muted">
                                Necesario para recibir las liquidaciones de arbitraje
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Licencia Principal</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo formatLicenciaNivel($arbitro['licencia']); ?>" 
                                   readonly>
                            <small class="form-text text-muted">
                                Contacta con la administración para cambios en la licencia
                            </small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                            <a href="../cambiar-password.php" class="btn btn-secondary">
                                <i class="fas fa-key"></i> Cambiar Contraseña
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resumen de Estado -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i> Resumen de Estado
                </div>
                <div class="card-body">
                    <!-- Estado de la cuenta -->
                    <div class="status-item">
                        <div class="status-icon">
                            <i class="fas fa-user-check" style="color: var(--success);"></i>
                        </div>
                        <div class="status-content">
                            <h5>Estado de la Cuenta</h5>
                            <p class="text-success">Activa</p>
                        </div>
                    </div>

                    <!-- Estado de la contraseña -->
                    <div class="status-item">
                        <div class="status-icon">
                            <?php if ($arbitro['password_temporal']): ?>
                                <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                            <?php else: ?>
                                <i class="fas fa-shield-alt" style="color: var(--success);"></i>
                            <?php endif; ?>
                        </div>
                        <div class="status-content">
                            <h5>Contraseña</h5>
                            <?php if ($arbitro['password_temporal']): ?>
                                <p class="text-warning">Temporal - Requiere cambio</p>
                                <a href="../cambiar-password.php" class="btn btn-warning btn-sm">
                                    <i class="fas fa-key"></i> Cambiar Ahora
                                </a>
                            <?php else: ?>
                                <p class="text-success">Configurada</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Estado del IBAN -->
                    <div class="status-item">
                        <div class="status-icon">
                            <?php if (empty($arbitro['iban'])): ?>
                                <i class="fas fa-exclamation-circle" style="color: var(--warning);"></i>
                            <?php else: ?>
                                <i class="fas fa-university" style="color: var(--success);"></i>
                            <?php endif; ?>
                        </div>
                        <div class="status-content">
                            <h5>Datos Bancarios</h5>
                            <?php if (empty($arbitro['iban'])): ?>
                                <p class="text-warning">No configurado</p>
                                <small class="text-muted">Necesario para liquidaciones</small>
                            <?php else: ?>
                                <p class="text-success">Configurado</p>
                                <small class="text-muted"><?php echo substr($arbitro['iban'], 0, 8) . '***'; ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Resumen de licencias -->
                    <div class="status-item">
                        <div class="status-icon">
                            <i class="fas fa-certificate" style="color: var(--info);"></i>
                        </div>
                        <div class="status-content">
                            <h5>Licencias</h5>
                            <p><?php echo count($licencias); ?> licencia(s) registrada(s)</p>
                            <?php 
                            $licencias_activas = array_filter($licencias, function($lic) {
                                $estado = getLicenciaEstado($lic['fecha_vencimiento'], $lic['activa']);
                                return $estado['estado'] === 'Vigente' || $estado['estado'] === 'Próxima a vencer';
                            });
                            ?>
                            <small class="text-muted"><?php echo count($licencias_activas); ?> vigente(s)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Licencias -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-certificate"></i> Mis Licencias
                <div class="card-actions">
                    <span class="badge" style="background: var(--info);">
                        <?php echo count($licencias); ?> Total
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($licencias) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nivel</th>
                                    <th>Fecha del Curso</th>
                                    <th>Lugar</th>
                                    <th>Vigencia</th>
                                    <th>Estado</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($licencias as $licencia): ?>
                                    <?php 
                                    $estado = getLicenciaEstado($licencia['fecha_vencimiento'], $licencia['activa']);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo formatLicenciaNivel($licencia['nivel_licencia']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo format_date($licencia['fecha_curso']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($licencia['lugar_curso']); ?>
                                        </td>
                                        <td>
                                            <strong>Inicio:</strong> <?php echo format_date($licencia['fecha_inicio']); ?><br>
                                            <strong>Vence:</strong> <?php echo format_date($licencia['fecha_vencimiento']); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $estado['clase']; ?>">
                                                <?php echo $estado['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($licencia['observaciones'])): ?>
                                                <small><?php echo htmlspecialchars($licencia['observaciones']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Estadísticas de licencias -->
                    <div class="mt-4">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <?php
                            $stats_licencias = [
                                'vigentes' => 0,
                                'proximas_vencer' => 0,
                                'vencidas' => 0,
                                'inactivas' => 0
                            ];
                            
                            foreach ($licencias as $licencia) {
                                $estado = getLicenciaEstado($licencia['fecha_vencimiento'], $licencia['activa']);
                                switch ($estado['estado']) {
                                    case 'Vigente':
                                        $stats_licencias['vigentes']++;
                                        break;
                                    case 'Próxima a vencer':
                                        $stats_licencias['proximas_vencer']++;
                                        break;
                                    case 'Vencida':
                                        $stats_licencias['vencidas']++;
                                        break;
                                    case 'Inactiva':
                                        $stats_licencias['inactivas']++;
                                        break;
                                }
                            }
                            ?>

                            <div class="stat-card" style="background: var(--success); color: white; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold;"><?php echo $stats_licencias['vigentes']; ?></div>
                                <div>Vigentes</div>
                            </div>

                            <?php if ($stats_licencias['proximas_vencer'] > 0): ?>
                            <div class="stat-card" style="background: var(--warning); color: white; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold;"><?php echo $stats_licencias['proximas_vencer']; ?></div>
                                <div>Próximas a vencer</div>
                            </div>
                            <?php endif; ?>

                            <?php if ($stats_licencias['vencidas'] > 0): ?>
                            <div class="stat-card" style="background: var(--danger); color: white; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold;"><?php echo $stats_licencias['vencidas']; ?></div>
                                <div>Vencidas</div>
                            </div>
                            <?php endif; ?>

                            <?php if ($stats_licencias['inactivas'] > 0): ?>
                            <div class="stat-card" style="background: var(--medium-gray); color: white; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold;"><?php echo $stats_licencias['inactivas']; ?></div>
                                <div>Inactivas</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-certificate" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">Sin licencias registradas</h4>
                        <p class="text-muted">
                            No tienes licencias registradas en el sistema. Contacta con la administración 
                            si necesitas que se añadan tus licencias.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        
    </main>

    <script src="../assets/js/app.js"></script>
    
    <script>
        // Formatear IBAN automáticamente
        document.getElementById('iban').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').toUpperCase();
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Validación del formulario
        document.getElementById('formPerfil').addEventListener('submit', function(e) {
            const iban = document.getElementById('iban').value.replace(/\s/g, '');
            
            if (iban && (iban.length < 15 || iban.length > 34)) {
                e.preventDefault();
                alert('El IBAN debe tener entre 15 y 34 caracteres');
                return false;
            }
        });

        // Mostrar notificación si hay contraseña temporal
        <?php if ($arbitro['password_temporal']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.createElement('div');
            alert.className = 'alert alert-warning alert-dismissible';
            alert.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Atención:</strong> Tu contraseña es temporal. Te recomendamos cambiarla por una personalizada.
                <a href="../cambiar-password.php" class="btn btn-warning btn-sm ml-2">
                    <i class="fas fa-key"></i> Cambiar Ahora
                </a>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;
            
            const mainContent = document.querySelector('.main-content');
            const contentHeader = mainContent.querySelector('.content-header');
            contentHeader.parentNode.insertBefore(alert, contentHeader.nextSibling);
        });
        <?php endif; ?>
    </script>

    <style>
        .status-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-icon {
            margin-right: 15px;
            font-size: 1.5rem;
        }

        .status-content h5 {
            margin: 0 0 5px 0;
            color: var(--primary-black);
        }

        .status-content p {
            margin: 0;
            font-weight: 500;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .stat-card {
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .badge-success {
            background-color: var(--success);
        }

        .badge-warning {
            background-color: var(--warning);
        }

        .badge-danger {
            background-color: var(--danger);
        }

        .alert-dismissible .close {
            position: absolute;
            top: 0;
            right: 0;
            padding: .75rem 1.25rem;
            color: inherit;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .main-content > div:first-of-type {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
