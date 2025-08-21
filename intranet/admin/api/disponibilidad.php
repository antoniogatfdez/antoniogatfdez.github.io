<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');

try {
    if (isset($_GET['fecha'])) {
        // Obtener árbitros disponibles para una fecha específica
        $fecha = sanitize_input($_GET['fecha']);
        
        // Debug
        error_log("=== ADMIN API: Consulta por fecha ===");
        error_log("Fecha solicitada: $fecha");
        error_log("Fecha de hoy: " . date('Y-m-d'));
        error_log("=====================================");
        
        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new Exception('Formato de fecha inválido. Use YYYY-MM-DD');
        }
        
        // Verificar que la fecha no sea anterior a hoy
        if ($fecha < date('Y-m-d')) {
            throw new Exception('No se puede consultar disponibilidad para fechas pasadas');
        }
        
        $query = "SELECT a.id, a.nombre, a.apellidos, 
                         CONCAT(a.nombre, ' ', a.apellidos) as nombre_completo,
                         COALESCE(da.disponible, 0) as disponible,
                         da.observaciones,
                         a.licencia,
                         a.ciudad
                  FROM arbitros a
                  LEFT JOIN disponibilidad_arbitros da ON a.id = da.arbitro_id AND da.fecha = ?
                  WHERE a.activo = 1
                  ORDER BY a.nombre, a.apellidos";
        $stmt = $conn->prepare($query);
        $stmt->execute([$fecha]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agregar información adicional para debug
        $response = [
            'fecha_consultada' => $fecha,
            'total_arbitros' => count($result),
            'disponibles' => array_filter($result, function($a) { return $a['disponible'] == 1; }),
            'no_disponibles' => array_filter($result, function($a) { return $a['disponible'] == 0; }),
            'arbitros' => $result
        ];
        
        echo json_encode($result); // Mantener compatibilidad con el código existente
        
    } elseif (isset($_GET['arbitro_id'])) {
        // Obtener disponibilidad de un árbitro específico
        $arbitro_id = (int)$_GET['arbitro_id'];
        
        if (isset($_GET['month'])) {
            // Obtener disponibilidad para un mes específico
            $month = sanitize_input($_GET['month']); // Formato: YYYY-MM
            
            // Debug
            error_log("=== ADMIN API: Consulta por mes ===");
            error_log("Arbitro ID: $arbitro_id");
            error_log("Mes solicitado: $month");
            
            // Validar formato del mes
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                throw new Exception('Formato de mes inválido. Use YYYY-MM');
            }
            
            $firstDay = $month . '-01';
            $lastDay = date('Y-m-t', strtotime($firstDay));
            
            error_log("Rango de fechas: $firstDay a $lastDay");
            error_log("==================================");
            
            $query = "SELECT fecha, disponible, observaciones 
                      FROM disponibilidad_arbitros 
                      WHERE arbitro_id = ? AND fecha BETWEEN ? AND ?
                      ORDER BY fecha";
            $stmt = $conn->prepare($query);
            $stmt->execute([$arbitro_id, $firstDay, $lastDay]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Registros encontrados: " . count($result));
            if (count($result) > 0) {
                error_log("Primeros registros: " . json_encode(array_slice($result, 0, 3)));
            }
            
        } else {
            // Obtener disponibilidad desde hoy hacia adelante (próximos 3 meses)
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-t', strtotime('+3 months'));
            
            $query = "SELECT fecha, disponible, observaciones 
                      FROM disponibilidad_arbitros 
                      WHERE arbitro_id = ? AND fecha BETWEEN ? AND ?
                      ORDER BY fecha";
            $stmt = $conn->prepare($query);
            $stmt->execute([$arbitro_id, $startDate, $endDate]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode($result);
        
    } else {
        throw new Exception('Parámetros insuficientes. Se requiere fecha o arbitro_id.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>
