<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireUserType('club');

$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => ''];

try {
    // Obtener ID del club
    $stmt = $conn->prepare("SELECT id FROM clubes WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$club) {
        throw new Exception('Club no encontrado');
    }
    
    $club_id = $club['id'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'list_documentos':
                $stmt = $conn->prepare("
                    SELECT d.*, u.email as usuario_email 
                    FROM documentos_clubes d
                    LEFT JOIN usuarios u ON d.usuario_subida = u.id
                    WHERE d.club_id = ? AND d.activo = 1
                    ORDER BY d.fecha_subida DESC
                ");
                $stmt->execute([$club_id]);
                $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['documentos'] = $documentos;
                break;
                
            case 'download':
                $documento_id = (int)($_GET['documento_id'] ?? 0);
                
                if (!$documento_id) {
                    throw new Exception('ID del documento es obligatorio');
                }
                
                // Verificar que el documento pertenece al club
                $stmt = $conn->prepare("SELECT * FROM documentos_clubes WHERE id = ? AND club_id = ? AND activo = 1");
                $stmt->execute([$documento_id, $club_id]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$documento) {
                    throw new Exception('Documento no encontrado o no autorizado');
                }
                
                $ruta_archivo = __DIR__ . '/../../' . $documento['ruta_archivo'];
                
                if (!file_exists($ruta_archivo)) {
                    throw new Exception('Archivo no encontrado en el servidor');
                }
                
                // Establecer headers para descarga
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $documento['nombre_archivo'] . '"');
                header('Content-Length: ' . filesize($ruta_archivo));
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                
                // Leer y enviar archivo
                readfile($ruta_archivo);
                exit;
                
            case 'view':
                $documento_id = (int)($_GET['documento_id'] ?? 0);
                
                if (!$documento_id) {
                    throw new Exception('ID del documento es obligatorio');
                }
                
                // Verificar que el documento pertenece al club
                $stmt = $conn->prepare("SELECT * FROM documentos_clubes WHERE id = ? AND club_id = ? AND activo = 1");
                $stmt->execute([$documento_id, $club_id]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$documento) {
                    throw new Exception('Documento no encontrado o no autorizado');
                }
                
                $ruta_archivo = __DIR__ . '/../../' . $documento['ruta_archivo'];
                
                if (!file_exists($ruta_archivo)) {
                    throw new Exception('Archivo no encontrado en el servidor');
                }
                
                // Determinar el tipo MIME según la extensión
                $extension = strtolower($documento['tipo_archivo']);
                $mimeTypes = [
                    'pdf' => 'application/pdf',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'bmp' => 'image/bmp',
                    'webp' => 'image/webp',
                    'txt' => 'text/plain',
                    'html' => 'text/html',
                    'htm' => 'text/html'
                ];
                
                $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
                
                // Establecer headers para visualización
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . filesize($ruta_archivo));
                header('Cache-Control: public, max-age=3600');
                header('Content-Disposition: inline; filename="' . $documento['nombre_archivo'] . '"');
                
                // Leer y enviar archivo
                readfile($ruta_archivo);
                exit;
                
            default:
                throw new Exception('Acción no válida');
        }
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
