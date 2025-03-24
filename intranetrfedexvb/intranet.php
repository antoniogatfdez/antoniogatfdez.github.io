<!-- intranet.php -->
<?php
  session_start();
  if (!isset($_SESSION["arbitro_id"])) {
    header("Location: login.php");
    exit;
  }
  $arbitro_id = $_SESSION["arbitro_id"];
  // Mostrar información del árbitro
  $conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");
  if (!$conn) {
    die("Error de conexión: ". mysqli_connect_error());
  }
  $sql = "SELECT * FROM arbitros WHERE id = '$arbitro_id'";
  $result = mysqli_query($conn, $sql);
  $arbitro = mysqli_fetch_assoc($result);
  mysqli_close($conn);
?>


<!-- intranet.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Intranet FEDEXV</title>
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
</head>
<body>
  <header>
    <nav>
      <ul>
        <li><a href="logout.php">Cerrar sesión</a></li>
        <li><a href="loginadmin.html">Administración</a></li>
        
      </ul>
    </nav>
  </header>
  <main>
    <h1>Intranet</h1>
    <section id="disponibilidad">
      <h2>Indicar disponibilidad semanal</h2>
      <div class="form-container">
      <form id="disponibilidad-form">
        <label for="semana">Día:</label>
        <input type="date" id="semana" name="semana" class="form-input"><br><br>
        <label for="zona">Ciudad:</label>
        <input type="text" id="zona" name="zona" class="form-input"><br><br> 
        <label for="disponible">Disponible:</label>
        <select id="disponible" name="disponible" class="form-input">
        <option value="Si">Sí</option>
        <option value="No">No</option>
        </select><br><br>
        <input type="submit" value="Guardar" class="form-submit">
      </form> 
    </section>
    <section id="partidos">
      <h2>Registrar resultados de partidos</h2>
      <div class="form-container">
      <form id="partidos-form">
        <label for="fecha">Fecha:</label>
        <input type="date" id="fecha" name="fecha" class="form-input"><br><br>
        <label for="categoria">Categoria:</label>
        <input type="text" id="categoria" name="categoria" class="form-input"><br><br> 
        <label for="equipo_local">Equipo local:</label>
        <input type="text" id="equipo_local" name="equipo_local" class="form-input"><br><br> 
        <label for="resultado_local">Resultado local:</label>
        <input type="text" id="resultado_local" name="resultado_local" class="form-input"><br><br>
        <label for="equipo_visitante">Equipo visitante:</label>
        <input type="text" id="equipo_visitante" name="equipo_visitante" class="form-input"><br><br>
        <label for="resultado_visitante">Resultado visitante:</label>
        <input type="text" id="resultado_visitante" name="resultado_visitante" class="form-input"><br><br>
        <input type="submit" value="Guardar" class="form-submit">
      </form>
    </section>
  </main>
  <script src="intranet.js"></script>
</body>
<style>
  #footer {
    position: absolute;
    bottom: 0;
    width: 100%;
  }
  body {
    height: 100vh;
    min-height: 100vh;
    margin: 0;
  }
</style>


</html>

