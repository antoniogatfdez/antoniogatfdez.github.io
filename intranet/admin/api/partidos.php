<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // Obtener detalles de un partido
    $partido_id = sanitize_input($_GET['id']);
    
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
               CONCAT(an.nombre, ' ', an.apellidos) as anotador_nombre
        FROM partidos p
        LEFT JOIN equipos el ON p.equipo_local_id = el.id
        LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN pabellones pab ON p.pabellon_id = pab.id
        LEFT JOIN arbitros a1 ON p.arbitro_principal_id = a1.id
        LEFT JOIN arbitros a2 ON p.arbitro_segundo_id = a2.id
        LEFT JOIN arbitros an ON p.anotador_id = an.id
        WHERE p.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$partido_id]);
    $partido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($partido) {
        // Formatear la fecha y hora
        $partido['fecha'] = format_date($partido['fecha_solo']);
        $partido['hora'] = substr($partido['hora_solo'], 0, 5);
        
        // Mantener formato original para formulario
        $partido['fecha_original'] = $partido['fecha_solo'];
        $partido['hora_original'] = substr($partido['hora_solo'], 0, 5);
        
        // Obtener detalles de los sets si el partido está finalizado
        if ($partido['sets_local'] !== null && $partido['sets_visitante'] !== null) {
            $query = "SELECT numero_set, puntos_local, puntos_visitante 
                     FROM sets_partidos 
                     WHERE partido_id = ? 
                     ORDER BY numero_set";
            $stmt = $conn->prepare($query);
            $stmt->execute([$partido_id]);
            $partido['sets_detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $partido['sets_detalle'] = [];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($partido);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'guardar_resultado') {
        // Guardar/modificar resultado del partido (solo admin)
        try {
            $partido_id = sanitize_input($_POST['partido_id']);
            $sets_local = intval($_POST['sets_local']);
            $sets_visitante = intval($_POST['sets_visitante']);
            
            // Validar sets
            if ($sets_local < 0 || $sets_visitante < 0 || $sets_local > 5 || $sets_visitante > 5) {
                throw new Exception('Número de sets inválido');
            }
            
            if ($sets_local == $sets_visitante) {
                throw new Exception('No puede haber empate en voleibol');
            }
            
            $conn->beginTransaction();
            
            // Actualizar resultado principal
            $query = "UPDATE partidos SET 
                        sets_local = ?, 
                        sets_visitante = ?,
                        estado = 'finalizado',
                        fecha_actualizacion = NOW()
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$sets_local, $sets_visitante, $partido_id]);
            
            // Guardar detalles de sets
            // Primero eliminar registros existentes
            $query = "DELETE FROM sets_partidos WHERE partido_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$partido_id]);
            
            // Insertar nuevos sets
            for ($i = 1; $i <= ($sets_local + $sets_visitante); $i++) {
                if (isset($_POST["set{$i}_local"]) && isset($_POST["set{$i}_visitante"])) {
                    $puntos_local = intval($_POST["set{$i}_local"]);
                    $puntos_visitante = intval($_POST["set{$i}_visitante"]);
                    
                    // Validar puntos del set
                    if ($puntos_local < 0 || $puntos_visitante < 0) {
                        throw new Exception("Puntos inválidos en set $i");
                    }
                    
                    $query = "INSERT INTO sets_partidos (partido_id, numero_set, puntos_local, puntos_visitante) 
                             VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$partido_id, $i, $puntos_local, $puntos_visitante]);
                }
            }
            
            $conn->commit();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Resultado guardado correctamente'
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acción no válida']);
}
?>
