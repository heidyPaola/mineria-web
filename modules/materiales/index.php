<?php
// modules/materiales/index.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Procesar eliminación
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    $check = "SELECT COUNT(*) as total FROM viajes WHERE material_id = :id";
    $stmt = $conn->prepare($check);
    $stmt->execute([':id' => $id]);
    $viajes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($viajes > 0) {
        $_SESSION['error_msg'] = "No se puede eliminar porque tiene $viajes viajes asociados.";
        header('Location: index.php');
        exit();
    }
    
    $query = "UPDATE materiales SET estado = 0 WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'ELIMINAR', 'materiales', $id);
    header('Location: index.php?msg=eliminado');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';
$stock_bajo = isset($_GET['stock_bajo']) ? true : false;

$query = "SELECT m.*, 
          (SELECT COUNT(*) FROM viajes WHERE material_id = m.id) as total_viajes,
          (SELECT SUM(peso) FROM viajes WHERE material_id = m.id) as total_peso
          FROM materiales m WHERE m.estado = 1";
$params = [];

if (!empty($search)) {
    $query .= " AND (m.nombre LIKE :search OR m.codigo LIKE :search OR m.categoria LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($categoria_filtro)) {
    $query .= " AND m.categoria = :categoria";
    $params[':categoria'] = $categoria_filtro;
}
if ($stock_bajo) {
    $query .= " AND m.stock_actual <= m.stock_minimo";
}

$query .= " ORDER BY m.nombre ASC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($materiales);
$stock_total = array_sum(array_column($materiales, 'stock_actual'));
$stock_bajo_count = count(array_filter($materiales, fn($m) => $m['stock_actual'] <= $m['stock_minimo']));
$precio_promedio = round(array_sum(array_column($materiales, 'precio_unitario')) / max($total, 1), 2);

// Categorías únicas
$categorias = $conn->query("SELECT DISTINCT categoria FROM materiales WHERE categoria IS NOT NULL AND estado = 1")->fetchAll(PDO::FETCH_COLUMN);
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-material {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .material-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        background: linear-gradient(135deg, #10b981, #059669);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }
    .stock-bajo {
        background: #ef444420;
        color: #ef4444;
        border: 1px solid #ef4444;
        border-radius: 20px;
        padding: 2px 8px;
        font-size: 10px;
    }
    .stock-normal {
        background: #10b98120;
        color: #10b981;
        border-radius: 20px;
        padding: 2px 8px;
        font-size: 10px;
    }
    .categoria-tag {
        background: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 10px;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-cubes me-2"></i> Materiales</h2>
            <p class="text-muted mb-0">Gestión de minerales y materiales de transporte</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-gradient-outline" id="btnExportExcel"><i class="fas fa-file-excel me-2"></i>Excel</button>
                <button class="btn btn-gradient-outline" id="btnExportPDF"><i class="fas fa-file-pdf me-2"></i>PDF</button>
            </div>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalMaterial" onclick="limpiarFormulario()">
                <i class="fas fa-plus me-2"></i>Nuevo Material
            </button>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'creado') echo '✅ Material creado exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo '✅ Material actualizado exitosamente';
                elseif ($_GET['msg'] == 'eliminado') echo '🗑️ Material eliminado exitosamente';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-material">
                <i class="fas fa-cubes fa-2x mb-2" style="color: #10b981;"></i>
                <h3><?php echo $total; ?></h3>
                <small>MATERIALES</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-material">
                <i class="fas fa-weight-hanging fa-2x mb-2" style="color: #3b82f6;"></i>
                <h3><?php echo number_format($stock_total, 0); ?></h3>
                <small>STOCK TOTAL (TN)</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-material">
                <i class="fas fa-exclamation-triangle fa-2x mb-2" style="color: #f59e0b;"></i>
                <h3><?php echo $stock_bajo_count; ?></h3>
                <small>STOCK BAJO</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-material">
                <i class="fas fa-dollar-sign fa-2x mb-2" style="color: #f59e0b;"></i>
                <h3>S/ <?php echo number_format($precio_promedio, 0); ?></h3>
                <small>PRECIO PROMEDIO</small>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 Buscar por nombre, código o categoría..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select id="categoriaFilter" class="form-select">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $categoria_filtro == $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-2">
                    <input type="checkbox" id="stockBajoFilter" class="form-check-input" <?php echo $stock_bajo ? 'checked' : ''; ?>>
                    <label class="form-check-label">Mostrar solo stock bajo</label>
                </div>
            </div>
            <div class="col-md-2">
                <button id="applyFiltersBtn" class="btn btn-gradient w-100">Aplicar</button>
            </div>
        </div>
    </div>
    
    <!-- Tabla -->
    <div class="card-glass p-3">
        <div class="table-responsive">
            <table class="table table-glass" id="tablaMateriales">
                <thead>
                    <tr>
                        <th style="width: 50px">ID</th>
                        <th>Material</th>
                        <th>Código</th>
                        <th>Categoría</th>
                        <th>Unidad</th>
                        <th>Stock</th>
                        <th>Precio</th>
                        <th>Viajes</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materiales)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No hay materiales registrados</td></tr>
                    <?php else: ?>
                        <?php foreach ($materiales as $m): ?>
                            <?php $stock_bajo_alert = $m['stock_actual'] <= $m['stock_minimo'] && $m['stock_minimo'] > 0; ?>
                            <tr>
                                <td><?php echo $m['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="material-icon"><i class="fas fa-gem"></i></div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($m['nombre']); ?></strong>
                                            <?php if ($m['codigo_barras']): ?>
                                                <br><small class="text-muted"><?php echo $m['codigo_barras']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?php echo $m['codigo'] ?: '---'; ?></code></td>
                                <td><span class="categoria-tag"><?php echo $m['categoria']; ?></span></td>
                                <td><?php echo $m['unidad_medida']; ?></td>
                                <td>
                                    <span class="<?php echo $stock_bajo_alert ? 'stock-bajo' : 'stock-normal'; ?>">
                                        <?php echo number_format($m['stock_actual'], 2); ?>
                                    </span>
                                    <?php if ($stock_bajo_alert): ?>
                                        <br><small class="text-danger">Mínimo: <?php echo number_format($m['stock_minimo'], 2); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    S/ <?php echo number_format($m['precio_unitario'], 2); ?>
                                    <?php if ($m['ultimo_precio_compra']): ?>
                                        <br><small>Compra: S/ <?php echo number_format($m['ultimo_precio_compra'], 2); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $m['total_viajes'] ?? 0; ?> viajes</span>
                                    <br><small><?php echo number_format($m['total_peso'] ?? 0, 0); ?> TN</small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-info" onclick="verDetalle(<?php echo $m['id']; ?>)" title="Ver detalle"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-warning" onclick="editarMaterial(<?php echo $m['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger" onclick="eliminarMaterial(<?php echo $m['id']; ?>, '<?php echo htmlspecialchars($m['nombre']); ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
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
<div class="modal fade" id="modalMaterial" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-cubes me-2"></i> Nuevo Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formMaterial" method="POST" action="guardar.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="material_id">
                    
                    <ul class="nav nav-tabs mb-3 border-secondary">
                        <li class="nav-item"><a class="nav-link active" data-tab="basicos" href="#">📋 Datos Básicos</a></li>
                        <li class="nav-item"><a class="nav-link" data-tab="inventario" href="#">📦 Inventario</a></li>
                        <li class="nav-item"><a class="nav-link" data-tab="precios" href="#">💰 Precios</a></li>
                    </ul>
                    
                    <div id="tab-basicos" class="tab-pane active">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del Material *</label>
                                <input type="text" class="form-control" name="nombre" id="nombre" required placeholder="Ej: Cobre, Hierro, Oro">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" name="codigo" id="codigo" placeholder="MAT-001">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" name="categoria" id="categoria">
                                    <option value="Mineral">Mineral</option>
                                    <option value="Concentrado">Concentrado</option>
                                    <option value="Relave">Relave</option>
                                    <option value="Insumo">Insumo</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unidad de Medida *</label>
                                <select class="form-select" name="unidad_medida" id="unidad_medida" required>
                                    <option value="Tonelada">Tonelada (TN)</option>
                                    <option value="Kilogramo">Kilogramo (KG)</option>
                                    <option value="Metro">Metro (M)</option>
                                    <option value="Litro">Litro (L)</option>
                                    <option value="Unidad">Unidad (UND)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ubicación / Almacén</label>
                            <input type="text" class="form-control" name="ubicacion" id="ubicacion" placeholder="Ej: Almacén Central, Zona A">
                        </div>
                    </div>
                    
                    <div id="tab-inventario" class="tab-pane" style="display:none">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Actual</label>
                                <input type="number" class="form-control" name="stock_actual" id="stock_actual" step="0.01" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Mínimo (Alerta)</label>
                                <input type="number" class="form-control" name="stock_minimo" id="stock_minimo" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código de Barras</label>
                                <input type="text" class="form-control" name="codigo_barras" id="codigo_barras" placeholder="Código QR/Barras">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proveedor</label>
                                <input type="text" class="form-control" name="proveedor" id="proveedor" placeholder="Nombre del proveedor">
                            </div>
                        </div>
                    </div>
                    
                    <div id="tab-precios" class="tab-pane" style="display:none">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Precio Unitario (Venta) *</label>
                                <input type="number" class="form-control" name="precio_unitario" id="precio_unitario" step="0.01" required placeholder="S/ 0.00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Último Precio de Compra</label>
                                <input type="number" class="form-control" name="ultimo_precio_compra" id="ultimo_precio_compra" step="0.01" placeholder="S/ 0.00">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha Última Compra</label>
                                <input type="date" class="form-control" name="ultima_compra" id="ultima_compra">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notas / Observaciones</label>
                        <textarea class="form-control" name="notas" id="notas" rows="2" placeholder="Información adicional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Material</button>
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
                <h5 class="modal-title"><i class="fas fa-cubes me-2"></i> Detalle del Material</h5>
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
let currentMaterialId = null;

function limpiarFormulario() {
    document.getElementById('formMaterial').reset();
    document.getElementById('material_id').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-cubes me-2"></i> Nuevo Material';
    mostrarTab('basicos');
}

function mostrarTab(tab) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    document.getElementById(`tab-${tab}`).style.display = 'block';
    document.querySelectorAll('[data-tab]').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
}

function editarMaterial(id) {
    fetch(`get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                for(let key in data.material) {
                    let el = document.getElementById(key);
                    if(el) el.value = data.material[key] || '';
                }
                document.getElementById('material_id').value = data.material.id;
                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Material';
                new bootstrap.Modal(document.getElementById('modalMaterial')).show();
            }
        });
}

function verDetalle(id) {
    currentMaterialId = id;
    fetch(`detalle.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalleContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
}

function eliminarMaterial(id, nombre) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Eliminar material "${nombre}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
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
    const categoria = document.getElementById('categoriaFilter').value;
    const stock_bajo = document.getElementById('stockBajoFilter').checked ? 1 : 0;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&categoria=${categoria}&stock_bajo=${stock_bajo}`;
});

// Excel
document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaMateriales');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Materiales');
    XLSX.writeFile(wb, `materiales_${new Date().toISOString().slice(0,19)}.xlsx`);
});

// PDF
document.getElementById('btnExportPDF')?.addEventListener('click', function() {
    const tabla = document.getElementById('tablaMateriales');
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head><title>Materiales H&H MINERIA</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            .header { text-align: center; border-bottom: 2px solid #10b981; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #10b981; color: white; padding: 10px; }
            td { border: 1px solid #ddd; padding: 8px; }
        </style>
        </head><body>
        <div class="header"><h1>H&H MINERIA</h1><p>Listado de Materiales - ${new Date().toLocaleString()}</p></div>
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