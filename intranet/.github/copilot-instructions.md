<!-- Use this file to provide workspace-specific custom instructions to Copilot. For more details, visit https://code.visualstudio.com/docs/copilot/copilot-customization#_use-a-githubcopilotinstructionsmd-file -->

# Instrucciones para GitHub Copilot - Intranet FEDEXVB

## Contexto del Proyecto
Este es un sistema de intranet para la Federación Extremeña de Voleibol (FEDEXVB) desarrollado con PHP, MySQL, HTML, CSS y JavaScript.

## Arquitectura del Sistema

### Tecnologías
- **Backend**: PHP 7.4+ con PDO para base de datos
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **Base de datos**: MySQL 5.7+
- **Iconos**: Font Awesome 6
- **Diseño**: Responsive con CSS Grid y Flexbox

### Estructura de Usuarios
El sistema maneja 3 tipos de usuarios:
1. **Administrador**: Gestión completa del sistema
2. **Árbitro**: Gestión de disponibilidad, consulta de partidos y liquidaciones
3. **Club**: Gestión de equipos, jugadores y consulta de información

### Paleta de Colores
```css
--primary-green: #2E7D32;
--light-green: #4CAF50;
--dark-green: #1B5E20;
--primary-black: #212121;
--primary-white: #FFFFFF;
--light-gray: #F5F5F5;
```

## Patrones de Código a Seguir

### PHP
- Usar PDO para todas las consultas a base de datos
- Implementar try-catch para manejo de errores
- Sanitizar todas las entradas con `sanitize_input()`
- Usar prepared statements siempre
- Incluir autenticación en cada página: `$auth->requireUserType('tipo')`

### Base de Datos
- Usar transacciones para operaciones múltiples
- Implementar soft delete con campo `activo`
- Usar nombres descriptivos para tablas y campos
- Implementar claves foráneas con CASCADE

### Frontend
- Seguir la estructura de clases CSS establecida
- Usar los componentes definidos (cards, buttons, modals, tables)
- Implementar validación tanto en cliente como servidor
- Usar Font Awesome para iconos consistentes

### JavaScript
- Usar la clase `FedexvbApp` como base
- Implementar funciones de utilidad globales
- Usar fetch API para peticiones AJAX
- Mostrar notificaciones con `showNotification()`

## Convenciones de Nomenclatura

### Archivos
- Páginas principales: `dashboard.php`, `usuarios.php`, etc.
- Incluir siempre: `auth.php`, `functions.php`, `database.php`
- CSS: usar kebab-case para clases
- JavaScript: usar camelCase para funciones

### Base de Datos
- Tablas: plural en español (`usuarios`, `partidos`)
- Campos: snake_case (`fecha_creacion`, `tipo_usuario`)
- IDs: siempre `id` como clave primaria
- Referencias: `tabla_id` (`usuario_id`, `equipo_id`)

### CSS
- Variables CSS para colores y espaciado
- Clases utilitarias: `.text-center`, `.mb-3`, etc.
- Componentes reutilizables: `.card`, `.btn`, `.table`
- Estados: `.active`, `.available`, `.selected`

## Funcionalidades Específicas

### Autenticación
```php
// Requerir login
$auth->requireLogin();

// Requerir tipo específico
$auth->requireUserType('administrador');

// Verificar tipo
if ($auth->getUserType() === 'arbitro') { ... }
```

### Gestión de Formularios
```php
// Sanitizar entrada
$email = sanitize_input($_POST['email']);

// Validar datos
if (!validate_email($email)) {
    $message = error_message('Email no válido');
}
```

### Notificaciones
```javascript
// Mostrar notificación
showNotification('Operación exitosa', 'success');
showNotification('Error en la operación', 'error');
```

### Modales
```javascript
// Abrir modal
openModal('modalId');

// Cerrar modal
closeModal('modalId');
```

## Estructura de Página Estándar

```php
<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireUserType('tipo_usuario');

$database = new Database();
$conn = $database->getConnection();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Título - FEDEXVB</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header común -->
    <!-- Sidebar común -->
    <!-- Contenido principal -->
    
    <script src="../assets/js/app.js"></script>
</body>
</html>
```

## Funcionalidades Pendientes de Implementar

1. **Sistema completo de partidos** (CRUD)
2. **Gestión de liquidaciones** para árbitros
3. **Sistema de archivos PDF** para noticias
4. **Gestión completa de equipos** y jugadores
5. **Sistema de notificaciones** en tiempo real
6. **Reportes y estadísticas** avanzadas

## Buenas Prácticas de Seguridad

- Nunca usar `$_GET`/`$_POST` directamente sin sanitizar
- Implementar CSRF tokens en formularios críticos
- Validar permisos en cada acción
- Usar HTTPS en producción
- Escapar output HTML con `htmlspecialchars()`

## Testing y Debugging

- Usar usuarios de prueba para cada tipo
- Probar responsive design en diferentes dispositivos
- Validar formularios con datos inválidos
- Verificar manejo de errores de base de datos

Sigue estos patrones y convenciones para mantener consistencia en el código y facilitar el mantenimiento del sistema.
