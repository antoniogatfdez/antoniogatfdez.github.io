<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => ''];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        $club_id = (int)($_GET['club_id'] ?? 0);
        
        switch ($action) {
            case 'equipos':
                $stmt = $conn->prepare("
                    SELECT e.*, c.nombre as categoria,
                           (SELECT COUNT(*) FROM tecnicos t WHERE t.equipo_id = e.id) as total_tecnicos,
                           (SELECT COUNT(*) FROM jugadores j WHERE j.equipo_id = e.id) as total_jugadores
                    FROM equipos e 
                    JOIN categorias c ON e.categoria_id = c.id 
                    WHERE e.club_id = ? 
                    ORDER BY e.nombre
                ");
                $stmt->execute([$club_id]);
                $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['equipos'] = $equipos;
                break;
                
            case 'tecnicos':
                $stmt = $conn->prepare("
                    SELECT t.*, e.nombre as equipo_nombre 
                    FROM tecnicos t 
                    JOIN equipos e ON t.equipo_id = e.id 
                    WHERE e.club_id = ? 
                    ORDER BY t.nombre, t.apellidos
                ");
                $stmt->execute([$club_id]);
                $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['tecnicos'] = $tecnicos;
                break;
                
            case 'jugadores':
                $stmt = $conn->prepare("
                    SELECT j.*, e.nombre as equipo_nombre 
                    FROM jugadores j 
                    JOIN equipos e ON j.equipo_id = e.id 
                    WHERE e.club_id = ? 
                    ORDER BY j.nombre, j.apellidos
                ");
                $stmt->execute([$club_id]);
                $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['jugadores'] = $jugadores;
                break;
                
            case 'categorias':
                $stmt = $conn->prepare("SELECT * FROM categorias ORDER BY nombre");
                $stmt->execute();
                $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['categorias'] = $categorias;
                break;
                
            default:
                // Código existente para obtener detalles del club
                if (isset($_GET['id'])) {
                    $club_id = $_GET['id'];
                    
                    $query = "SELECT c.*, u.email,
                                     COALESCE(eq.total_equipos, 0) as total_equipos,
                                     COALESCE(tec.total_tecnicos, 0) as total_tecnicos,
                                     COALESCE(jug.total_jugadores, 0) as total_jugadores
                              FROM clubes c
                              LEFT JOIN usuarios u ON c.usuario_id = u.id
                              LEFT JOIN (
                                  SELECT club_id, COUNT(*) as total_equipos 
                                  FROM equipos 
                                  WHERE club_id = ? AND activo = 1
                                  GROUP BY club_id
                              ) eq ON c.id = eq.club_id
                              LEFT JOIN (
                                  SELECT e.club_id, COUNT(*) as total_tecnicos
                                  FROM tecnicos t
                                  LEFT JOIN equipos e ON t.equipo_id = e.id
                                  WHERE e.club_id = ? AND t.activo = 1
                                  GROUP BY e.club_id
                              ) tec ON c.id = tec.club_id
                              LEFT JOIN (
                                  SELECT e.club_id, COUNT(*) as total_jugadores
                                  FROM jugadores j
                                  LEFT JOIN equipos e ON j.equipo_id = e.id
                                  WHERE e.club_id = ? AND j.activo = 1
                                  GROUP BY e.club_id
                              ) jug ON c.id = jug.club_id
                              WHERE c.id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$club_id, $club_id, $club_id, $club_id]);
                    $club = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $response['success'] = true;
                    $response['club'] = $club;
                }
                break;
        }
    }
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_equipo':
                $nombre = sanitize_input($input['nombre'] ?? $_POST['nombre_equipo']);
                $club_id = (int)($input['club_id'] ?? $_POST['club_id']);
                $categoria_id = (int)($input['categoria_id'] ?? $_POST['categoria_id']);
                
                if (empty($nombre) || !$club_id || !$categoria_id) {
                    throw new Exception('Todos los campos son obligatorios');
                }
                
                $stmt = $conn->prepare("INSERT INTO equipos (club_id, categoria_id, nombre) VALUES (?, ?, ?)");
                $stmt->execute([$club_id, $categoria_id, $nombre]);
                
                $response['success'] = true;
                $response['message'] = 'Equipo creado exitosamente';
                break;
                
            case 'update_equipo':
                $id = (int)($input['id'] ?? $_POST['id']);
                $nombre = sanitize_input($input['nombre'] ?? $_POST['nombre']);
                $categoria_id = (int)($input['categoria_id'] ?? $_POST['categoria_id']);
                
                if (empty($nombre) || !$categoria_id) {
                    throw new Exception('Todos los campos son obligatorios');
                }
                
                $stmt = $conn->prepare("UPDATE equipos SET nombre = ?, categoria_id = ? WHERE id = ?");
                $stmt->execute([$nombre, $categoria_id, $id]);
                
                $response['success'] = true;
                $response['message'] = 'Equipo actualizado exitosamente';
                break;
                
            case 'delete_equipo':
                $id = (int)($input['id'] ?? $_POST['id']);
                // Verificar si el campo activo existe, si no, usar DELETE
                $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'equipos' AND COLUMN_NAME = 'activo'");
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Campo activo existe, usar soft delete
                    $stmt = $conn->prepare("UPDATE equipos SET activo = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                } else {
                    // Campo activo no existe, usar DELETE directo
                    $stmt = $conn->prepare("DELETE FROM equipos WHERE id = ?");
                    $stmt->execute([$id]);
                }
                
                $response['success'] = true;
                $response['message'] = 'Equipo eliminado exitosamente';
                break;
                
            case 'create_tecnico':
                $equipo_id = (int)($input['equipo_id'] ?? $_POST['equipo_id']);
                $nombre = sanitize_input($input['nombre'] ?? $_POST['nombre']);
                $apellidos = sanitize_input($input['apellidos'] ?? $_POST['apellidos']);
                $dni = sanitize_input($input['dni'] ?? $_POST['dni']);
                $nivel = sanitize_input($input['nivel'] ?? $_POST['nivel']);
                $email = sanitize_input($input['email'] ?? $_POST['email']);
                $telefono = sanitize_input($input['telefono'] ?? $_POST['telefono']);
                
                if (empty($nombre) || empty($apellidos) || empty($dni) || empty($nivel) || !$equipo_id) {
                    throw new Exception('Los campos nombre, apellidos, DNI, nivel y equipo son obligatorios');
                }
                
                // Verificar que el DNI no esté duplicado
                $stmt = $conn->prepare("SELECT id FROM tecnicos WHERE dni = ? AND activo = 1");
                $stmt->execute([$dni]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe un técnico con este DNI');
                }
                
                $stmt = $conn->prepare("INSERT INTO tecnicos (equipo_id, nombre, apellidos, dni, nivel, email, telefono) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$equipo_id, $nombre, $apellidos, $dni, $nivel, $email, $telefono]);
                
                $response['success'] = true;
                $response['message'] = 'Técnico creado exitosamente';
                break;
                
            case 'update_tecnico':
                $id = (int)($input['id'] ?? $_POST['tecnico_id']);
                $equipo_id = (int)($input['equipo_id'] ?? $_POST['equipo_id']);
                $nombre = sanitize_input($input['nombre'] ?? $_POST['nombre']);
                $apellidos = sanitize_input($input['apellidos'] ?? $_POST['apellidos']);
                $dni = sanitize_input($input['dni'] ?? $_POST['dni']);
                $nivel = sanitize_input($input['nivel'] ?? $_POST['nivel']);
                $email = sanitize_input($input['email'] ?? $_POST['email']);
                $telefono = sanitize_input($input['telefono'] ?? $_POST['telefono']);
                
                if (empty($nombre) || empty($apellidos) || empty($dni) || empty($nivel) || !$equipo_id) {
                    throw new Exception('Los campos nombre, apellidos, DNI, nivel y equipo son obligatorios');
                }
                
                // Verificar que el DNI no esté duplicado (excepto el propio registro)
                $stmt = $conn->prepare("SELECT id FROM tecnicos WHERE dni = ? AND id != ? AND activo = 1");
                $stmt->execute([$dni, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe un técnico con este DNI');
                }
                
                $stmt = $conn->prepare("UPDATE tecnicos SET equipo_id = ?, nombre = ?, apellidos = ?, dni = ?, nivel = ?, email = ?, telefono = ? WHERE id = ?");
                $stmt->execute([$equipo_id, $nombre, $apellidos, $dni, $nivel, $email, $telefono, $id]);
                
                $response['success'] = true;
                $response['message'] = 'Técnico actualizado exitosamente';
                break;
                
            case 'delete_tecnico':
                $id = (int)($input['id'] ?? $_POST['id']);
                $stmt = $conn->prepare("UPDATE tecnicos SET activo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                
                $response['success'] = true;
                $response['message'] = 'Técnico eliminado exitosamente';
                break;
                
            case 'create_jugador':
                $equipo_id = (int)($input['equipo_id'] ?? $_POST['equipo_id']);
                $nombre = sanitize_input($input['nombre'] ?? $_POST['nombre']);
                $apellidos = sanitize_input($input['apellidos'] ?? $_POST['apellidos']);
                $dni = sanitize_input($input['dni'] ?? $_POST['dni']);
                $fecha_nacimiento = $input['fecha_nacimiento'] ?? $_POST['fecha_nacimiento'];
                $email = sanitize_input($input['email'] ?? $_POST['email']);
                $telefono = sanitize_input($input['telefono'] ?? $_POST['telefono']);
                
                if (empty($nombre) || empty($apellidos) || empty($dni) || empty($fecha_nacimiento) || !$equipo_id) {
                    throw new Exception('Los campos nombre, apellidos, DNI, fecha de nacimiento y equipo son obligatorios');
                }
                
                // Verificar que el DNI no esté duplicado
                $stmt = $conn->prepare("SELECT id FROM jugadores WHERE dni = ? AND activo = 1");
                $stmt->execute([$dni]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe un jugador con este DNI');
                }
                
                $stmt = $conn->prepare("INSERT INTO jugadores (equipo_id, nombre, apellidos, dni, fecha_nacimiento, email, telefono) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$equipo_id, $nombre, $apellidos, $dni, $fecha_nacimiento, $email, $telefono]);
                
                $response['success'] = true;
                $response['message'] = 'Jugador creado exitosamente';
                break;
                
            case 'update_jugador':
                $id = (int)($input['id'] ?? $_POST['jugador_id']);
                $equipo_id = (int)($input['equipo_id'] ?? $_POST['equipo_id']);
                $nombre = sanitize_input($input['nombre'] ?? $_POST['nombre']);
                $apellidos = sanitize_input($input['apellidos'] ?? $_POST['apellidos']);
                $dni = sanitize_input($input['dni'] ?? $_POST['dni']);
                $fecha_nacimiento = $input['fecha_nacimiento'] ?? $_POST['fecha_nacimiento'];
                $email = sanitize_input($input['email'] ?? $_POST['email']);
                $telefono = sanitize_input($input['telefono'] ?? $_POST['telefono']);
                
                if (empty($nombre) || empty($apellidos) || empty($dni) || empty($fecha_nacimiento) || !$equipo_id) {
                    throw new Exception('Los campos nombre, apellidos, DNI, fecha de nacimiento y equipo son obligatorios');
                }
                
                // Verificar que el DNI no esté duplicado (excepto el propio registro)
                $stmt = $conn->prepare("SELECT id FROM jugadores WHERE dni = ? AND id != ? AND activo = 1");
                $stmt->execute([$dni, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe un jugador con este DNI');
                }
                
                $stmt = $conn->prepare("UPDATE jugadores SET equipo_id = ?, nombre = ?, apellidos = ?, dni = ?, fecha_nacimiento = ?, email = ?, telefono = ? WHERE id = ?");
                $stmt->execute([$equipo_id, $nombre, $apellidos, $dni, $fecha_nacimiento, $email, $telefono, $id]);
                
                $response['success'] = true;
                $response['message'] = 'Jugador actualizado exitosamente';
                break;
                
            case 'delete_jugador':
                $id = (int)($input['id'] ?? $_POST['id']);
                $stmt = $conn->prepare("UPDATE jugadores SET activo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                
                $response['success'] = true;
                $response['message'] = 'Jugador eliminado exitosamente';
                break;
                
            default:
                // Código existente para crear equipos con técnicos y jugadores
                if (isset($_POST['club_id']) && isset($_POST['nombre_equipo'])) {
                    $conn->beginTransaction();
                    
                    $club_id = sanitize_input($_POST['club_id']);
                    $nombre_equipo = sanitize_input($_POST['nombre_equipo']);
                    $categoria_id = sanitize_input($_POST['categoria_id']);
                    $tecnicos = json_decode($_POST['tecnicos'] ?? '[]', true) ?: [];
                    $jugadores = json_decode($_POST['jugadores'] ?? '[]', true) ?: [];
                    
                    // Validar datos
                    if (empty($club_id) || empty($nombre_equipo) || empty($categoria_id)) {
                        throw new Exception('Faltan datos obligatorios');
                    }
                    
                    // Crear el equipo
                    $query = "INSERT INTO equipos (club_id, nombre, categoria_id) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$club_id, $nombre_equipo, $categoria_id]);
                    $equipo_id = $conn->lastInsertId();
                    
                    // Agregar técnicos
                    foreach ($tecnicos as $tecnico) {
                        if ($tecnico['id'] > 0) {
                            // Técnico existente del pool, mover al nuevo equipo
                            $query = "UPDATE tecnicos SET equipo_id = ? WHERE id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->execute([$equipo_id, $tecnico['id']]);
                        } else {
                            // Técnico nuevo, crear
                            $query = "INSERT INTO tecnicos (equipo_id, nombre, apellidos, nivel) VALUES (?, ?, ?, ?)";
                            $stmt = $conn->prepare($query);
                            $stmt->execute([
                                $equipo_id,
                                $tecnico['nombre'],
                                $tecnico['apellidos'],
                                $tecnico['nivel'] ?: 'Nivel 1'
                            ]);
                        }
                    }
                    
                    // Agregar jugadores
                    foreach ($jugadores as $jugador) {
                        if ($jugador['id'] > 0) {
                            // Jugador existente del pool, mover al nuevo equipo
                            $query = "UPDATE jugadores SET equipo_id = ? WHERE id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->execute([$equipo_id, $jugador['id']]);
                        } else {
                            // Jugador nuevo, crear
                            $query = "INSERT INTO jugadores (equipo_id, nombre, apellidos, dni, fecha_nacimiento) VALUES (?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($query);
                            $stmt->execute([
                                $equipo_id,
                                $jugador['nombre'],
                                $jugador['apellidos'],
                                $jugador['dni'],
                                $jugador['fecha_nacimiento']
                            ]);
                        }
                    }
                    
                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Equipo creado exitosamente';
                }
                break;
        }
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
