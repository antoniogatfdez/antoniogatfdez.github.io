<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireUserType('administrador');

$database = new Database();
$conn = $database->getConnection();

$message = '';

// Procesar formulario de nuevo club
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create':
                $nombre = sanitize_input($_POST['nombre']);
                $direccion = sanitize_input($_POST['direccion']);
                $telefono = sanitize_input($_POST['telefono']);
                $email = sanitize_input($_POST['email']);
                $presidente = sanitize_input($_POST['presidente']);
                $telefono_presidente = sanitize_input($_POST['telefono_presidente']);
                
                if (empty($nombre) || empty($email)) {
                    throw new Exception('Nombre y email son obligatorios');
                }
                
                if (!validate_email($email)) {
                    throw new Exception('Email no válido');
                }
                
                // Verificar si el email ya existe
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception('El email ya está registrado');
                }
                
                // Iniciar transacción
                $conn->beginTransaction();
                
                try {
                    // Crear usuario
                    $password_temporal = generate_password();
                    $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("INSERT INTO usuarios (tipo_usuario, email, password, password_temporal, fecha_creacion, activo) VALUES ('club', ?, ?, 1, NOW(), 1)");
                    $stmt->execute([$email, $password_hash]);
                    $usuario_id = $conn->lastInsertId();
                    
                    // Crear club
                    $stmt = $conn->prepare("INSERT INTO clubes (usuario_id, nombre_club, nombre_responsable, iban) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$usuario_id, $nombre, $presidente, '']);
                    
                    $conn->commit();
                    $message = success_message("Club creado exitosamente. Password temporal: $password_temporal");
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $nombre = sanitize_input($_POST['nombre']);
                $direccion = sanitize_input($_POST['direccion']);
                $telefono = sanitize_input($_POST['telefono']);
                $email = sanitize_input($_POST['email']);
                $presidente = sanitize_input($_POST['presidente']);
                $telefono_presidente = sanitize_input($_POST['telefono_presidente']);
                
                if (empty($nombre) || empty($email)) {
                    throw new Exception('Nombre y email son obligatorios');
                }
                
                if (!validate_email($email)) {
                    throw new Exception('Email no válido');
                }
                
                // Verificar si el email ya existe en otro usuario
                $stmt = $conn->prepare("SELECT u.id FROM usuarios u JOIN clubes c ON u.id = c.usuario_id WHERE u.email = ? AND c.id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception('El email ya está registrado por otro club');
                }
                
                // Iniciar transacción
                $conn->beginTransaction();
                
                try {
                    // Actualizar usuario
                    $stmt = $conn->prepare("UPDATE usuarios u JOIN clubes c ON u.id = c.usuario_id SET u.email = ? WHERE c.id = ?");
                    $stmt->execute([$email, $id]);
                    
                    // Actualizar club
                    $stmt = $conn->prepare("UPDATE clubes SET nombre_club = ?, nombre_responsable = ? WHERE id = ?");
                    $stmt->execute([$nombre, $presidente, $id]);
                    
                    $conn->commit();
                    $message = success_message('Club actualizado exitosamente');
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Verificar si tiene equipos asociados
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM equipos WHERE club_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['total'] > 0) {
                    throw new Exception('No se puede eliminar el club porque tiene equipos asociados');
                }
                
                // Desactivar usuario (esto desactivará efectivamente el club)
                $stmt = $conn->prepare("UPDATE usuarios u JOIN clubes c ON u.id = c.usuario_id SET u.activo = 0 WHERE c.id = ?");
                $stmt->execute([$id]);
                
                $message = success_message('Club eliminado exitosamente');
                break;
        }
    } catch (Exception $e) {
        $message = error_message($e->getMessage());
    }
}

// Obtener lista de clubes con información de usuario
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.usuario_id, c.nombre_club as nombre, c.razon_social, 
               c.nombre_responsable as presidente, c.iban,
               u.email,
               '' as direccion,
               '' as telefono,
               '' as telefono_presidente,
               COUNT(e.id) as total_equipos,
               (SELECT COUNT(*) FROM tecnicos t 
                JOIN equipos eq ON t.equipo_id = eq.id 
                WHERE eq.club_id = c.id) as total_tecnicos,
               (SELECT COUNT(*) FROM jugadores j 
                JOIN equipos eq ON j.equipo_id = eq.id 
                WHERE eq.club_id = c.id) as total_jugadores
        FROM clubes c 
        JOIN usuarios u ON c.usuario_id = u.id
        LEFT JOIN equipos e ON c.id = e.club_id
        WHERE u.activo = 1 AND u.tipo_usuario = 'club'
        GROUP BY c.id 
        ORDER BY c.nombre_club
    ");
    $stmt->execute();
    $clubes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $clubes = [];
    $message = error_message('Error al cargar los clubes: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clubes - FEDEXVB</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/search-bar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-volleyball-ball"></i>
                    <span>FEDEXVB - Administrador</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($auth->getUsername()); ?></div>
                        <div class="user-role">Administrador</div>
                    </div>
                    <a href="../includes/logout.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="usuarios.php"><i class="fas fa-users"></i> Gestión de Usuarios</a></li>
            <li><a href="partidos.php"><i class="fas fa-calendar-alt"></i> Gestión de Partidos</a></li>
            <li><a href="arbitros.php"><i class="fa-solid fa-person"></i> Gestión de Árbitros</a></li>
            <li><a href="clubes.php" class="active"><i class="fas fa-building"></i> Gestión de Clubes</a></li>
            <li><a href="licencias.php"><i class="fas fa-id-card"></i> Gestión de Licencias</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Liquidaciones</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-building"></i> Gestión de Clubes</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> Inicio / Gestión de Clubes
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Acciones rápidas 
        <div class="card">
            <div class="card-header">
                <i class="fas fa-rocket"></i> Acciones Rápidas
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <button onclick="openModal('modalNuevoClub')" class="btn btn-primary" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <i class="fas fa-plus" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Nuevo Club
                    </button>
                    
                    <a href="equipos.php" class="btn btn-info" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-users" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Gestión de Equipos
                    </a>
                    
                    <a href="tecnicos.php" class="btn btn-success" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-chalkboard-teacher" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Gestión Técnicos
                    </a>
                    
                    <a href="jugadores.php" class="btn btn-warning" style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-decoration: none;">
                        <i class="fas fa-user-friends" style="font-size: 1.5rem; margin-bottom: 5px;"></i>
                        Gestión Jugadores
                    </a>
                </div>
            </div>
        </div>
        -->

        <!-- Lista de clubes -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Lista de Clubes
                <span class="badge" style="background: var(--primary-green); color: white; margin-left: 10px;">
                    <span id="total-count"><?php echo count($clubes); ?></span> clubes
                </span>
            </div>
            <div class="card-body">
                <?php if (!empty($clubes)): ?>
                    <!-- Barra de búsqueda -->
                    <div class="search-container">
                        <div class="search-input-group">
                            <div class="search-input-icon">
                                <i class="fas fa-search"></i>
                                <input type="text" 
                                       id="searchInput" 
                                       placeholder="Buscar por nombre del club, presidente, email..." 
                                       class="search-input-field">
                            </div>
                            <button type="button" 
                                    id="searchClear" 
                                    class="btn search-clear-btn" 
                                    style="display: none;"
                                    title="Limpiar búsqueda">
                                <i class="fas fa-times"></i> Limpiar
                            </button>
                        </div>
                        <div id="searchInfo" class="search-info" style="display: none;">
                            <i class="fas fa-info-circle"></i> 
                            <span id="searchResults">0</span> club(es) encontrado(s)
                        </div>
                        <div class="search-help">
                            <i class="fas fa-lightbulb"></i> 
                            <em>Busca por nombre del club, presidente o email. Usa ESC para limpiar.</em>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table searchable-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Presidente</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Estadísticas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clubes as $club): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($club['nombre']); ?></strong>
                                            <?php if ($club['direccion']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($club['direccion']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($club['presidente']); ?>
                                            <?php if ($club['telefono_presidente']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($club['telefono_presidente']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($club['email']); ?></td>
                                        <td><?php echo htmlspecialchars($club['telefono']); ?></td>
                                        <td>
                                            <div class="estadisticas-club">
                                                <span class="badge badge-info"><?php echo $club['total_equipos']; ?> equipos</span>
                                                <span class="badge badge-success"><?php echo $club['total_tecnicos']; ?> técnicos</span>
                                                <span class="badge badge-warning"><?php echo $club['total_jugadores']; ?> jugadores</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-info btn-sm" onclick="verDetallesClub(<?php echo $club['id']; ?>, '<?php echo htmlspecialchars($club['nombre']); ?>')" title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm" onclick="gestorArchivos(<?php echo $club['id']; ?>, '<?php echo htmlspecialchars($club['nombre']); ?>')" title="Gestor de Archivos">
                                                    <i class="fas fa-folder-open"></i>
                                                </button>
                                                <button class="btn btn-primary btn-sm" onclick="editarClub(<?php echo htmlspecialchars(json_encode($club)); ?>)" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="eliminarClub(<?php echo $club['id']; ?>, '<?php echo htmlspecialchars($club['nombre']); ?>')" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-building" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No hay clubes registrados</h4>
                        <p class="text-muted">Comience agregando el primer club del sistema</p>
                        <button class="btn btn-primary" onclick="openModal('modalNuevoClub')">
                            <i class="fas fa-plus"></i> Crear Primer Club
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo/Editar Club -->
    <div id="modalNuevoClub" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-green); color: white;">
                <h3 id="modalTitle" style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-plus"></i> Nuevo Club
                </h3>
                <span class="close" onclick="closeModal('modalNuevoClub')" style="color: white; font-size: 1.5rem;">&times;</span>
            </div>
            <form id="formClub" method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="clubId">
                
                <div class="modal-body" style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="nombre" style="font-weight: 600; margin-bottom: 8px; display: block;">Nombre del Club *</label>
                            <input type="text" id="nombre" name="nombre" required
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label for="email" style="font-weight: 600; margin-bottom: 8px; display: block;">Email *</label>
                            <input type="email" id="email" name="email" required
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <div class="form-group">
                            <label for="direccion" style="font-weight: 600; margin-bottom: 8px; display: block;">Dirección</label>
                            <input type="text" id="direccion" name="direccion"
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;">
                        <div class="form-group">
                            <label for="telefono" style="font-weight: 600; margin-bottom: 8px; display: block;">Teléfono del Club</label>
                            <input type="tel" id="telefono" name="telefono"
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                        <div class="form-group">
                            <label for="presidente" style="font-weight: 600; margin-bottom: 8px; display: block;">Presidente</label>
                            <input type="text" id="presidente" name="presidente"
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label for="telefono_presidente" style="font-weight: 600; margin-bottom: 8px; display: block;">Teléfono del Presidente</label>
                            <input type="tel" id="telefono_presidente" name="telefono_presidente"
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn" onclick="closeModal('modalNuevoClub')"
                            style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit"
                            style="padding: 10px 20px; background: var(--primary-green); color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-save"></i> Guardar Club
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmación Eliminar -->
    <div id="modalEliminar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
                <span class="close" onclick="closeModal('modalEliminar')">&times;</span>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar el club <strong id="clubNombre"></strong>?</p>
                <p class="text-warning">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEliminar')">Cancelar</button>
                <form id="formEliminar" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="eliminarId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalles del Club -->
    <div id="modalDetallesClub" class="modal modal-large">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Detalles del Club: <span id="detallesClubNombre"></span></h3>
                <span class="close" onclick="closeModal('modalDetallesClub')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="tabs">
                    <div class="tab-nav">
                        <button class="tab-btn active" onclick="cambiarTab('equipos')">
                            <i class="fas fa-users"></i> Equipos
                        </button>
                        <button class="tab-btn" onclick="cambiarTab('tecnicos')">
                            <i class="fas fa-chalkboard-teacher"></i> Técnicos
                        </button>
                        <button class="tab-btn" onclick="cambiarTab('jugadores')">
                            <i class="fas fa-user-friends"></i> Jugadores
                        </button>
                        <button class="tab-btn" onclick="cambiarTab('documentos')">
                            <i class="fas fa-folder-open"></i> Documentos
                        </button>
                    </div>
                    
                    <!-- Tab Equipos -->
                    <div id="tab-equipos" class="tab-content active">
                        <div class="tab-header">
                            <h4><i class="fas fa-users"></i> Equipos del Club</h4>
                            <button class="btn btn-primary btn-sm" onclick="nuevoEquipo()">
                                <i class="fas fa-plus"></i> Nuevo Equipo
                            </button>
                        </div>
                        <div id="listaEquipos">
                            <!-- Se cargará dinámicamente -->
                        </div>
                    </div>
                    
                    <!-- Tab Técnicos -->
                    <div id="tab-tecnicos" class="tab-content">
                        <div class="tab-header">
                            <h4><i class="fas fa-chalkboard-teacher"></i> Técnicos del Club</h4>
                            <button class="btn btn-primary btn-sm" onclick="nuevoTecnico()">
                                <i class="fas fa-plus"></i> Nuevo Técnico
                            </button>
                        </div>
                        <div id="listaTecnicos">
                            <!-- Se cargará dinámicamente -->
                        </div>
                    </div>
                    
                    <!-- Tab Jugadores -->
                    <div id="tab-jugadores" class="tab-content">
                        <div class="tab-header">
                            <h4><i class="fas fa-user-friends"></i> Jugadores del Club</h4>
                            <button class="btn btn-primary btn-sm" onclick="nuevoJugador()">
                                <i class="fas fa-plus"></i> Nuevo Jugador
                            </button>
                        </div>
                        <div id="listaJugadores">
                            <!-- Se cargará dinámicamente -->
                        </div>
                    </div>
                    
                    <!-- Tab Documentos -->
                    <div id="tab-documentos" class="tab-content">
                        <div class="tab-header">
                            <h4><i class="fas fa-folder-open"></i> Documentos del Club</h4>
                            <button class="btn btn-primary btn-sm" onclick="subirDocumento()">
                                <i class="fas fa-upload"></i> Subir Documento
                            </button>
                        </div>
                        <div id="listaDocumentos">
                            <!-- Se cargará dinámicamente -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Técnico -->
    <div id="modalTecnico" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-green); color: white;">
                <h3 id="modalTecnicoTitle" style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chalkboard-teacher"></i> Nuevo Técnico
                </h3>
                <span class="close" onclick="closeModal('modalTecnico')" style="color: white; font-size: 1.5rem;">&times;</span>
            </div>
            <form id="formTecnico">
                <input type="hidden" id="tecnicoId">
                <input type="hidden" id="tecnicoEquipoId">
                
                <div class="modal-body" style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="tecnicoNombre" style="font-weight: 600; margin-bottom: 8px; display: block;">Nombre *</label>
                            <input type="text" id="tecnicoNombre" required 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label for="tecnicoApellidos" style="font-weight: 600; margin-bottom: 8px; display: block;">Apellidos *</label>
                            <input type="text" id="tecnicoApellidos" required 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                        <div class="form-group">
                            <label for="tecnicoDni" style="font-weight: 600; margin-bottom: 8px; display: block;">DNI *</label>
                            <input type="text" id="tecnicoDni" required 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label for="tecnicoNivel" style="font-weight: 600; margin-bottom: 8px; display: block;">Nivel *</label>
                            <select id="tecnicoNivel" required 
                                    style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: white;">
                                <option value="">Seleccionar nivel</option>
                                <option value="Iniciación">Iniciación</option>
                                <option value="Nivel 1">Nivel 1</option>
                                <option value="Nivel 2">Nivel 2</option>
                                <option value="Nivel 3">Nivel 3</option>
                                <option value="Superior">Superior</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                        <div class="form-group">
                            <label for="tecnicoEmail" style="font-weight: 600; margin-bottom: 8px; display: block;">Email</label>
                            <input type="email" id="tecnicoEmail" 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label for="tecnicoTelefono" style="font-weight: 600; margin-bottom: 8px; display: block;">Teléfono</label>
                            <input type="tel" id="tecnicoTelefono" 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <div class="form-group">
                            <label for="tecnicoEquipo" style="font-weight: 600; margin-bottom: 8px; display: block;">Equipo *</label>
                            <select id="tecnicoEquipo" required 
                                    style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: white;">
                                <option value="">Seleccionar equipo</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn" onclick="closeModal('modalTecnico')" 
                            style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" 
                            style="padding: 10px 20px; background: var(--primary-green); color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-save"></i> Guardar Técnico
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Jugador -->
    <div id="modalJugador" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-green); color: white;">
                <h3 id="modalJugadorTitle" style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user-friends"></i> Nuevo Jugador
                </h3>
                <span class="close" onclick="closeModal('modalJugador')" style="color: white; font-size: 1.5rem;">&times;</span>
            </div>
            <form id="formJugador">
                <input type="hidden" id="jugadorId">
                <input type="hidden" id="jugadorEquipoId">
                
                <div class="modal-body" style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="jugadorNombre" style="font-weight: 600; margin-bottom: 8px; display: block;">Nombre *</label>
                            <input type="text" id="jugadorNombre" required 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label for="jugadorApellidos" style="font-weight: 600; margin-bottom: 8px; display: block;">Apellidos *</label>
                            <input type="text" id="jugadorApellidos" required 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                        <div class="form-group">
                            <label for="jugadorDni" style="font-weight: 600; margin-bottom: 8px; display: block;">DNI *</label>
                            <input type="text" id="jugadorDni" required 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label for="jugadorFechaNacimiento" style="font-weight: 600; margin-bottom: 8px; display: block;">Fecha de Nacimiento *</label>
                            <input type="date" id="jugadorFechaNacimiento" required 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                        <div class="form-group">
                            <label for="jugadorEmail" style="font-weight: 600; margin-bottom: 8px; display: block;">Email</label>
                            <input type="email" id="jugadorEmail" 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label for="jugadorTelefono" style="font-weight: 600; margin-bottom: 8px; display: block;">Teléfono</label>
                            <input type="tel" id="jugadorTelefono" 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <div class="form-group">
                            <label for="jugadorEquipo" style="font-weight: 600; margin-bottom: 8px; display: block;">Equipo *</label>
                            <select id="jugadorEquipo" required 
                                    style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: white;">
                                <option value="">Seleccionar equipo</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn" onclick="closeModal('modalJugador')" 
                            style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" 
                            style="padding: 10px 20px; background: var(--primary-green); color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-save"></i> Guardar Jugador
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Equipo -->
    <div id="modalEquipo" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-green); color: white;">
                <h3 id="modalEquipoTitle" style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-users"></i> Nuevo Equipo
                </h3>
                <span class="close" onclick="closeModal('modalEquipo')" style="color: white; font-size: 1.5rem;">&times;</span>
            </div>
            <form id="formEquipo">
                <input type="hidden" id="equipoId">
                <input type="hidden" id="equipoClubId">
                
                <div class="modal-body" style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="equipoNombre" style="font-weight: 600; margin-bottom: 8px; display: block;">Nombre del Equipo *</label>
                            <input type="text" id="equipoNombre" required placeholder="Ej: CV Badajoz Senior Masculino" 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>
                        
                        <div class="form-group">
                            <label for="equipoCategoria" style="font-weight: 600; margin-bottom: 8px; display: block;">Categoría *</label>
                            <select id="equipoCategoria" required 
                                    style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: white;">
                                <option value="">Seleccionar categoría</option>
                                <!-- Se cargarán dinámicamente -->
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn" onclick="closeModal('modalEquipo')" 
                            style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" 
                            style="padding: 10px 20px; background: var(--primary-green); color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-save"></i> Guardar Equipo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Gestor de Archivos -->
    <div id="modalGestorArchivos" class="modal modal-large">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-green); color: white;">
                <h3 style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-folder-open"></i> Gestor de Archivos: <span id="nombreClubArchivos"></span>
                </h3>
                <span class="close" onclick="closeModal('modalGestorArchivos')" style="color: white; font-size: 1.5rem;">&times;</span>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4><i class="fas fa-file-alt"></i> Documentos del Club</h4>
                    <button class="btn btn-primary" onclick="abrirModalSubirDocumento()">
                        <i class="fas fa-upload"></i> Subir Documento
                    </button>
                </div>
                
                <div id="listaDocumentosGestor">
                    <!-- Se cargará dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Subir Documento -->
    <div id="modalSubirDocumento" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-green); color: white;">
                <h3 style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-upload"></i> Subir Documento
                </h3>
                <span class="close" onclick="closeModal('modalSubirDocumento')" style="color: white; font-size: 1.5rem;">&times;</span>
            </div>
            <form id="formSubirDocumento" enctype="multipart/form-data">
                <input type="hidden" id="clubIdDocumento" name="clubIdDocumento">
                
                <div class="modal-body" style="padding: 25px;">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="nombreDocumento" style="font-weight: 600; margin-bottom: 8px; display: block;">Nombre del Documento *</label>
                        <input type="text" id="nombreDocumento" name="nombreDocumento" required placeholder="Ej: Licencias Temporada 2025-26"
                               style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="archivoDocumento" style="font-weight: 600; margin-bottom: 8px; display: block;">Archivo *</label>
                        <input type="file" id="archivoDocumento" name="archivoDocumento" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt"
                               style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <small style="color: #666; margin-top: 5px; display: block;">
                            Tipos permitidos: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, TXT. Máximo 10MB.
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn" onclick="closeModal('modalSubirDocumento')"
                            style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary"
                            style="padding: 10px 20px; background: var(--primary-green); color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-upload"></i> Subir Documento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        let clubActual = null;
        
        function editarClub(club) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Club';
            document.getElementById('modalTitle').style.color = 'white';
            document.getElementById('modalTitle').style.margin = '0';
            document.getElementById('modalTitle').style.display = 'flex';
            document.getElementById('modalTitle').style.alignItems = 'center';
            document.getElementById('modalTitle').style.gap = '10px';
            document.getElementById('formAction').value = 'update';
            document.getElementById('clubId').value = club.id;
            document.getElementById('nombre').value = club.nombre;
            document.getElementById('email').value = club.email;
            document.getElementById('direccion').value = club.direccion || '';
            document.getElementById('telefono').value = club.telefono || '';
            document.getElementById('presidente').value = club.presidente || '';
            document.getElementById('telefono_presidente').value = club.telefono_presidente || '';
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Actualizar Club';
            
            openModal('modalNuevoClub');
        }

        function eliminarClub(id, nombre) {
            document.getElementById('clubNombre').textContent = nombre;
            document.getElementById('eliminarId').value = id;
            openModal('modalEliminar');
        }

        function verDetallesClub(clubId, nombreClub) {
            clubActual = clubId;
            document.getElementById('detallesClubNombre').textContent = nombreClub;
            
            // Cargar equipos por defecto
            cargarEquipos(clubId);
            
            openModal('modalDetallesClub');
        }

        function cambiarTab(tab) {
            // Ocultar todos los tabs
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            
            // Mostrar tab seleccionado
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.classList.add('active');
            
            // Cargar contenido según el tab
            switch(tab) {
                case 'equipos':
                    cargarEquipos(clubActual);
                    break;
                case 'tecnicos':
                    cargarTecnicos(clubActual);
                    break;
                case 'jugadores':
                    cargarJugadores(clubActual);
                    break;
                case 'documentos':
                    cargarDocumentos(clubActual);
                    break;
            }
        }

        async function cargarEquipos(clubId) {
            try {
                const response = await fetch(`api/clubes.php?action=equipos&club_id=${clubId}`);
                const data = await response.json();
                
                if (data.success) {
                    mostrarEquipos(data.equipos);
                } else {
                    showNotification('Error al cargar equipos', 'error');
                }
            } catch (error) {
                showNotification('Error de conexión', 'error');
            }
        }

        async function cargarTecnicos(clubId) {
            try {
                const response = await fetch(`api/clubes.php?action=tecnicos&club_id=${clubId}`);
                const data = await response.json();
                
                if (data.success) {
                    mostrarTecnicos(data.tecnicos);
                } else {
                    showNotification('Error al cargar técnicos', 'error');
                }
            } catch (error) {
                showNotification('Error de conexión', 'error');
            }
        }

        async function cargarJugadores(clubId) {
            try {
                const response = await fetch(`api/clubes.php?action=jugadores&club_id=${clubId}`);
                const data = await response.json();
                
                if (data.success) {
                    mostrarJugadores(data.jugadores);
                } else {
                    showNotification('Error al cargar jugadores', 'error');
                }
            } catch (error) {
                showNotification('Error de conexión', 'error');
            }
        }

        function mostrarEquipos(equipos) {
            const container = document.getElementById('listaEquipos');
            
            if (equipos.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No hay equipos registrados para este club.</p></div>';
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Equipo</th><th>Categoría</th><th>Técnicos</th><th>Jugadores</th><th>Acciones</th></tr></thead><tbody>';
            
            equipos.forEach(equipo => {
                html += `
                    <tr>
                        <td><strong>${equipo.nombre}</strong></td>
                        <td>${equipo.categoria}</td>
                        <td><span class="badge badge-success">${equipo.total_tecnicos} técnicos</span></td>
                        <td><span class="badge badge-warning">${equipo.total_jugadores} jugadores</span></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="editarEquipo(${equipo.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="eliminarEquipo(${equipo.id})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function mostrarTecnicos(tecnicos) {
            const container = document.getElementById('listaTecnicos');
            
            if (tecnicos.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No hay técnicos registrados para este club.</p></div>';
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Nombre</th><th>DNI</th><th>Nivel</th><th>Equipo</th><th>Contacto</th><th>Acciones</th></tr></thead><tbody>';
            
            tecnicos.forEach(tecnico => {
                html += `
                    <tr>
                        <td><strong>${tecnico.nombre} ${tecnico.apellidos}</strong></td>
                        <td>${tecnico.dni}</td>
                        <td><span class="badge badge-info">${tecnico.nivel}</span></td>
                        <td>${tecnico.equipo_nombre}</td>
                        <td>
                            ${tecnico.email ? `<small>${tecnico.email}</small><br>` : ''}
                            ${tecnico.telefono ? `<small>${tecnico.telefono}</small>` : ''}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="editarTecnico(${tecnico.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="eliminarTecnico(${tecnico.id})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function mostrarJugadores(jugadores) {
            const container = document.getElementById('listaJugadores');
            
            if (jugadores.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No hay jugadores registrados para este club.</p></div>';
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Nombre</th><th>DNI</th><th>Fecha Nac.</th><th>Equipo</th><th>Contacto</th><th>Acciones</th></tr></thead><tbody>';
            
            jugadores.forEach(jugador => {
                const fechaNac = new Date(jugador.fecha_nacimiento).toLocaleDateString('es-ES');
                html += `
                    <tr>
                        <td><strong>${jugador.nombre} ${jugador.apellidos}</strong></td>
                        <td>${jugador.dni}</td>
                        <td>${fechaNac}</td>
                        <td>${jugador.equipo_nombre}</td>
                        <td>
                            ${jugador.email ? `<small>${jugador.email}</small><br>` : ''}
                            ${jugador.telefono ? `<small>${jugador.telefono}</small>` : ''}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="editarJugador(${jugador.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="eliminarJugador(${jugador.id})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        async function nuevoEquipo() {
            await cargarCategorias();
            document.getElementById('modalEquipoTitle').innerHTML = '<i class="fas fa-users"></i> Nuevo Equipo';
            document.getElementById('formEquipo').reset();
            document.getElementById('equipoId').value = '';
            document.getElementById('equipoClubId').value = clubActual;
            openModal('modalEquipo');
        }

        async function nuevoTecnico() {
            await cargarEquiposSelect('tecnicoEquipo');
            document.getElementById('modalTecnicoTitle').innerHTML = '<i class="fas fa-chalkboard-teacher"></i> Nuevo Técnico';
            document.getElementById('formTecnico').reset();
            document.getElementById('tecnicoId').value = '';
            openModal('modalTecnico');
        }

        async function nuevoJugador() {
            await cargarEquiposSelect('jugadorEquipo');
            document.getElementById('modalJugadorTitle').innerHTML = '<i class="fas fa-user-friends"></i> Nuevo Jugador';
            document.getElementById('formJugador').reset();
            document.getElementById('jugadorId').value = '';
            openModal('modalJugador');
        }

        async function cargarCategorias() {
            try {
                const response = await fetch('api/clubes.php?action=categorias');
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById('equipoCategoria');
                    select.innerHTML = '<option value="">Seleccionar categoría</option>';
                    
                    data.categorias.forEach(categoria => {
                        select.innerHTML += `<option value="${categoria.id}">${categoria.nombre}</option>`;
                    });
                }
            } catch (error) {
                showNotification('Error al cargar categorías', 'error');
            }
        }

        async function cargarEquiposSelect(selectId) {
            try {
                const response = await fetch(`api/clubes.php?action=equipos&club_id=${clubActual}`);
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById(selectId);
                    select.innerHTML = '<option value="">Seleccionar equipo</option>';
                    
                    data.equipos.forEach(equipo => {
                        select.innerHTML += `<option value="${equipo.id}">${equipo.nombre} - ${equipo.categoria}</option>`;
                    });
                }
            } catch (error) {
                showNotification('Error al cargar equipos', 'error');
            }
        }

        function editarEquipo(equipoId) {
            // Cargar categorías y luego los datos del equipo
            cargarCategorias().then(() => {
                fetch(`api/clubes.php?action=equipo&id=${equipoId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const equipo = data.equipo;
                            document.getElementById('modalEquipoTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Equipo';
                            document.getElementById('equipoId').value = equipo.id;
                            document.getElementById('equipoClubId').value = equipo.club_id;
                            document.getElementById('equipoNombre').value = equipo.nombre;
                            document.getElementById('equipoCategoria').value = equipo.categoria_id;
                            openModal('modalEquipo');
                        }
                    })
                    .catch(error => {
                        showNotification('Error al cargar datos del equipo', 'error');
                    });
            });
        }

        function eliminarEquipo(equipoId) {
            if (confirm('¿Estás seguro de que deseas eliminar este equipo? Esta acción no se puede deshacer.')) {
                fetch('api/clubes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_equipo',
                        id: equipoId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Equipo eliminado exitosamente', 'success');
                        cargarEquipos(clubActual);
                    } else {
                        showNotification(data.message || 'Error al eliminar equipo', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error de conexión', 'error');
                });
            }
        }

        function editarTecnico(tecnicoId) {
            // Implementar función de editar técnico
            showNotification('Función de editar técnico en desarrollo', 'info');
        }

        function eliminarTecnico(tecnicoId) {
            if (confirm('¿Estás seguro de que deseas eliminar este técnico?')) {
                fetch('api/clubes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_tecnico',
                        id: tecnicoId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Técnico eliminado exitosamente', 'success');
                        cargarTecnicos(clubActual);
                    } else {
                        showNotification(data.message || 'Error al eliminar técnico', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error de conexión', 'error');
                });
            }
        }

        function editarJugador(jugadorId) {
            // Implementar función de editar jugador
            showNotification('Función de editar jugador en desarrollo', 'info');
        }

        function eliminarJugador(jugadorId) {
            if (confirm('¿Estás seguro de que deseas eliminar este jugador?')) {
                fetch('api/clubes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_jugador',
                        id: jugadorId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Jugador eliminado exitosamente', 'success');
                        cargarJugadores(clubActual);
                    } else {
                        showNotification(data.message || 'Error al eliminar jugador', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error de conexión', 'error');
                });
            }
        }

        // Manejo de formularios
        // Event listener para formulario de equipos
        document.getElementById('formEquipo').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            console.log('Formulario de equipo enviado'); // Debug
            
            const formData = {
                action: document.getElementById('equipoId').value ? 'update_equipo' : 'create_equipo',
                id: document.getElementById('equipoId').value,
                club_id: clubActual,
                nombre: document.getElementById('equipoNombre').value,
                categoria_id: document.getElementById('equipoCategoria').value
            };
            
            console.log('Datos a enviar:', formData); // Debug
            
            try {
                const response = await fetch('api/clubes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                console.log('Respuesta recibida:', response.status); // Debug
                
                const data = await response.json();
                console.log('Datos de respuesta:', data); // Debug
                
                if (data.success) {
                    showNotification('Equipo guardado exitosamente', 'success');
                    closeModal('modalEquipo');
                    cargarEquipos(clubActual);
                } else {
                    showNotification(data.message || 'Error al guardar equipo', 'error');
                }
            } catch (error) {
                console.error('Error en fetch:', error); // Debug
                showNotification('Error de conexión', 'error');
            }
        });

        document.getElementById('formTecnico').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                action: document.getElementById('tecnicoId').value ? 'update_tecnico' : 'create_tecnico',
                id: document.getElementById('tecnicoId').value,
                equipo_id: document.getElementById('tecnicoEquipo').value,
                nombre: document.getElementById('tecnicoNombre').value,
                apellidos: document.getElementById('tecnicoApellidos').value,
                dni: document.getElementById('tecnicoDni').value,
                nivel: document.getElementById('tecnicoNivel').value,
                email: document.getElementById('tecnicoEmail').value,
                telefono: document.getElementById('tecnicoTelefono').value
            };
            
            try {
                const response = await fetch('api/clubes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Técnico guardado exitosamente', 'success');
                    closeModal('modalTecnico');
                    cargarTecnicos(clubActual);
                } else {
                    showNotification(data.message || 'Error al guardar técnico', 'error');
                }
            } catch (error) {
                showNotification('Error de conexión', 'error');
            }
        });

        document.getElementById('formJugador').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                action: document.getElementById('jugadorId').value ? 'update_jugador' : 'create_jugador',
                id: document.getElementById('jugadorId').value,
                equipo_id: document.getElementById('jugadorEquipo').value,
                nombre: document.getElementById('jugadorNombre').value,
                apellidos: document.getElementById('jugadorApellidos').value,
                dni: document.getElementById('jugadorDni').value,
                fecha_nacimiento: document.getElementById('jugadorFechaNacimiento').value,
                email: document.getElementById('jugadorEmail').value,
                telefono: document.getElementById('jugadorTelefono').value
            };
            
            try {
                const response = await fetch('api/clubes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Jugador guardado exitosamente', 'success');
                    closeModal('modalJugador');
                    cargarJugadores(clubActual);
                } else {
                    showNotification(data.message || 'Error al guardar jugador', 'error');
                }
            } catch (error) {
                showNotification('Error de conexión', 'error');
            }
        });

        // Resetear modal al cerrar
        document.getElementById('modalNuevoClub').addEventListener('hidden', function() {
            document.getElementById('formClub').reset();
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Nuevo Club';
            document.getElementById('modalTitle').style.color = 'white';
            document.getElementById('modalTitle').style.margin = '0';
            document.getElementById('modalTitle').style.display = 'flex';
            document.getElementById('modalTitle').style.alignItems = 'center';
            document.getElementById('modalTitle').style.gap = '10px';
            document.getElementById('formAction').value = 'create';
            document.getElementById('clubId').value = '';
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Guardar Club';
        });

        // Validación del formulario
        document.getElementById('formClub').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!nombre || !email) {
                e.preventDefault();
                showNotification('Nombre y email son obligatorios', 'error');
                return false;
            }
            
            if (!validateEmail(email)) {
                e.preventDefault();
                showNotification('Por favor, introduce un email válido', 'error');
                return false;
            }
        });

        // Funciones para gestor de archivos
        function gestorArchivos(clubId, nombreClub) {
            clubActual = clubId;
            document.getElementById('nombreClubArchivos').textContent = nombreClub;
            cargarDocumentosGestor(clubId);
            openModal('modalGestorArchivos');
        }

        function abrirModalSubirDocumento() {
            document.getElementById('clubIdDocumento').value = clubActual;
            document.getElementById('formSubirDocumento').reset();
            openModal('modalSubirDocumento');
        }

        function subirDocumento() {
            abrirModalSubirDocumento();
        }

        async function cargarDocumentos(clubId) {
            try {
                const response = await fetch(`api/documentos.php?action=list_documentos&club_id=${clubId}`);
                const data = await response.json();
                
                if (data.success) {
                    mostrarDocumentos(data.documentos);
                } else {
                    showNotification('Error al cargar documentos', 'error');
                }
            } catch (error) {
                showNotification('Error de conexión', 'error');
            }
        }

        async function cargarDocumentosGestor(clubId) {
            try {
                const response = await fetch(`api/documentos.php?action=list_documentos&club_id=${clubId}`);
                const data = await response.json();
                
                if (data.success) {
                    mostrarDocumentosGestor(data.documentos);
                } else {
                    showNotification('Error al cargar documentos', 'error');
                }
            } catch (error) {
                showNotification('Error de conexión', 'error');
            }
        }

        function mostrarDocumentos(documentos) {
            const container = document.getElementById('listaDocumentos');
            
            if (documentos.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No hay documentos subidos para este club.</p></div>';
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Documento</th><th>Archivo</th><th>Tamaño</th><th>Fecha Subida</th><th>Acciones</th></tr></thead><tbody>';
            
            documentos.forEach(doc => {
                const fechaSubida = new Date(doc.fecha_subida).toLocaleDateString('es-ES');
                const tamaño = formatFileSize(doc.tamaño_archivo);
                
                html += `
                    <tr>
                        <td><strong>${doc.nombre_documento}</strong></td>
                        <td>${doc.nombre_archivo}</td>
                        <td>${tamaño}</td>
                        <td>${fechaSubida}</td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="descargarDocumento(${doc.id})" title="Descargar">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="eliminarDocumento(${doc.id})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function mostrarDocumentosGestor(documentos) {
            const container = document.getElementById('listaDocumentosGestor');
            
            if (documentos.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <h4 class="mt-3">No hay documentos</h4>
                        <p class="text-muted">Comience subiendo el primer documento</p>
                        <button class="btn btn-primary" onclick="abrirModalSubirDocumento()">
                            <i class="fas fa-upload"></i> Subir Primer Documento
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="row">';
            
            documentos.forEach(doc => {
                const fechaSubida = new Date(doc.fecha_subida).toLocaleDateString('es-ES');
                const tamaño = formatFileSize(doc.tamaño_archivo);
                const icono = getFileIcon(doc.tipo_archivo);
                
                html += `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card document-card" style="border: 1px solid #ddd; border-radius: 8px;">
                            <div class="card-body" style="padding: 15px; text-align: center;">
                                <div style="font-size: 2.5rem; color: var(--primary-green); margin-bottom: 10px;">
                                    <i class="${icono}"></i>
                                </div>
                                <h6 style="margin-bottom: 10px; font-weight: 600;">${doc.nombre_documento}</h6>
                                <p style="margin: 5px 0; font-size: 0.9rem; color: #666;">${doc.nombre_archivo}</p>
                                <p style="margin: 5px 0; font-size: 0.8rem; color: #999;">${tamaño} • ${fechaSubida}</p>
                                <div style="margin-top: 15px;">
                                    <button class="btn btn-success btn-sm" onclick="descargarDocumento(${doc.id})" title="Descargar">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm ml-2" onclick="eliminarDocumento(${doc.id})" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        function getFileIcon(tipoArchivo) {
            const iconos = {
                'pdf': 'fas fa-file-pdf',
                'doc': 'fas fa-file-word',
                'docx': 'fas fa-file-word',
                'xls': 'fas fa-file-excel',
                'xlsx': 'fas fa-file-excel',
                'jpg': 'fas fa-file-image',
                'jpeg': 'fas fa-file-image',
                'png': 'fas fa-file-image',
                'txt': 'fas fa-file-alt'
            };
            
            return iconos[tipoArchivo.toLowerCase()] || 'fas fa-file';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function descargarDocumento(documentoId) {
            window.open(`api/documentos.php?action=download&documento_id=${documentoId}`, '_blank');
        }

        function eliminarDocumento(documentoId) {
            if (confirm('¿Estás seguro de que deseas eliminar este documento? Esta acción no se puede deshacer.')) {
                fetch('api/documentos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_documento&documento_id=${documentoId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Documento eliminado exitosamente', 'success');
                        cargarDocumentosGestor(clubActual);
                        if (document.getElementById('tab-documentos').classList.contains('active')) {
                            cargarDocumentos(clubActual);
                        }
                    } else {
                        showNotification(data.message || 'Error al eliminar documento', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error de conexión', 'error');
                });
            }
        }

        // Manejo del formulario de subir documento
        // Event listener para formulario de subir documento
        const formSubirDocumento = document.getElementById('formSubirDocumento');
        if (formSubirDocumento) {
            formSubirDocumento.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('action', 'upload_documento');
                formData.append('club_id', document.getElementById('clubIdDocumento').value);
                formData.append('nombre_documento', document.getElementById('nombreDocumento').value);
                formData.append('archivo', document.getElementById('archivoDocumento').files[0]);
                
                try {
                    const response = await fetch('api/documentos.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();                if (data.success) {
                    showNotification('Documento subido exitosamente', 'success');
                    closeModal('modalSubirDocumento');
                    cargarDocumentosGestor(clubActual);
                    if (document.getElementById('tab-documentos').classList.contains('active')) {
                        cargarDocumentos(clubActual);
                    }
                } else {
                    showNotification(data.message || 'Error al subir documento', 'error');
                }
            } catch (error) {
                showNotification('Error de conexión', 'error');
            }
        });
        } else {
            console.error('No se encontró el formulario formSubirDocumento');
        }
    </script>
    
    <script src="../assets/js/search-bar.js"></script>
    <script>
        // Inicializar búsqueda para la tabla de clubes
        document.addEventListener('DOMContentLoaded', function() {
            new TableSearchBar({
                searchInputId: 'searchInput',
                clearBtnId: 'searchClear',
                searchInfoId: 'searchInfo',
                searchResultsId: 'searchResults',
                totalCountId: 'total-count',
                tableSelector: 'tbody tr',
                columnsCount: 6,
                noResultsId: 'noResultsRow'
            });
        });
    </script>
</body>
</html>
