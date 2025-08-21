<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$auth = new Auth();
$message = '';

// Si ya está logueado, redirigir según el tipo de usuario
if ($auth->isLoggedIn()) {
    // Si tiene contraseña temporal, redirigir a cambio de contraseña
    if (isset($_SESSION['password_temporal']) && $_SESSION['password_temporal'] == 1) {
        header("Location: cambiar-password.php");
        exit();
    }
    
    $userType = $auth->getUserType();
    switch ($userType) {
        case 'administrador':
            header("Location: admin/dashboard.php");
            break;
        case 'arbitro':
            header("Location: arbitro/dashboard.php");
            break;
        case 'club':
            header("Location: club/dashboard.php");
            break;
    }
    exit();
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $message = error_message('Por favor, complete todos los campos');
    } else {
        if ($auth->login($email, $password)) {
            // Verificar si tiene contraseña temporal
            if (isset($_SESSION['password_temporal']) && $_SESSION['password_temporal'] == 1) {
                header("Location: cambiar-password.php");
                exit();
            }
            
            $userType = $auth->getUserType();
            switch ($userType) {
                case 'administrador':
                    header("Location: admin/dashboard.php");
                    break;
                case 'arbitro':
                    header("Location: arbitro/dashboard.php");
                    break;
                case 'club':
                    header("Location: club/dashboard.php");
                    break;
            }
            exit();
        } else {
            $message = error_message('Email o contraseña incorrectos');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intranet - Federación Extremeña de Voleibol</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, var(--primary-green), var(--light-green));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: var(--primary-white);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }
        
        .login-header {
            background: var(--primary-green);
            color: var(--primary-white);
            padding: 30px;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--medium-gray);
            z-index: 2;
        }
        
        .form-control {
            padding-left: 45px;
            height: 50px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }
        
        .btn-login {
            width: 100%;
            height: 50px;
            background: var(--primary-green);
            color: var(--primary-white);
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        }
        
        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: var(--light-gray);
            font-size: 0.9rem;
            color: var(--dark-gray);
        }
        
        .demo-credentials {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.875rem;
        }
        
        .demo-credentials h4 {
            margin: 0 0 10px 0;
            color: var(--primary-green);
            font-size: 1rem;
        }
        
        .demo-credentials ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .demo-credentials li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-volleyball-ball"></i>
            <h1>FEDEXVB</h1>
            <p>Federación Extremeña de Voleibol</p>
        </div>
        
        <div class="login-body">
            <?php echo $message; ?>
            
            <!--<div class="demo-credentials">
                <h4><i class="fas fa-info-circle"></i> Credenciales de Demo</h4>
                <ul>
                    <li><strong>Admin:</strong> admin@fedexvb.es / password</li>
                    <li><strong>Árbitro:</strong> arbitro@fedexvb.es / password</li>
                    <li><strong>Club:</strong> club@fedexvb.es / password</li>
                </ul>
            </div>-->
            
            <form method="POST" class="validate-form">
                <div class="form-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required>
                </div>
                
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <i class="fas fa-shield-alt"></i>
            Acceso seguro al sistema interno
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
