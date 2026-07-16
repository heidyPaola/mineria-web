<?php
// modules/rutas/index.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Procesar eliminación
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    $check = "SELECT COUNT(*) as total FROM viajes WHERE ruta_id = :id";
    $stmt = $conn->prepare($check);
    $stmt->execute([':id' => $id]);
    $viajes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($viajes > 0) {
        $_SESSION['error_msg'] = "No se puede eliminar porque tiene $viajes viajes asociados.";
        header('Location: index.php');
        exit();
    }
    
    $query = "UPDATE rutas SET estado = 0 WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'ELIMINAR', 'rutas', $id);
    header('Location: index.php?msg=eliminado');
    exit();
}

// Cambiar estado rápido
if (isset($_GET['cambiar_estado']) && isset($_GET['estado'])) {
    $id = $_GET['cambiar_estado'];
    $nuevo_estado = $_GET['estado'];
    $query = "UPDATE rutas SET estado = :estado WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
    header('Location: index.php?msg=estado');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$tipo_filtro = $_GET['tipo'] ?? '';
$dificultad_filtro = $_GET['dificultad'] ?? '';

$query = "SELECT r.*, 
          (SELECT COUNT(*) FROM viajes WHERE ruta_id = r.id) as total_viajes,
          (SELECT SUM(peso) FROM viajes WHERE ruta_id = r.id) as total_peso
          FROM rutas r WHERE r.estado = 1";
$params = [];

if (!empty($search)) {
    $query .= " AND (r.origen LIKE :search OR r.destino LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($tipo_filtro)) {
    $query .= " AND r.tipo = :tipo";
    $params[':tipo'] = $tipo_filtro;
}
if (!empty($dificultad_filtro)) {
    $query .= " AND r.dificultad = :dificultad";
    $params[':dificultad'] = $dificultad_filtro;
}

$query .= " ORDER BY r.id DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($rutas);
$distancia_total = array_sum(array_column($rutas, 'distancia'));
$viajes_totales = array_sum(array_column($rutas, 'total_viajes'));

// Tipos únicos
$tipos = $conn->query("SELECT DISTINCT tipo FROM rutas WHERE tipo IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$dificultades = ['baja', 'media', 'alta', 'extrema'];
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-ruta {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(124, 58, 237, 0.05));
        border: 1px solid rgba(139, 92, 246, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .ruta-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }
    .dificultad-baja { background: #10b98120; color: #10b981; border: 1px solid #10b981; }
    .dificultad-media { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b; }
    .dificultad-alta { background: #ef444420; color: #ef4444; border: 1px solid #ef4444; }
    .dificultad-extrema { background: #8b5cf620; color: #8b5cf6; border: 1px solid #8b5cf6; }
    .ruta-origen-destino {
        font-family: monospace;
        font-size: 13px;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-map-marked-alt me-2"></i> Rutas</h2>
            <p class="text-muted mb-0">Gestión de rutas de transporte minero</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-gradient-outline" id="btnExportExcel"><i class="fas fa-file-excel me-2"></i>Excel</button>
                <button class="btn btn-gradient-outline" id="btnExportPDF"><i class="fas fa-file-pdf me-2"></i>PDF</button>
            </div>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalRuta" onclick="limpiarFormulario()">
                <i class="fas fa-plus me-2"></i>Nueva Ruta
            </button>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'creado') echo '✅ Ruta creada exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo '✅ Ruta actualizada exitosamente';
                elseif ($_GET['msg'] == 'eliminado') echo '🗑️ Ruta eliminada exitosamente';
                elseif ($_GET['msg'] == 'estado') echo '🔄 Estado actualizado exitosamente';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-ruta">
                <i class="fas fa-route fa-2x mb-2" style="color: #8b5cf6;"></i>
                <h3><?php echo $total; ?></h3>
                <small>TOTAL RUTAS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-ruta">
                <i class="fas fa-road fa-2x mb-2" style="color: #3b82f6;"></i>
                <h3><?php echo number_format($distancia_total, 0); ?> <small>km</small></h3>
                <small>DISTANCIA TOTAL</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-ruta">
                <i class="fas fa-truck fa-2x mb-2" style="color: #10b981;"></i>
                <h3><?php echo $viajes_totales; ?></h3>
                <small>VIAJES REALIZADOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-ruta">
                <i class="fas fa-clock fa-2x mb-2" style="color: #f59e0b;"></i>
                <h3><?php echo round($distancia_total / max($total, 1), 1); ?> <small>km</small></h3>
                <small>PROMEDIO POR RUTA</small>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 Buscar por origen o destino..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select id="tipoFilter" class="form-select">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $tipo_filtro == $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="dificultadFilter" class="form-select">
                    <option value="">Todas las dificultades</option>
                    <option value="baja" <?php echo $dificultad_filtro == 'baja' ? 'selected' : ''; ?>>Baja</option>
                    <option value="media" <?php echo $dificultad_filtro == 'media' ? 'selected' : ''; ?>>Media</option>
                    <option value="alta" <?php echo $dificultad_filtro == 'alta' ? 'selected' : ''; ?>>Alta</option>
                    <option value="extrema" <?php echo $dificultad_filtro == 'extrema' ? 'selected' : ''; ?>>Extrema</option>
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
            <table class="table table-glass" id="tablaRutas">
                <thead>
                    <tr>
                        <th style="width: 50px">ID</th>
                        <th>Ruta</th>
                        <th>Origen → Destino</th>
                        <th>Distancia</th>
                        <th>Tiempo</th>
                        <th>Dificultad</th>
                        <th>Tipo</th>
                        <th>Viajes</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rutas)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No hay rutas registradas</td></tr>
                    <?php else: ?>
                        <?php foreach ($rutas as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="ruta-icon"><i class="fas fa-map-marker-alt"></i></div>
                                    <div>
                                        <strong><?php echo $r['origen']; ?> → <?php echo $r['destino']; ?></strong>
                                        <?php if ($r['puntos_intermedios']): ?>
                                            <br><small class="text-muted"><i class="fas fa-stop"></i> <?php echo substr($r['puntos_intermedios'], 0, 30); ?>...</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                             </tr>
                            <td>
                                <div class="ruta-origen-destino">
                                    <i class="fas fa-flag-checkered text-muted"></i> <?php echo $r['origen']; ?><br>
                                    <i class="fas fa-arrow-down text-muted"></i><br>
                                    <i class="fas fa-flag-checkered text-muted"></i> <?php echo $r['destino']; ?>
                                </div>
                             </tr>
                            <td><?php echo number_format($r['distancia'], 1); ?> km</td>
                            <td><?php echo $r['tiempo_estimado']; ?> min</td>
                            <td>
                                <span class="badge dificultad-<?php echo $r['dificultad']; ?>">
                                    <?php echo ucfirst($r['dificultad']); ?>
                                </span>
                             </tr>
                            <td><span class="categoria-tag"><?php echo $r['tipo']; ?></span></td>
                            <td>
                                <span class="badge bg-info"><?php echo $r['total_viajes']; ?> viajes</span>
                                <br><small><?php echo number_format($r['total_peso'] ?? 0, 0); ?> TN</small>
                             </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-info" onclick="verDetalle(<?php echo $r['id']; ?>)" title="Ver detalle"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-warning" onclick="editarRuta(<?php echo $r['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger" onclick="eliminarRuta(<?php echo $r['id']; ?>, '<?php echo $r['origen']; ?> → <?php echo $r['destino']; ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
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
<div class="modal fade" id="modalRuta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-map-marked-alt me-2"></i> Nueva Ruta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRuta" method="POST" action="guardar.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="ruta_id">
                    
                    <ul class="nav nav-tabs mb-3 border-secondary">
                        <li class="nav-item"><a class="nav-link active" data-tab="basicos" href="#">📍 Datos Básicos</a></li>
                        <li class="nav-item"><a class="nav-link" data-tab="detalles" href="#">⚙️ Detalles</a></li>
                        <li class="nav-item"><a class="nav-link" data-tab="restricciones" href="#">🚧 Restricciones</a></li>
                    </ul>
                    
                    <div id="tab-basicos" class="tab-pane active">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Origen *</label>
                                <input type="text" class="form-control" name="origen" id="origen" required placeholder="Ej: Mina Cerro Verde">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Destino *</label>
                                <input type="text" class="form-control" name="destino" id="destino" required placeholder="Ej: Puerto Matarani">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Distancia (km) *</label>
                                <input type="number" class="form-control" name="distancia" id="distancia" step="0.1" required placeholder="0.00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tiempo Estimado (minutos) *</label>
                                <input type="number" class="form-control" name="tiempo_estimado" id="tiempo_estimado" required placeholder="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Puntos Intermedios</label>
                            <textarea class="form-control" name="puntos_intermedios" id="puntos_intermedios" rows="2" placeholder="Ej: Pueblo Nuevo, Puente Río, Caseta Control..."></textarea>
                        </div>
                    </div>
                    
                    <div id="tab-detalles" class="tab-pane" style="display:none">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Ruta</label>
                                <select class="form-select" name="tipo" id="tipo">
                                    <option value="Terrestre">Terrestre</option>
                                    <option value="Minería">Minería</option>
                                    <option value="Urbana">Urbana</option>
                                    <option value="Mixta">Mixta</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dificultad</label>
                                <select class="form-select" name="dificultad" id="dificultad">
                                    <option value="baja">Baja</option>
                                    <option value="media">Media</option>
                                    <option value="alta">Alta</option>
                                    <option value="extrema">Extrema</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Peligrosidad</label>
                                <select class="form-select" name="peligrosidad" id="peligrosidad">
                                    <option value="baja">Baja</option>
                                    <option value="media">Media</option>
                                    <option value="alta">Alta</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado" id="estado">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Condiciones de la Ruta</label>
                            <textarea class="form-control" name="condiciones" id="condiciones" rows="2" placeholder="Asfaltado, trocha, zonas peligrosas..."></textarea>
                        </div>
                    </div>
                    
                    <div id="tab-restricciones" class="tab-pane" style="display:none">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Restricción de Peso (TN)</label>
                                <input type="number" class="form-control" name="restriccion_peso" id="restriccion_peso" step="0.5" placeholder="Ej: 40">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Restricción de Altura (m)</label>
                                <input type="number" class="form-control" name="restriccion_altura" id="restriccion_altura" step="0.1" placeholder="Ej: 4.5">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Coordenadas Origen</label>
                                <input type="text" class="form-control" name="coordenadas_origen" id="coordenadas_origen" placeholder="Lat, Lng">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Coordenadas Destino</label>
                                <input type="text" class="form-control" name="coordenadas_destino" id="coordenadas_destino" placeholder="Lat, Lng">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Último Mantenimiento</label>
                            <input type="date" class="form-control" name="ultimo_mantenimiento" id="ultimo_mantenimiento">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Ruta</button>
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
                <h5 class="modal-title"><i class="fas fa-map-marked-alt me-2"></i> Detalle de la Ruta</h5>
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
let currentRutaId = null;

function limpiarFormulario() {
    document.getElementById('formRuta').reset();
    document.getElementById('ruta_id').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-map-marked-alt me-2"></i> Nueva Ruta';
    mostrarTab('basicos');
}

function mostrarTab(tab) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    document.getElementById(`tab-${tab}`).style.display = 'block';
    document.querySelectorAll('[data-tab]').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
}

function editarRuta(id) {
    fetch(`get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                for(let key in data.ruta) {
                    let el = document.getElementById(key);
                    if(el) el.value = data.ruta[key] || '';
                }
                document.getElementById('ruta_id').value = data.ruta.id;
                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Ruta';
                new bootstrap.Modal(document.getElementById('modalRuta')).show();
            }
        });
}

function verDetalle(id) {
    currentRutaId = id;
    fetch(`detalle.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalleContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
}

function eliminarRuta(id, nombre) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Eliminar ruta ${nombre}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `index.php?delete_id=${id}`;
    });
}

document.querySelectorAll('[data-tab]').forEach(tab => {
    tab.addEventListener('click', (e) => {
        e.preventDefault();
        mostrarTab(tab.getAttribute('data-tab'));
    });
});

document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
    const search = document.getElementById('searchInput').value;
    const tipo = document.getElementById('tipoFilter').value;
    const dificultad = document.getElementById('dificultadFilter').value;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&tipo=${tipo}&dificultad=${dificultad}`;
});

document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaRutas');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Rutas');
    XLSX.writeFile(wb, `rutas_${new Date().toISOString().slice(0,19)}.xlsx`);
});

document.getElementById('btnExportPDF')?.addEventListener('click', function() {
    const tabla = document.getElementById('tablaRutas');
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head><title>Rutas H&H MINERIA</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            .header { text-align: center; border-bottom: 2px solid #8b5cf6; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #8b5cf6; color: white; padding: 10px; }
            td { border: 1px solid #ddd; padding: 8px; }
        </style>
        </head><body>
        <div class="header"><h1>H&H MINERIA</h1><p>Listado de Rutas - ${new Date().toLocaleString()}</p></div>
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