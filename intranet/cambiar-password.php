<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$message = '';

// Procesar el cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = sanitize_input($_POST['password_actual']);
    $password_nueva = sanitize_input($_POST['password_nueva']);
    $password_confirmar = sanitize_input($_POST['password_confirmar']);
    
    try {
        // Verificar contraseña actual
        $query = "SELECT password FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($password_actual, $user['password'])) {
            $message = error_message('La contraseña actual no es correcta');
        } elseif ($password_nueva !== $password_confirmar) {
            $message = error_message('Las contraseñas nuevas no coinciden');
        } elseif (strlen($password_nueva) < 6) {
            $message = error_message('La nueva contraseña debe tener al menos 6 caracteres');
        } else {
            // Actualizar contraseña
            $hashed_password = password_hash($password_nueva, PASSWORD_DEFAULT);
            $query = "UPDATE usuarios SET password = ?, password_temporal = 0 WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            // Actualizar sesión
            $_SESSION['password_temporal'] = false;
            
            $message = success_message('Contraseña actualizada correctamente');
            
            // Redirigir al dashboard correspondiente después de 2 segundos
            $redirect_url = '';
            switch ($_SESSION['user_type']) {
                case 'administrador':
                    $redirect_url = 'admin/dashboard.php';
                    break;
                case 'arbitro':
                    $redirect_url = 'arbitro/dashboard.php';
                    break;
                case 'club':
                    $redirect_url = 'club/dashboard.php';
                    break;
            }
            
            if ($redirect_url) {
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '$redirect_url';
                    }, 2000);
                </script>";
            }
        }
    } catch (Exception $e) {
        $message = error_message('Error al cambiar la contraseña: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - FEDEXVB</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .change-password-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            padding: 20px;
        }
        
        .change-password-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        
        .change-password-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .change-password-header .logo {
            color: var(--primary-green);
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .change-password-header h1 {
            color: var(--primary-black);
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }
        
        .change-password-header p {
            color: var(--medium-gray);
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            color: var(--primary-black);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--light-gray);
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-green);
            outline: none;
        }
        
        .btn-change {
            width: 100%;
            padding: 12px;
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-change:hover {
            background: var(--dark-green);
        }
        
        .password-requirements {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .password-requirements h4 {
            margin: 0 0 10px 0;
            color: var(--primary-black);
            font-size: 14px;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: var(--medium-gray);
            font-size: 13px;
        }
        
        .warning-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .warning-banner i {
            color: #f39c12;
            font-size: 1.2rem;
            margin-right: 8px;
        }
        
        .warning-banner span {
            color: #8b7300;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="change-password-container">
        <div class="change-password-card">
            <div class="change-password-header">
                <div class="logo">
                    <i class="fas fa-volleyball-ball"></i>
                </div>
                <h1>Cambiar Contraseña</h1>
                <p>Por seguridad, debe cambiar su contraseña temporal</p>
            </div>

            <?php if ($_SESSION['password_temporal']): ?>
            <div class="warning-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Su contraseña es temporal. Debe cambiarla para continuar.</span>
            </div>
            <?php endif; ?>

            <?php echo $message; ?>

            <form method="POST" class="change-password-form">
                <div class="form-group">
                    <label for="password_actual" class="form-label">
                        <i class="fas fa-lock"></i> Contraseña Actual
                    </label>
                    <input type="password" 
                           id="password_actual" 
                           name="password_actual" 
                           class="form-control" 
                           placeholder="Ingrese su contraseña actual"
                           required>
                </div>

                <div class="password-requirements">
                    <h4><i class="fas fa-info-circle"></i> Requisitos de la nueva contraseña:</h4>
                    <ul>
                        <li>Mínimo 6 caracteres</li>
                        <li>Se recomienda usar mayúsculas, minúsculas y números</li>
                        <li>Evite usar información personal</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="password_nueva" class="form-label">
                        <i class="fas fa-key"></i> Nueva Contraseña
                    </label>
                    <input type="password" 
                           id="password_nueva" 
                           name="password_nueva" 
                           class="form-control" 
                           placeholder="Ingrese su nueva contraseña"
                           minlength="6"
                           required>
                </div>

                <div class="form-group">
                    <label for="password_confirmar" class="form-label">
                        <i class="fas fa-check-circle"></i> Confirmar Nueva Contraseña
                    </label>
                    <input type="password" 
                           id="password_confirmar" 
                           name="password_confirmar" 
                           class="form-control" 
                           placeholder="Confirme su nueva contraseña"
                           minlength="6"
                           required>
                </div>

                <button type="submit" class="btn-change">
                    <i class="fas fa-save"></i> Cambiar Contraseña
                </button>
            </form>

            <div style="text-align: center; margin-top: 20px;">
                <small style="color: var(--medium-gray);">
                    <i class="fas fa-shield-alt"></i> 
                    Sus datos están protegidos con encriptación
                </small>
            </div>
        </div>
    </div>

    <script>
        // Validación en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const nuevaPassword = document.getElementById('password_nueva');
            const confirmarPassword = document.getElementById('password_confirmar');
            
            function validatePasswords() {
                if (nuevaPassword.value && confirmarPassword.value) {
                    if (nuevaPassword.value !== confirmarPassword.value) {
                        confirmarPassword.setCustomValidity('Las contraseñas no coinciden');
                    } else {
                        confirmarPassword.setCustomValidity('');
                    }
                }
            }
            
            nuevaPassword.addEventListener('input', validatePasswords);
            confirmarPassword.addEventListener('input', validatePasswords);
            
            // Prevenir envío si las contraseñas no coinciden
            document.querySelector('.change-password-form').addEventListener('submit', function(e) {
                if (nuevaPassword.value !== confirmarPassword.value) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden');
                }
            });
        });
    </script>
</body>
</html>
