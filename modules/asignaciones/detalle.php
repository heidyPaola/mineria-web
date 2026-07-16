<?php
// modules/asignaciones/detalle.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    echo '<p class="text-center text-danger">ID no proporcionado</p>';
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

$query = "SELECT a.*, 
          v.placa, v.marca, v.modelo, v.capacidad,
          c.nombre as conductor_nombre, c.licencia, c.telefono as conductor_telefono
          FROM asignaciones a
          LEFT JOIN vehiculos v ON a.vehiculo_id = v.id
          LEFT JOIN conductores c ON a.conductor_id = c.id
          WHERE a.id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$a) {
    echo '<p class="text-center text-danger">Asignación no encontrada</p>';
    exit();
}

$dias = (strtotime($a['fecha_fin']) - strtotime($a['fecha_asignacion'])) / (60 * 60 * 24);
$dias_restantes = (strtotime($a['fecha_fin']) - time()) / (60 * 60 * 24);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Vehículo</label>
                <p class="mb-0"><strong><?php echo $a['placa']; ?></strong> - <?php echo $a['marca'] . ' ' . $a['modelo']; ?></p>
                <small>Capacidad: <?php echo number_format($a['capacidad'], 2); ?> TN</small>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Conductor</label>
                <p class="mb-0"><strong><?php echo htmlspecialchars($a['conductor_nombre']); ?></strong></p>
                <small>Licencia: <?php echo $a['licencia']; ?> | Tel: <?php echo $a['conductor_telefono']; ?></small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Estado</label>
                <p><span class="badge estado-<?php echo $a['estado']; ?>"><?php echo ucfirst($a['estado']); ?></span></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Duración</label>
                <p><?php echo ceil($dias); ?> días (<?php echo date('d/m/Y', strtotime($a['fecha_asignacion'])); ?> → <?php echo date('d/m/Y', strtotime($a['fecha_fin'])); ?>)</p>
                <?php if ($a['estado'] == 'activa'): ?>
                    <small><?php echo $dias_restantes > 0 ? ceil($dias_restantes) . ' días restantes' : 'Vence hoy'; ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($a['motivo']): ?>
    <hr class="border-secondary">
    <div class="mb-3">
        <label class="text-muted small">Motivo</label>
        <p><?php echo htmlspecialchars($a['motivo']); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($a['observaciones']): ?>
    <hr class="border-secondary">
    <div class="mb-3">
        <label class="text-muted small">Observaciones</label>
        <p><?php echo nl2br(htmlspecialchars($a['observaciones'])); ?></p>
    </div>
    <?php endif; ?>
</div>