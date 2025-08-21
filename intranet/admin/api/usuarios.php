<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_user':
        getUserData($conn, $_GET['id']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

function getUserData($conn, $user_id) {
    try {
        $query = "SELECT u.*, 
                         a.nombre as admin_nombre, a.apellidos as admin_apellidos,
                         ar.nombre as arbitro_nombre, ar.apellidos as arbitro_apellidos, 
                         ar.ciudad, ar.iban as arbitro_iban, ar.licencia,
                         c.nombre_club, c.razon_social, c.nombre_responsable, c.iban as club_iban
                  FROM usuarios u
                  LEFT JOIN administradores a ON u.id = a.usuario_id
                  LEFT JOIN arbitros ar ON u.id = ar.usuario_id
                  LEFT JOIN clubes c ON u.id = c.usuario_id
                  WHERE u.id = ? AND u.activo = 1";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Organizar datos según tipo de usuario
            $userData = [
                'id' => $user['id'],
                'email' => $user['email'],
                'tipo_usuario' => $user['tipo_usuario']
            ];
            
            switch ($user['tipo_usuario']) {
                case 'administrador':
                    $userData['nombre'] = $user['admin_nombre'];
                    $userData['apellidos'] = $user['admin_apellidos'];
                    break;
                    
                case 'arbitro':
                    $userData['nombre'] = $user['arbitro_nombre'];
                    $userData['apellidos'] = $user['arbitro_apellidos'];
                    $userData['ciudad'] = $user['ciudad'];
                    $userData['iban'] = $user['arbitro_iban'];
                    $userData['licencia'] = $user['licencia'];
                    break;
                    
                case 'club':
                    $userData['nombre_club'] = $user['nombre_club'];
                    $userData['razon_social'] = $user['razon_social'];
                    $userData['nombre_responsable'] = $user['nombre_responsable'];
                    $userData['iban'] = $user['club_iban'];
                    break;
            }
            
            echo json_encode(['success' => true, 'user' => $userData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener los datos del usuario']);
    }
}
?>
