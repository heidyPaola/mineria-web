<?php
// modules/viajes/nuevo.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Generar código de viaje automático
$query = "SELECT MAX(id) as ultimo FROM viajes";
$stmt = $conn->query($query);
$ultimo = $stmt->fetch(PDO::FETCH_ASSOC)['ultimo'];
$nuevo_codigo = 'VIA-' . str_pad(($ultimo + 1), 4, '0', STR_PAD_LEFT);

// Obtener datos para selects
$clientes = $conn->query("SELECT id, nombre FROM clientes WHERE estado = 1 ORDER BY nombre")->fetchAll();
$conductores = $conn->query("SELECT id, nombre, estado FROM conductores WHERE estado = 'disponible' ORDER BY nombre")->fetchAll();
$vehiculos = $conn->query("SELECT id, placa, marca, modelo, capacidad FROM vehiculos WHERE estado = 'activo' ORDER BY placa")->fetchAll();
$materiales = $conn->query("SELECT id, nombre, unidad_medida, precio_unitario FROM materiales WHERE estado = 1 ORDER BY nombre")->fetchAll();
$rutas = $conn->query("SELECT id, origen, destino, distancia, tiempo_estimado FROM rutas WHERE estado = 1 ORDER BY origen")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codigo = $_POST['codigo'];
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
    
    $query = "INSERT INTO viajes (codigo, cliente_id, conductor_id, vehiculo_id, material_id, 
              ruta_id, peso, ingreso_total, fecha_viaje, observaciones, estado) 
              VALUES (:codigo, :cliente_id, :conductor_id, :vehiculo_id, :material_id, 
              :ruta_id, :peso, :ingreso_total, :fecha_viaje, :observaciones, 'pendiente')";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':codigo' => $codigo, ':cliente_id' => $cliente_id, ':conductor_id' => $conductor_id,
        ':vehiculo_id' => $vehiculo_id, ':material_id' => $material_id, ':ruta_id' => $ruta_id,
        ':peso' => $peso, ':ingreso_total' => $ingreso_total, ':fecha_viaje' => $fecha_viaje,
        ':observaciones' => $observaciones
    ]);
    
    registrarAuditoria($conn, 'CREAR', 'viajes', $conn->lastInsertId());
    header('Location: index.php?msg=creado');
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
    .preview-card {
        background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(217,119,6,0.05));
        border: 1px solid rgba(245,158,11,0.2);
        border-radius: 12px;
        padding: 15px;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-plus-circle me-2"></i> Nuevo Viaje</h2>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Volver</a>
    </div>
    
    <form method="POST" action="">
        <div class="row">
            <div class="col-md-8">
                <!-- Datos principales -->
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Datos del Viaje</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" class="form-control" name="codigo" value="<?php echo $nuevo_codigo; ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fecha de Viaje *</label>
                            <input type="date" class="form-control" name="fecha_viaje" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Peso (TN) *</label>
                            <input type="number" class="form-control" name="peso" id="peso" step="0.01" required onchange="calcularIngreso()">
                        </div>
                    </div>
                </div>
                
                <!-- Selección de Cliente -->
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-building me-2"></i>Cliente</h6>
                    <select class="form-select" name="cliente_id" required>
                        <option value="">Seleccione un cliente</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Selección de Conductor -->
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-id-card me-2"></i>Conductor</h6>
                    <select class="form-select" name="conductor_id" required>
                        <option value="">Seleccione un conductor</option>
                        <?php foreach ($conductores as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?> (<?php echo $c['estado']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Selección de Vehículo -->
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-truck me-2"></i>Vehículo</h6>
                    <select class="form-select" name="vehiculo_id" required>
                        <option value="">Seleccione un vehículo</option>
                        <?php foreach ($vehiculos as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo $v['placa']; ?> - <?php echo $v['marca'] . ' ' . $v['modelo']; ?> (Cap: <?php echo $v['capacidad']; ?> TN)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Selección de Material -->
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-cubes me-2"></i>Material</h6>
                    <select class="form-select" name="material_id" id="material_id" required onchange="calcularIngreso()">
                        <option value="">Seleccione un material</option>
                        <?php foreach ($materiales as $m): ?>
                            <option value="<?php echo $m['id']; ?>" data-precio="<?php echo $m['precio_unitario']; ?>"><?php echo htmlspecialchars($m['nombre']); ?> (<?php echo $m['unidad_medida']; ?>) - S/ <?php echo number_format($m['precio_unitario'], 2); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Selección de Ruta -->
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-map-marked-alt me-2"></i>Ruta</h6>
                    <select class="form-select" name="ruta_id" required>
                        <option value="">Seleccione una ruta</option>
                        <?php foreach ($rutas as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo $r['origen']; ?> → <?php echo $r['destino']; ?> (<?php echo $r['distancia']; ?> km, <?php echo $r['tiempo_estimado']; ?> min)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Observaciones -->
                <div class="form-section">
                    <h6 class="mb-3"><i class="fas fa-comment me-2"></i>Observaciones</h6>
                    <textarea class="form-control" name="observaciones" rows="3" placeholder="Notas adicionales sobre el viaje..."></textarea>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Resumen -->
                <div class="preview-card">
                    <h6 class="mb-3"><i class="fas fa-calculator me-2"></i>Resumen del Viaje</h6>
                    <div class="mb-2">
                        <small class="text-muted">Código:</small>
                        <p class="mb-1"><strong><?php echo $nuevo_codigo; ?></strong></p>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Peso:</small>
                        <p class="mb-1"><strong id="previewPeso">0.00</strong> TN</p>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Precio Unitario:</small>
                        <p class="mb-1"><strong id="previewPrecio">S/ 0.00</strong></p>
                    </div>
                    <hr class="border-secondary">
                    <div class="mb-2">
                        <small class="text-muted">Ingreso Estimado:</small>
                        <h4 class="text-gradient mb-0" id="previewIngreso">S/ 0.00</h4>
                    </div>
                </div>
                
                <div class="preview-card mt-3">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Información</h6>
                    <small class="text-muted">El viaje se creará en estado <strong>PENDIENTE</strong>. Podrás cambiar el estado desde el listado.</small>
                </div>
                
                <button type="submit" class="btn btn-gradient w-100 mt-3">
                    <i class="fas fa-save me-2"></i>Guardar Viaje
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function calcularIngreso() {
    const materialSelect = document.getElementById('material_id');
    const peso = parseFloat(document.getElementById('peso').value) || 0;
    const selectedOption = materialSelect.options[materialSelect.selectedIndex];
    const precio = parseFloat(selectedOption?.dataset.precio) || 0;
    const ingreso = peso * precio;
    
    document.getElementById('previewPeso').innerText = peso.toFixed(2);
    document.getElementById('previewPrecio').innerText = 'S/ ' + precio.toFixed(2);
    document.getElementById('previewIngreso').innerHTML = 'S/ ' + ingreso.toFixed(2);
}

document.getElementById('peso')?.addEventListener('input', calcularIngreso);
document.getElementById('material_id')?.addEventListener('change', calcularIngreso);
</script>

<?php include '../../includes/footer.php'; ?>