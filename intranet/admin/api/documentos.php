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
    if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'upload_documento':
                $club_id = (int)($_POST['club_id'] ?? 0);
                $nombre_documento = sanitize_input($_POST['nombre_documento'] ?? '');
                
                if (!$club_id || empty($nombre_documento)) {
                    throw new Exception('Club ID y nombre del documento son obligatorios');
                }
                
                // Verificar que el club existe
                $stmt = $conn->prepare("SELECT id FROM clubes WHERE id = ?");
                $stmt->execute([$club_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('El club especificado no existe');
                }
                
                // Verificar archivo
                if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Error al subir el archivo');
                }
                
                $archivo = $_FILES['archivo'];
                $tamaño_maximo = 10 * 1024 * 1024; // 10MB
                
                if ($archivo['size'] > $tamaño_maximo) {
                    throw new Exception('El archivo es demasiado grande. Máximo 10MB permitido');
                }
                
                // Tipos de archivo permitidos
                $tipos_permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'];
                $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                
                if (!in_array($extension, $tipos_permitidos)) {
                    throw new Exception('Tipo de archivo no permitido. Permitidos: ' . implode(', ', $tipos_permitidos));
                }
                
                // Generar nombre único para el archivo
                $nombre_archivo = uniqid() . '_' . time() . '.' . $extension;
                $directorio_destino = __DIR__ . '/../../assets/uploads/documentos_clubes/';
                $ruta_completa = $directorio_destino . $nombre_archivo;
                
                // Crear directorio si no existe
                if (!is_dir($directorio_destino)) {
                    mkdir($directorio_destino, 0755, true);
                }
                
                // Mover archivo
                if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                    throw new Exception('Error al guardar el archivo en el servidor');
                }
                
                // Guardar en base de datos
                $stmt = $conn->prepare("
                    INSERT INTO documentos_clubes 
                    (club_id, nombre_documento, nombre_archivo, ruta_archivo, tipo_archivo, tamaño_archivo, usuario_subida) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $ruta_relativa = 'assets/uploads/documentos_clubes/' . $nombre_archivo;
                $usuario_id = $auth->getUserId();
                
                $stmt->execute([
                    $club_id,
                    $nombre_documento,
                    $archivo['name'],
                    $ruta_relativa,
                    $extension,
                    $archivo['size'],
                    $usuario_id
                ]);
                
                $response['success'] = true;
                $response['message'] = 'Documento subido exitosamente';
                $response['documento_id'] = $conn->lastInsertId();
                break;
                
            case 'delete_documento':
                $documento_id = (int)($_POST['documento_id'] ?? 0);
                
                if (!$documento_id) {
                    throw new Exception('ID del documento es obligatorio');
                }
                
                // Obtener datos del documento
                $stmt = $conn->prepare("SELECT * FROM documentos_clubes WHERE id = ? AND activo = 1");
                $stmt->execute([$documento_id]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$documento) {
                    throw new Exception('Documento no encontrado');
                }
                
                // Eliminar archivo físico
                $ruta_archivo = __DIR__ . '/../../' . $documento['ruta_archivo'];
                if (file_exists($ruta_archivo)) {
                    unlink($ruta_archivo);
                }
                
                // Marcar como eliminado en base de datos
                $stmt = $conn->prepare("UPDATE documentos_clubes SET activo = 0 WHERE id = ?");
                $stmt->execute([$documento_id]);
                
                $response['success'] = true;
                $response['message'] = 'Documento eliminado exitosamente';
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
    }
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'list_documentos':
                $club_id = (int)($_GET['club_id'] ?? 0);
                
                if (!$club_id) {
                    throw new Exception('Club ID es obligatorio');
                }
                
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
                
                $stmt = $conn->prepare("SELECT * FROM documentos_clubes WHERE id = ? AND activo = 1");
                $stmt->execute([$documento_id]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$documento) {
                    throw new Exception('Documento no encontrado');
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
