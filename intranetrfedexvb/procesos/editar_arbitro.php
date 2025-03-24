<?php
// Connect to database
$conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");

// Check connection
if (!$conn) {
    die("Connection failed: ". mysqli_connect_error());
}

// Get id from POST
$id = $_POST['id'];

// Query to retrieve data from arbitros table
$arbitro_query = "SELECT * FROM arbitros WHERE id = '$id'";
$arbitro_result = mysqli_query($conn, $arbitro_query);
$arbitro_row = mysqli_fetch_assoc($arbitro_result);
?>

<!-- Include the same HTML structure as index.html -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Árbitro</title>
  <link rel="stylesheet" href="/index.css"> <!-- Use the same CSS file as index.html -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
</head>
<body>
  <header>
    <nav>
      <ul>
        <li><a href="/admin.php">Atrás</a></li>
        <li><a href="/index.php">Salir</a></li>
      </ul>
    </nav>
  </header>
  <main>
    <h1>Editar Árbitro</h1>
    <form action="update_arbitro.php" method="post">
      <input type="hidden" name="id" class="input-field" value="<?php echo $id; ?>">
      <label for="nombre">Nombre:</label>
      <input type="text" name="nombre" class="input-field" value="<?php echo $arbitro_row['nombre']; ?>"><br><br>
      <label for="apellidos">Apellidos:</label>
      <input type="text" name="apellidos" class="input-field" value="<?php echo $arbitro_row['apellidos']; ?>"><br><br>
      <label for="ncuenta">Nº Cuenta:</label>
      <input type="text" name="ncuenta" class="input-field" value="<?php echo $arbitro_row['ncuenta']; ?>"><br><br>
      <label for="rango">Rango:</label>
      <input type="text" name="rango" class="input-field" value="<?php echo $arbitro_row['rango']; ?>"><br><br>
      <label for="email">Correo:</label>
      <input type="email" name="email" class="input-field" value="<?php echo $arbitro_row['email']; ?>"><br><br>
      <input type="submit" value="Actualizar" class="submit-btn">
    </form>
  </main>
</body>
</html>

<?php
// Close connection
mysqli_close($conn);
?>