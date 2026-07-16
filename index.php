<?php
// index.php
// Dashboard principal del sistema

// Requerir autenticación
require_once 'config/auth.php';
requireLogin();

// Incluir conexión a la base de datos
require_once 'config/conexion.php';

$conn = getConnection();

// ========== ESTADÍSTICAS ==========

// Total clientes activos
$query = "SELECT COUNT(*) as total FROM clientes WHERE estado = 1";
$stmt = $conn->query($query);
$stats['clientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total conductores disponibles
$query = "SELECT COUNT(*) as total FROM conductores WHERE estado = 'disponible'";
$stmt = $conn->query($query);
$stats['conductores_disponibles'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total conductores totales
$query = "SELECT COUNT(*) as total FROM conductores WHERE estado != 'inactivo'";
$stmt = $conn->query($query);
$stats['conductores'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total vehículos activos
$query = "SELECT COUNT(*) as total FROM vehiculos WHERE estado = 'activo'";
$stmt = $conn->query($query);
$stats['vehiculos_activos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total vehículos totales
$query = "SELECT COUNT(*) as total FROM vehiculos";
$stmt = $conn->query($query);
$stats['vehiculos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Viajes del mes actual
$query = "SELECT COUNT(*) as total FROM viajes WHERE MONTH(fecha_viaje) = MONTH(CURRENT_DATE()) AND YEAR(fecha_viaje) = YEAR(CURRENT_DATE())";
$stmt = $conn->query($query);
$stats['viajes_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Viajes totales
$query = "SELECT COUNT(*) as total FROM viajes";
$stmt = $conn->query($query);
$stats['viajes_totales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Alertas activas
$query = "SELECT COUNT(*) as total FROM alertas WHERE estado = 'activa'";
$stmt = $conn->query($query);
$stats['alertas_activas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// ========== VIAJES RECIENTES ==========
$query = "SELECT v.*, c.nombre as cliente_nombre, co.nombre as conductor_nombre, 
                 ve.placa, m.nombre as material_nombre
          FROM viajes v 
          LEFT JOIN clientes c ON v.cliente_id = c.id 
          LEFT JOIN conductores co ON v.conductor_id = co.id 
          LEFT JOIN vehiculos ve ON v.vehiculo_id = ve.id 
          LEFT JOIN materiales m ON v.material_id = m.id
          ORDER BY v.created_at DESC LIMIT 10";
$stmt = $conn->query($query);
$viajes_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== DATOS PARA GRÁFICOS ==========

// Viajes por mes (últimos 6 meses)
$query = "SELECT DATE_FORMAT(fecha_viaje, '%Y-%m') as mes, COUNT(*) as total 
          FROM viajes 
          WHERE fecha_viaje >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(fecha_viaje, '%Y-%m')
          ORDER BY mes ASC";
$stmt = $conn->query($query);
$viajes_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meses = [];
$viajes_mensuales = [];
foreach ($viajes_por_mes as $item) {
    $meses[] = $item['mes'];
    $viajes_mensuales[] = $item['total'];
}

// Estado de viajes
$query = "SELECT estado, COUNT(*) as total FROM viajes GROUP BY estado";
$stmt = $conn->query($query);
$estado_viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top materiales transportados
$query = "SELECT m.nombre, SUM(v.peso) as total_peso 
          FROM viajes v 
          JOIN materiales m ON v.material_id = m.id 
          GROUP BY v.material_id 
          ORDER BY total_peso DESC LIMIT 5";
$stmt = $conn->query($query);
$top_materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Guardar datos en sesión para JavaScript
$_SESSION['alertas_pendientes'] = $stats['alertas_activas'];
?>
<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <!-- Título -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            <small class="text-muted fs-6">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></small>
        </h2>
        <div>
            <span class="badge bg-info me-2">
                <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d/m/Y'); ?>
            </span>
            <span class="badge bg-gradient">
                <i class="fas fa-user me-1"></i> <?php echo $_SESSION['user_rol']; ?>
            </span>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card text-center">
                <i class="fas fa-users"></i>
                <h3><?php echo $stats['clientes']; ?></h3>
                <p>Clientes Activos</p>
                <small class="text-muted">Total registrados</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card text-center">
                <i class="fas fa-id-card"></i>
                <h3><?php echo $stats['conductores']; ?> <small class="fs-6 text-success">(<?php echo $stats['conductores_disponibles']; ?> disp.)</small></h3>
                <p>Conductores</p>
                <small class="text-muted">Disponibles para trabajar</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card text-center">
                <i class="fas fa-truck"></i>
                <h3><?php echo $stats['vehiculos']; ?> <small class="fs-6 text-success">(<?php echo $stats['vehiculos_activos']; ?> act.)</small></h3>
                <p>Vehículos</p>
                <small class="text-muted">En operación</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card text-center">
                <i class="fas fa-route"></i>
                <h3><?php echo $stats['viajes_totales']; ?></h3>
                <p>Viajes Totales</p>
                <small class="text-muted"><?php echo $stats['viajes_mes']; ?> este mes</small>
            </div>
        </div>
    </div>
    
    <!-- Gráficos -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-3">
            <div class="chart-container">
                <h5><i class="fas fa-chart-line me-2"></i> Viajes por Mes</h5>
                <canvas id="viajesPorMesChart" height="250"></canvas>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="chart-container">
                <h5><i class="fas fa-chart-pie me-2"></i> Estado de Viajes</h5>
                <canvas id="estadoViajesChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Materiales y Alertas -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-3">
            <div class="chart-container">
                <h5><i class="fas fa-cubes me-2"></i> Top Materiales Transportados</h5>
                <canvas id="topMaterialesChart" height="250"></canvas>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card-glass p-3">
                <h5><i class="fas fa-bell me-2"></i> Alertas Recientes</h5>
                <?php
                $query = "SELECT * FROM alertas WHERE estado = 'activa' ORDER BY created_at DESC LIMIT 5";
                $stmt = $conn->query($query);
                $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (count($alertas) > 0): ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($alertas as $alerta): ?>
                            <div class="list-group-item bg-transparent text-light border-secondary">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge badge-<?php echo $alerta['nivel']; ?> me-2">
                                            <?php echo ucfirst($alerta['nivel']); ?>
                                        </span>
                                        <strong><?php echo htmlspecialchars($alerta['titulo']); ?></strong>
                                    </div>
                                    <small class="text-muted"><?php echo date('d/m H:i', strtotime($alerta['created_at'])); ?></small>
                                </div>
                                <p class="mb-0 small text-muted mt-1"><?php echo htmlspecialchars(substr($alerta['descripcion'], 0, 100)); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No hay alertas activas</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Viajes Recientes -->
    <div class="row">
        <div class="col-12">
            <div class="card-glass p-3">
                <h5 class="mb-3">
                    <i class="fas fa-history me-2"></i> Viajes Recientes
                    <a href="/MINERIA/modules/viajes/" class="btn btn-sm btn-gradient-outline float-end">Ver todos</a>
                </h5>
                <div class="table-responsive">
                    <table class="table table-glass">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Cliente</th>
                                <th>Conductor</th>
                                <th>Vehículo</th>
                                <th>Material</th>
                                <th>Peso (TN)</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($viajes_recientes as $viaje): ?>
                            <tr>
                                <td><strong><?php echo $viaje['codigo']; ?></strong></td>
                                <td><?php echo htmlspecialchars($viaje['cliente_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($viaje['conductor_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo $viaje['placa'] ?? 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($viaje['material_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($viaje['peso'], 2); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = match($viaje['estado']) {
                                        'completado' => 'success',
                                        'en_progreso' => 'warning',
                                        'pendiente' => 'info',
                                        'cancelado' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($viaje['estado']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($viaje['fecha_viaje'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($viajes_recientes)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No hay viajes registrados</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Datos para gráficos
const viajesPorMesData = {
    labels: <?php echo json_encode($meses); ?>,
    values: <?php echo json_encode($viajes_mensuales); ?>
};

const estadoViajesData = <?php echo json_encode($estado_viajes); ?>;

const topMaterialesData = {
    labels: <?php echo json_encode(array_column($top_materiales, 'nombre')); ?>,
    values: <?php echo json_encode(array_column($top_materiales, 'total_peso')); ?>
};
</script>

<?php include 'includes/footer.php'; ?>