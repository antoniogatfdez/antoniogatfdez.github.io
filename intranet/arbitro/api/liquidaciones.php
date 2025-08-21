<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Habilitamos los errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

$auth = new Auth();
$auth->requireUserType('arbitro');

$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');

// Obtener ID del árbitro
$query = "SELECT id FROM arbitros WHERE usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$arbitro_id = $stmt->fetchColumn();

if (!$arbitro_id) {
    echo json_encode(['error' => 'No se encontró el árbitro asociado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    // Obtener detalles de liquidación (solo del árbitro autenticado)
    $liquidacion_id = $_GET['id'];
    
    $query = "SELECT l.*, 
                     DATE_FORMAT(l.fecha_inicio, '%d/%m/%Y') as fecha_inicio,
                     DATE_FORMAT(l.fecha_fin, '%d/%m/%Y') as fecha_fin,
                     CONCAT(a.nombre, ' ', a.apellidos) as arbitro_nombre
              FROM liquidaciones l
              LEFT JOIN arbitros a ON l.arbitro_id = a.id
              WHERE l.id = ? AND l.arbitro_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$liquidacion_id, $arbitro_id]);
    $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($liquidacion) {
        // Obtener partidos de la liquidación
        $query = "SELECT lp.*, 
                         DATE_FORMAT(p.fecha, '%d/%m/%Y %H:%i') as fecha,
                         CONCAT(COALESCE(el.nombre, 'Equipo Local'), ' vs ', COALESCE(ev.nombre, 'Equipo Visitante')) as equipos
                  FROM liquidaciones_partidos lp
                  LEFT JOIN partidos p ON lp.partido_id = p.id
                  LEFT JOIN equipos el ON p.equipo_local_id = el.id
                  LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
                  WHERE lp.liquidacion_id = ?
                  ORDER BY p.fecha";
        $stmt = $conn->prepare($query);
        $stmt->execute([$liquidacion_id]);
        $liquidacion['partidos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular total
        $total = 0;
        foreach ($liquidacion['partidos'] as $partido) {
            $total += ($partido['importe_partido'] ?? 0) + ($partido['importe_dieta'] ?? 0) + ($partido['importe_kilometraje'] ?? 0);
        }
        $liquidacion['total_importe'] = number_format($total, 2);
        
        echo json_encode($liquidacion);
    } else {
        echo json_encode(['error' => 'Liquidación no encontrada o no autorizada']);
    }
} else {
    echo json_encode(['error' => 'Método no permitido o parámetros faltantes']);
}
?>
