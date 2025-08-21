<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireUserType('club');

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // Obtener detalles de un partido
    $partido_id = sanitize_input($_GET['id']);
    
    // Obtener ID del club
    $query = "SELECT id FROM clubes WHERE usuario_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $club_id = $stmt->fetchColumn();
    
    if (!$club_id) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Club no encontrado']);
        exit;
    }
    
    $query = "
        SELECT p.*, 
               DATE(p.fecha) as fecha_solo,
               TIME(p.fecha) as hora_solo,
               el.nombre as equipo_local,
               ev.nombre as equipo_visitante,
               c.nombre as categoria,
               pab.nombre as pabellon,
               pab.ciudad,
               CONCAT(a1.nombre, ' ', a1.apellidos) as arbitro1_nombre,
               CONCAT(a2.nombre, ' ', a2.apellidos) as arbitro2_nombre,
               CONCAT(an.nombre, ' ', an.apellidos) as anotador_nombre,
               CASE 
                   WHEN p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) THEN 'LOCAL'
                   ELSE 'VISITANTE'
               END as condicion,
               CASE 
                   WHEN p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) THEN el.nombre
                   ELSE ev.nombre
               END as mi_equipo,
               CASE 
                   WHEN p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) THEN ev.nombre
                   ELSE el.nombre
               END as equipo_rival
        FROM partidos p
        LEFT JOIN equipos el ON p.equipo_local_id = el.id
        LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN pabellones pab ON p.pabellon_id = pab.id
        LEFT JOIN arbitros a1 ON p.arbitro_principal_id = a1.id
        LEFT JOIN arbitros a2 ON p.arbitro_segundo_id = a2.id
        LEFT JOIN arbitros an ON p.anotador_id = an.id
        WHERE p.id = ?
        AND (p.equipo_local_id IN (SELECT id FROM equipos WHERE club_id = ?) 
             OR p.equipo_visitante_id IN (SELECT id FROM equipos WHERE club_id = ?))
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id, $club_id, $club_id, $partido_id, $club_id, $club_id]);
    $partido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($partido) {
        // Formatear la fecha y hora
        $partido['fecha'] = format_date($partido['fecha_solo']);
        $partido['hora'] = substr($partido['hora_solo'], 0, 5);
        
        // Obtener detalles de los sets si el partido está finalizado
        if ($partido['sets_local'] !== null && $partido['sets_visitante'] !== null) {
            $query = "SELECT numero_set, puntos_local, puntos_visitante 
                     FROM sets_partidos 
                     WHERE partido_id = ? 
                     ORDER BY numero_set";
            $stmt = $conn->prepare($query);
            $stmt->execute([$partido_id]);
            $partido['sets_detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular resultado desde la perspectiva del club
            if ($partido['condicion'] === 'LOCAL') {
                $partido['mis_sets'] = $partido['sets_local'];
                $partido['sets_rival'] = $partido['sets_visitante'];
                $partido['resultado'] = ($partido['sets_local'] > $partido['sets_visitante']) ? 'GANADO' : 'PERDIDO';
            } else {
                $partido['mis_sets'] = $partido['sets_visitante'];
                $partido['sets_rival'] = $partido['sets_local'];
                $partido['resultado'] = ($partido['sets_visitante'] > $partido['sets_local']) ? 'GANADO' : 'PERDIDO';
            }
        } else {
            $partido['sets_detalle'] = [];
            $partido['mis_sets'] = null;
            $partido['sets_rival'] = null;
            $partido['resultado'] = 'SIN_RESULTADO';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($partido);
    
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acción no válida']);
}
?>
