<?php
// Connect to database
$conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");

// Check connection
if (!$conn) {
    die("Connection failed: ". mysqli_connect_error());
}

// Get the ID of the arbitro to delete
$id = $_POST['id'];

// Query to delete the arbitro
$query = "DELETE FROM arbitros WHERE id = '$id'";

// Execute the query
if (mysqli_query($conn, $query)) {
    echo "Arbitro eliminado con éxito";
} else {
    echo "Error al eliminar el arbitro: ". mysqli_error($conn);
}

// Close connection
mysqli_close($conn);
?>