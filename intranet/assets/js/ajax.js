// Funciones específicas para AJAX de la intranet
async function toggleAvailabilityAjax(dateStr) {
    try {
        const formData = new FormData();
        formData.append('fecha', dateStr);
        
        const response = await fetch('disponibilidad.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Recargar calendario
            window.location.reload();
        } else {
            showNotification('Error al actualizar disponibilidad', 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Funciones para gestión de usuarios
function createUser(formData) {
    fetch('usuarios.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        document.body.innerHTML = html;
        showNotification('Usuario creado correctamente', 'success');
    })
    .catch(error => {
        showNotification('Error al crear usuario', 'error');
    });
}

// Funciones para validación de formularios
function validateUserForm(form) {
    const tipo = form.querySelector('[name="tipo_usuario"]').value;
    let isValid = true;
    
    if (!tipo) {
        showNotification('Debe seleccionar un tipo de usuario', 'error');
        return false;
    }
    
    // Validaciones específicas por tipo
    switch (tipo) {
        case 'administrador':
            isValid = validateRequiredFields(form, ['nombre', 'apellidos', 'email']);
            break;
        case 'arbitro':
            isValid = validateRequiredFields(form, ['nombre', 'apellidos', 'ciudad', 'email', 'licencia']);
            break;
        case 'club':
            isValid = validateRequiredFields(form, ['nombre_club', 'nombre_responsable', 'email']);
            break;
    }
    
    return isValid;
}

function validateRequiredFields(form, fields) {
    let isValid = true;
    
    fields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input || !input.value.trim()) {
            input?.classList.add('error');
            isValid = false;
        } else {
            input?.classList.remove('error');
        }
    });
    
    return isValid;
}

// Funciones para manejo de tablas
function exportTable(tableId) {
    const table = document.getElementById(tableId);
    const csv = tableToCSV(table);
    downloadCSV(csv, 'export.csv');
}

function tableToCSV(table) {
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            rowData.push(col.textContent.trim());
        });
        csv.push(rowData.join(','));
    });
    
    return csv.join('\n');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Funciones para drag and drop de archivos
function initFileUpload(dropZone, fileInput) {
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFileUpload(files[0]);
        }
    });
}

function handleFileUpload(file) {
    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    
    if (!allowedTypes.includes(file.type)) {
        showNotification('Tipo de archivo no permitido', 'error');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB
        showNotification('El archivo es demasiado grande (máximo 5MB)', 'error');
        return;
    }
    
    showNotification('Archivo válido: ' + file.name, 'success');
}

// Funciones de utilidad para fechas
function formatDateToSpanish(dateStr) {
    const date = new Date(dateStr);
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        weekday: 'long'
    };
    return date.toLocaleDateString('es-ES', options);
}

function isWeekend(dateStr) {
    const date = new Date(dateStr);
    const day = date.getDay();
    return day === 0 || day === 6; // Domingo o Sábado
}

// Funciones para notificaciones push (futuro)
function requestNotificationPermission() {
    if ('Notification' in window) {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showNotification('Notificaciones activadas', 'success');
            }
        });
    }
}

function showPushNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, {
            body: body,
            icon: '/assets/images/icon.png'
        });
    }
}

// Funciones para estadísticas y gráficos (futuro)
function createChart(canvasId, data, type = 'bar') {
    // Placeholder para futura implementación de gráficos
    console.log('Chart data:', data);
}

// Funciones para búsqueda avanzada
function advancedSearch(query, filters = {}) {
    const searchParams = new URLSearchParams();
    searchParams.append('q', query);
    
    Object.keys(filters).forEach(key => {
        if (filters[key]) {
            searchParams.append(key, filters[key]);
        }
    });
    
    fetch(`search.php?${searchParams.toString()}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data);
        });
}

function displaySearchResults(results) {
    const container = document.getElementById('search-results');
    container.innerHTML = '';
    
    if (results.length === 0) {
        container.innerHTML = '<p>No se encontraron resultados</p>';
        return;
    }
    
    results.forEach(result => {
        const div = document.createElement('div');
        div.className = 'search-result';
        div.innerHTML = `
            <h4>${result.title}</h4>
            <p>${result.description}</p>
            <a href="${result.url}">Ver más</a>
        `;
        container.appendChild(div);
    });
}
