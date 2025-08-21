<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

$message = '';
$user_id = $_SESSION['user_id'];

// Obtener información del usuario
$query = "SELECT u.*, a.nombre, a.apellidos, a.telefono 
          FROM usuarios u 
          JOIN administradores a ON u.id = a.usuario_id 
          WHERE u.id = ? AND u.activo = 1";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ../unauthorized.php');
    exit();
}

// Debug: Verificar los datos del usuario
error_log("Usuario encontrado: " . print_r($user, true));

// Procesar actualización del perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $conn->beginTransaction();
        
        $nombre = sanitize_input($_POST['nombre']);
        $apellidos = sanitize_input($_POST['apellidos']);
        $email = sanitize_input($_POST['email']);
        $telefono = sanitize_input($_POST['telefono']);
        
        // Validaciones
        if (empty($nombre) || empty($apellidos) || empty($email)) {
            throw new Exception('Los campos nombre, apellidos y email son obligatorios');
        }
        
        if (!validate_email($email)) {
            throw new Exception('El email no es válido');
        }
        
        // Verificar que el email no esté en uso por otro usuario
        $query = "SELECT id FROM usuarios WHERE email = ? AND id != ? AND activo = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('El email ya está en uso por otro usuario');
        }
        
        // Actualizar información del usuario en la tabla usuarios
        $query = "UPDATE usuarios SET email = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$email, $user_id]);
        
        // Actualizar información en la tabla administradores
        $query = "UPDATE administradores SET nombre = ?, apellidos = ?, telefono = ? WHERE usuario_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$nombre, $apellidos, $telefono, $user_id]);
        
        // Actualizar datos de sesión
        $_SESSION['user_name'] = $nombre;
        $_SESSION['user_lastname'] = $apellidos;
        $_SESSION['user_email'] = $email;
        
        $conn->commit();
        $message = success_message('Perfil actualizado correctamente');
        
        // Recargar datos del usuario
        $query = "SELECT u.*, a.nombre, a.apellidos, a.telefono 
                  FROM usuarios u 
                  JOIN administradores a ON u.id = a.usuario_id 
                  WHERE u.id = ? AND u.activo = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = error_message($e->getMessage());
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        $password_actual = $_POST['password_actual'];
        $password_nueva = $_POST['password_nueva'];
        $password_confirmar = $_POST['password_confirmar'];
        
        // Validaciones
        if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
            throw new Exception('Todos los campos de contraseña son obligatorios');
        }
        
        if ($password_nueva !== $password_confirmar) {
            throw new Exception('La nueva contraseña y su confirmación no coinciden');
        }
        
        if (strlen($password_nueva) < 6) {
            throw new Exception('La nueva contraseña debe tener al menos 6 caracteres');
        }
        
        // Verificar contraseña actual
        if (!password_verify($password_actual, $user['password'])) {
            throw new Exception('La contraseña actual es incorrecta');
        }
        
        // Actualizar contraseña
        $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
        $query = "UPDATE usuarios SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$password_hash, $user_id]);
        
        $message = success_message('Contraseña actualizada correctamente');
        
    } catch (Exception $e) {
        $message = error_message($e->getMessage());
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
            <li><a href="arbitros.php"><i class="fa-solid fa-person"></i> Gestión de Árbitros</a></li>
            <li><a href="clubes.php"><i class="fas fa-building"></i> Gestión de Clubes</a></li>
            <li><a href="licencias.php"><i class="fas fa-id-card"></i> Gestión de Licencias</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Liquidaciones</a></li>
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

        <?php if ($message): ?>
            <div style="margin-bottom: 20px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Información del Perfil -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i> Información Personal
                </div>
                <div class="card-body">
                    <form method="POST" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="nombre">
                                <i class="fas fa-user"></i> Nombre *
                            </label>
                            <input type="text" id="nombre" name="nombre" class="form-control" 
                                   value="<?php echo isset($user['nombre']) ? htmlspecialchars($user['nombre']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="apellidos">
                                <i class="fas fa-user"></i> Apellidos *
                            </label>
                            <input type="text" id="apellidos" name="apellidos" class="form-control" 
                                   value="<?php echo isset($user['apellidos']) ? htmlspecialchars($user['apellidos']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email *
                            </label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="telefono">
                                <i class="fas fa-phone"></i> Teléfono
                            </label>
                            <input type="text" id="telefono" name="telefono" class="form-control" 
                                   value="<?php echo isset($user['telefono']) ? htmlspecialchars($user['telefono']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-shield-alt"></i> Tipo de Usuario
                            </label>
                            <input type="text" class="form-control" value="Administrador" readonly>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-calendar"></i> Fecha de Registro
                            </label>
                            <input type="text" class="form-control" value="<?php echo isset($user['fecha_creacion']) ? format_datetime($user['fecha_creacion']) : 'No disponible'; ?>" readonly>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cambiar Contraseña -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-key"></i> Cambiar Contraseña
                </div>
                <div class="card-body">
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="password_actual">
                                <i class="fas fa-lock"></i> Contraseña Actual *
                            </label>
                            <input type="password" id="password_actual" name="password_actual" 
                                   class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="password_nueva">
                                <i class="fas fa-key"></i> Nueva Contraseña *
                            </label>
                            <input type="password" id="password_nueva" name="password_nueva" 
                                   class="form-control" required minlength="6">
                            <small class="form-text">Mínimo 6 caracteres</small>
                        </div>

                        <div class="form-group">
                            <label for="password_confirmar">
                                <i class="fas fa-key"></i> Confirmar Nueva Contraseña *
                            </label>
                            <input type="password" id="password_confirmar" name="password_confirmar" 
                                   class="form-control" required minlength="6">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key"></i> Cambiar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Información adicional 
        <div class="card" style="margin-top: 30px;">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Información del Sistema
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="text-center">
                        <i class="fas fa-shield-alt" style="font-size: 2rem; color: var(--primary-green); margin-bottom: 10px;"></i>
                        <h5>Privilegios de Administrador</h5>
                        <p class="text-muted">Acceso completo al sistema</p>
                    </div>
                    
                    <div class="text-center">
                        <i class="fas fa-clock" style="font-size: 2rem; color: var(--info); margin-bottom: 10px;"></i>
                        <h5>Última Conexión</h5>
                        <p class="text-muted"><?php echo isset($user['fecha_actualizacion']) ? format_datetime($user['fecha_actualizacion']) : 'No disponible'; ?></p>
                    </div>
                    
                    <div class="text-center">
                        <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success); margin-bottom: 10px;"></i>
                        <h5>Estado de la Cuenta</h5>
                        <p class="text-muted">Activa</p>
                    </div>
                </div>
            </div>
        </div> -->
    </main>

    <script src="../assets/js/app.js"></script>
    <script>
        // Validación de confirmación de contraseña
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const nueva = document.getElementById('password_nueva').value;
            const confirmar = document.getElementById('password_confirmar').value;
            
            if (nueva !== confirmar) {
                e.preventDefault();
                showNotification('Las contraseñas no coinciden', 'error');
                return false;
            }
        });

        // Validación del formulario de perfil
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const apellidos = document.getElementById('apellidos').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!nombre || !apellidos || !email) {
                e.preventDefault();
                showNotification('Los campos nombre, apellidos y email son obligatorios', 'error');
                return false;
            }
        });
    </script>
</body>
</html>
