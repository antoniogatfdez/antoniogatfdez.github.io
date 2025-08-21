// Funciones principales de la intranet FEDEXVB
class FedexvbApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeComponents();
    }

    setupEventListeners() {
        // Menu toggle para móviles
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });
        }

        // Cerrar modales al hacer clic fuera
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal(e.target.id);
            }
        });

        // Confirmaciones de eliminación
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-delete')) {
                e.preventDefault();
                const message = e.target.getAttribute('data-message') || '¿Está seguro de que desea eliminar este elemento?';
                if (confirm(message)) {
                    window.location.href = e.target.href;
                }
            }
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    }

    initializeComponents() {
        this.initializeCalendar();
        this.initializeTables();
        this.initializeFormValidation();
    }

    // Funciones de Modal
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Calendario de disponibilidad
    initializeCalendar() {
        const calendar = document.getElementById('availability-calendar');
        if (!calendar) return;

        const currentDate = new Date();
        const currentMonth = currentDate.getMonth();
        const currentYear = currentDate.getFullYear();

        this.renderCalendar(currentMonth, currentYear);
    }

    renderCalendar(month, year) {
        const calendar = document.getElementById('availability-calendar');
        if (!calendar) return;

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const monthNames = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];

        let calendarHTML = `
            <div class="calendar">
                <div class="calendar-header">
                    <button onclick="app.previousMonth()" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h3>${monthNames[month]} ${year}</h3>
                    <button onclick="app.nextMonth()" class="btn btn-secondary">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="calendar-grid">
                    <div class="calendar-day-header">Dom</div>
                    <div class="calendar-day-header">Lun</div>
                    <div class="calendar-day-header">Mar</div>
                    <div class="calendar-day-header">Mié</div>
                    <div class="calendar-day-header">Jue</div>
                    <div class="calendar-day-header">Vie</div>
                    <div class="calendar-day-header">Sáb</div>
        `;

        // Días vacíos del mes anterior
        for (let i = 0; i < firstDay; i++) {
            calendarHTML += '<div class="calendar-day empty"></div>';
        }

        // Días del mes actual
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isAvailable = this.isDateAvailable(dateStr);
            const classNames = `calendar-day ${isAvailable ? 'available' : 'unavailable'}`;
            
            calendarHTML += `
                <div class="${classNames}" onclick="app.toggleAvailability('${dateStr}')">
                    <span class="day-number">${day}</span>
                    <span class="availability-status">
                        <i class="fas ${isAvailable ? 'fa-check' : 'fa-times'}"></i>
                    </span>
                </div>
            `;
        }

        calendarHTML += '</div></div>';
        calendar.innerHTML = calendarHTML;
    }

    isDateAvailable(dateStr) {
        // Aquí se consultaría la base de datos
        // Por ahora devuelve un valor aleatorio para demo
        return Math.random() > 0.3;
    }

    toggleAvailability(dateStr) {
        if (typeof toggleAvailabilityAjax === 'function') {
            toggleAvailabilityAjax(dateStr);
        }
    }

    previousMonth() {
        const calendar = document.getElementById('availability-calendar');
        const currentMonth = parseInt(calendar.getAttribute('data-month') || new Date().getMonth());
        const currentYear = parseInt(calendar.getAttribute('data-year') || new Date().getFullYear());
        
        let newMonth = currentMonth - 1;
        let newYear = currentYear;
        
        if (newMonth < 0) {
            newMonth = 11;
            newYear--;
        }
        
        calendar.setAttribute('data-month', newMonth);
        calendar.setAttribute('data-year', newYear);
        this.renderCalendar(newMonth, newYear);
    }

    nextMonth() {
        const calendar = document.getElementById('availability-calendar');
        const currentMonth = parseInt(calendar.getAttribute('data-month') || new Date().getMonth());
        const currentYear = parseInt(calendar.getAttribute('data-year') || new Date().getFullYear());
        
        let newMonth = currentMonth + 1;
        let newYear = currentYear;
        
        if (newMonth > 11) {
            newMonth = 0;
            newYear++;
        }
        
        calendar.setAttribute('data-month', newMonth);
        calendar.setAttribute('data-year', newYear);
        this.renderCalendar(newMonth, newYear);
    }

    // Inicializar tablas con funcionalidad de búsqueda y ordenación
    initializeTables() {
        const tables = document.querySelectorAll('.data-table');
        tables.forEach(table => {
            this.makeTableSortable(table);
            this.addTableSearch(table);
        });
    }

    makeTableSortable(table) {
        const headers = table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(table, header);
            });
        });
    }

    sortTable(table, header) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const column = header.cellIndex;
        const isAscending = header.classList.contains('sort-asc');

        rows.sort((a, b) => {
            const aText = a.cells[column].textContent.trim();
            const bText = b.cells[column].textContent.trim();
            
            if (isAscending) {
                return bText.localeCompare(aText);
            } else {
                return aText.localeCompare(bText);
            }
        });

        // Limpiar clases de ordenación
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });

        // Agregar clase de ordenación
        header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');

        // Reordenar filas
        rows.forEach(row => tbody.appendChild(row));
    }

    // Validación de formularios
    initializeFormValidation() {
        const forms = document.querySelectorAll('.validate-form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'Este campo es obligatorio');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }

            // Validaciones específicas
            if (field.type === 'email' && field.value && !this.isValidEmail(field.value)) {
                this.showFieldError(field, 'Ingrese un email válido');
                isValid = false;
            }

            if (field.classList.contains('dni') && field.value && !this.isValidDNI(field.value)) {
                this.showFieldError(field, 'DNI no válido');
                isValid = false;
            }
        });

        return isValid;
    }

    showFieldError(field, message) {
        field.classList.add('error');
        let errorDiv = field.parentNode.querySelector('.field-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'field-error text-danger';
            field.parentNode.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
    }

    clearFieldError(field) {
        field.classList.remove('error');
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidDNI(dni) {
        const dniRegex = /^\d{8}[A-Z]$/;
        return dniRegex.test(dni.toUpperCase());
    }

    // Funciones AJAX
    async makeRequest(url, options = {}) {
        try {
            const response = await fetch(url, {
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                body: options.body ? JSON.stringify(options.body) : null
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Error en la petición:', error);
            this.showNotification('Error en la comunicación con el servidor', 'error');
            return null;
        }
    }

    // Notificaciones
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification`;
        notification.innerHTML = `
            <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            ${message}
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
        `;

        // Agregar al container de notificaciones o al body
        const container = document.getElementById('notifications-container') || document.body;
        container.appendChild(notification);

        // Auto-remove después de 5 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Utilidades
    formatDate(date, format = 'DD/MM/YYYY') {
        if (!date) return '';
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        
        return format
            .replace('DD', day)
            .replace('MM', month)
            .replace('YYYY', year);
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Inicializar la aplicación inmediatamente
window.app = null;

// Función para inicializar cuando esté listo
function initApp() {
    if (!window.app) {
        window.app = new FedexvbApp();
    }
}

// Inicializar cuando el DOM esté listo, pero también intentar inmediatamente
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

// Funciones globales para compatibilidad - versión mejorada
function openModal(modalId) {
    if (window.app && window.app.openModal) {
        window.app.openModal(modalId);
    } else {
        // Fallback - abrir modal directamente
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }
}

function closeModal(modalId) {
    if (window.app && window.app.closeModal) {
        window.app.closeModal(modalId);
    } else {
        // Fallback - cerrar modal directamente
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
}

function showNotification(message, type = 'info') {
    if (window.app && window.app.showNotification) {
        window.app.showNotification(message, type);
    } else {
        // Fallback - crear notificación simple
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 15px; border-radius: 5px; margin-bottom: 10px;';
        
        const colors = {
            'success': '#d4edda',
            'error': '#f8d7da',
            'warning': '#fff3cd',
            'info': '#d1ecf1'
        };
        
        notification.style.backgroundColor = colors[type] || colors.info;
        notification.innerHTML = message;
        
        document.body.appendChild(notification);
        
        // Auto-remove después de 5 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
}
