<?php
// modules/materiales/detalle.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    echo '<p class="text-center text-danger">ID no proporcionado</p>';
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM materiales WHERE id = :id");
$stmt->execute([':id' => $id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$m) {
    echo '<p class="text-center text-danger">Material no encontrado</p>';
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) as viajes, SUM(peso) as peso FROM viajes WHERE material_id = :id");
$stmt->execute([':id' => $id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Nombre</label>
                <p class="mb-0"><strong><?php echo htmlspecialchars($m['nombre']); ?></strong></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Código / Código Barras</label>
                <p class="mb-0"><?php echo $m['codigo'] ?: 'N/A'; ?> | <?php echo $m['codigo_barras'] ?: 'N/A'; ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Categoría / Unidad</label>
                <p class="mb-0"><?php echo $m['categoria']; ?> | <?php echo $m['unidad_medida']; ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Ubicación / Proveedor</label>
                <p class="mb-0"><?php echo $m['ubicacion'] ?: 'N/A'; ?> | <?php echo $m['proveedor'] ?: 'N/A'; ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Stock Actual</label>
                <p><strong><?php echo number_format($m['stock_actual'], 2); ?> <?php echo $m['unidad_medida']; ?></strong>
                <?php if ($m['stock_actual'] <= $m['stock_minimo'] && $m['stock_minimo'] > 0): ?>
                    <span class="badge bg-danger">Stock Bajo</span>
                <?php endif; ?>
                </p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Stock Mínimo</label>
                <p><?php echo number_format($m['stock_minimo'], 2); ?> <?php echo $m['unidad_medida']; ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Precio Unitario</label>
                <p><strong>S/ <?php echo number_format($m['precio_unitario'], 2); ?></strong></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Última Compra</label>
                <p><?php echo $m['ultima_compra'] ? date('d/m/Y', strtotime($m['ultima_compra'])) : 'N/A'; ?>
                <?php if ($m['ultimo_precio_compra']): ?> - S/ <?php echo number_format($m['ultimo_precio_compra'], 2); ?><?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    
    <hr class="border-secondary">
    <h6 class="mb-3"><i class="fas fa-chart-line"></i> Estadísticas de Transporte</h6>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Viajes realizados:</strong> <?php echo $stats['viajes'] ?? 0; ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Total transportado:</strong> <?php echo number_format($stats['peso'] ?? 0, 2); ?> <?php echo $m['unidad_medida']; ?></p>
        </div>
    </div>
    
    <?php if ($m['notas']): ?>
    <hr class="border-secondary">
    <div class="mb-3">
        <label class="text-muted small">Notas / Observaciones</label>
        <p><?php echo nl2br(htmlspecialchars($m['notas'])); ?></p>
    </div>
    <?php endif; ?>
</div>