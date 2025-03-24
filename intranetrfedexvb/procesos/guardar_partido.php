<!-- guardar_partido.php -->
<?php
  session_start();
  if (!isset($_SESSION["arbitro_id"])) {
    header("Location: login.php");
    exit;
  }
  $arbitro_id = $_SESSION["arbitro_id"];
  $fecha = $_POST["fecha"];
  $categoria = $_POST["categoria"];
  $equipo_local = $_POST["equipo_local"];
  $equipo_visitante = $_POST["equipo_visitante"];
  $resultado_local = $_POST["resultado_local"];
  $resultado_visitante = $_POST["resultado_visitante"];
  // Insertar partido en la base de datos
  $conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");
  if (!$conn) {
    die("Error de conexión: ". mysqli_connect_error());
  }
  $sql = "INSERT INTO partidos (fecha, categoria, equipo_local, equipo_visitante, arbitro_id, resultado_local, resultado_visitante) VALUES ('$fecha', '$categoria', '$equipo_local', '$equipo_visitante', '$arbitro_id', '$resultado_local', '$resultado_visitante')";
  if (mysqli_query($conn, $sql)) {
    echo "<script>alert('Partido guardado con éxito');</script>";
    header("Location: /intranet.php");
    exit;
  } else {
    echo "<script>alert('Error al guardar partido');</script>";
  }
  mysqli_close($conn);
?>