<?php
$conn = new mysqli("localhost", "root", "", "arbitros_extremadura");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<style>
    #calendario {
        border-collapse: collapse;
        width: 100%;
    }

    #calendario th, #calendario td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }

    #calendario th {
        background-color: #f0f0f0;
    }

    #calendario tr:nth-child(even) {
        background-color: #f2f2f2;
    }
</style>

<h2>‎ Calendario de Disponibilidad</h2>
<table id="calendario" class="table">
    <tr>
        <th><b>Fecha</b></th>
        <th><b>Arbitros Disponibles</b></th>
    </tr>
    <?php
    // Get the distinct dates from the disponibilidad table
    $dates_query = "SELECT DISTINCT semana FROM disponibilidad WHERE disponible = 'si'";
    $dates_result = mysqli_query($conn, $dates_query);

    while ($date_row = mysqli_fetch_assoc($dates_result)) {
        $fecha = $date_row['semana'];
        ?>
        <tr>
            <td><?php echo $fecha; ?></td>
            <td>
                <?php
                // Get the arbitros with disponibilidad 'si' for this date
                $arbitros_query = "SELECT a.id, a.nombre, a.apellidos FROM arbitros a JOIN disponibilidad d ON a.id = d.arbitro_id WHERE d.semana = '$fecha' AND d.disponible = 'si'";
                $arbitros_result = mysqli_query($conn, $arbitros_query);

                while ($arbitro_row = mysqli_fetch_assoc($arbitros_result)) {
                    echo $arbitro_row['id'] . ' - ' . $arbitro_row['nombre'] . ' ' . $arbitro_row['apellidos'] . '<br>';
                }
                ?>
            </td>
        </tr>
        <?php
    }
    ?>
</table>