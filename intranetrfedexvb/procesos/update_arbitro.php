<?php
// Connect to database
$conn = mysqli_connect("127.0.0.1", "root", "", "arbitros_extremadura");

// Check connection
if (!$conn) {
    die("Connection failed: ". mysqli_connect_error());
}

// Get data from POST
$id = $_POST['id'];
$nombre = $_POST['nombre'];
$apellidos = $_POST['apellidos'];
$ncuenta = $_POST['ncuenta'];
$email = $_POST['email'];

// Update arbitro data
$update_query = "UPDATE arbitros SET nombre = '$nombre', apellidos = '$apellidos', ncuenta = '$ncuenta', email = '$email' WHERE id = '$id'";
mysqli_query($conn, $update_query);

// Redirect to admin page
header('Location: /admin.php');
exit;

// Close connection
mysqli_close($conn);
?>
