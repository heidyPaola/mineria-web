<?php
// modules/viajes/index.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Cambiar estado del viaje (rápido)
if (isset($_GET['cambiar_estado']) && isset($_GET['estado'])) {
    $id = $_GET['cambiar_estado'];
    $nuevo_estado = $_GET['estado'];
    
    $query = "UPDATE viajes SET estado = :estado WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
    
    registrarAuditoria($conn, 'CAMBIAR_ESTADO', 'viajes', $id, null, ['nuevo_estado' => $nuevo_estado]);
    header('Location: index.php?msg=estado');
    exit();
}

// Eliminar viaje
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $query = "DELETE FROM viajes WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'ELIMINAR', 'viajes', $id);
    header('Location: index.php?msg=eliminado');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$cliente_filtro = $_GET['cliente'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$query = "SELECT v.*, 
          c.nombre as cliente_nombre, 
          co.nombre as conductor_nombre,
          co.telefono as conductor_telefono,
          ve.placa, ve.marca as vehiculo_marca, ve.modelo as vehiculo_modelo,
          m.nombre as material_nombre, m.unidad_medida,
          r.origen, r.destino, r.distancia
          FROM viajes v
          LEFT JOIN clientes c ON v.cliente_id = c.id
          LEFT JOIN conductores co ON v.conductor_id = co.id
          LEFT JOIN vehiculos ve ON v.vehiculo_id = ve.id
          LEFT JOIN materiales m ON v.material_id = m.id
          LEFT JOIN rutas r ON v.ruta_id = r.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (v.codigo LIKE :search OR c.nombre LIKE :search OR ve.placa LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($estado_filtro)) {
    $query .= " AND v.estado = :estado";
    $params[':estado'] = $estado_filtro;
}
if (!empty($cliente_filtro)) {
    $query .= " AND v.cliente_id = :cliente";
    $params[':cliente'] = $cliente_filtro;
}
if (!empty($fecha_desde)) {
    $query .= " AND v.fecha_viaje >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}
if (!empty($fecha_hasta)) {
    $query .= " AND v.fecha_viaje <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY v.id DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($viajes);
$pendientes = count(array_filter($viajes, fn($v) => $v['estado'] == 'pendiente'));
$en_progreso = count(array_filter($viajes, fn($v) => $v['estado'] == 'en_progreso'));
$completados = count(array_filter($viajes, fn($v) => $v['estado'] == 'completado'));
$cancelados = count(array_filter($viajes, fn($v) => $v['estado'] == 'cancelado'));

$ingreso_total = array_sum(array_column($viajes, 'ingreso_total'));
$peso_total = array_sum(array_column($viajes, 'peso'));

// Datos para selects
$clientes = $conn->query("SELECT id, nombre FROM clientes WHERE estado = 1 ORDER BY nombre")->fetchAll();
$conductores = $conn->query("SELECT id, nombre, estado FROM conductores WHERE estado = 'disponible' ORDER BY nombre")->fetchAll();
$vehiculos = $conn->query("SELECT id, placa, marca, modelo, estado FROM vehiculos WHERE estado = 'activo' ORDER BY placa")->fetchAll();
$materiales = $conn->query("SELECT id, nombre, unidad_medida, precio_unitario FROM materiales WHERE estado = 1 ORDER BY nombre")->fetchAll();
$rutas = $conn->query("SELECT id, origen, destino, distancia, tiempo_estimado FROM rutas WHERE estado = 1 ORDER BY origen")->fetchAll();
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-viaje {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
        border: 1px solid rgba(245, 158, 11, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .estado-pendiente { background: #3b82f620; color: #3b82f6; border: 1px solid #3b82f6; }
    .estado-en_progreso { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b; }
    .estado-completado { background: #10b98120; color: #10b981; border: 1px solid #10b981; }
    .estado-cancelado { background: #ef444420; color: #ef4444; border: 1px solid #ef4444; }
    .viaje-codigo {
        font-family: monospace;
        font-weight: bold;
        font-size: 14px;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-route me-2"></i> Viajes</h2>
            <p class="text-muted mb-0">Gestión completa de viajes y transporte</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-gradient-outline" id="btnExportExcel"><i class="fas fa-file-excel me-2"></i>Excel</button>
                <button class="btn btn-gradient-outline" id="btnExportPDF"><i class="fas fa-file-pdf me-2"></i>PDF</button>
            </div>
            <a href="nuevo.php" class="btn btn-gradient">
                <i class="fas fa-plus me-2"></i>Nuevo Viaje
            </a>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'creado') echo '✅ Viaje creado exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo '✅ Viaje actualizado exitosamente';
                elseif ($_GET['msg'] == 'eliminado') echo '🗑️ Viaje eliminado exitosamente';
                elseif ($_GET['msg'] == 'estado') echo '🔄 Estado del viaje actualizado';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-viaje">
                <i class="fas fa-route fa-2x mb-2" style="color: #3b82f6;"></i>
                <h3><?php echo $total; ?></h3>
                <small>TOTAL VIAJES</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-viaje">
                <i class="fas fa-clock fa-2x mb-2" style="color: #f59e0b;"></i>
                <h3><?php echo $pendientes + $en_progreso; ?></h3>
                <small>ACTIVOS</small>
                <div><small class="text-muted"><?php echo $pendientes; ?> pendientes | <?php echo $en_progreso; ?> en curso</small></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-viaje">
                <i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i>
                <h3><?php echo $completados; ?></h3>
                <small>COMPLETADOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-viaje">
                <i class="fas fa-dollar-sign fa-2x mb-2" style="color: #10b981;"></i>
                <h3>S/ <?php echo number_format($ingreso_total, 0); ?></h3>
                <small>INGRESOS TOTALES</small>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 Código, Cliente, Placa..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select id="estadoFilter" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="pendiente" <?php echo $estado_filtro == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="en_progreso" <?php echo $estado_filtro == 'en_progreso' ? 'selected' : ''; ?>>En Progreso</option>
                    <option value="completado" <?php echo $estado_filtro == 'completado' ? 'selected' : ''; ?>>Completado</option>
                    <option value="cancelado" <?php echo $estado_filtro == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="clienteFilter" class="form-select">
                    <option value="">Todos los clientes</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $cliente_filtro == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" id="fechaDesde" class="form-control" placeholder="Fecha desde" value="<?php echo $fecha_desde; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" id="fechaHasta" class="form-control" placeholder="Fecha hasta" value="<?php echo $fecha_hasta; ?>">
            </div>
            <div class="col-md-1">
                <button id="applyFiltersBtn" class="btn btn-gradient w-100">Filtrar</button>
            </div>
        </div>
    </div>
    
    <!-- Tabla de Viajes -->
    <div class="card-glass p-3">
        <div class="table-responsive">
            <table class="table table-glass" id="tablaViajes">
                <thead>
                    <tr>
                        <th style="width: 50px">ID</th>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Conductor</th>
                        <th>Vehículo</th>
                        <th>Material</th>
                        <th>Peso</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th style="width: 100px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($viajes)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No hay viajes registrados</td></tr>
                    <?php else: ?>
                        <?php foreach ($viajes as $v): ?>
                        <tr>
                            <td><?php echo $v['id']; ?></td>
                            <td><span class="viaje-codigo"><?php echo $v['codigo']; ?></span></td>
                            <td><?php echo htmlspecialchars($v['cliente_nombre'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($v['conductor_nombre'] ?? 'N/A'); ?></td>
                            <td><?php echo $v['placa'] ?? 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($v['material_nombre'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($v['peso'], 2); ?> <?php echo $v['unidad_medida'] ?? 'TN'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($v['fecha_viaje'])); ?></td>
                            <td>
                                <span class="badge estado-<?php echo $v['estado']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $v['estado'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?php echo $v['id']; ?>" class="btn btn-info" title="Ver detalle"><i class="fas fa-eye"></i></a>
                                    <a href="editar.php?id=<?php echo $v['id']; ?>" class="btn btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                    <button class="btn btn-danger" onclick="eliminarViaje(<?php echo $v['id']; ?>, '<?php echo $v['codigo']; ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </div>
                                <?php if ($v['estado'] == 'pendiente'): ?>
                                    <div class="dropdown mt-1">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Cambiar</button>
                                        <ul class="dropdown-menu bg-dark">
                                            <li><a class="dropdown-item" href="?cambiar_estado=<?php echo $v['id']; ?>&estado=en_progreso">🚛 En Progreso</a></li>
                                            <li><a class="dropdown-item" href="?cambiar_estado=<?php echo $v['id']; ?>&estado=cancelado">❌ Cancelar</a></li>
                                        </ul>
                                    </div>
                                <?php elseif ($v['estado'] == 'en_progreso'): ?>
                                    <div class="dropdown mt-1">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Cambiar</button>
                                        <ul class="dropdown-menu bg-dark">
                                            <li><a class="dropdown-item" href="?cambiar_estado=<?php echo $v['id']; ?>&estado=completado">✅ Completar</a></li>
                                            <li><a class="dropdown-item" href="?cambiar_estado=<?php echo $v['id']; ?>&estado=cancelado">❌ Cancelar</a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
function eliminarViaje(id, codigo) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Eliminar viaje ${codigo}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `index.php?delete_id=${id}`;
    });
}

// Filtros
document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
    const search = document.getElementById('searchInput').value;
    const estado = document.getElementById('estadoFilter').value;
    const cliente = document.getElementById('clienteFilter').value;
    const fecha_desde = document.getElementById('fechaDesde').value;
    const fecha_hasta = document.getElementById('fechaHasta').value;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&estado=${estado}&cliente=${cliente}&fecha_desde=${fecha_desde}&fecha_hasta=${fecha_hasta}`;
});

// Excel
document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaViajes');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Viajes');
    XLSX.writeFile(wb, `viajes_${new Date().toISOString().slice(0,19)}.xlsx`);
});

// PDF
document.getElementById('btnExportPDF')?.addEventListener('click', function() {
    const tabla = document.getElementById('tablaViajes');
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head><title>Viajes H&H MINERIA</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            .header { text-align: center; border-bottom: 2px solid #f59e0b; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #f59e0b; color: white; padding: 10px; }
            td { border: 1px solid #ddd; padding: 8px; }
        </style>
        </head><body>
        <div class="header"><h1>H&H MINERIA</h1><p>Listado de Viajes - ${new Date().toLocaleString()}</p></div>
        ${tabla.outerHTML}
        </body></html>
    `);
    ventana.document.close();
    ventana.print();
});

// Enter en búsqueda
document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') document.getElementById('applyFiltersBtn').click();
});
</script>

<?php include '../../includes/footer.php'; ?>