<?php
// modules/vehiculos/detalle.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    echo '<p class="text-center text-danger">ID no proporcionado</p>';
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM vehiculos WHERE id = :id");
$stmt->execute([':id' => $id]);
$v = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$v) {
    echo '<p class="text-center text-danger">Vehículo no encontrado</p>';
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) as viajes, SUM(peso) as peso FROM viajes WHERE vehiculo_id = :id");
$stmt->execute([':id' => $id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

function estadoDoc($fecha) {
    if (!$fecha) return '';
    return strtotime($fecha) < time() ? 'VENCIDO' : 'Vigente';
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Placa</label>
                <p class="mb-0"><strong><?php echo $v['placa']; ?></strong></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Marca / Modelo</label>
                <p class="mb-0"><strong><?php echo $v['marca'] . ' ' . $v['modelo']; ?></strong></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Año / Color</label>
                <p class="mb-0"><?php echo $v['año'] ?: 'N/A'; ?> | <?php echo $v['color'] ?: 'N/A'; ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Capacidad</label>
                <p class="mb-0"><?php echo number_format($v['capacidad'], 2); ?> Toneladas</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Estado</label>
                <p><span class="badge estado-<?php echo $v['estado']; ?>"><?php echo ucfirst($v['estado']); ?></span></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Motor / Chasis</label>
                <p class="mb-0">Motor: <?php echo $v['numero_motor'] ?: 'N/A'; ?></p>
                <p>Chasis: <?php echo $v['numero_chasis'] ?: 'N/A'; ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Kilometraje</label>
                <p><?php echo number_format($v['kilometraje'], 0); ?> km</p>
            </div>
        </div>
    </div>
    
    <hr class="border-secondary">
    <h6 class="mb-3"><i class="fas fa-file-alt"></i> Documentos</h6>
    <div class="row">
        <div class="col-md-4">
            <p><strong>SOAT:</strong> <?php echo $v['soat_vencimiento'] ? date('d/m/Y', strtotime($v['soat_vencimiento'])) : 'N/A'; ?>
            <?php if ($v['soat_vencimiento'] && strtotime($v['soat_vencimiento']) < time()): ?>
                <span class="badge bg-danger">VENCIDO</span>
            <?php endif; ?>
            </p>
        </div>
        <div class="col-md-4">
            <p><strong>Revisión Técnica:</strong> <?php echo $v['revision_tecnica'] ? date('d/m/Y', strtotime($v['revision_tecnica'])) : 'N/A'; ?>
            <?php if ($v['revision_tecnica'] && strtotime($v['revision_tecnica']) < time()): ?>
                <span class="badge bg-danger">VENCIDO</span>
            <?php endif; ?>
            </p>
        </div>
        <div class="col-md-4">
            <p><strong>Seguro:</strong> <?php echo $v['seguro_vencimiento'] ? date('d/m/Y', strtotime($v['seguro_vencimiento'])) : 'N/A'; ?>
            <?php if ($v['seguro_vencimiento'] && strtotime($v['seguro_vencimiento']) < time()): ?>
                <span class="badge bg-danger">VENCIDO</span>
            <?php endif; ?>
            </p>
        </div>
    </div>
    
    <hr class="border-secondary">
    <h6 class="mb-3"><i class="fas fa-tools"></i> Mantenimiento</h6>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Último Mantenimiento:</strong> <?php echo $v['ultimo_mantenimiento'] ? date('d/m/Y', strtotime($v['ultimo_mantenimiento'])) : 'N/A'; ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Próximo Mantenimiento:</strong> <?php echo $v['proximo_mantenimiento'] ? date('d/m/Y', strtotime($v['proximo_mantenimiento'])) : 'N/A'; ?></p>
        </div>
    </div>
    
    <hr class="border-secondary">
    <h6 class="mb-3"><i class="fas fa-chart-line"></i> Estadísticas</h6>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Viajes realizados:</strong> <?php echo $stats['viajes'] ?? 0; ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Toneladas transportadas:</strong> <?php echo number_format($stats['peso'] ?? 0, 2); ?> TN</p>
        </div>
    </div>
    
    <?php if ($v['observaciones']): ?>
    <hr class="border-secondary">
    <div class="mb-3">
        <label class="text-muted small">Observaciones</label>
        <p><?php echo nl2br(htmlspecialchars($v['observaciones'])); ?></p>
    </div>
    <?php endif; ?>
</div>