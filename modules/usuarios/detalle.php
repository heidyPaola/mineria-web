<?php
// modules/usuarios/detalle.php
require_once '../../config/auth.php';
requireLogin();
requireRole('admin');
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    echo '<p class="text-center text-danger">ID no proporcionado</p>';
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    echo '<p class="text-center text-danger">Usuario no encontrado</p>';
    exit();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Usuario</label>
                <p class="mb-0"><strong><?php echo htmlspecialchars($u['username']); ?></strong></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Nombre Completo</label>
                <p class="mb-0"><?php echo htmlspecialchars($u['nombre']); ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Email</label>
                <p class="mb-0"><?php echo htmlspecialchars($u['email']); ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Rol</label>
                <p><span class="rol-badge rol-<?php echo $u['rol']; ?>"><?php echo ucfirst($u['rol']); ?></span></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Estado</label>
                <p>
                    <?php if ($u['estado'] == 1): ?>
                        <span class="estado-activo">✅ Activo</span>
                    <?php else: ?>
                        <span class="estado-inactivo">⛔ Inactivo</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Fecha de Registro</label>
                <p><?php echo date('d/m/Y H:i:s', strtotime($u['created_at'])); ?></p>
            </div>
        </div>
    </div>
    
    <?php if ($u['ultimo_acceso']): ?>
    <hr class="border-secondary">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Último Acceso</label>
                <p><?php echo date('d/m/Y H:i:s', strtotime($u['ultimo_acceso'])); ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Última IP</label>
                <p><span class="ip-badge"><?php echo $u['ultimo_ip'] ?? 'N/A'; ?></span></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($u['intentos_fallidos'] > 0): ?>
    <hr class="border-secondary">
    <div class="alert alert-warning mb-0">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Intentos fallidos de inicio de sesión: <strong><?php echo $u['intentos_fallidos']; ?></strong>
    </div>
    <?php endif; ?>
</div>