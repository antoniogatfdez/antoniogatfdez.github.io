<!-- logout.php -->
<?php
  ob_start(); // Start output buffering

  // Iniciar sesión para poder destruirla
  if (!session_start()) {
    // Handle session_start() error
    error_log("Error starting session");
    exit;
  }

  // Destruir la sesión
  session_unset();
  session_destroy();

  // Redirigir al usuario a la página de login
  header("Location: login.php");
  die(); // Stop script execution and perform redirect
?>