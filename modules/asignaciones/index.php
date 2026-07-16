<?php
// modules/asignaciones/index.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Procesar eliminación
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $query = "DELETE FROM asignaciones WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'ELIMINAR', 'asignaciones', $id);
    header('Location: index.php?msg=eliminado');
    exit();
}

// Cambiar estado rápido
if (isset($_GET['cambiar_estado']) && isset($_GET['estado'])) {
    $id = $_GET['cambiar_estado'];
    $nuevo_estado = $_GET['estado'];
    
    // Si se completa, liberar vehículo y conductor
    if ($nuevo_estado == 'completada' || $nuevo_estado == 'cancelada') {
        $query = "UPDATE asignaciones SET estado = :estado, fecha_fin = CURDATE() WHERE id = :id";
    } else {
        $query = "UPDATE asignaciones SET estado = :estado WHERE id = :id";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
    
    // Actualizar estado del conductor
    $query = "SELECT conductor_id FROM asignaciones WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    $conductor_id = $stmt->fetch(PDO::FETCH_ASSOC)['conductor_id'];
    
    if ($nuevo_estado == 'activa') {
        $conn->prepare("UPDATE conductores SET estado = 'ocupado' WHERE id = :id")->execute([':id' => $conductor_id]);
    } else {
        $conn->prepare("UPDATE conductores SET estado = 'disponible' WHERE id = :id")->execute([':id' => $conductor_id]);
    }
    
    registrarAuditoria($conn, 'CAMBIAR_ESTADO', 'asignaciones', $id);
    header('Location: index.php?msg=estado');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$query = "SELECT a.*, 
          v.placa, v.marca, v.modelo,
          c.nombre as conductor_nombre, c.telefono as conductor_telefono
          FROM asignaciones a
          LEFT JOIN vehiculos v ON a.vehiculo_id = v.id
          LEFT JOIN conductores c ON a.conductor_id = c.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (v.placa LIKE :search OR c.nombre LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($estado_filtro)) {
    $query .= " AND a.estado = :estado";
    $params[':estado'] = $estado_filtro;
}
if (!empty($fecha_desde)) {
    $query .= " AND a.fecha_asignacion >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}
if (!empty($fecha_hasta)) {
    $query .= " AND a.fecha_asignacion <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY a.id DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($asignaciones);
$activas = count(array_filter($asignaciones, fn($a) => $a['estado'] == 'activa'));
$completadas = count(array_filter($asignaciones, fn($a) => $a['estado'] == 'completada'));
$canceladas = count(array_filter($asignaciones, fn($a) => $a['estado'] == 'cancelada'));

// Asignaciones que vencen en los próximos 3 días
$vencimiento_proximo = count(array_filter($asignaciones, function($a) {
    if ($a['estado'] != 'activa') return false;
    $fecha_fin = strtotime($a['fecha_fin']);
    $dias = ($fecha_fin - time()) / (60 * 60 * 24);
    return $dias <= 3 && $dias > 0;
}));

// Datos para selects
$vehiculos_disponibles = $conn->query("SELECT id, placa, marca, modelo FROM vehiculos WHERE estado = 'activo' ORDER BY placa")->fetchAll();
$conductores_disponibles = $conn->query("SELECT id, nombre FROM conductores WHERE estado = 'disponible' ORDER BY nombre")->fetchAll();
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-asignacion {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .estado-activa { background: #10b98120; color: #10b981; border: 1px solid #10b981; }
    .estado-completada { background: #3b82f620; color: #3b82f6; border: 1px solid #3b82f6; }
    .estado-cancelada { background: #ef444420; color: #ef4444; border: 1px solid #ef4444; }
    .asignacion-placa {
        font-family: monospace;
        font-weight: bold;
        font-size: 14px;
        color: #f59e0b;
    }
    .vencimiento-proximo {
        background: #f59e0b20;
        color: #f59e0b;
        border: 1px solid #f59e0b;
        border-radius: 20px;
        padding: 2px 10px;
        font-size: 11px;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-tasks me-2"></i> Asignaciones</h2>
            <p class="text-muted mb-0">Asignación de vehículos y conductores</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-gradient-outline" id="btnExportExcel"><i class="fas fa-file-excel me-2"></i>Excel</button>
                <button class="btn btn-gradient-outline" id="btnExportPDF"><i class="fas fa-file-pdf me-2"></i>PDF</button>
            </div>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalAsignacion" onclick="limpiarFormulario()">
                <i class="fas fa-plus me-2"></i>Nueva Asignación
            </button>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'creado') echo '✅ Asignación creada exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo '✅ Asignación actualizada exitosamente';
                elseif ($_GET['msg'] == 'eliminado') echo '🗑️ Asignación eliminada exitosamente';
                elseif ($_GET['msg'] == 'estado') echo '🔄 Estado actualizado exitosamente';
                elseif ($_GET['msg'] == 'error') echo '❌ ' . ($_SESSION['error_msg'] ?? 'Error en la operación');
                unset($_SESSION['error_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-asignacion">
                <i class="fas fa-tasks fa-2x mb-2" style="color: #3b82f6;"></i>
                <h3><?php echo $total; ?></h3>
                <small>TOTAL ASIGNACIONES</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-asignacion">
                <i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i>
                <h3><?php echo $activas; ?></h3>
                <small>ACTIVAS</small>
                <?php if ($vencimiento_proximo > 0): ?>
                    <br><small class="vencimiento-proximo">⚠️ <?php echo $vencimiento_proximo; ?> vencen pronto</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-asignacion">
                <i class="fas fa-flag-checkered fa-2x mb-2" style="color: #3b82f6;"></i>
                <h3><?php echo $completadas; ?></h3>
                <small>COMPLETADAS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-asignacion">
                <i class="fas fa-times-circle fa-2x mb-2" style="color: #ef4444;"></i>
                <h3><?php echo $canceladas; ?></h3>
                <small>CANCELADAS</small>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 Buscar por placa o conductor..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select id="estadoFilter" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="activa" <?php echo $estado_filtro == 'activa' ? 'selected' : ''; ?>>Activa</option>
                    <option value="completada" <?php echo $estado_filtro == 'completada' ? 'selected' : ''; ?>>Completada</option>
                    <option value="cancelada" <?php echo $estado_filtro == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" id="fechaDesde" class="form-control" placeholder="Desde" value="<?php echo $fecha_desde; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" id="fechaHasta" class="form-control" placeholder="Hasta" value="<?php echo $fecha_hasta; ?>">
            </div>
            <div class="col-md-2">
                <button id="applyFiltersBtn" class="btn btn-gradient w-100">Aplicar</button>
            </div>
        </div>
    </div>
    
    <!-- Tabla -->
    <div class="card-glass p-3">
        <div class="table-responsive">
            <table class="table table-glass" id="tablaAsignaciones">
                <thead>
                    <tr>
                        <th style="width: 50px">ID</th>
                        <th>Vehículo</th>
                        <th>Conductor</th>
                        <th>Fecha Asignación</th>
                        <th>Fecha Fin</th>
                        <th>Días</th>
                        <th>Estado</th>
                        <th style="width: 120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($asignaciones)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay asignaciones registradas</td></tr>
                    <?php else: ?>
                        <?php foreach ($asignaciones as $a): ?>
                            <?php 
                            $dias = (strtotime($a['fecha_fin']) - strtotime($a['fecha_asignacion'])) / (60 * 60 * 24);
                            $dias_restantes = (strtotime($a['fecha_fin']) - time()) / (60 * 60 * 24);
                            $proximo_vencer = $a['estado'] == 'activa' && $dias_restantes <= 3 && $dias_restantes > 0;
                            ?>
                            <tr>
                                <td><?php echo $a['id']; ?></td>
                                <td>
                                    <strong class="asignacion-placa"><?php echo $a['placa']; ?></strong>
                                    <br><small><?php echo $a['marca'] . ' ' . $a['modelo']; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($a['conductor_nombre']); ?></strong>
                                    <br><small><?php echo $a['conductor_telefono']; ?></small>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($a['fecha_asignacion'])); ?></td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($a['fecha_fin'])); ?>
                                    <?php if ($proximo_vencer): ?>
                                        <br><span class="vencimiento-proximo">⚠️ <?php echo ceil($dias_restantes); ?> días</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo ceil($dias); ?> días</td>
                                <td>
                                    <span class="badge estado-<?php echo $a['estado']; ?>">
                                        <?php echo ucfirst($a['estado']); ?>
                                    </span>
                                    <?php if ($a['estado'] == 'activa'): ?>
                                        <div class="dropdown mt-1">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Cambiar</button>
                                            <ul class="dropdown-menu bg-dark">
                                                <li><a class="dropdown-item" href="?cambiar_estado=<?php echo $a['id']; ?>&estado=completada">✅ Completar</a></li>
                                                <li><a class="dropdown-item" href="?cambiar_estado=<?php echo $a['id']; ?>&estado=cancelada">❌ Cancelar</a></li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-info" onclick="verDetalle(<?php echo $a['id']; ?>)" title="Ver detalle"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-warning" onclick="editarAsignacion(<?php echo $a['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger" onclick="eliminarAsignacion(<?php echo $a['id']; ?>, '<?php echo $a['placa']; ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal CREAR/EDITAR -->
<div class="modal fade" id="modalAsignacion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-tasks me-2"></i> Nueva Asignación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAsignacion" method="POST" action="guardar.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="asignacion_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehículo *</label>
                            <select class="form-select" name="vehiculo_id" id="vehiculo_id" required>
                                <option value="">Seleccione un vehículo</option>
                                <?php foreach ($vehiculos_disponibles as $v): ?>
                                    <option value="<?php echo $v['id']; ?>"><?php echo $v['placa']; ?> - <?php echo $v['marca'] . ' ' . $v['modelo']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Solo vehículos activos</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Conductor *</label>
                            <select class="form-select" name="conductor_id" id="conductor_id" required>
                                <option value="">Seleccione un conductor</option>
                                <?php foreach ($conductores_disponibles as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Solo conductores disponibles</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Asignación *</label>
                            <input type="date" class="form-control" name="fecha_asignacion" id="fecha_asignacion" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Fin *</label>
                            <input type="date" class="form-control" name="fecha_fin" id="fecha_fin" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo / Descripción</label>
                        <input type="text" class="form-control" name="motivo" id="motivo" placeholder="Ej: Asignación para ruta minera">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" id="observaciones" rows="2" placeholder="Notas adicionales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Asignación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal DETALLE -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-tasks me-2"></i> Detalle de Asignación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContent"></div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
let currentAsignacionId = null;

function limpiarFormulario() {
    document.getElementById('formAsignacion').reset();
    document.getElementById('asignacion_id').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-tasks me-2"></i> Nueva Asignación';
    document.getElementById('fecha_asignacion').value = new Date().toISOString().slice(0,10);
}

function editarAsignacion(id) {
    fetch(`get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                for(let key in data.asignacion) {
                    let el = document.getElementById(key);
                    if(el) el.value = data.asignacion[key] || '';
                }
                document.getElementById('asignacion_id').value = data.asignacion.id;
                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Asignación';
                new bootstrap.Modal(document.getElementById('modalAsignacion')).show();
            }
        });
}

function verDetalle(id) {
    currentAsignacionId = id;
    fetch(`detalle.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalleContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
}

function eliminarAsignacion(id, placa) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Eliminar asignación del vehículo ${placa}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `index.php?delete_id=${id}`;
    });
}

document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
    const search = document.getElementById('searchInput').value;
    const estado = document.getElementById('estadoFilter').value;
    const fecha_desde = document.getElementById('fechaDesde').value;
    const fecha_hasta = document.getElementById('fechaHasta').value;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&estado=${estado}&fecha_desde=${fecha_desde}&fecha_hasta=${fecha_hasta}`;
});

document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaAsignaciones');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Asignaciones');
    XLSX.writeFile(wb, `asignaciones_${new Date().toISOString().slice(0,19)}.xlsx`);
});

document.getElementById('btnExportPDF')?.addEventListener('click', function() {
    const tabla = document.getElementById('tablaAsignaciones');
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head><title>Asignaciones H&H MINERIA</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            .header { text-align: center; border-bottom: 2px solid #3b82f6; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #3b82f6; color: white; padding: 10px; }
            td { border: 1px solid #ddd; padding: 8px; }
        </style>
        </head><body>
        <div class="header"><h1>H&H MINERIA</h1><p>Listado de Asignaciones - ${new Date().toLocaleString()}</p></div>
        ${tabla.outerHTML}
        </body></html>
    `);
    ventana.document.close();
    ventana.print();
});

document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') document.getElementById('applyFiltersBtn').click();
});
</script>

<?php include '../../includes/footer.php'; ?>