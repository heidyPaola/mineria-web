<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_rol = $_SESSION['user_rol'] ?? '';

function canView($modulo) {
    global $user_rol;
    
    $permisos = [
        'dashboard' => ['admin', 'supervisor', 'operador', 'cliente'],
        'clientes' => ['admin', 'supervisor', 'operador'],
        'conductores' => ['admin', 'supervisor', 'operador'],
        'vehiculos' => ['admin', 'supervisor', 'operador'],
        'viajes' => ['admin', 'supervisor', 'operador', 'cliente'],
        'materiales' => ['admin', 'supervisor', 'operador'],
        'rutas' => ['admin', 'supervisor', 'operador'],
        'asignaciones' => ['admin', 'supervisor'],
        'alertas' => ['admin', 'supervisor', 'operador'],
        'auditoria' => ['admin'],
        'reportes' => ['admin', 'supervisor', 'operador'],
        'usuarios' => ['admin']
    ];
    
    return in_array($user_rol, $permisos[$modulo] ?? []);
}

$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>H&H MINERIA - Sistema de Gestión Minera</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo $isLoggedIn ? '/MINERIA/assets/css/style.css' : 'assets/css/style.css'; ?>">
</head>
<body>

<?php if ($isLoggedIn): ?>
<div class="wrapper">
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-hard-hat"></i>
                <h3>H&H MINERIA</h3>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="/MINERIA/index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <!-- Clientes -->
            <?php if (canView('clientes')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/clientes/">
                    <i class="fas fa-users"></i> Clientes
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Conductores -->
            <?php if (canView('conductores')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/conductores/">
                    <i class="fas fa-id-card"></i> Conductores
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Vehículos -->
            <?php if (canView('vehiculos')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/vehiculos/">
                    <i class="fas fa-truck"></i> Vehículos
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Viajes -->
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/viajes/">
                    <i class="fas fa-route"></i> Viajes
                </a>
            </li>
            
            <!-- Materiales -->
            <?php if (canView('materiales')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/materiales/">
                    <i class="fas fa-cubes"></i> Materiales
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Rutas -->
            <?php if (canView('rutas')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/rutas/">
                    <i class="fas fa-map-marked-alt"></i> Rutas
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Asignaciones -->
            <?php if (canView('asignaciones')): ?>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#menuAsignaciones">
                    <i class="fas fa-tasks"></i> Asignaciones <i class="fas fa-chevron-down float-end"></i>
                </a>
                <div class="collapse" id="menuAsignaciones">
                    <a class="nav-link ms-3" href="/MINERIA/modules/asignaciones/">
                        <i class="fas fa-list"></i> Listado
                    </a>
                    <a class="nav-link ms-3" href="/MINERIA/modules/asignaciones/calendario.php">
                        <i class="fas fa-calendar-alt"></i> Calendario
                    </a>
                    <a class="nav-link ms-3" href="/MINERIA/modules/asignaciones/reporte.php">
                        <i class="fas fa-chart-bar"></i> Reportes
                    </a>
                </div>
            </li>
            <?php endif; ?>
            
            <!-- Alertas -->
            <?php if (canView('alertas')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/alertas/">
                    <i class="fas fa-bell"></i> Alertas
                    <?php if (isset($_SESSION['alertas_pendientes']) && $_SESSION['alertas_pendientes'] > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $_SESSION['alertas_pendientes']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Auditoría -->
            <?php if (canView('auditoria')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/auditoria/">
                    <i class="fas fa-history"></i> Auditoría
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Reportes -->
            <?php if (canView('reportes')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/reportes/">
                    <i class="fas fa-chart-bar"></i> Reportes
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Usuarios -->
            <?php if (canView('usuarios')): ?>
            <li class="nav-item">
                <a class="nav-link" href="/MINERIA/modules/usuarios/">
                    <i class="fas fa-user-shield"></i> Usuarios
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item mt-4">
                <hr class="mx-3" style="border-color: rgba(255,255,255,0.1);">
            </li>
            
            <li class="nav-item">
                <div class="user-info text-center py-3">
                    <i class="fas fa-user-circle fa-2x mb-2" style="color: #f59e0b;"></i>
                    <p class="mb-0"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?></p>
                    <small class="text-muted"><?php echo ucfirst($_SESSION['user_rol'] ?? 'rol'); ?></small>
                </div>
            </li>
            
            <li class="nav-item">
                <a class="nav-link text-danger" href="/MINERIA/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="main-content">
<?php else: ?>
    <div class="container-fluid p-0">
<?php endif; ?> 