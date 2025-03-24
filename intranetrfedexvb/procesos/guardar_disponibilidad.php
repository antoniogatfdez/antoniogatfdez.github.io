<?php
// guardar_disponibilidad.php
session_start();
  if (!isset($_SESSION["arbitro_id"])) {
    header("Location: login.php");
    exit;
  }
$arbitro_id = $_SESSION["arbitro_id"];
$semana = $_POST["semana"];
$zona = $_POST["zona"];
$disponible = $_POST["disponible"];

$conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");
if (!$conn) {
    die("Error de conexión: ". mysqli_connect_error());
}

$sql = "INSERT INTO disponibilidad (arbitro_id, semana, zona, disponible) VALUES ('$arbitro_id', '$semana', '$zona', '$disponible')";
$result = mysqli_query($conn, $sql);

if ($result) {
    echo "Disponibilidad guardada correctamente";
} else {
    echo "Error al guardar disponibilidad";
}

mysqli_close($conn);
?>



