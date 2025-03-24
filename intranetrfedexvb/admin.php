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

// Start session
session_start();

// Set session variables
$_SESSION['id'] = '';
$_SESSION['nombre'] = '';
$_SESSION['apellidos'] = '';

?>


<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADMIN - Arbitros Extremadura</title>
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
</head>
<body>
<header>
<nav>
    <ul>
    <li><a href="intranet.php">Volver</a></li>
    <li><a href="registro.php">Registrar Nuevo Arbitro</a></li>
    </ul>
</nav>
  </header>
    <h1>‎ Arbitros Extremadura</h1>
    <h2>‎ Usuarios</h2>
    <table id="usuarios" class="table">
    <tr>
        <th><b>LICENCIA</b></th>
        <th><b>RANGO</b></th>
        <th><b>NOMBRE</b></th>
        <th><b>APELLIDOS</b></th>
        <th><b>CORREO</b></th>
        <th><b>Nº CUENTA</b></th>
        <th><b>OPCIONES</b></th>
    </tr>
    <?php while($row = mysqli_fetch_assoc($arbitros_result)) { ?>
    <tr>
        <td><?php echo $row['id']; ?></td>
        <td><?php echo $row['rango']; ?></td>
        <td><?php echo $row['nombre']; ?></td>
        <td><?php echo $row['apellidos']; ?></td>
        <td><?php echo $row['email']; ?></td>
        <td><?php echo $row['ncuenta']; ?></td>
        <td>
            <form action="procesos/editar_arbitro.php" method="post">
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>" />
                <button class="editar-btn" type="editar"><i class="fas fa-pencil-alt"></i></button>
            </form>
            <button class="eliminar-btn" onclick="eliminarArbitro(<?php echo $row['id'];?>)"><i class="fas fa-trash-alt"></i></button>
            <script>
                function eliminarArbitro(id) {
                if (confirm('¿Estás seguro de eliminar este arbitro?')) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'procesos/eliminar_arbitro.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('id=' + id);
                xhr.onload = function() {
                if (xhr.status === 200) {
                alert('Arbitro eliminado con éxito');
                location.reload(); // Recarga la página actual
                } else {
                alert('Error al eliminar el arbitro');
                }
                };
    }
}
</script>
        </td>
    </tr>
    <?php } ?>
</table>

    <h2>‎ Disponibilidad</h2>
    <table id="disponibilidad" class="table">
        <tr>
            <th><b>LICENCIA</b></th>
            <th><b>FECHA</b></th>
            <th><b>DISPONIBLE</b></th>
        </tr>
        <?php while($row = mysqli_fetch_assoc($disponibilidad_result)) {?>
        <tr>
            <td><?php echo $row['arbitro_id'];?></td>
            <td><?php echo $row['semana'];?></td>    
            <td><?php echo $row['disponible'];?></td>
        </tr>
        <?php }?>
    </table>
    
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

<?php
// Close connection
mysqli_close($conn);
?>

</body>

</html>