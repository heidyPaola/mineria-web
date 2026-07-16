<?php
// modules/alertas/detalle.php
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
          v.codigo as viaje_codigo, v.estado as viaje_estado,
          u.nombre as usuario_creo_nombre,
          u2.nombre as usuario_resolvio_nombre
          FROM alertas a
          LEFT JOIN viajes v ON a.viaje_id = v.id
          LEFT JOIN usuarios u ON a.usuario_creo = u.id
          LEFT JOIN usuarios u2 ON a.usuario_resolvio = u2.id
          WHERE a.id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$a) {
    echo '<p class="text-center text-danger">Alerta no encontrada</p>';
    exit();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Nivel</label>
                <p><span class="nivel-<?php echo $a['nivel']; ?>"><?php echo ucfirst($a['nivel']); ?></span></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Categoría</label>
                <p><span class="categoria-badge"><?php echo $a['categoria'] ?? 'General'; ?></span></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Prioridad</label>
                <p><?php echo str_repeat('⭐', $a['prioridad'] ?? 1); ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Estado</label>
                <p><span class="estado-<?php echo $a['estado']; ?>"><?php echo ucfirst($a['estado']); ?></span></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Título</label>
                <p class="mb-0"><strong><?php echo htmlspecialchars($a['titulo']); ?></strong></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Fecha de Creación</label>
                <p><?php echo date('d/m/Y H:i:s', strtotime($a['created_at'])); ?></p>
            </div>
            <?php if ($a['fecha_resolucion']): ?>
                <div class="mb-3">
                    <label class="text-muted small">Fecha de Resolución</label>
                    <p><?php echo date('d/m/Y H:i:s', strtotime($a['fecha_resolucion'])); ?></p>
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="text-muted small">Creado por</label>
                <p><?php echo $a['usuario_creo_nombre'] ?? 'Sistema'; ?></p>
            </div>
            <?php if ($a['usuario_resolvio_nombre']): ?>
                <div class="mb-3">
                    <label class="text-muted small">Resuelto por</label>
                    <p><?php echo $a['usuario_resolvio_nombre']; ?></p>
                </div>
            <?php endif; ?>
            <?php if ($a['viaje_id']): ?>
                <div class="mb-3">
                    <label class="text-muted small">Viaje Relacionado</label>
                    <p><a href="/MINERIA/modules/viajes/ver.php?id=<?php echo $a['viaje_id']; ?>" class="text-warning">
                        <?php echo $a['viaje_codigo']; ?> (<?php echo ucfirst($a['viaje_estado']); ?>)
                    </a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($a['descripcion']): ?>
    <hr class="border-secondary">
    <div class="mb-3">
        <label class="text-muted small">Descripción</label>
        <p><?php echo nl2br(htmlspecialchars($a['descripcion'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($a['notificacion_enviada']): ?>
    <hr class="border-secondary">
    <div class="alert alert-info mb-0">
        <i class="fas fa-check-circle me-2"></i> Notificación enviada
    </div>
    <?php endif; ?>
</div>