<?php
// Configurar sesiones antes de iniciarlas
require_once __DIR__ . '/../config/config.php';

// Iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function login($email, $password) {
        try {
            $query = "SELECT u.*, 
                            CASE 
                                WHEN u.tipo_usuario = 'administrador' THEN a.nombre
                                WHEN u.tipo_usuario = 'arbitro' THEN ar.nombre
                                WHEN u.tipo_usuario = 'club' THEN c.nombre_responsable
                            END as nombre,
                            CASE 
                                WHEN u.tipo_usuario = 'administrador' THEN a.apellidos
                                WHEN u.tipo_usuario = 'arbitro' THEN ar.apellidos
                                WHEN u.tipo_usuario = 'club' THEN c.nombre_club
                            END as apellidos_o_club
                     FROM usuarios u
                     LEFT JOIN administradores a ON u.id = a.usuario_id
                     LEFT JOIN arbitros ar ON u.id = ar.usuario_id
                     LEFT JOIN clubes c ON u.id = c.usuario_id
                     WHERE u.email = ? AND u.activo = 1";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['tipo_usuario'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['nombre'];
                $_SESSION['user_lastname'] = $user['apellidos_o_club'];
                $_SESSION['password_temporal'] = $user['password_temporal'];
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function logout() {
        // Limpiar todas las variables de sesión
        $_SESSION = array();
        
        // Destruir la cookie de sesión si existe
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir la sesión
        session_destroy();
        
        // Redireccionar a la página principal
        header("Location: " . $this->getBaseUrl() . "/index.php");
        exit();
    }
    
    private function getBaseUrl() {
        // Obtener la URL base del proyecto
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $path = dirname($script);
        
        // Si estamos en el directorio includes, subir un nivel
        if (basename($path) === 'includes') {
            $path = dirname($path);
        }
        
        return $protocol . '://' . $host . $path;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }
    
    public function getUsername() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $name = $_SESSION['user_name'] ?? '';
        $lastname = $_SESSION['user_lastname'] ?? '';
        
        // Para clubes, el lastname contiene el nombre del club
        if ($this->getUserType() === 'club') {
            return $lastname;
        }
        
        // Para administradores y árbitros, concatenar nombre y apellidos
        return trim($name . ' ' . $lastname);
    }
    
    public function getUserEmail() {
        return $_SESSION['user_email'] ?? null;
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: ../index.php");
            exit();
        }
        
        // Verificar si tiene contraseña temporal y no está en la página de cambio
        if (isset($_SESSION['password_temporal']) && $_SESSION['password_temporal'] == 1) {
            $current_page = basename($_SERVER['PHP_SELF']);
            if ($current_page !== 'cambiar-password.php') {
                header("Location: ../cambiar-password.php");
                exit();
            }
        }
    }
    
    public function requireUserType($type) {
        $this->requireLogin();
        if ($this->getUserType() !== $type) {
            header("Location: ../unauthorized.php");
            exit();
        }
        
        // Verificar contraseña temporal también aquí
        if (isset($_SESSION['password_temporal']) && $_SESSION['password_temporal'] == 1) {
            $current_page = basename($_SERVER['PHP_SELF']);
            if ($current_page !== 'cambiar-password.php') {
                header("Location: ../cambiar-password.php");
                exit();
            }
        }
    }
    
    public function changePassword($newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $query = "UPDATE usuarios SET password = ?, password_temporal = 0 WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
            
            if ($result) {
                $_SESSION['password_temporal'] = false;
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
