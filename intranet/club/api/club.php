<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireUserType('club');

$database = new Database();
$conn = $database->getConnection();

// Obtener ID del club
$query = "SELECT id FROM clubes WHERE usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    http_response_code(403);
    echo json_encode(['error' => 'Club no encontrado']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'estadisticas':
            getEstadisticas($conn, $club['id']);
            break;
            
        case 'equipos':
            getEquipos($conn, $club['id']);
            break;
            
        case 'jugadores':
            getJugadores($conn, $club['id']);
            break;
            
        case 'tecnicos':
            getTecnicos($conn, $club['id']);
            break;
            
        case 'partidos':
            getPartidos($conn, $club['id']);
            break;
            
        case 'proximos_partidos':
            getProximosPartidos($conn, $club['id']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

function getEstadisticas($conn, $club_id) {
    $stats = [];
    
    // Equipos
    $query = "SELECT COUNT(*) FROM equipos WHERE club_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
    $stats['equipos'] = (int)$stmt->fetchColumn();
    
    // Jugadores
    $query = "SELECT COUNT(*) FROM jugadores j 
              JOIN equipos e ON j.equipo_id = e.id 
              WHERE e.club_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
    $stats['jugadores'] = (int)$stmt->fetchColumn();
    
    // Técnicos
    $query = "SELECT COUNT(*) FROM tecnicos t 
              JOIN equipos e ON t.equipo_id = e.id 
              WHERE e.club_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
    $stats['tecnicos'] = (int)$stmt->fetchColumn();
    
    // Próximos partidos
    $query = "SELECT COUNT(*) FROM partidos p
              WHERE (p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) 
                    OR p.equipo_visitante_id IN (SELECT id FROM equipos WHERE club_id = ?))
                    AND p.fecha >= NOW() AND p.finalizado = 0";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id, $club_id]);
    $stats['proximos_partidos'] = (int)$stmt->fetchColumn();
    
    echo json_encode($stats);
}

function getEquipos($conn, $club_id) {
    $query = "SELECT e.*, c.nombre as categoria 
              FROM equipos e 
              JOIN categorias c ON e.categoria_id = c.id 
              WHERE e.club_id = ?
              ORDER BY c.nombre, e.nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($equipos);
}

function getJugadores($conn, $club_id) {
    $equipo_filtro = $_GET['equipo'] ?? null;
    
    $query = "SELECT j.*, e.nombre as equipo_nombre, c.nombre as categoria,
                     TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) as edad
              FROM jugadores j 
              JOIN equipos e ON j.equipo_id = e.id 
              JOIN categorias c ON e.categoria_id = c.id 
              WHERE e.club_id = ?";
    $params = [$club_id];
    
    if ($equipo_filtro) {
        $query .= " AND e.id = ?";
        $params[] = $equipo_filtro;
    }
    
    $query .= " ORDER BY e.nombre, j.apellidos, j.nombre";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($jugadores);
}

function getTecnicos($conn, $club_id) {
    $equipo_filtro = $_GET['equipo'] ?? null;
    
    $query = "SELECT t.*, e.nombre as equipo_nombre, c.nombre as categoria
              FROM tecnicos t 
              JOIN equipos e ON t.equipo_id = e.id 
              JOIN categorias c ON e.categoria_id = c.id 
              WHERE e.club_id = ?";
    $params = [$club_id];
    
    if ($equipo_filtro) {
        $query .= " AND e.id = ?";
        $params[] = $equipo_filtro;
    }
    
    $query .= " ORDER BY e.nombre, t.apellidos, t.nombre";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($tecnicos);
}

function getPartidos($conn, $club_id) {
    $limite = $_GET['limite'] ?? null;
    
    $query = "SELECT p.*, 
                     el.nombre as equipo_local, ev.nombre as equipo_visitante,
                     pab.nombre as pabellon, pab.ciudad as ciudad_pabellon,
                     cat.nombre as categoria,
                     CASE 
                         WHEN p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) THEN 'LOCAL'
                         ELSE 'VISITANTE'
                     END as condicion
              FROM partidos p
              JOIN equipos el ON p.equipo_local_id = el.id
              JOIN equipos ev ON p.equipo_visitante_id = ev.id
              JOIN pabellones pab ON p.pabellon_id = pab.id
              JOIN categorias cat ON p.categoria_id = cat.id
              WHERE (p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) 
                    OR p.equipo_visitante_id IN (SELECT id FROM equipos WHERE club_id = ?))
              ORDER BY p.fecha DESC";
    
    if ($limite) {
        $query .= " LIMIT " . (int)$limite;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id, $club_id, $club_id]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($partidos);
}

function getProximosPartidos($conn, $club_id) {
    $limite = $_GET['limite'] ?? 5;
    
    $query = "SELECT p.*, 
                     el.nombre as equipo_local, ev.nombre as equipo_visitante,
                     pab.nombre as pabellon, pab.ciudad as ciudad_pabellon,
                     cat.nombre as categoria,
                     CASE 
                         WHEN p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) THEN 'LOCAL'
                         ELSE 'VISITANTE'
                     END as condicion
              FROM partidos p
              JOIN equipos el ON p.equipo_local_id = el.id
              JOIN equipos ev ON p.equipo_visitante_id = ev.id
              JOIN pabellones pab ON p.pabellon_id = pab.id
              JOIN categorias cat ON p.categoria_id = cat.id
              WHERE (p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) 
                    OR p.equipo_visitante_id IN (SELECT id FROM equipos WHERE club_id = ?))
                    AND p.fecha >= NOW() AND p.finalizado = 0
              ORDER BY p.fecha ASC
              LIMIT " . (int)$limite;
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id, $club_id, $club_id]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($partidos);
}
?>
