<?php
// modules/viajes/ver.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

$query = "SELECT v.*, 
          c.nombre as cliente_nombre, c.ruc, c.telefono as cliente_telefono, c.email as cliente_email,
          co.nombre as conductor_nombre, co.licencia, co.telefono as conductor_telefono,
          ve.placa, ve.marca, ve.modelo, ve.capacidad,
          m.nombre as material_nombre, m.unidad_medida, m.precio_unitario,
          r.origen, r.destino, r.distancia, r.tiempo_estimado
          FROM viajes v
          LEFT JOIN clientes c ON v.cliente_id = c.id
          LEFT JOIN conductores co ON v.conductor_id = co.id
          LEFT JOIN vehiculos ve ON v.vehiculo_id = ve.id
          LEFT JOIN materiales m ON v.material_id = m.id
          LEFT JOIN rutas r ON v.ruta_id = r.id
          WHERE v.id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    header('Location: index.php');
    exit();
}

// Calcular ganancia
$ingreso = $viaje['ingreso_total'] ?? ($viaje['peso'] * $viaje['precio_unitario']);
?>
<?php include '../../includes/header.php'; ?>

<style>
    .detail-card {
        background: rgba(255,255,255,0.03);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .estado-badge {
        font-size: 14px;
        padding: 5px 12px;
    }
    .info-label {
        color: #9ca3af;
        font-size: 12px;
        margin-bottom: 5px;
    }
    .info-value {
        font-size: 16px;
        font-weight: 500;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-route me-2"></i> Detalle del Viaje</h2>
            <p class="text-muted mb-0">Código: <strong class="viaje-codigo"><?php echo $viaje['codigo']; ?></strong></p>
        </div>
        <div>
            <a href="editar.php?id=<?php echo $viaje['id']; ?>" class="btn btn-warning"><i class="fas fa-edit me-2"></i>Editar</a>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Volver</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Información del Viaje -->
            <div class="detail-card">
                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Información del Viaje</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="info-label">Código</div>
                        <div class="info-value"><?php echo $viaje['codigo']; ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="info-label">Fecha de Viaje</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($viaje['fecha_viaje'])); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="info-label">Estado</div>
                        <div class="info-value">
                            <span class="badge estado-<?php echo $viaje['estado']; ?> estado-badge">
                                <?php echo ucfirst(str_replace('_', ' ', $viaje['estado'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Material</div>
                        <div class="info-value"><?php echo htmlspecialchars($viaje['material_nombre']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Peso</div>
                        <div class="info-value"><?php echo number_format($viaje['peso'], 2); ?> <?php echo $viaje['unidad_medida']; ?></div>
                    </div>
                </div>
                <?php if ($viaje['observaciones']): ?>
                <div class="mb-3">
                    <div class="info-label">Observaciones</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($viaje['observaciones'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Cliente -->
            <div class="detail-card">
                <h6 class="mb-3"><i class="fas fa-building me-2"></i>Cliente</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Nombre</div>
                        <div class="info-value"><?php echo htmlspecialchars($viaje['cliente_nombre']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">RUC</div>
                        <div class="info-value"><?php echo $viaje['ruc']; ?></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value"><?php echo $viaje['cliente_telefono']; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo $viaje['cliente_email']; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Conductor -->
            <div class="detail-card">
                <h6 class="mb-3"><i class="fas fa-id-card me-2"></i>Conductor</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Nombre</div>
                        <div class="info-value"><?php echo htmlspecialchars($viaje['conductor_nombre']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Licencia</div>
                        <div class="info-value"><?php echo $viaje['licencia']; ?></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value"><?php echo $viaje['conductor_telefono']; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Vehículo -->
            <div class="detail-card">
                <h6 class="mb-3"><i class="fas fa-truck me-2"></i>Vehículo</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="info-label">Placa</div>
                        <div class="info-value"><?php echo $viaje['placa']; ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="info-label">Marca/Modelo</div>
                        <div class="info-value"><?php echo $viaje['marca'] . ' ' . $viaje['modelo']; ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="info-label">Capacidad</div>
                        <div class="info-value"><?php echo number_format($viaje['capacidad'], 2); ?> TN</div>
                    </div>
                </div>
            </div>
            
            <!-- Ruta -->
            <div class="detail-card">
                <h6 class="mb-3"><i class="fas fa-map-marked-alt me-2"></i>Ruta</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Origen</div>
                        <div class="info-value"><?php echo $viaje['origen']; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Destino</div>
                        <div class="info-value"><?php echo $viaje['destino']; ?></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Distancia</div>
                        <div class="info-value"><?php echo number_format($viaje['distancia'], 2); ?> km</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Tiempo Estimado</div>
                        <div class="info-value"><?php echo $viaje['tiempo_estimado']; ?> minutos</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Finanzas -->
            <div class="detail-card">
                <h6 class="mb-3"><i class="fas fa-dollar-sign me-2"></i>Finanzas</h6>
                <div class="mb-3">
                    <div class="info-label">Peso Transportado</div>
                    <div class="info-value"><?php echo number_format($viaje['peso'], 2); ?> <?php echo $viaje['unidad_medida']; ?></div>
                </div>
                <div class="mb-3">
                    <div class="info-label">Precio Unitario</div>
                    <div class="info-value">S/ <?php echo number_format($viaje['precio_unitario'], 2); ?></div>
                </div>
                <hr class="border-secondary">
                <div class="mb-3">
                    <div class="info-label">Ingreso Total</div>
                    <h4 class="text-gradient">S/ <?php echo number_format($ingreso, 2); ?></h4>
                </div>
            </div>
            
            <!-- Acciones Rápidas -->
            <div class="detail-card">
                <h6 class="mb-3"><i class="fas fa-bolt me-2"></i>Acciones Rápidas</h6>
                <?php if ($viaje['estado'] == 'pendiente'): ?>
                    <a href="?cambiar_estado=<?php echo $viaje['id']; ?>&estado=en_progreso" class="btn btn-warning w-100 mb-2">
                        <i class="fas fa-play me-2"></i>Iniciar Viaje
                    </a>
                <?php elseif ($viaje['estado'] == 'en_progreso'): ?>
                    <a href="?cambiar_estado=<?php echo $viaje['id']; ?>&estado=completado" class="btn btn-success w-100 mb-2">
                        <i class="fas fa-check me-2"></i>Completar Viaje
                    </a>
                <?php endif; ?>
                <?php if ($viaje['estado'] != 'cancelado' && $viaje['estado'] != 'completado'): ?>
                    <a href="?cambiar_estado=<?php echo $viaje['id']; ?>&estado=cancelado" class="btn btn-danger w-100">
                        <i class="fas fa-times me-2"></i>Cancelar Viaje
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
