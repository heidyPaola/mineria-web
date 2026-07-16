<?php
// modules/vehiculos/index.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Procesar eliminación
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    $check = "SELECT COUNT(*) as total FROM viajes WHERE vehiculo_id = :id";
    $stmt = $conn->prepare($check);
    $stmt->execute([':id' => $id]);
    $viajes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($viajes > 0) {
        $_SESSION['error_msg'] = "No se puede eliminar porque tiene $viajes viajes asociados.";
        header('Location: index.php');
        exit();
    }
    
    $query = "DELETE FROM vehiculos WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'ELIMINAR', 'vehiculos', $id);
    header('Location: index.php?msg=eliminado');
    exit();
}

// Cambiar estado rápido
if (isset($_GET['cambiar_estado']) && isset($_GET['estado'])) {
    $id = $_GET['cambiar_estado'];
    $nuevo_estado = $_GET['estado'];
    $query = "UPDATE vehiculos SET estado = :estado WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
    header('Location: index.php?msg=estado');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$marca_filtro = $_GET['marca'] ?? '';

$query = "SELECT v.*, 
          (SELECT COUNT(*) FROM viajes WHERE vehiculo_id = v.id) as viajes_count,
          (SELECT SUM(peso) FROM viajes WHERE vehiculo_id = v.id) as toneladas_total
          FROM vehiculos v WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (v.placa LIKE :search OR v.marca LIKE :search OR v.modelo LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($estado_filtro)) {
    $query .= " AND v.estado = :estado";
    $params[':estado'] = $estado_filtro;
}
if (!empty($marca_filtro)) {
    $query .= " AND v.marca = :marca";
    $params[':marca'] = $marca_filtro;
}

$query .= " ORDER BY v.id DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($vehiculos);
$activos = count(array_filter($vehiculos, fn($v) => $v['estado'] == 'activo'));
$mantenimiento = count(array_filter($vehiculos, fn($v) => $v['estado'] == 'mantenimiento'));
$inactivos = count(array_filter($vehiculos, fn($v) => $v['estado'] == 'inactivo'));

// Marcas únicas para filtro
$marcas = $conn->query("SELECT DISTINCT marca FROM vehiculos ORDER BY marca")->fetchAll(PDO::FETCH_COLUMN);
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-vehiculo {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .stat-card-vehiculo:hover {
        transform: translateY(-3px);
        border-color: #3b82f6;
    }
    .stat-card-vehiculo h3 {
        font-size: 2rem;
        margin: 10px 0 0;
        color: #3b82f6;
    }
    .vehiculo-placa {
        font-family: monospace;
        font-size: 16px;
        font-weight: bold;
        background: rgba(245, 158, 11, 0.2);
        display: inline-block;
        padding: 4px 10px;
        border-radius: 8px;
        color: #f59e0b;
    }
    .estado-activo { background: #10b98120; color: #10b981; border: 1px solid #10b981; }
    .estado-mantenimiento { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b; }
    .estado-inactivo { background: #6b728020; color: #6b7280; border: 1px solid #6b7280; }
    .vehiculo-avatar {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
    }
    .documento-badge {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 10px;
        display: inline-block;
        margin: 2px;
    }
    .documento-vencido {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }
    .btn-group-sm .btn {
        padding: 4px 8px;
        font-size: 12px;
    }
</style>

<div class="container-fluid">
    <!-- Header con botones -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-truck me-2"></i> Vehículos</h2>
            <p class="text-muted mb-0">Gestión completa de la flota vehicular</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-gradient-outline" id="btnExportExcel">
                <i class="fas fa-file-excel me-2"></i>Excel
            </button>
            <button class="btn btn-gradient-outline" id="btnExportPDF">
                <i class="fas fa-file-pdf me-2"></i>PDF
            </button>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalVehiculo" onclick="limpiarFormulario()">
                <i class="fas fa-plus me-2"></i>Nuevo Vehículo
            </button>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'creado') echo '✅ Vehículo creado exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo '✅ Vehículo actualizado exitosamente';
                elseif ($_GET['msg'] == 'eliminado') echo '🗑️ Vehículo eliminado exitosamente';
                elseif ($_GET['msg'] == 'estado') echo '🔄 Estado actualizado exitosamente';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-vehiculo">
                <i class="fas fa-truck fa-2x" style="color: #3b82f6;"></i>
                <h3><?php echo $total; ?></h3>
                <small>TOTAL VEHÍCULOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-vehiculo">
                <i class="fas fa-check-circle fa-2x" style="color: #10b981;"></i>
                <h3><?php echo $activos; ?></h3>
                <small>ACTIVOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-vehiculo">
                <i class="fas fa-tools fa-2x" style="color: #f59e0b;"></i>
                <h3><?php echo $mantenimiento; ?></h3>
                <small>MANTENIMIENTO</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-vehiculo">
                <i class="fas fa-ban fa-2x" style="color: #6b7280;"></i>
                <h3><?php echo $inactivos; ?></h3>
                <small>INACTIVOS</small>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 Buscar por placa, marca, modelo..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select id="estadoFilter" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="activo" <?php echo $estado_filtro == 'activo' ? 'selected' : ''; ?>>Activo</option>
                    <option value="mantenimiento" <?php echo $estado_filtro == 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                    <option value="inactivo" <?php echo $estado_filtro == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="marcaFilter" class="form-select">
                    <option value="">Todas las marcas</option>
                    <?php foreach ($marcas as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $marca_filtro == $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button id="applyFiltersBtn" class="btn btn-gradient w-100">Aplicar</button>
            </div>
        </div>
    </div>
    
    <!-- Tabla -->
    <div class="card-glass p-3">
        <div class="table-responsive">
            <table class="table table-glass" id="tablaVehiculos">
                <thead>
                    <tr>
                        <th style="width: 50px">ID</th>
                        <th>Vehículo</th>
                        <th>Placa</th>
                        <th>Marca/Modelo</th>
                        <th>Capacidad</th>
                        <th>Documentos</th>
                        <th>Viajes</th>
                        <th>Estado</th>
                        <th style="width: 120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehiculos)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No hay vehículos registrados</td></tr>
                    <?php else: ?>
                        <?php foreach ($vehiculos as $v): ?>
                        <tr>
                            <td><?php echo $v['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="vehiculo-avatar"><i class="fas fa-truck"></i></div>
                                    <div>
                                        <strong><?php echo $v['marca'] . ' ' . $v['modelo']; ?></strong>
                                        <br><small class="text-muted"><?php echo $v['color'] ?: 'Sin color'; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="vehiculo-placa"><?php echo $v['placa']; ?></span></td>
                            <td>
                                <?php echo $v['marca']; ?><br>
                                <small class="text-muted"><?php echo $v['modelo'] . ' - ' . ($v['año'] ?? 'N/A'); ?></small>
                            </td>
                            <td><?php echo number_format($v['capacidad'], 2); ?> TN</td>
                            <td>
                                <?php 
                                $docs = [];
                                if ($v['soat_vencimiento']) {
                                    $vencido = strtotime($v['soat_vencimiento']) < time();
                                    $docs[] = '<span class="documento-badge ' . ($vencido ? 'documento-vencido' : '') . '">SOAT</span>';
                                }
                                if ($v['revision_tecnica']) {
                                    $vencido = strtotime($v['revision_tecnica']) < time();
                                    $docs[] = '<span class="documento-badge ' . ($vencido ? 'documento-vencido' : '') . '">Rev.Téc</span>';
                                }
                                echo implode(' ', $docs) ?: '<span class="text-muted">---</span>';
                                ?>
                             </td>
                            <td>
                                <span class="badge bg-info"><?php echo $v['viajes_count'] ?? 0; ?> viajes</span>
                                <br><small><?php echo number_format($v['toneladas_total'] ?? 0, 0); ?> TN</small>
                             </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm estado-<?php echo $v['estado']; ?> dropdown-toggle" data-bs-toggle="dropdown">
                                        <?php echo ucfirst($v['estado']); ?>
                                    </button>
                                    <ul class="dropdown-menu bg-dark">
                                        <li><a class="dropdown-item text-success" href="?cambiar_estado=<?php echo $v['id']; ?>&estado=activo">✅ Activo</a></li>
                                        <li><a class="dropdown-item text-warning" href="?cambiar_estado=<?php echo $v['id']; ?>&estado=mantenimiento">🔧 Mantenimiento</a></li>
                                        <li><a class="dropdown-item text-secondary" href="?cambiar_estado=<?php echo $v['id']; ?>&estado=inactivo">⛔ Inactivo</a></li>
                                    </ul>
                                </div>
                             </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-info" onclick="verDetalle(<?php echo $v['id']; ?>)" title="Ver detalle"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-warning" onclick="editarVehiculo(<?php echo $v['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger" onclick="eliminarVehiculo(<?php echo $v['id']; ?>, '<?php echo $v['placa']; ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
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
<div class="modal fade" id="modalVehiculo" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-truck me-2"></i> Nuevo Vehículo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVehiculo" method="POST" action="guardar.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="vehiculo_id">
                    <ul class="nav nav-tabs mb-3 border-secondary">
                        <li class="nav-item"><a class="nav-link active" data-tab="basicos" href="#">📋 Datos Básicos</a></li>
                        <li class="nav-item"><a class="nav-link" data-tab="documentos" href="#">📄 Documentos</a></li>
                        <li class="nav-item"><a class="nav-link" data-tab="mantenimiento" href="#">🔧 Mantenimiento</a></li>
                    </ul>
                    
                    <div id="tab-basicos" class="tab-pane active">
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Placa *</label><input type="text" class="form-control" name="placa" id="placa" required placeholder="ABC-123"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Marca *</label><input type="text" class="form-control" name="marca" id="marca" required placeholder="Volvo"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Modelo *</label><input type="text" class="form-control" name="modelo" id="modelo" required placeholder="FH16"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Año</label><input type="number" class="form-control" name="año" id="año" placeholder="2022"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Capacidad (TN)</label><input type="number" class="form-control" name="capacidad" id="capacidad" step="0.01" placeholder="40.00"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Color</label><input type="text" class="form-control" name="color" id="color" placeholder="Blanco"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Estado</label>
                                <select class="form-select" name="estado" id="estado">
                                    <option value="activo">Activo</option>
                                    <option value="mantenimiento">Mantenimiento</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Número Motor</label><input type="text" class="form-control" name="numero_motor" id="numero_motor" placeholder="Motor #"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Número Chasis</label><input type="text" class="form-control" name="numero_chasis" id="numero_chasis" placeholder="Chasis #"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" id="observaciones" rows="2" placeholder="Notas adicionales..."></textarea></div>
                    </div>
                    
                    <div id="tab-documentos" class="tab-pane" style="display:none">
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">SOAT Vencimiento</label><input type="date" class="form-control" name="soat_vencimiento" id="soat_vencimiento"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Revisión Técnica</label><input type="date" class="form-control" name="revision_tecnica" id="revision_tecnica"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Seguro Vencimiento</label><input type="date" class="form-control" name="seguro_vencimiento" id="seguro_vencimiento"></div>
                        </div>
                    </div>
                    
                    <div id="tab-mantenimiento" class="tab-pane" style="display:none">
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Último Mantenimiento</label><input type="date" class="form-control" name="ultimo_mantenimiento" id="ultimo_mantenimiento"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Próximo Mantenimiento</label><input type="date" class="form-control" name="proximo_mantenimiento" id="proximo_mantenimiento"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Kilometraje</label><input type="number" class="form-control" name="kilometraje" id="kilometraje" placeholder="0"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Vehículo</button>
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
                <h5 class="modal-title"><i class="fas fa-truck me-2"></i> Detalle del Vehículo</h5>
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
let currentVehiculoId = null;

function limpiarFormulario() {
    document.getElementById('formVehiculo').reset();
    document.getElementById('vehiculo_id').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-truck me-2"></i> Nuevo Vehículo';
    mostrarTab('basicos');
}

function mostrarTab(tab) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    document.getElementById(`tab-${tab}`).style.display = 'block';
    document.querySelectorAll('[data-tab]').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
}

function editarVehiculo(id) {
    fetch(`get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                for(let key in data.vehiculo) {
                    let el = document.getElementById(key);
                    if(el) el.value = data.vehiculo[key] || '';
                }
                document.getElementById('vehiculo_id').value = data.vehiculo.id;
                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Vehículo';
                new bootstrap.Modal(document.getElementById('modalVehiculo')).show();
            }
        });
}

function verDetalle(id) {
    currentVehiculoId = id;
    fetch(`detalle.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalleContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
}

function eliminarVehiculo(id, placa) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Eliminar vehículo ${placa}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `index.php?delete_id=${id}`;
    });
}

// Tabs
document.querySelectorAll('[data-tab]').forEach(tab => {
    tab.addEventListener('click', (e) => {
        e.preventDefault();
        mostrarTab(tab.getAttribute('data-tab'));
    });
});

// Filtros
document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
    const search = document.getElementById('searchInput').value;
    const estado = document.getElementById('estadoFilter').value;
    const marca = document.getElementById('marcaFilter').value;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&estado=${estado}&marca=${marca}`;
});

// Excel
document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaVehiculos');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Vehículos');
    XLSX.writeFile(wb, `vehiculos_${new Date().toISOString().slice(0,19)}.xlsx`);
});

// PDF
document.getElementById('btnExportPDF')?.addEventListener('click', function() {
    const tabla = document.getElementById('tablaVehiculos');
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head><title>Vehículos H&H MINERIA</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 30px; }
            .header { text-align: center; border-bottom: 2px solid #f59e0b; margin-bottom: 20px; padding-bottom: 10px; }
            .header h1 { color: #f59e0b; margin: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #f59e0b; color: white; padding: 10px; text-align: left; }
            td { border: 1px solid #ddd; padding: 8px; }
            tr:nth-child(even) { background: #f9f9f9; }
            .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
        </style>
        </head><body>
        <div class="header"><h1>🏗️ H&H MINERIA</h1><p>Listado de Vehículos - ${new Date().toLocaleString()}</p></div>
        ${tabla.outerHTML}
        <div class="footer"><p>Sistema de Gestión Minera - Reporte generado automáticamente</p></div>
        </body></html>
    `);
    ventana.document.close();
    ventana.print();
});

// Buscar con Enter
document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') document.getElementById('applyFiltersBtn').click();
});
</script>

<?php include '../../includes/footer.php'; ?>