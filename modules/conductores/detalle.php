<?php
// modules/conductores/detalle.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    echo '<p class="text-center text-danger">ID no proporcionado</p>';
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM conductores WHERE id = :id");
$stmt->execute([':id' => $id]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$c) {
    echo '<p class="text-center text-danger">Conductor no encontrado</p>';
    exit();
}

// Viajes del conductor
$stmt = $conn->prepare("SELECT COUNT(*) as viajes, SUM(peso) as peso FROM viajes WHERE conductor_id = :id");
$stmt->execute([':id' => $id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <p><strong><i class="fas fa-user"></i> Nombre:</strong> <?php echo htmlspecialchars($c['nombre']); ?></p>
            <p><strong><i class="fas fa-id-card"></i> Licencia:</strong> <?php echo $c['licencia']; ?></p>
            <p><strong><i class="fas fa-phone"></i> Teléfono:</strong> <?php echo $c['telefono'] ?: 'No registrado'; ?></p>
        </div>
        <div class="col-md-6">
            <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($c['email']) ?: 'No registrado'; ?></p>
            <p><strong><i class="fas fa-flag-checkered"></i> Estado:</strong> 
                <span class="badge badge-<?php echo $c['estado']; ?>"><?php echo ucfirst($c['estado']); ?></span>
            </p>
            <p><strong><i class="fas fa-calendar"></i> Registro:</strong> <?php echo date('d/m/Y', strtotime($c['created_at'])); ?></p>
        </div>
    </div>
    <hr class="border-secondary">
    <div class="row">
        <div class="col-md-6">
            <p><strong><i class="fas fa-route"></i> Viajes realizados:</strong> <?php echo $stats['viajes'] ?? 0; ?></p>
        </div>
        <div class="col-md-6">
            <p><strong><i class="fas fa-weight-hanging"></i> Toneladas transportadas:</strong> <?php echo number_format($stats['peso'] ?? 0, 2); ?> TN</p>
        </div>
    </div>
    <?php if ($c['direccion']): ?>
    <hr class="border-secondary">
    <p><strong><i class="fas fa-map-marker-alt"></i> Dirección:</strong> <?php echo nl2br(htmlspecialchars($c['direccion'])); ?></p>
    <?php endif; ?>
</div>