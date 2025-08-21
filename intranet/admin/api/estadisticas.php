<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');

if (isset($_GET['arbitro_id'])) {
    $arbitro_id = $_GET['arbitro_id'];
    
    // Estadísticas generales
    $query = "SELECT 
                (SELECT COUNT(*) FROM partidos WHERE arbitro1_id = ?) as como_principal,
                (SELECT COUNT(*) FROM partidos WHERE arbitro2_id = ?) as como_segundo,
                (SELECT COUNT(*) FROM partidos WHERE anotador_id = ?) as como_anotador";
    $stmt = $conn->prepare($query);
    $stmt->execute([$arbitro_id, $arbitro_id, $arbitro_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total_partidos'] = $stats['como_principal'] + $stats['como_segundo'] + $stats['como_anotador'];
    
    // Últimos partidos
    $query = "SELECT p.fecha, 
                     CONCAT(el.nombre, ' vs ', ev.nombre) as equipos,
                     CASE 
                         WHEN p.arbitro1_id = ? THEN '1º Árbitro'
                         WHEN p.arbitro2_id = ? THEN '2º Árbitro'
                         WHEN p.anotador_id = ? THEN 'Anotador'
                     END as rol
              FROM partidos p
              LEFT JOIN equipos el ON p.equipo_local_id = el.id
              LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
              WHERE p.arbitro1_id = ? OR p.arbitro2_id = ? OR p.anotador_id = ?
              ORDER BY p.fecha DESC
              LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->execute([$arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id, $arbitro_id]);
    $stats['ultimos_partidos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($stats);
} else {
    echo json_encode(['error' => 'ID de árbitro no proporcionado']);
}
?>
