<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    // Obtener detalles de liquidación
    $liquidacion_id = $_GET['id'];
    
    $query = "SELECT l.*, 
                     DATE_FORMAT(l.fecha_inicio, '%d/%m/%Y') as fecha_inicio,
                     DATE_FORMAT(l.fecha_fin, '%d/%m/%Y') as fecha_fin,
                     CONCAT(a.nombre, ' ', a.apellidos) as arbitro_nombre
              FROM liquidaciones l
              LEFT JOIN arbitros a ON l.arbitro_id = a.id
              WHERE l.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$liquidacion_id]);
    $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($liquidacion) {
        // Obtener partidos de la liquidación
        $query = "SELECT lp.*, 
                         DATE_FORMAT(p.fecha, '%d/%m/%Y %H:%i') as fecha,
                         CONCAT(el.nombre, ' vs ', ev.nombre) as equipos
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
            $total += $partido['importe_partido'] + $partido['importe_dieta'] + $partido['importe_kilometraje'];
        }
        $liquidacion['total_importe'] = number_format($total, 2);
    }
    
    echo json_encode($liquidacion);

} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action == 'rectificaciones') {
        // Obtener todas las rectificaciones
        $query = "SELECT r.*, 
                         CONCAT(a.nombre, ' ', a.apellidos) as arbitro_nombre,
                         DATE_FORMAT(r.fecha_solicitud, '%d/%m/%Y %H:%i') as fecha_solicitud,
                         DATE_FORMAT(r.fecha_respuesta, '%d/%m/%Y %H:%i') as fecha_respuesta,
                         CONCAT('Del ', DATE_FORMAT(l.fecha_inicio, '%d/%m/%Y'), ' al ', DATE_FORMAT(l.fecha_fin, '%d/%m/%Y')) as periodo_liquidacion
                  FROM rectificaciones_liquidaciones r
                  LEFT JOIN arbitros a ON r.arbitro_id = a.id
                  LEFT JOIN liquidaciones l ON r.liquidacion_id = l.id
                  ORDER BY r.fecha_solicitud DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $rectificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($rectificaciones);
        
    } elseif ($action == 'rectificacion_detalle' && isset($_GET['id'])) {
        // Obtener detalle de una rectificación específica
        $rectificacion_id = $_GET['id'];
        
        $query = "SELECT r.*, 
                         CONCAT(a.nombre, ' ', a.apellidos) as arbitro_nombre,
                         DATE_FORMAT(r.fecha_solicitud, '%d/%m/%Y %H:%i') as fecha_solicitud,
                         DATE_FORMAT(r.fecha_respuesta, '%d/%m/%Y %H:%i') as fecha_respuesta,
                         CONCAT('Del ', DATE_FORMAT(l.fecha_inicio, '%d/%m/%Y'), ' al ', DATE_FORMAT(l.fecha_fin, '%d/%m/%Y')) as periodo_liquidacion
                  FROM rectificaciones_liquidaciones r
                  LEFT JOIN arbitros a ON r.arbitro_id = a.id
                  LEFT JOIN liquidaciones l ON r.liquidacion_id = l.id
                  WHERE r.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$rectificacion_id]);
        $rectificacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($rectificacion);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Actualizar importes
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input['action'] == 'actualizar_importes') {
        try {
            $conn->beginTransaction();
            
            $liquidacion_id = $input['liquidacion_id'];
            
            // Obtener partidos de la liquidación
            $query = "SELECT id FROM liquidaciones_partidos WHERE liquidacion_id = ? ORDER BY id";
            $stmt = $conn->prepare($query);
            $stmt->execute([$liquidacion_id]);
            $partidos_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Actualizar cada partido
            foreach ($input['importes'] as $index => $importes) {
                if (isset($partidos_ids[$index])) {
                    $query = "UPDATE liquidaciones_partidos SET 
                                importe_partido = ?, 
                                importe_dieta = ?, 
                                importe_kilometraje = ?
                              WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([
                        $importes['partido'] ?? 0,
                        $importes['dieta'] ?? 0,
                        $importes['kilometraje'] ?? 0,
                        $partidos_ids[$index]
                    ]);
                }
            }
            
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['error' => 'Método no permitido']);
}
?>
