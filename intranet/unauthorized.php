<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso No Autorizado - FEDEXVB</title>
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
        
        .error-container {
            background: var(--primary-white);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            margin: 20px;
        }
        
        .error-icon {
            font-size: 4rem;
            color: var(--error);
            margin-bottom: 20px;
        }
        
        .error-title {
            color: var(--primary-black);
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .error-message {
            color: var(--dark-gray);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn-back {
            background: var(--primary-green);
            color: var(--primary-white);
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-shield-alt error-icon"></i>
        <h1 class="error-title">Acceso No Autorizado</h1>
        <p class="error-message">
            No tienes permisos para acceder a esta sección del sistema. 
            Si crees que esto es un error, contacta con el administrador.
        </p>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Volver al Inicio
        </a>
    </div>
</body>
</html>
