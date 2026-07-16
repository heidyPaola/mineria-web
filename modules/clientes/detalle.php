<?php
// modules/clientes/detalle.php
// Carga el contenido del modal de detalle

require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    echo '<div class="text-center py-5 text-danger">ID no proporcionado</div>';
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

// Obtener datos del cliente
$query = "SELECT * FROM clientes WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    echo '<div class="text-center py-5 text-danger">Cliente no encontrado</div>';
    exit();
}

// Obtener viajes del cliente
$query = "SELECT v.*, co.nombre as conductor_nombre, ve.placa, m.nombre as material_nombre
          FROM viajes v
          LEFT JOIN conductores co ON v.conductor_id = co.id
          LEFT JOIN vehiculos ve ON v.vehiculo_id = ve.id
          LEFT JOIN materiales m ON v.material_id = m.id
          WHERE v.cliente_id = :id
          ORDER BY v.fecha_viaje DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$query = "SELECT 
          COUNT(*) as total_viajes,
          SUM(peso) as total_peso,
          AVG(peso) as promedio_peso,
          COUNT(DISTINCT conductor_id) as conductores_distintos
          FROM viajes WHERE cliente_id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h3><?php echo htmlspecialchars($cliente['nombre']); ?></h3>
            <p class="text-muted mb-0">
                <i class="fas fa-id-card me-1"></i> RUC: <?php echo $cliente['ruc']; ?>
                <?php if ($cliente['telefono']): ?>
                    | <i class="fas fa-phone me-1"></i> <?php echo $cliente['telefono']; ?>
                <?php endif; ?>
                <?php if ($cliente['email']): ?>
                    | <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($cliente['email']); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="text-end">
            <small class="text-muted">Cliente desde: <?php echo date('d/m/Y', strtotime($cliente['created_at'])); ?></small>
        </div>
    </div>
    
    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-2">
            <div class="stat-card-mini text-center">
                <i class="fas fa-route fa-2x mb-2" style="color: #f59e0b;"></i>
                <h4 class="mb-0"><?php echo $stats['total_viajes'] ?? 0; ?></h4>
                <small>Viajes Totales</small>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="stat-card-mini text-center">
                <i class="fas fa-weight-hanging fa-2x mb-2" style="color: #10b981;"></i>
                <h4 class="mb-0"><?php echo number_format($stats['total_peso'] ?? 0, 2); ?> TN</h4>
                <small>Toneladas Transportadas</small>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="stat-card-mini text-center">
                <i class="fas fa-chart-line fa-2x mb-2" style="color: #3b82f6;"></i>
                <h4 class="mb-0"><?php echo number_format($stats['promedio_peso'] ?? 0, 2); ?> TN</h4>
                <small>Promedio por Viaje</small>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="stat-card-mini text-center">
                <i class="fas fa-users fa-2x mb-2" style="color: #8b5cf6;"></i>
                <h4 class="mb-0"><?php echo $stats['conductores_distintos'] ?? 0; ?></h4>
                <small>Conductores Asignados</small>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <ul class="nav nav-tabs-custom mb-3" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
        <li class="nav-item">
            <a class="nav-link active" data-tab="info" href="#">
                <i class="fas fa-info-circle me-2"></i> Información General
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-tab="viajes" href="#">
                <i class="fas fa-truck me-2"></i> Viajes Realizados
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-tab="direccion" href="#">
                <i class="fas fa-map-marker-alt me-2"></i> Dirección
            </a>
        </li>
    </ul>
    
    <!-- Tab Información -->
    <div id="tab-info" class="tab-content-active">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="text-muted small">Nombre / Razón Social</label>
                    <p class="mb-0"><strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong></p>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">RUC</label>
                    <p class="mb-0"><code><?php echo $cliente['ruc']; ?></code></p>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Teléfono</label>
                    <p class="mb-0"><?php echo $cliente['telefono'] ?: '<span class="text-muted">No registrado</span>'; ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="text-muted small">Correo Electrónico</label>
                    <p class="mb-0"><?php echo $cliente['email'] ?: '<span class="text-muted">No registrado</span>'; ?></p>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Fecha de Registro</label>
                    <p class="mb-0"><?php echo date('d/m/Y H:i:s', strtotime($cliente['created_at'])); ?></p>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Última Actualización</label>
                    <p class="mb-0"><?php echo date('d/m/Y H:i:s', strtotime($cliente['updated_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tab Viajes -->
    <div id="tab-viajes" class="tab-content-active" style="display: none;">
        <?php if (empty($viajes)): ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-truck fa-3x mb-2 d-block"></i>
                Este cliente aún no tiene viajes registrados
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-glass table-sm">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Vehículo</th>
                            <th>Material</th>
                            <th>Peso (TN)</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viajes as $viaje): ?>
                        <tr>
                            <td><strong><?php echo $viaje['codigo']; ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($viaje['fecha_viaje'])); ?></td>
                            <td><?php echo htmlspecialchars($viaje['conductor_nombre'] ?? 'N/A'); ?></td>
                            <td><?php echo $viaje['placa'] ?? 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($viaje['material_nombre'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($viaje['peso'], 2); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($viaje['estado']) {
                                        'completado' => 'success',
                                        'en_progreso' => 'warning',
                                        'pendiente' => 'info',
                                        'cancelado' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($viaje['estado']); ?>
                                </span>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($viajes) >= 10): ?>
                <div class="text-center mt-3">
                    <a href="/MINERIA/modules/viajes/?cliente=<?php echo $id; ?>" class="btn btn-sm btn-gradient-outline">
                        Ver todos los viajes
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Tab Dirección -->
    <div id="tab-direccion" class="tab-content-active" style="display: none;">
        <?php if (empty($cliente['direccion'])): ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-map-marked-alt fa-3x mb-2 d-block"></i>
                No hay dirección registrada
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="text-muted small">Dirección Completa</label>
                        <p class="mb-3"><?php echo nl2br(htmlspecialchars($cliente['direccion'])); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-glass p-3 text-center">
                        <i class="fas fa-map-pin fa-2x mb-2" style="color: #f59e0b;"></i>
                        <p class="small text-muted mb-0">Ubicación referencial</p>
                        <button class="btn btn-sm btn-gradient-outline mt-2" onclick="copyToClipboard('<?php echo addslashes($cliente['direccion']); ?>')">
                            <i class="fas fa-copy"></i> Copiar dirección
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Tabs
document.querySelectorAll('[data-tab]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        const tabId = this.getAttribute('data-tab');
        
        document.querySelectorAll('[data-tab]').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        document.querySelectorAll('.tab-content-active').forEach(content => {
            content.style.display = 'none';
        });
        
        if (tabId === 'info') document.getElementById('tab-info').style.display = 'block';
        if (tabId === 'viajes') document.getElementById('tab-viajes').style.display = 'block';
        if (tabId === 'direccion') document.getElementById('tab-direccion').style.display = 'block';
    });
});

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copiado',
            text: 'Dirección copiada al portapapeles',
            timer: 1500,
            showConfirmButton: false
        });
    });
}
</script>