<?php
// modules/clientes/index.php
// Versión Mejorada - Con filtros, exportación y estadísticas

require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// ========== PROCESAR ELIMINACIÓN ==========
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // Verificar si tiene viajes asociados
    $check = "SELECT COUNT(*) as total FROM viajes WHERE cliente_id = :id";
    $stmt = $conn->prepare($check);
    $stmt->execute([':id' => $id]);
    $viajes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($viajes > 0) {
        $_SESSION['error_msg'] = "No se puede eliminar el cliente porque tiene $viajes viajes asociados.";
        header('Location: index.php');
        exit();
    }
    
    $query = "UPDATE clientes SET estado = 0 WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    
    registrarAuditoria($conn, 'ELIMINAR', 'clientes', $id);
    
    header('Location: index.php?msg=eliminado');
    exit();
}

// ========== OBTENER ESTADÍSTICAS ==========

// Total clientes
$query = "SELECT COUNT(*) as total FROM clientes WHERE estado = 1";
$stmt = $conn->query($query);
$total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Clientes con más viajes
$query = "SELECT c.id, c.nombre, COUNT(v.id) as total_viajes, SUM(v.peso) as total_peso
          FROM clientes c
          LEFT JOIN viajes v ON c.id = v.cliente_id
          WHERE c.estado = 1
          GROUP BY c.id
          ORDER BY total_viajes DESC
          LIMIT 5";
$stmt = $conn->query($query);
$top_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== FILTROS ==========
$search = $_GET['search'] ?? '';
$order_by = $_GET['order_by'] ?? 'id';
$order_dir = $_GET['order_dir'] ?? 'DESC';

// Validar order_by para evitar inyección SQL
$allowed_order = ['id', 'nombre', 'ruc', 'created_at'];
if (!in_array($order_by, $allowed_order)) {
    $order_by = 'id';
}
$order_dir = ($order_dir == 'ASC') ? 'ASC' : 'DESC';

// Construir consulta con filtro
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM viajes WHERE cliente_id = c.id) as total_viajes,
          (SELECT SUM(peso) FROM viajes WHERE cliente_id = c.id) as total_peso
          FROM clientes c 
          WHERE c.estado = 1";

$params = [];

if (!empty($search)) {
    $query .= " AND (c.nombre LIKE :search OR c.ruc LIKE :search OR c.email LIKE :search OR c.telefono LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY $order_by $order_dir";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-mini {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
        border: 1px solid rgba(245, 158, 11, 0.2);
        border-radius: 12px;
        padding: 15px;
        transition: all 0.3s;
    }
    .stat-card-mini:hover {
        transform: translateY(-3px);
        border-color: var(--primary-orange);
    }
    .client-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary-orange), var(--primary-orange-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
    }
    .filtro-active {
        background: var(--primary-orange);
        color: white !important;
    }
    .nav-tabs-custom .nav-link {
        color: var(--text-gray);
        border: none;
        padding: 10px 20px;
    }
    .nav-tabs-custom .nav-link.active {
        color: var(--primary-orange);
        background: transparent;
        border-bottom: 2px solid var(--primary-orange);
    }
    .timeline-item {
        border-left: 2px solid var(--primary-orange);
        padding-left: 20px;
        margin-bottom: 20px;
        position: relative;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--primary-orange);
    }
</style>

<div class="container-fluid">
    <!-- Título -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2>
                <i class="fas fa-users me-2"></i> Clientes
                <span class="badge bg-gradient ms-2"><?php echo $total_clientes; ?> registros</span>
            </h2>
            <p class="text-muted mb-0">Gestión completa de clientes mineros</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button type="button" class="btn btn-gradient-outline" id="btnExportExcel">
                    <i class="fas fa-file-excel me-2"></i> Excel
                </button>
                <button type="button" class="btn btn-gradient-outline" id="btnExportPDF">
                    <i class="fas fa-file-pdf me-2"></i> PDF
                </button>
            </div>
            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalCliente" onclick="limpiarFormulario()">
                <i class="fas fa-plus me-2"></i> Nuevo Cliente
            </button>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-<?php echo strpos($_GET['msg'], 'error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo strpos($_GET['msg'], 'error') !== false ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
            <?php 
                if ($_GET['msg'] == 'creado') echo 'Cliente creado exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo 'Cliente actualizado exitosamente';
                elseif ($_GET['msg'] == 'eliminado') echo 'Cliente eliminado exitosamente';
                elseif ($_GET['msg'] == 'error') echo $_SESSION['error_msg'] ?? 'Error en la operación';
                unset($_SESSION['error_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
<!-- Estadísticas Rápidas -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card-mini text-center">
            <i class="fas fa-building fa-2x mb-2" style="color: #f59e0b;"></i>
            <h4><?php echo $total_clientes; ?></h4>
            <small>CLIENTES ACTIVOS</small>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card-mini text-center">
            <i class="fas fa-truck fa-2x mb-2" style="color: #10b981;"></i>
            <h4><?php echo number_format(array_sum(array_column($clientes, 'total_viajes')), 0); ?></h4>
            <small>VIAJES TOTALES</small>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card-mini text-center">
            <i class="fas fa-weight-hanging fa-2x mb-2" style="color: #3b82f6;"></i>
            <h4><?php echo number_format(array_sum(array_column($clientes, 'total_peso')), 0); ?> <span style="font-size: 14px;">TN</span></h4>
            <small>TONELADAS</small>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card-mini text-center">
            <i class="fas fa-calendar-alt fa-2x mb-2" style="color: #8b5cf6;"></i>
            <h4><?php echo date('Y'); ?></h4>
            <small>AÑO ACTUAL</small>
        </div>
    </div>
</div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-secondary">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre, RUC, email o teléfono..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select id="orderBySelect" class="form-select">
                    <option value="id" <?php echo $order_by == 'id' ? 'selected' : ''; ?>>Ordenar por ID</option>
                    <option value="nombre" <?php echo $order_by == 'nombre' ? 'selected' : ''; ?>>Ordenar por Nombre</option>
                    <option value="ruc" <?php echo $order_by == 'ruc' ? 'selected' : ''; ?>>Ordenar por RUC</option>
                    <option value="created_at" <?php echo $order_by == 'created_at' ? 'selected' : ''; ?>>Ordenar por Fecha</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="btn-group w-100">
                    <button id="orderDirBtn" class="btn btn-secondary">
                        <i class="fas fa-arrow-<?php echo $order_dir == 'DESC' ? 'down' : 'up'; ?>"></i>
                        <?php echo $order_dir == 'DESC' ? 'Descendente' : 'Ascendente'; ?>
                    </button>
                    <button id="applyFiltersBtn" class="btn btn-gradient">Aplicar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabla de Clientes -->
    <div class="card-glass p-3">
        <div class="table-responsive">
            <table class="table table-glass" id="tablaClientes">
                <thead>
                    <tr>
                        <th style="width: 50px">ID</th>
                        <th>Cliente</th>
                        <th>RUC</th>
                        <th>Contacto</th>
                        <th>Viajes</th>
                        <th>Toneladas</th>
                        <th>Registro</th>
                        <th style="width: 120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td><?php echo $cliente['id']; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="client-avatar">
                                    <?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong>
                                    <?php if (!empty($cliente['direccion'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($cliente['direccion'], 0, 40)); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <code><?php echo $cliente['ruc']; ?></code>
                        </td>
                        <td>
                            <?php if ($cliente['telefono']): ?>
                                <i class="fas fa-phone me-1 text-muted"></i> <?php echo $cliente['telefono']; ?><br>
                            <?php endif; ?>
                            <?php if ($cliente['email']): ?>
                                <i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($cliente['email']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $cliente['total_viajes']; ?> viajes</span>
                        </td>
                        <td>
                            <?php echo number_format($cliente['total_peso'], 2); ?> TN
                        </td>
                        <td>
                            <small><?php echo date('d/m/Y', strtotime($cliente['created_at'])); ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-info" onclick="verDetalle(<?php echo $cliente['id']; ?>)" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-warning" onclick="editarCliente(<?php echo $cliente['id']; ?>)" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger" onclick="eliminarCliente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars($cliente['nombre']); ?>')" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-2 d-block"></i>
                            No hay clientes registrados
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Top Clientes -->
    <?php if (!empty($top_clientes)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card-glass p-3">
                <h6 class="mb-3"><i class="fas fa-trophy me-2" style="color: #f59e0b;"></i> Top 5 Clientes con más viajes</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-glass">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Viajes</th>
                                <th>Toneladas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($top_clientes as $top): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($top['nombre']); ?></td>
                                <td><?php echo $top['total_viajes']; ?></td>
                                <td><?php echo number_format($top['total_peso'], 2); ?> TN</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ========== MODAL CLIENTE (CREAR/EDITAR) ========== -->
<div class="modal fade" id="modalCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitulo">
                    <i class="fas fa-user-tie me-2"></i> Nuevo Cliente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCliente" method="POST" action="guardar.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="cliente_id">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label" style="color: #d1d5db !important;">Nombre / Razón Social *</label>
                            <input type="text" class="form-control" name="nombre" id="nombre" required 
                                   placeholder="Ej: Minera Los Andes S.A.C."
                                   style="background: rgba(255,255,255,0.1); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <small style="color: #9ca3af; font-size: 12px;">Ejemplo: Minera Los Andes S.A.C.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="color: #d1d5db !important;">RUC *</label>
                            <input type="text" class="form-control" name="ruc" id="ruc" required maxlength="11" 
                                   placeholder="20123456789"
                                   style="background: rgba(255,255,255,0.1); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <small style="color: #9ca3af; font-size: 12px;">Ejemplo: 20123456789</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: #d1d5db !important;">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" id="telefono" 
                                   placeholder="987654321"
                                   style="background: rgba(255,255,255,0.1); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <small style="color: #9ca3af; font-size: 12px;">Ejemplo: 987654321</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: #d1d5db !important;">Email</label>
                            <input type="email" class="form-control" name="email" id="email" 
                                   placeholder="contacto@minera.com"
                                   style="background: rgba(255,255,255,0.1); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <small style="color: #9ca3af; font-size: 12px;">Ejemplo: contacto@minera.com</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="color: #d1d5db !important;">Dirección</label>
                        <textarea class="form-control" name="direccion" id="direccion" rows="2" 
                                  placeholder="Av. Principal 123, Lima"
                                  style="background: rgba(255,255,255,0.1); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.2);"></textarea>
                        <small style="color: #9ca3af; font-size: 12px;">Ejemplo: Av. Principal 123, Lima</small>
                    </div>
                    
                    <hr class="border-secondary">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: #d1d5db !important;">Contacto Principal</label>
                            <input type="text" class="form-control" 
                                   placeholder="Juan Pérez"
                                   style="background: rgba(255,255,255,0.1); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <small style="color: #9ca3af; font-size: 12px;">Ejemplo: Juan Pérez</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: #d1d5db !important;">Cargo</label>
                            <input type="text" class="form-control" 
                                   placeholder="Gerente de Operaciones"
                                   style="background: rgba(255,255,255,0.1); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <small style="color: #9ca3af; font-size: 12px;">Ejemplo: Gerente de Operaciones</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save me-2"></i> Guardar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== MODAL DETALLE CLIENTE ========== -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-building me-2"></i> Detalle del Cliente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-warning" role="status"></div>
                    <p class="mt-2">Cargando información...</p>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-gradient" id="btnEditarDesdeDetalle">Editar Cliente</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
let currentClienteId = null;

// Limpiar formulario
function limpiarFormulario() {
    document.getElementById('formCliente').reset();
    document.getElementById('cliente_id').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-user-tie me-2"></i> Nuevo Cliente';
}

// Editar cliente
function editarCliente(id) {
    fetch(`get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cliente_id').value = data.cliente.id;
                document.getElementById('nombre').value = data.cliente.nombre;
                document.getElementById('ruc').value = data.cliente.ruc;
                document.getElementById('telefono').value = data.cliente.telefono || '';
                document.getElementById('email').value = data.cliente.email || '';
                document.getElementById('direccion').value = data.cliente.direccion || '';
                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Cliente';
                
                var modal = new bootstrap.Modal(document.getElementById('modalCliente'));
                modal.show();
            }
        })
        .catch(error => {
            Swal.fire('Error', 'No se pudo cargar el cliente', 'error');
        });
}

// Ver detalle completo
function verDetalle(id) {
    currentClienteId = id;
    const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
    
    fetch(`detalle.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalleContent').innerHTML = html;
            modal.show();
        })
        .catch(error => {
            document.getElementById('detalleContent').innerHTML = '<div class="text-center py-5 text-danger">Error al cargar los detalles</div>';
            modal.show();
        });
}

// Eliminar cliente
function eliminarCliente(id, nombre) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Deseas eliminar al cliente "${nombre}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `index.php?delete_id=${id}`;
        }
    });
}

// Exportar a Excel
document.getElementById('btnExportExcel')?.addEventListener('click', function() {
    const table = document.getElementById('tablaClientes');
    const ws = XLSX.utils.table_to_sheet(table, { raw: true });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Clientes');
    XLSX.writeFile(wb, `clientes_${new Date().toISOString().slice(0,19)}.xlsx`);
});

// Exportar a PDF
document.getElementById('btnExportPDF')?.addEventListener('click', async function() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    
    doc.setFontSize(16);
    doc.text('Listado de Clientes - H&H MINERIA', 14, 15);
    doc.setFontSize(10);
    doc.text(`Generado: ${new Date().toLocaleString()}`, 14, 25);
    
    const table = document.getElementById('tablaClientes');
    const rows = [];
    const headers = ['ID', 'Cliente', 'RUC', 'Teléfono', 'Email', 'Viajes', 'Toneladas'];
    rows.push(headers);
    
    for (let row of table.querySelectorAll('tbody tr')) {
        const cols = row.querySelectorAll('td');
        if (cols.length >= 7) {
            rows.push([
                cols[0]?.innerText || '',
                cols[1]?.innerText.split('\n')[0] || '',
                cols[2]?.innerText || '',
                cols[3]?.innerText.split('\n')[0] || '',
                cols[3]?.innerText.split('\n')[1] || '',
                cols[4]?.innerText || '',
                cols[5]?.innerText || ''
            ]);
        }
    }
    
    doc.autoTable({
        head: [headers],
        body: rows.slice(1),
        startY: 35,
        theme: 'dark',
        styles: { fontSize: 8, textColor: [255, 255, 255], fillColor: [18, 22, 35] },
        headStyles: { fillColor: [245, 158, 11], textColor: [0, 0, 0] }
    });
    
    doc.save(`clientes_${new Date().toISOString().slice(0,19)}.pdf`);
});

// Filtros
document.getElementById('applyFiltersBtn')?.addEventListener('click', function() {
    const search = document.getElementById('searchInput').value;
    const order_by = document.getElementById('orderBySelect').value;
    const order_dir = document.getElementById('orderDirBtn').innerText.includes('Descendente') ? 'DESC' : 'ASC';
    window.location.href = `index.php?search=${encodeURIComponent(search)}&order_by=${order_by}&order_dir=${order_dir}`;
});

document.getElementById('orderDirBtn')?.addEventListener('click', function() {
    const currentText = this.innerText;
    if (currentText.includes('Descendente')) {
        this.innerHTML = '<i class="fas fa-arrow-up"></i> Ascendente';
    } else {
        this.innerHTML = '<i class="fas fa-arrow-down"></i> Descendente';
    }
});

// Buscar al presionar Enter
document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('applyFiltersBtn').click();
    }
});

// Botón editar desde detalle
document.getElementById('btnEditarDesdeDetalle')?.addEventListener('click', function() {
    bootstrap.Modal.getInstance(document.getElementById('modalDetalle')).hide();
    editarCliente(currentClienteId);
});
</script>

<?php include '../../includes/footer.php'; ?>