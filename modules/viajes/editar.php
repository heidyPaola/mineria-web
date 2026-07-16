<?php
// modules/viajes/editar.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

// Obtener viaje
$query = "SELECT * FROM viajes WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    header('Location: index.php');
    exit();
}

// Obtener datos para selects
$clientes = $conn->query("SELECT id, nombre FROM clientes WHERE estado = 1 ORDER BY nombre")->fetchAll();
$conductores = $conn->query("SELECT id, nombre, estado FROM conductores WHERE estado != 'inactivo' ORDER BY nombre")->fetchAll();
$vehiculos = $conn->query("SELECT id, placa, marca, modelo, capacidad FROM vehiculos WHERE estado != 'inactivo' ORDER BY placa")->fetchAll();
$materiales = $conn->query("SELECT id, nombre, unidad_medida, precio_unitario FROM materiales WHERE estado = 1 ORDER BY nombre")->fetchAll();
$rutas = $conn->query("SELECT id, origen, destino, distancia, tiempo_estimado FROM rutas WHERE estado = 1 ORDER BY origen")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $conductor_id = $_POST['conductor_id'];
    $vehiculo_id = $_POST['vehiculo_id'];
    $material_id = $_POST['material_id'];
    $ruta_id = $_POST['ruta_id'];
    $peso = $_POST['peso'];
    $fecha_viaje = $_POST['fecha_viaje'];
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Calcular ingresos
    $stmt = $conn->prepare("SELECT precio_unitario FROM materiales WHERE id = :id");
    $stmt->execute([':id' => $material_id]);
    $precio = $stmt->fetch(PDO::FETCH_ASSOC)['precio_unitario'] ?? 0;
    $ingreso_total = $peso * $precio;
    
    $query = "UPDATE viajes SET 
              cliente_id = :cliente_id, conductor_id = :conductor_id, vehiculo_id = :vehiculo_id,
              material_id = :material_id, ruta_id = :ruta_id, peso = :peso,
              ingreso_total = :ingreso_total, fecha_viaje = :fecha_viaje, observaciones = :observaciones
              WHERE id = :id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':cliente_id' => $cliente_id, ':conductor_id' => $conductor_id, ':vehiculo_id' => $vehiculo_id,
        ':material_id' => $material_id, ':ruta_id' => $ruta_id, ':peso' => $peso,
        ':ingreso_total' => $ingreso_total, ':fecha_viaje' => $fecha_viaje,
        ':observaciones' => $observaciones, ':id' => $id
    ]);
    
    registrarAuditoria($conn, 'ACTUALIZAR', 'viajes', $id);
    header('Location: ver.php?id=' . $id . '&msg=actualizado');
    exit();
}
?>
<?php include '../../includes/header.php'; ?>

<style>
    .form-section {
        background: rgba(255,255,255,0.03);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit me-2"></i> Editar Viaje</h2>
        <div>
            <a href="ver.php?id=<?php echo $id; ?>" class="btn btn-info"><i class="fas fa-eye me-2"></i>Ver</a>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Volver</a>
        </div>
    </div>
    
    <form method="POST" action="">
        <div class="row">
            <div class="col-md-8">
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Datos del Viaje</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" class="form-control" value="<?php echo $viaje['codigo']; ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fecha de Viaje *</label>
                            <input type="date" class="form-control" name="fecha_viaje" required value="<?php echo $viaje['fecha_viaje']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Peso (TN) *</label>
                            <input type="number" class="form-control" name="peso" id="peso" step="0.01" required value="<?php echo $viaje['peso']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-building me-2"></i>Cliente</h6>
                    <select class="form-select" name="cliente_id" required>
                        <option value="">Seleccione un cliente</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $viaje['cliente_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-id-card me-2"></i>Conductor</h6>
                    <select class="form-select" name="conductor_id" required>
                        <option value="">Seleccione un conductor</option>
                        <?php foreach ($conductores as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $viaje['conductor_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-truck me-2"></i>Vehículo</h6>
                    <select class="form-select" name="vehiculo_id" required>
                        <option value="">Seleccione un vehículo</option>
                        <?php foreach ($vehiculos as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo $viaje['vehiculo_id'] == $v['id'] ? 'selected' : ''; ?>><?php echo $v['placa']; ?> - <?php echo $v['marca'] . ' ' . $v['modelo']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-cubes me-2"></i>Material</h6>
                    <select class="form-select" name="material_id" id="material_id" required>
                        <option value="">Seleccione un material</option>
                        <?php foreach ($materiales as $m): ?>
                            <option value="<?php echo $m['id']; ?>" data-precio="<?php echo $m['precio_unitario']; ?>" <?php echo $viaje['material_id'] == $m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['nombre']); ?> (S/ <?php echo number_format($m['precio_unitario'], 2); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-map-marked-alt me-2"></i>Ruta</h6>
                    <select class="form-select" name="ruta_id" required>
                        <option value="">Seleccione una ruta</option>
                        <?php foreach ($rutas as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $viaje['ruta_id'] == $r['id'] ? 'selected' : ''; ?>><?php echo $r['origen']; ?> → <?php echo $r['destino']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-comment me-2"></i>Observaciones</h6>
                    <textarea class="form-control" name="observaciones" rows="3"><?php echo htmlspecialchars($viaje['observaciones']); ?></textarea>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-calculator me-2"></i>Resumen</h6>
                    <div class="mb-2">
                        <small class="text-muted">Peso:</small>
                        <p><strong id="previewPeso"><?php echo number_format($viaje['peso'], 2); ?></strong> TN</p>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Precio Unitario:</small>
                        <p><strong id="previewPrecio">S/ 0.00</strong></p>
                    </div>
                    <hr>
                    <div class="mb-2">
                        <small class="text-muted">Ingreso Estimado:</small>
                        <h4 class="text-gradient" id="previewIngreso">S/ 0.00</h4>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-gradient w-100">
                    <i class="fas fa-save me-2"></i>Actualizar Viaje
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function actualizarResumen() {
    const materialSelect = document.getElementById('material_id');
    const peso = parseFloat(document.getElementById('peso').value) || 0;
    const selectedOption = materialSelect.options[materialSelect.selectedIndex];
    const precio = parseFloat(selectedOption?.dataset.precio) || 0;
    const ingreso = peso * precio;
    
    document.getElementById('previewPeso').innerText = peso.toFixed(2);
    document.getElementById('previewPrecio').innerHTML = 'S/ ' + precio.toFixed(2);
    document.getElementById('previewIngreso').innerHTML = 'S/ ' + ingreso.toFixed(2);
}

document.getElementById('peso')?.addEventListener('input', actualizarResumen);
document.getElementById('material_id')?.addEventListener('change', actualizarResumen);
actualizarResumen();
</script>

<?php include '../../includes/footer.php'; ?>