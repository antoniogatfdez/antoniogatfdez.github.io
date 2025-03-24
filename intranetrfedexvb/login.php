<?php
session_start();

if (isset($_POST["email"]) && isset($_POST["password"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];

    // Conectar a la base de datos
    $conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");
    if (!$conn) {
        die("Error de conexión: ". mysqli_connect_error());
    }

    // Preparar la consulta
    $sql = "SELECT * FROM arbitros WHERE email =? AND password =?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $email, $password);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Árbitro encontrado, iniciar sesión
        $arbitro = mysqli_fetch_assoc($result);
        $_SESSION["arbitro_id"] = $arbitro["id"];
        $_SESSION["arbitro_nombre"] = $arbitro["nombre"];
        $_SESSION["arbitro_apellidos"] = $arbitro["apellidos"];
        header("Location: cargando.html");
        exit;
    } else {
        // Árbitro no encontrado, mostrar error
        echo "<script>alert('Email o contraseña incorrectos');</script>";
    }
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión</title>
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
</head>
<body>
<header class="header">
  <nav>
    <ul>
      <li><a href="index.php">Volver</a></li>
    </ul>
  </nav>
</header>
<main>
  <h1>‎</h1>
  <h1>‎</h1>
  <h1>‎</h1>
  <h1>‎</h1>
  <h1>‎</h1> 
  <h1>Iniciar sesión</h1>
  <form id="login-form" action="login.php" method="post">
    <div class="form-group">
      <label for="email">Correo electrónico:</label>
      <input type="email" id="email" name="email">
    </div>
    <div class="form-group">
      <label for="password">Contraseña:</label>
      <input type="password" id="password" name="password">
    </div>
    <input type="submit" value="Iniciar sesión">
    <div id="error-message"></div>
  </form>
</main>
</body>

</html>