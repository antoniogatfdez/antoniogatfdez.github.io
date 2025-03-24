<?php
// Connect to database
$conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");

// Check connection
if (!$conn) {
    die("Connection failed: ". mysqli_connect_error());
}

// Query to retrieve data from tables
$arbitros_query = "SELECT * FROM arbitros";
$disponibilidad_query = "SELECT * FROM disponibilidad";
$partidos_query = "SELECT * FROM partidos";

$arbitros_result = mysqli_query($conn, $arbitros_query);
$disponibilidad_result = mysqli_query($conn, $disponibilidad_query);
$partidos_result = mysqli_query($conn, $partidos_query);

// Close connection
mysqli_close($conn);
?>



<!-- index.html -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Árbitros FEDEXV</title>
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
</head>
<body>
  <header>
    <nav>
      <ul>
        <li><a href="login.php">Iniciar sesión</a></li>
        <li><a href="https://fedexvoleibol.com">FEDEXV</a></li>
      </ul>
    </nav>
  </header>
  <main>
    <h1>Colegio Extremeño de Arbitros</h1>
    <p>Página de uso privado para los Árbitros de la Federación Extremeña de Voleibol</p>
    <h2>‎ Partidos</h2>
    <table id="partidos" class="table">
        <tr>
            <th><b>LICENCIA</b></th>
            <th><b>FECHA</b></th>
            <th><b>CATEGORIA</b></th>
            <th><b>LOCAL</b></th>
            <th><b>VISITANTE</b></th>
            <th><b>RESULTADO LOCAL</b></th>
            <th><b>RESULTADO VISITANTE</b></th>
        </tr>
        <?php while($row = mysqli_fetch_assoc($partidos_result)) {?>
        <tr>
            <td><?php echo $row['arbitro_id'];?></td>
            <td><?php echo $row['fecha'];?></td>
            <td><?php echo $row['categoria'];?></td>
            <td><?php echo $row['equipo_local'];?></td>
            <td><?php echo $row['equipo_visitante'];?></td>
            <td><?php echo $row['resultado_local'];?></td>
            <td><?php echo $row['resultado_visitante'];?></td>
        </tr>
        <?php }?>
    </table>
  </main>
</body>

</html>