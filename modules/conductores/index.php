<?php
// modules/conductores/index.php - VERSIÓN MEJORADA
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Procesar eliminación
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    $check = "SELECT COUNT(*) as total FROM viajes WHERE conductor_id = :id";
    $stmt = $conn->prepare($check);
    $stmt->execute([':id' => $id]);
    $viajes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($viajes > 0) {
        $_SESSION['error_msg'] = "No se puede eliminar porque tiene $viajes viajes asociados.";
        header('Location: index.php');
        exit();
    }
    
    $query = "DELETE FROM conductores WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'ELIMINAR', 'conductores', $id);
    header('Location: index.php?msg=eliminado');
    exit();
}

// Procesar cambio de estado rápido
if (isset($_GET['cambiar_estado']) && isset($_GET['estado'])) {
    $id = $_GET['cambiar_estado'];
    $nuevo_estado = $_GET['estado'];
    $query = "UPDATE conductores SET estado = :estado WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
    header('Location: index.php?msg=estado');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$order_by = $_GET['order_by'] ?? 'id';
$order_dir = $_GET['order_dir'] ?? 'DESC';

$allowed_order = ['id', 'nombre', 'estado', 'calificacion', 'total_viajes'];
if (!in_array($order_by, $allowed_order)) $order_by = 'id';
$order_dir = ($order_dir == 'ASC') ? 'ASC' : 'DESC';

// Construir consulta
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM viajes WHERE conductor_id = c.id) as viajes_count,
          (SELECT SUM(peso) FROM viajes WHERE conductor_id = c.id) as toneladas_total
          FROM conductores c WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (c.nombre LIKE :search OR c.licencia LIKE :search OR c.telefono LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($estado_filtro)) {
    $query .= " AND c.estado = :estado";
    $params[':estado'] = $estado_filtro;
}

$query .= " ORDER BY $order_by $order_dir";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$conductores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_conductores = count($conductores);
$disponibles = count(array_filter($conductores, fn($c) => $c['estado'] == 'disponible'));
$ocupados = count(array_filter($conductores, fn($c) => $c['estado'] == 'ocupado'));
$calificacion_promedio = round(array_sum(array_column($conductores, 'calificacion')) / max($total_conductores, 1), 1);

// Top conductores
$top_query = "SELECT c.nombre, COUNT(v.id) as viajes, SUM(v.peso) as peso
              FROM conductores c
              LEFT JOIN viajes v ON c.id = v.conductor_id
              GROUP BY c.id
              ORDER BY viajes DESC LIMIT 5";
$top_conductores = $conn->query($top_query)->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-driver {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 16px;
        padding: 20px;
        transition: all 0.3s;
    }
    .stat-card-driver:hover {
        transform: translateY(-3px);
        border-color: #10b981;
    }
    .driver-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #10b981, #059669);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: bold;
        color: white;
    }
    .rating-stars i {
        color: #fbbf24;
        font-size: 12px;
    }
    .estado-selector {
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 12px;
    }
    .estado-disponible { background: #10b98120; color: #10b981; border: 1px solid #10b981; }
    .estado-ocupado { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b; }
    .estado-vacaciones { background: #8b5cf620; color: #8b5cf6; border: 1px solid #8b5cf6; }
    .estado-inactivo { background: #6b728020; color: #6b7280; border: 1px solid #6b7280; }
    .quick-stats { font-size: 12px; color: #9ca3af; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2>
                <i class="fas fa-id-card me-2"></i> Conductores
                <span class="badge bg-gradient ms-2"><?php echo $total_conductores; ?> registros</span>
            </h2>
            <p class="text-muted mb-0">Gestión completa de conductores mineros</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-gradient-outline" id="btnExportExcel"><i class="fas fa-file-excel me-2"></i>Excel</button>
                <button class="btn btn-gradient-outline" id="btnExportPDF"><i class="fas fa-file-pdf me-2"></i>PDF</button>
            </div>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalConductor" onclick="limpiarFormulario()">
                <i class="fas fa-plus me-2"></i>Nuevo Conductor
            </button>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-<?php echo $_GET['msg'] == 'eliminado' ? 'danger' : 'success'; ?> alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'creado') echo '✅ Conductor creado exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo '✅ Conductor actualizado exitosamente';
                elseif ($_GET['msg'] == 'eliminado') echo '🗑️ Conductor eliminado exitosamente';
                elseif ($_GET['msg'] == 'estado') echo '🔄 Estado actualizado exitosamente';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-driver text-center">
                <i class="fas fa-users fa-2x mb-2" style="color: #10b981;"></i>
                <h3 class="mb-0"><?php echo $total_conductores; ?></h3>
                <small>TOTAL CONDUCTORES</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-driver text-center">
                <i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i>
                <h3 class="mb-0"><?php echo $disponibles; ?></h3>
                <small>DISPONIBLES</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-driver text-center">
                <i class="fas fa-clock fa-2x mb-2" style="color: #f59e0b;"></i>
                <h3 class="mb-0"><?php echo $ocupados; ?></h3>
                <small>OCUPADOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-driver text-center">
                <i class="fas fa-star fa-2x mb-2" style="color: #fbbf24;"></i>
                <h3 class="mb-0"><?php echo $calificacion_promedio; ?></h3>
                <small>CALIFICACIÓN PROMEDIO</small>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-secondary"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre, licencia, teléfono..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select id="estadoFilter" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="disponible" <?php echo $estado_filtro == 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                    <option value="ocupado" <?php echo $estado_filtro == 'ocupado' ? 'selected' : ''; ?>>Ocupado</option>
                    <option value="vacaciones" <?php echo $estado_filtro == 'vacaciones' ? 'selected' : ''; ?>>Vacaciones</option>
                    <option value="inactivo" <?php echo $estado_filtro == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="orderBySelect" class="form-select">
                    <option value="id" <?php echo $order_by == 'id' ? 'selected' : ''; ?>>Ordenar por ID</option>
                    <option value="nombre" <?php echo $order_by == 'nombre' ? 'selected' : ''; ?>>Ordenar por Nombre</option>
                    <option value="estado" <?php echo $order_by == 'estado' ? 'selected' : ''; ?>>Ordenar por Estado</option>
                    <option value="calificacion" <?php echo $order_by == 'calificacion' ? 'selected' : ''; ?>>Ordenar por Calificación</option>
                    <option value="total_viajes" <?php echo $order_by == 'total_viajes' ? 'selected' : ''; ?>>Ordenar por Viajes</option>
                </select>
            </div>
            <div class="col-md-2">
                <button id="applyFiltersBtn" class="btn btn-gradient w-100">Aplicar</button>
            </div>
        </div>
    </div>
    
    <!-- Tabla de Conductores -->
    <div class="card-glass p-3">
        <div class="table-responsive">
            <table class="table table-glass" id="tablaConductores">
                <thead>
                    <tr>
                        <th style="width: 60px">ID</th>
                        <th>Conductor</th>
                        <th>Licencia</th>
                        <th>Contacto</th>
                        <th>Calif.</th>
                        <th>Viajes</th>
                        <th>Toneladas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conductores as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="driver-avatar">
                                    <?php echo strtoupper(substr($c['nombre'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($c['nombre']); ?></strong>
                                    <div class="quick-stats">
                                        <?php if ($c['experiencia_anos'] > 0): ?>
                                            <i class="fas fa-briefcase"></i> <?php echo $c['experiencia_anos']; ?> años
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <code><?php echo $c['licencia']; ?></code>
                            <?php if ($c['fecha_vencimiento_licencia']): ?>
                                <br><small class="text-muted">Vence: <?php echo date('d/m/Y', strtotime($c['fecha_vencimiento_licencia'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['telefono']): ?>
                                <i class="fas fa-phone me-1 text-muted"></i> <?php echo $c['telefono']; ?><br>
                            <?php endif; ?>
                            <?php if ($c['email']): ?>
                                <i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars(substr($c['email'], 0, 20)); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['calificacion'] > 0): ?>
                                <div class="rating-stars">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= round($c['calificacion']) ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <small><?php echo $c['calificacion']; ?>/5</small>
                            <?php else: ?>
                                <span class="text-muted">Sin calificar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $c['viajes_count'] ?? 0; ?> viajes</span>
                        </td>
                        <td><?php echo number_format($c['toneladas_total'] ?? 0, 0); ?> TN</td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm estado-<?php echo $c['estado']; ?> estado-selector dropdown-toggle" data-bs-toggle="dropdown">
                                    <?php echo ucfirst($c['estado']); ?>
                                </button>
                                <ul class="dropdown-menu bg-dark">
                                    <li><a class="dropdown-item text-success" href="?cambiar_estado=<?php echo $c['id']; ?>&estado=disponible">Disponible</a></li>
                                    <li><a class="dropdown-item text-warning" href="?cambiar_estado=<?php echo $c['id']; ?>&estado=ocupado">Ocupado</a></li>
                                    <li><a class="dropdown-item text-info" href="?cambiar_estado=<?php echo $c['id']; ?>&estado=vacaciones">Vacaciones</a></li>
                                    <li><a class="dropdown-item text-secondary" href="?cambiar_estado=<?php echo $c['id']; ?>&estado=inactivo">Inactivo</a></li>
                                </ul>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-info" onclick="verDetalle(<?php echo $c['id']; ?>)" title="Ver detalle"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-warning" onclick="editarConductor(<?php echo $c['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger" onclick="eliminarConductor(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['nombre']); ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Top Conductores -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card-glass p-3">
                <h6 class="mb-3"><i class="fas fa-trophy me-2" style="color: #f59e0b;"></i> Top 5 Conductores con más viajes</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-glass">
                        <thead>
                            <tr><th>#</th><th>Conductor</th><th>Viajes</th><th>Toneladas</th></tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($top_conductores as $top): ?>
                            <tr><td><?php echo $rank++; ?></td><td><?php echo htmlspecialchars($top['nombre']); ?></td><td><?php echo $top['viajes']; ?></td><td><?php echo number_format($top['peso'] ?? 0, 0); ?> TN</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal CREAR/EDITAR -->
<div class="modal fade" id="modalConductor" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-id-card me-2"></i> Nuevo Conductor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formConductor" method="POST" action="guardar.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id" id="conductor_id">
                    <ul class="nav nav-tabs mb-3" id="conductorTabs">
                        <li class="nav-item"><a class="nav-link active" data-tab="basicos">Datos Básicos</a></li>
                        <li class="nav-item"><a class="nav-link" data-tab="documentos">Documentos</a></li>
                        <li class="nav-item"><a class="nav-link" data-tab="evaluacion">Evaluación</a></li>
                    </ul>
                    
                    <div id="tab-basicos" class="tab-pane active">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label>Nombre Completo *</label><input type="text" class="form-control" name="nombre" id="nombre" required></div>
                            <div class="col-md-6 mb-3"><label>Licencia *</label><input type="text" class="form-control" name="licencia" id="licencia" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label>Teléfono</label><input type="text" class="form-control" name="telefono" id="telefono"></div>
                            <div class="col-md-6 mb-3"><label>Email</label><input type="email" class="form-control" name="email" id="email"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label>Fecha Nacimiento</label><input type="date" class="form-control" name="fecha_nacimiento" id="fecha_nacimiento"></div>
                            <div class="col-md-6 mb-3"><label>Años Experiencia</label><input type="number" class="form-control" name="experiencia_anos" id="experiencia_anos" min="0" max="50"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label>Vencimiento Licencia</label><input type="date" class="form-control" name="fecha_vencimiento_licencia" id="fecha_vencimiento_licencia"></div>
                            <div class="col-md-6 mb-3"><label>Teléfono Emergencia</label><input type="text" class="form-control" name="numero_emergencia" id="numero_emergencia"></div>
                        </div>
                        <div class="mb-3"><label>Dirección</label><textarea class="form-control" name="direccion" id="direccion" rows="2"></textarea></div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label>Estado</label><select class="form-select" name="estado" id="estado"><option value="disponible">Disponible</option><option value="ocupado">Ocupado</option><option value="vacaciones">Vacaciones</option><option value="inactivo">Inactivo</option></select></div>
                            <div class="col-md-6 mb-3"><label>Calificación (1-5)</label><input type="number" class="form-control" name="calificacion" id="calificacion" step="0.1" min="0" max="5"></div>
                        </div>
                    </div>
                    
                    <div id="tab-documentos" class="tab-pane" style="display:none">
                        <div class="mb-3"><label>Foto de Perfil</label><input type="file" class="form-control" name="foto" accept="image/*"></div>
                        <div class="mb-3"><label>Documentos (CV, Certificados)</label><input type="file" class="form-control" name="documentos" multiple></div>
                    </div>
                    
                    <div id="tab-evaluacion" class="tab-pane" style="display:none">
                        <div class="row">
                            <div class="col-md-4 mb-3"><label>Puntuación General</label><input type="number" class="form-control" name="puntuacion" id="puntuacion" step="1" min="0" max="100"></div>
                            <div class="col-md-4 mb-3"><label>Total Viajes</label><input type="number" class="form-control" name="total_viajes" id="total_viajes" readonly></div>
                            <div class="col-md-4 mb-3"><label>Total Toneladas</label><input type="number" class="form-control" name="total_toneladas" id="total_toneladas" step="0.01" readonly></div>
                        </div>
                        <div class="mb-3"><label>Último Servicio</label><input type="date" class="form-control" name="ultimo_servicio" id="ultimo_servicio"></div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Conductor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal DETALLE -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-id-card me-2"></i> Detalle del Conductor</h5>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
let currentConductorId = null;

function limpiarFormulario() {
    document.getElementById('formConductor').reset();
    document.getElementById('conductor_id').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-id-card me-2"></i> Nuevo Conductor';
    mostrarTab('basicos');
}

function mostrarTab(tab) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    document.getElementById(`tab-${tab}`).style.display = 'block';
    document.querySelectorAll('[data-tab]').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
}

function editarConductor(id) {
    fetch(`get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                for(let key in data.conductor) {
                    let el = document.getElementById(key);
                    if(el) el.value = data.conductor[key] || '';
                }
                document.getElementById('conductor_id').value = data.conductor.id;
                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Conductor';
                new bootstrap.Modal(document.getElementById('modalConductor')).show();
            }
        });
}

function verDetalle(id) {
    currentConductorId = id;
    fetch(`detalle.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalleContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
}

function eliminarConductor(id, nombre) {
    Swal.fire({title:'¿Estás seguro?', text:`¿Eliminar a "${nombre}"?`, icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Sí, eliminar'})
        .then((result) => { if(result.isConfirmed) window.location.href = `index.php?delete_id=${id}`; });
}

document.querySelectorAll('[data-tab]').forEach(tab => {
    tab.addEventListener('click', (e) => {
        e.preventDefault();
        mostrarTab(tab.getAttribute('data-tab'));
    });
});

document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
    const search = document.getElementById('searchInput').value;
    const estado = document.getElementById('estadoFilter').value;
    const order_by = document.getElementById('orderBySelect').value;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&estado=${estado}&order_by=${order_by}`;
});

document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaConductores');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Conductores');
    XLSX.writeFile(wb, `conductores_${new Date().toISOString().slice(0,19)}.xlsx`);
});

// Exportar a PDF - Versión mejorada (CORREGIDA)
document.getElementById('btnExportPDF')?.addEventListener('click', function() {
    // Obtener la tabla y clonarla
    const tablaOriginal = document.getElementById('tablaConductores');
    const tablaCopia = tablaOriginal.cloneNode(true);
    
    // Eliminar botones de acción y dropdowns de la copia
    tablaCopia.querySelectorAll('.btn-group, .dropdown, .estado-selector, .btn').forEach(el => el.remove());
    
    // Reemplazar los botones de estado por texto plano
    tablaCopia.querySelectorAll('td').forEach(td => {
        if (td.innerHTML.includes('dropdown')) {
            const estadoText = td.innerText.split('\n')[0];
            td.innerHTML = estadoText;
        }
    });
    
    // Crear ventana de impresión
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>H&H MINERIA - Conductores</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f59e0b; }
                .header h1 { color: #f59e0b; margin: 0; }
                .header p { color: #666; font-size: 12px; margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #f59e0b; color: white; padding: 10px; text-align: left; }
                td { border: 1px solid #ddd; padding: 8px; }
                tr:nth-child(even) { background: #f9f9f9; }
                .footer { text-align: center; margin-top: 20px; font-size: 10px; color: #999; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🏗️ H&H MINERIA</h1>
                <p>Listado de Conductores</p>
                <p>Generado: ${new Date().toLocaleString()}</p>
            </div>
            ${tablaCopia.outerHTML}
            <div class="footer">
                <p>Sistema de Gestión Minera - Reporte generado automáticamente</p>
            </div>
        </body>
        </html>
    `);
    ventana.document.close();
    ventana.print();
});
</script>