<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireUserType('arbitro');

$database = new Database();
$conn = $database->getConnection();
$message = '';

// Obtener ID del árbitro
$query = "SELECT id FROM arbitros WHERE usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$arbitro_id = $stmt->fetchColumn();

if (!$arbitro_id) {
    die('Error: No se encontró el árbitro asociado a este usuario. Contacte al administrador.');
}

// Procesar cambio de disponibilidad
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    $observaciones = isset($_POST['observaciones']) ? sanitize_input($_POST['observaciones']) : '';
    
    // Debug mejorado
    error_log("=== DEBUG DISPONIBILIDAD ===");
    error_log("POST datos recibidos:");
    error_log("- fecha: $fecha");
    error_log("- disponible: $disponible");
    error_log("- observaciones: $observaciones");
    error_log("- arbitro_id: $arbitro_id");
    error_log("- Fecha de hoy: " . date('Y-m-d'));
    error_log("=============================");
    
    try {
        $query = "INSERT INTO disponibilidad_arbitros (arbitro_id, fecha, disponible, observaciones) 
                  VALUES (?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE disponible = VALUES(disponible), observaciones = VALUES(observaciones)";
        $stmt = $conn->prepare($query);
        $result = $stmt->execute([$arbitro_id, $fecha, $disponible, $observaciones]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Disponibilidad actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar disponibilidad']);
        }
        exit();
    } catch (Exception $e) {
        error_log("Error en disponibilidad: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Obtener disponibilidad actual del mes
$currentMonth = $_GET['month'] ?? date('Y-m');
$firstDay = $currentMonth . '-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

$query = "SELECT fecha, disponible, observaciones FROM disponibilidad_arbitros 
          WHERE arbitro_id = ? AND fecha BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->execute([$arbitro_id, $firstDay, $lastDay]);
$disponibilidad = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Disponibilidad - FEDEXVB</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .availability-calendar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin: 20px 0;
        }
        
        .calendar-header {
            background: linear-gradient(135deg, var(--primary-green), var(--light-green));
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .calendar-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .calendar-navigation {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .calendar-navigation .btn {
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .calendar-navigation .btn:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border-top: 1px solid #e0e0e0;
        }
        
        #calendar-days {
            display: contents;
        }
        
        .calendar-day-header {
            background: var(--primary-green);
            color: white;
            padding: 18px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        
        .calendar-day-header:last-child {
            border-right: none;
        }
        
        .calendar-day {
            min-height: 90px;
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 12px 8px;
            position: relative;
            background: white;
        }
        
        .calendar-day:nth-child(7n) {
            border-right: none;
        }
        
        .calendar-day:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1;
        }
        
        .calendar-day.other-month {
            color: #bbb;
            background: #fafafa;
            cursor: not-allowed;
        }
        
        .calendar-day.other-month:hover {
            transform: none;
            box-shadow: none;
            background: #fafafa;
        }
        
        .calendar-day.available {
            background: linear-gradient(135deg, #e8f5e8, #f1f8e9);
            color: var(--dark-green);
            border-left: 4px solid var(--light-green);
        }
        
        .calendar-day.available:hover {
            background: linear-gradient(135deg, #dcedc8, #e8f5e8);
            transform: translateY(-2px);
        }
        
        .calendar-day.unavailable {
            background: linear-gradient(135deg, #ffebee, #fce4ec);
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        
        .calendar-day.unavailable:hover {
            background: linear-gradient(135deg, #ffcdd2, #ffebee);
            transform: translateY(-2px);
        }
        
        .calendar-day.today {
            border: 2px solid var(--primary-green);
            font-weight: bold;
        }
        
        .day-number {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .availability-icon {
            font-size: 1.6rem;
            opacity: 0.8;
        }
        
        .calendar-day.available .availability-icon {
            color: var(--light-green);
        }
        
        .calendar-day.unavailable .availability-icon {
            color: #f44336;
        }
        
        /* Estilo para el icono de observaciones */
        .calendar-day .fas.fa-edit {
            color: var(--primary-green);
            opacity: 0.8;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 40px;
            padding: 25px;
            background: linear-gradient(to right, #f8f9fa, #e9ecef, #f8f9fa);
            border-top: 1px solid #e0e0e0;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
            .legend-color {
                width: 24px;
                height: 24px;
                border-radius: 6px;
                border: 2px solid rgba(0,0,0,0.1);
            }
            
            /* Estilos para modal de observaciones */
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .modal-content {
                background: white;
                border-radius: 8px;
                width: 90%;
                max-width: 500px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            }
            
            .modal-header {
                background: var(--primary-green);
                color: white;
                padding: 20px;
                border-radius: 8px 8px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .modal-header h3 {
                margin: 0;
                font-size: 1.2rem;
            }
            
            .modal-header .close {
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 5px;
                border-radius: 4px;
                transition: background 0.2s;
            }
            
            .modal-header .close:hover {
                background: rgba(255,255,255,0.2);
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-body p {
                margin-bottom: 15px;
                color: #666;
            }
            
            .modal-body textarea {
                width: 100%;
                border: 2px solid #e0e0e0;
                border-radius: 6px;
                padding: 12px;
                font-size: 14px;
                line-height: 1.4;
                resize: vertical;
                min-height: 100px;
            }
            
            .modal-body textarea:focus {
                outline: none;
                border-color: var(--primary-green);
                box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
            }
            
            .modal-footer {
                padding: 15px 20px;
                border-top: 1px solid #e0e0e0;
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }
            
            .form-control {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }        /* Responsive */
        @media (max-width: 768px) {
            .calendar-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .calendar-navigation {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .calendar-day {
                min-height: 70px;
                padding: 8px 4px;
            }
            
            .day-number {
                font-size: 1.1rem;
            }
            
            .availability-icon {
                font-size: 1.3rem;
            }
            
            .legend {
                flex-direction: column;
                gap: 15px;
                align-items: center;
            }
        }
        
        @media (max-width: 480px) {
            .calendar-day-header {
                padding: 12px 5px;
                font-size: 0.8rem;
            }
            
            .calendar-day {
                min-height: 60px;
            }
            
            .day-number {
                font-size: 1rem;
            }
            
            .availability-icon {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-volleyball-ball"></i>
                    <span>FEDEXVB - Árbitro</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <div class="user-name"><?php echo $_SESSION['user_name'] . ' ' . $_SESSION['user_lastname']; ?></div>
                        <div class="user-role">Árbitro</div>
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
            <li><a href="disponibilidad.php" class="active"><i class="fas fa-calendar-check"></i> Mi Disponibilidad</a></li>
            <li><a href="partidos.php"><i class="fa-solid fa-globe"></i> Mis Partidos</a></li>
            <li><a href="liquidaciones.php"><i class="fas fa-file-invoice-dollar"></i> Mis Liquidaciones</a></li>
            <li><a href="arbitros.php"><i class="fas fa-users"></i> Lista de Árbitros</a></li>
            <li><a href="perfil.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
        </ul>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-calendar-check"></i> Mi Disponibilidad</h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i> <a href="dashboard.php">Inicio</a> / Mi Disponibilidad
            </div>
        </div>

        <!--
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Instrucciones
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p><strong>Cómo gestionar tu disponibilidad:</strong></p>
                    <ul class="mb-0">
                        <li>Haz clic en cualquier día del calendario para cambiar tu disponibilidad</li>
                        <li><span style="color: var(--success);"><i class="fas fa-check"></i> Verde</span> = Disponible para arbitrar</li>
                        <li><span style="color: var(--error);"><i class="fas fa-times"></i> Rojo</span> = No disponible</li>
                        <li>Por defecto, todos los días están marcados como no disponibles</li>
                        <li>Es importante mantener actualizada tu disponibilidad para recibir asignaciones</li>
                    </ul>
                </div>
            </div>
        </div>
        -->

        <div class="availability-calendar">
            <div class="calendar-header">
                <h2 id="month-year"></h2>
                <div class="calendar-navigation">
                    <button onclick="previousMonth()" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </button>
                    <button onclick="currentMonth()" class="btn btn-secondary">
                        <i class="fas fa-calendar-day"></i> Hoy
                    </button>
                    <button onclick="nextMonth()" class="btn btn-secondary">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="calendar-grid">
                <!-- Headers de días -->
                <div class="calendar-day-header">Lun</div>
                <div class="calendar-day-header">Mar</div>
                <div class="calendar-day-header">Mié</div>
                <div class="calendar-day-header">Jue</div>
                <div class="calendar-day-header">Vie</div>
                <div class="calendar-day-header">Sáb</div>
                <div class="calendar-day-header">Dom</div>
                
                <!-- Días del calendario -->
                <div id="calendar-days"></div>
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #e8f5e8, #f1f8e9); border-left: 4px solid var(--light-green);"></div>
                    <span><i class="fas fa-check" style="color: var(--light-green);"></i> Disponible</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #ffebee, #fce4ec); border-left: 4px solid #f44336;"></div>
                    <span><i class="fas fa-times" style="color: #f44336;"></i> No disponible</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: white; border: 2px solid #e0e0e0;"></div>
                    <span><i class="fas fa-question" style="color: #999;"></i> Sin definir (no disponible por defecto)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: white; border: 2px solid var(--primary-green);"></div>
                    <span><i class="fas fa-calendar-day" style="color: var(--primary-green);"></i> Día actual</span>
                </div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-lightning-bolt"></i> Acciones Rápidas
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button onclick="setAllAvailable()" class="btn btn-success">
                        <i class="fas fa-check-double"></i> Marcar todo como disponible
                    </button>
                    <button onclick="setWeekendsUnavailable()" class="btn btn-warning">
                        <i class="fas fa-calendar-minus"></i> Fines de semana no disponibles
                    </button>
                    <button onclick="setWeekdaysUnavailable()" class="btn btn-warning">
                        <i class="fas fa-calendar-times"></i> Entre semana no disponible
                    </button>
                    <button onclick="clearMonth()" class="btn btn-secondary">
                        <i class="fas fa-eraser"></i> Limpiar mes
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/app.js"></script>
    <script>
        // Sincronizar fecha JavaScript con el mes cargado por PHP
        <?php 
        echo "let currentDate = new Date('$currentMonth-01');";
        ?>
        let disponibilidadData = {};
        
        // Convertir los datos PHP a formato JavaScript
        <?php 
        echo "disponibilidadData = {";
        foreach($disponibilidad as $disp) {
            echo "'{$disp['fecha']}': {disponible: '{$disp['disponible']}', observaciones: " . json_encode($disp['observaciones']) . "},";
        }
        echo "};";
        ?>
        
        const monthNames = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];

        // Función auxiliar para formatear fecha en formato YYYY-MM-DD sin problemas de zona horaria
        function formatDateString(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const today = new Date();
            
            document.getElementById('month-year').textContent = `${monthNames[month]} ${year}`;
            
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDate = new Date(firstDay);
            
            // Ajustar para que la semana empiece en Lunes (1) en lugar de Domingo (0)
            let dayOfWeek = firstDay.getDay();
            dayOfWeek = dayOfWeek === 0 ? 6 : dayOfWeek - 1; // Convertir domingo (0) a 6, y el resto restar 1
            startDate.setDate(startDate.getDate() - dayOfWeek);
            
            const calendarDays = document.getElementById('calendar-days');
            calendarDays.innerHTML = '';
            
            const currentDateObj = new Date(startDate);
            
            for (let i = 0; i < 42; i++) {
                const dayDiv = document.createElement('div');
                dayDiv.className = 'calendar-day';
                
                const dayNumber = currentDateObj.getDate();
                const isCurrentMonth = currentDateObj.getMonth() === month;
                const dateStr = formatDateString(currentDateObj);
                const isToday = currentDateObj.toDateString() === today.toDateString();
                
                dayDiv.setAttribute('data-date', dateStr);
                
                if (!isCurrentMonth) {
                    dayDiv.classList.add('other-month');
                }
                
                if (isToday) {
                    dayDiv.classList.add('today');
                }
                
                const disponibilidadInfo = disponibilidadData[dateStr];
                const disponible = disponibilidadInfo ? disponibilidadInfo.disponible : null;
                const observaciones = disponibilidadInfo ? disponibilidadInfo.observaciones : '';
                
                if (disponible === '1') {
                    dayDiv.classList.add('available');
                } else if (disponible === '0') {
                    dayDiv.classList.add('unavailable');
                }
                
                let iconHtml = '';
                if (disponible === '1') {
                    iconHtml = '<i class="fas fa-check"></i>';
                    if (observaciones && observaciones.trim() !== '') {
                        iconHtml += '<i class="fas fa-edit" style="margin-left: 5px; font-size: 0.8em; opacity: 0.7;" title="Tiene observaciones"></i>';
                    }
                } else if (disponible === '0') {
                    iconHtml = '<i class="fas fa-times"></i>';
                } else {
                    iconHtml = '<i class="fas fa-question" style="opacity: 0.3;"></i>';
                }
                
                dayDiv.innerHTML = `
                    <div class="day-number">${dayNumber}</div>
                    <div class="availability-icon">${iconHtml}</div>
                `;
                
                if (isCurrentMonth) {
                    dayDiv.onclick = () => toggleAvailability(dateStr, dayDiv);
                    const statusText = disponible === '1' ? 'Disponible' : disponible === '0' ? 'No disponible' : 'Click para cambiar';
                    const obsText = observaciones && observaciones.trim() !== '' ? ` - Obs: ${observaciones}` : '';
                    dayDiv.title = `${dayNumber} de ${monthNames[month]} - ${statusText}${obsText}`;
                } else {
                    dayDiv.title = 'Día de otro mes';
                }
                
                calendarDays.appendChild(dayDiv);
                currentDateObj.setDate(currentDateObj.getDate() + 1);
            }
        }

        function toggleAvailability(dateStr, dayDiv) {
            console.log('toggleAvailability llamado con:', dateStr);
            console.log('Elemento dayDiv:', dayDiv);
            console.log('Fecha del elemento:', dayDiv.getAttribute('data-date'));
            
            const disponibilidadInfo = disponibilidadData[dateStr] || {};
            const currentState = disponibilidadInfo.disponible || null;
            const newState = currentState === '1' ? '0' : '1';
            
            console.log('Estado actual:', currentState, 'Nuevo estado:', newState);
            
            // Si se está marcando como disponible, mostrar modal para observaciones
            if (newState === '1') {
                showObservacionesModal(dateStr, dayDiv, disponibilidadInfo.observaciones || '');
            } else {
                // Si se está marcando como no disponible, no pedir observaciones
                updateAvailability(dateStr, dayDiv, newState, '');
            }
        }
        
        function showObservacionesModal(dateStr, dayDiv, currentObservaciones) {
            console.log('showObservacionesModal llamado:', dateStr, currentObservaciones);
            
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'block';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-edit"></i> Observaciones para ${formatDate(dateStr)}</h3>
                        <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <p>Puedes agregar observaciones opcionales para este día (ej: horarios preferidos, limitaciones, etc.):</p>
                        <textarea id="observacionesText" class="form-control" rows="4" placeholder="Ejemplo: Disponible solo por la mañana, Prefiero partidos locales, etc.">${currentObservaciones}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancelar</button>
                        <button type="button" class="btn btn-success" onclick="saveObservaciones('${dateStr}', this)">
                            <i class="fas fa-check"></i> Marcar como Disponible
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            document.getElementById('observacionesText').focus();
            
            console.log('Modal agregado al DOM');
        }
        
        function saveObservaciones(dateStr, button) {
            console.log('saveObservaciones llamado:', dateStr);
            
            const observaciones = document.getElementById('observacionesText').value.trim();
            console.log('Observaciones:', observaciones);
            
            // Buscar el dayDiv por fecha
            const dayDiv = Array.from(document.querySelectorAll('.calendar-day')).find(div => 
                div.getAttribute('data-date') === dateStr
            );
            
            console.log('dayDiv encontrado:', dayDiv);
            
            if (!dayDiv) {
                console.error('No se encontró el elemento del día para la fecha:', dateStr);
                button.closest('.modal').remove();
                return;
            }
            
            // Cerrar modal
            button.closest('.modal').remove();
            
            // Actualizar disponibilidad
            updateAvailability(dateStr, dayDiv, '1', observaciones);
        }
        
        function updateAvailability(dateStr, dayDiv, newState, observaciones) {
            console.log('=== updateAvailability ===');
            console.log('Fecha (dateStr):', dateStr);
            console.log('Nuevo estado:', newState);
            console.log('Observaciones:', observaciones);
            console.log('DayDiv data-date:', dayDiv.getAttribute('data-date'));
            
            const formData = new FormData();
            formData.append('fecha', dateStr);
            if (newState === '1') {
                formData.append('disponible', '1');
            }
            if (observaciones) {
                formData.append('observaciones', observaciones);
            }
            
            console.log('FormData enviado:');
            for (let [key, value] of formData.entries()) {
                console.log(key, ':', value);
            }
            
            // Mostrar loading
            const originalIcon = dayDiv.querySelector('.availability-icon');
            originalIcon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Respuesta del servidor:', response);
                return response.json();
            })
            .then(data => {
                console.log('Datos recibidos:', data);
                if (data.success) {
                    // Actualizar datos locales
                    disponibilidadData[dateStr] = {
                        disponible: newState,
                        observaciones: observaciones
                    };
                    
                    // Actualizar visualmente
                    dayDiv.className = 'calendar-day';
                    dayDiv.setAttribute('data-date', dateStr);
                    
                    // Mantener la clase today si es hoy
                    const today = new Date();
                    const dayDate = new Date(dateStr + 'T12:00:00'); // Usar mediodía para evitar problemas de zona horaria
                    if (dayDate.toDateString() === today.toDateString()) {
                        dayDiv.classList.add('today');
                    }
                    
                    if (newState === '1') {
                        dayDiv.classList.add('available');
                    } else {
                        dayDiv.classList.add('unavailable');
                    }
                    
                    // Actualizar iconos
                    const icon = dayDiv.querySelector('.availability-icon');
                    let iconHtml = '';
                    if (newState === '1') {
                        iconHtml = '<i class="fas fa-check"></i>';
                        if (observaciones && observaciones.trim() !== '') {
                            iconHtml += '<i class="fas fa-edit" style="margin-left: 5px; font-size: 0.8em; opacity: 0.7;" title="Tiene observaciones"></i>';
                        }
                    } else {
                        iconHtml = '<i class="fas fa-times"></i>';
                    }
                    icon.innerHTML = iconHtml;
                    
                    // Actualizar tooltip
                    const statusText = newState === '1' ? 'Disponible' : 'No disponible';
                    const obsText = observaciones && observaciones.trim() !== '' ? ` - Obs: ${observaciones}` : '';
                    dayDiv.title = `${dayDiv.querySelector('.day-number').textContent} de ${monthNames[currentDate.getMonth()]} - ${statusText}${obsText}`;
                    
                    showNotification(
                        `Disponibilidad ${newState === '1' ? 'activada' : 'desactivada'} para ${formatDate(dateStr)}`,
                        'success'
                    );
                } else {
                    console.error('Error del servidor:', data.message);
                    showNotification('Error al actualizar disponibilidad: ' + (data.message || 'Error desconocido'), 'error');
                    // Restaurar icono original
                    renderCalendar();
                }
            })
            .catch(error => {
                console.error('Error de red:', error);
                showNotification('Error de conexión', 'error');
                // Restaurar icono original
                renderCalendar();
            });
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr + 'T12:00:00'); // Usar mediodía para evitar problemas de zona horaria
            const day = date.getDate();
            const month = monthNames[date.getMonth()];
            return `${day} de ${month}`;
        }

        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            loadMonth();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            loadMonth();
        }

        function currentMonth() {
            currentDate = new Date();
            loadMonth();
        }

        function loadMonth() {
            const year = currentDate.getFullYear();
            const month = String(currentDate.getMonth() + 1).padStart(2, '0');
            const monthStr = `${year}-${month}`;
            window.location.href = `?month=${monthStr}`;
        }

        function setAllAvailable() {
            if (confirm('¿Marcar todos los días del mes como disponibles?')) {
                updateMonthAvailability(true);
            }
        }

        function setWeekendsUnavailable() {
            if (confirm('¿Marcar todos los fines de semana como no disponibles?')) {
                updateWeekendsAvailability(false);
            }
        }

        function setWeekdaysUnavailable() {
            if (confirm('¿Marcar todos los días entre semana como no disponibles?')) {
                updateWeekdaysAvailability(false);
            }
        }

        function clearMonth() {
            if (confirm('¿Limpiar toda la disponibilidad del mes?')) {
                updateMonthAvailability(null);
            }
        }

        function updateMonthAvailability(available) {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            showNotification('Actualizando disponibilidad del mes...', 'info');
            
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dateStr = formatDateString(date);
                
                const formData = new FormData();
                formData.append('fecha', dateStr);
                if (available !== null && available) {
                    formData.append('disponible', '1');
                }
                // No enviamos observaciones en las acciones masivas
                
                fetch('', {
                    method: 'POST',
                    body: formData
                });
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        function updateWeekendsAvailability(available) {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            showNotification('Actualizando fines de semana...', 'info');
            
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dayOfWeek = date.getDay();
                
                if (dayOfWeek === 0 || dayOfWeek === 6) { // Domingo (0) o Sábado (6)
                    const dateStr = formatDateString(date);
                    
                    const formData = new FormData();
                    formData.append('fecha', dateStr);
                    if (available) {
                        formData.append('disponible', '1');
                    }
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    });
                }
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        function updateWeekdaysAvailability(available) {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            showNotification('Actualizando días entre semana...', 'info');
            
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dayOfWeek = date.getDay();
                
                if (dayOfWeek >= 1 && dayOfWeek <= 5) { // Lunes a Viernes
                    const dateStr = formatDateString(date);
                    
                    const formData = new FormData();
                    formData.append('fecha', dateStr);
                    if (available) {
                        formData.append('disponible', '1');
                    }
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    });
                }
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        // Inicializar calendario
        document.addEventListener('DOMContentLoaded', function() {
            renderCalendar();
        });
    </script>
</body>
</html>
