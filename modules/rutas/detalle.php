<?php
// modules/rutas/detalle.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    echo '<p class="text-center text-danger">ID no proporcionado</p>';
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM rutas WHERE id = :id");
$stmt->execute([':id' => $id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    echo '<p class="text-center text-danger">Ruta no encontrada</p>';
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) as viajes, SUM(peso) as peso FROM viajes WHERE ruta_id = :id");
$stmt->execute([':id' => $id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Origen → Destino</label>
                <p class="mb-0"><strong><?php echo $r['origen']; ?> → <?php echo $r['destino']; ?></strong></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Distancia / Tiempo</label>
                <p class="mb-0"><?php echo number_format($r['distancia'], 1); ?> km | <?php echo $r['tiempo_estimado']; ?> minutos</p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Tipo / Dificultad</label>
                <p class="mb-0"><?php echo $r['tipo']; ?> | 
                    <span class="badge dificultad-<?php echo $r['dificultad']; ?>"><?php echo ucfirst($r['dificultad']); ?></span>
                </p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Peligrosidad</label>
                <p class="mb-0"><?php echo ucfirst($r['peligrosidad']); ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="text-muted small">Estado</label>
                <p><span class="badge <?php echo $r['estado'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                    <?php echo $r['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                </span></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Último Mantenimiento</label>
                <p><?php echo $r['ultimo_mantenimiento'] ? date('d/m/Y', strtotime($r['ultimo_mantenimiento'])) : 'N/A'; ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small">Restricciones</label>
                <p>Peso: <?php echo $r['restriccion_peso'] ? $r['restriccion_peso'] . ' TN' : 'N/A'; ?> | 
                   Altura: <?php echo $r['restriccion_altura'] ? $r['restriccion_altura'] . ' m' : 'N/A'; ?></p>
            </div>
        </div>
    </div>
    
    <?php if ($r['puntos_intermedios']): ?>
    <hr class="border-secondary">
    <div class="mb-3">
        <label class="text-muted small">Puntos Intermedios</label>
        <p><?php echo nl2br(htmlspecialchars($r['puntos_intermedios'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($r['condiciones']): ?>
    <hr class="border-secondary">
    <div class="mb-3">
        <label class="text-muted small">Condiciones de la Ruta</label>
        <p><?php echo nl2br(htmlspecialchars($r['condiciones'])); ?></p>
    </div>
    <?php endif; ?>
    
    <hr class="border-secondary">
    <h6 class="mb-3"><i class="fas fa-chart-line"></i> Estadísticas de Uso</h6>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Viajes realizados:</strong> <?php echo $stats['viajes'] ?? 0; ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Toneladas transportadas:</strong> <?php echo number_format($stats['peso'] ?? 0, 2); ?> TN</p>
        </div>
    </div>
    
    <?php if ($r['coordenadas_origen'] && $r['coordenadas_destino']): ?>
    <hr class="border-secondary">
    <div class="mb-3">
        <label class="text-muted small">Coordenadas</label>
        <p>Origen: <?php echo $r['coordenadas_origen']; ?><br>
           Destino: <?php echo $r['coordenadas_destino']; ?></p>
    </div>
    <?php endif; ?>
</div>