<!-- registro.php -->
<?php
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"];
    $nombre = $_POST["nombre"];
    $apellidos = $_POST["apellidos"];
    $rango = $_POST["rango"];
    $ncuenta = $_POST["ncuenta"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    // Insertar nuevo árbitro en la base de datos
    $conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");
    if (!$conn) {
      die("Error de conexión: ". mysqli_connect_error());
    }
    $sql = "INSERT INTO arbitros (id, nombre, apellidos, rango, ncuenta, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);  
    mysqli_stmt_bind_param($stmt, "issssss", $id, $nombre, $apellidos, $rango, $ncuenta, $email, $password);
    mysqli_stmt_execute($stmt);
    if (mysqli_query($conn, $sql)) {
      echo "<script>alert('Registro exitoso');</script>";
      header("Location: login.php");
      exit;
    } else {
      echo "<script>document.getElementById('error-message').innerHTML = 'Error al registrar árbitro';</script>";
    }
    mysqli_close($conn);
  }
?>


<!-- registro.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrarse</title>
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
</head>
<body>
  <header>
    <nav>
      <ul>
        <li><a href="admin.php">Volver</a></li>
      </ul>
    </nav>
  </header>
  <main>
    <h1>Registrar nuevo arbitro</h1>
    <form id="registro-form">
        <label for="licencia">Licencia:</label>
        <input type="text" id="id" name="licencia" class="input-field"><br><br>
        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" class="input-field"><br><br>
        <label for="apellidos">Apellidos:</label>
        <input type="text" id="apellidos" name="apellidos" class="input-field"><br><br>
        <label for="rango">Rango:</label>
        <input type="text" id="rango" name="rango" class="input-field"><br><br>
        <label for="ncuenta">Nº Cuenta:</label>
        <input type="text" id="ncuenta" name="ncuenta" class="input-field"><br><br>
        <label for="email">Correo electrónico:</label>
        <input type="email" id="email" name="email" class="input-field"><br><br>
        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" class="input-field"><br><br>
        <input type="submit" value="Registrarse" class="submit-btn">
    </form>
    <div id="error-message"></div>
  </main>
  <script src="registro.js"></script>
</body>

</html>