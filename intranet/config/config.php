<?php
// Configuración de entorno de desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de zona horaria
date_default_timezone_set('Europe/Madrid');

// Configuración de sesiones
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);

// Configuración de uploads
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_file_uploads', 20);

// Configuración de memoria
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'fedexvb_intranet');
define('DB_USER', 'root');
define('DB_PASS', '');

// Rutas del sistema
define('ROOT_PATH', __DIR__);
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// Configuración de la aplicación
define('APP_NAME', 'FEDEXVB Intranet');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', true);

// Crear directorios si no existen
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

if (!file_exists(LOG_PATH)) {
    mkdir(LOG_PATH, 0777, true);
}
?>
