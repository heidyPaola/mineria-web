<?php
// modules/auditoria/index.php
require_once '../../config/auth.php';
requireLogin();
requireRole('admin'); // Solo administradores

require_once '../../config/conexion.php';

$conn = getConnection();

// Limpiar registros antiguos (opcional)
if (isset($_GET['limpiar']) && $_GET['limpiar'] == 'si') {
    $fecha = $_GET['fecha'] ?? date('Y-m-d', strtotime('-30 days'));
    $query = "DELETE FROM auditoria WHERE created_at < :fecha";
    $stmt = $conn->prepare($query);
    $stmt->execute([':fecha' => $fecha . ' 00:00:00']);
    header('Location: index.php?msg=limpiado');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$usuario_filtro = $_GET['usuario'] ?? '';
$accion_filtro = $_GET['accion'] ?? '';
$tabla_filtro = $_GET['tabla'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$query = "SELECT a.* 
          FROM auditoria a
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (a.usuario_nombre LIKE :search OR a.accion LIKE :search OR a.tabla_afectada LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($usuario_filtro)) {
    $query .= " AND a.usuario_id = :usuario";
    $params[':usuario'] = $usuario_filtro;
}
if (!empty($accion_filtro)) {
    $query .= " AND a.accion = :accion";
    $params[':accion'] = $accion_filtro;
}
if (!empty($tabla_filtro)) {
    $query .= " AND a.tabla_afectada = :tabla";
    $params[':tabla'] = $tabla_filtro;
}
if (!empty($fecha_desde)) {
    $query .= " AND DATE(a.created_at) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}
if (!empty($fecha_hasta)) {
    $query .= " AND DATE(a.created_at) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY a.created_at DESC LIMIT 1000";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$auditoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($auditoria);

// Acciones por tipo
$acciones = [];
foreach ($auditoria as $a) {
    if (!isset($acciones[$a['accion']])) {
        $acciones[$a['accion']] = 0;
    }
    $acciones[$a['accion']]++;
}

// Tablas más afectadas
$tablas = [];
foreach ($auditoria as $a) {
    if (!isset($tablas[$a['tabla_afectada']])) {
        $tablas[$a['tabla_afectada']] = 0;
    }
    $tablas[$a['tabla_afectada']]++;
}

// Usuarios más activos
$usuarios = [];
foreach ($auditoria as $a) {
    $nombre = $a['usuario_nombre'] ?? 'Sistema';
    if (!isset($usuarios[$nombre])) {
        $usuarios[$nombre] = 0;
    }
    $usuarios[$nombre]++;
}

// Obtener lista de usuarios para filtro
$lista_usuarios = $conn->query("SELECT id, nombre FROM usuarios WHERE estado = 1 ORDER BY nombre")->fetchAll();

// Obtener acciones únicas
$acciones_unicas = $conn->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);
$tablas_unicas = $conn->query("SELECT DISTINCT tabla_afectada FROM auditoria ORDER BY tabla_afectada")->fetchAll(PDO::FETCH_COLUMN);
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-auditoria {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(124, 58, 237, 0.05));
        border: 1px solid rgba(139, 92, 246, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .stat-card-auditoria:hover {
        transform: translateY(-3px);
    }
    .accion-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
    }
    .accion-CREAR { background: #10b98120; color: #10b981; border: 1px solid #10b981; }
    .accion-ACTUALIZAR { background: #3b82f620; color: #3b82f6; border: 1px solid #3b82f6; }
    .accion-ELIMINAR { background: #ef444420; color: #ef4444; border: 1px solid #ef4444; }
    .accion-INICIO_SESION { background: #8b5cf620; color: #8b5cf6; border: 1px solid #8b5cf6; }
    .accion-CIERRE_SESION { background: #6b728020; color: #6b7280; border: 1px solid #6b7280; }
    .accion-CAMBIAR_ESTADO { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b; }
    
    .detalle-cambio {
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
        padding: 10px;
        margin-top: 5px;
        font-size: 12px;
    }
    .valor-antiguo { color: #ef4444; }
    .valor-nuevo { color: #10b981; }
    .ip-badge {
        background: rgba(255,255,255,0.05);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-family: monospace;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-history me-2"></i> Auditoría</h2>
            <p class="text-muted mb-0">Registro de todas las actividades del sistema</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-gradient-outline" id="btnExportExcel"><i class="fas fa-file-excel me-2"></i>Excel</button>
            <button class="btn btn-gradient-outline" id="btnExportPDF"><i class="fas fa-file-pdf me-2"></i>PDF</button>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalLimpiar">
                <i class="fas fa-trash-alt me-2"></i>Limpiar
            </button>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'limpiado') echo '✅ Registros antiguos eliminados exitosamente';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-auditoria">
                <i class="fas fa-list fa-2x mb-2" style="color: #8b5cf6;"></i>
                <h3><?php echo $total; ?></h3>
                <small>TOTAL REGISTROS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-auditoria" style="border-color: rgba(16, 185, 129, 0.3);">
                <i class="fas fa-user-circle fa-2x mb-2" style="color: #10b981;"></i>
                <h3><?php echo count($usuarios); ?></h3>
                <small>USUARIOS ACTIVOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-auditoria" style="border-color: rgba(245, 158, 11, 0.3);">
                <i class="fas fa-table fa-2x mb-2" style="color: #f59e0b;"></i>
                <h3><?php echo count($tablas); ?></h3>
                <small>TABLAS AFECTADAS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-auditoria" style="border-color: rgba(59, 130, 246, 0.3);">
                <i class="fas fa-calendar-day fa-2x mb-2" style="color: #3b82f6;"></i>
                <h3><?php echo date('d/m/Y'); ?></h3>
                <small>REGISTROS HOY</small>
            </div>
        </div>
    </div>
    
    <!-- Resumen de acciones -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card-glass p-3">
                <h6><i class="fas fa-chart-bar me-2"></i> Acciones por Tipo</h6>
                <div class="row">
                    <?php foreach ($acciones as $accion => $cantidad): ?>
                        <div class="col-6 col-md-4 mb-2">
                            <span class="accion-badge accion-<?php echo strtoupper($accion); ?>">
                                <?php echo $accion; ?>: <?php echo $cantidad; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card-glass p-3">
                <h6><i class="fas fa-users me-2"></i> Usuarios más activos</h6>
                <?php 
                arsort($usuarios);
                $top_usuarios = array_slice($usuarios, 0, 5);
                foreach ($top_usuarios as $usuario => $cantidad): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span><?php echo htmlspecialchars($usuario); ?></span>
                        <span class="badge bg-secondary"><?php echo $cantidad; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 Buscar..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select id="usuarioFilter" class="form-select">
                    <option value="">Todos los usuarios</option>
                    <?php foreach ($lista_usuarios as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $usuario_filtro == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select id="accionFilter" class="form-select">
                    <option value="">Todas las acciones</option>
                    <?php foreach ($acciones_unicas as $acc): ?>
                        <option value="<?php echo $acc; ?>" <?php echo $accion_filtro == $acc ? 'selected' : ''; ?>><?php echo $acc; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select id="tablaFilter" class="form-select">
                    <option value="">Todas las tablas</option>
                    <?php foreach ($tablas_unicas as $tab): ?>
                        <option value="<?php echo $tab; ?>" <?php echo $tabla_filtro == $tab ? 'selected' : ''; ?>><?php echo ucfirst($tab); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <input type="date" id="fechaDesde" class="form-control" placeholder="Desde" value="<?php echo $fecha_desde; ?>">
                    <input type="date" id="fechaHasta" class="form-control" placeholder="Hasta" value="<?php echo $fecha_hasta; ?>">
                </div>
            </div>
            <div class="col-md-12">
                <button id="applyFiltersBtn" class="btn btn-gradient"><i class="fas fa-filter me-2"></i>Aplicar Filtros</button>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-undo me-2"></i>Limpiar</a>
            </div>
        </div>
    </div>
    
<!-- Tabla -->
<div class="card-glass p-3">
    <div class="table-responsive">
        <table class="table table-glass" id="tablaAuditoria">
            <thead>
                <tr>
                    <th style="width: 50px">ID</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Tabla</th>
                    <th>Registro ID</th>
                    <th>Notas</th>
                    <th>Revisado</th>
                    <th>Detalle</th>
                    <th>IP</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditoria)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x d-block mb-2"></i>
                            No hay registros de auditoría
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($auditoria as $a): ?>
                    <tr>
                        <td><?php echo $a['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($a['usuario_nombre'] ?? 'Sistema'); ?></strong>
                            <?php if ($a['usuario_id']): ?>
                                <br><small class="text-muted">ID: <?php echo $a['usuario_id']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="accion-badge accion-<?php echo strtoupper($a['accion']); ?>">
                                <?php echo $a['accion']; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo ucfirst($a['tabla_afectada']); ?>
                        </td>
                        <td>
                            <?php if ($a['registro_id']): ?>
                                <code><?php echo $a['registro_id']; ?></code>
                            <?php else: ?>
                                <span class="text-muted">---</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['notas']): ?>
                                <span class="text-muted" title="<?php echo htmlspecialchars($a['notas']); ?>">
                                    <?php echo htmlspecialchars(substr($a['notas'], 0, 30)); ?>
                                    <?php if (strlen($a['notas'] ?? '') > 30): ?>...<?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">---</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['revisado']): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i> Revisado
                                </span>
                                <?php if ($a['fecha_revision']): ?>
                                    <br><small class="text-muted"><?php echo date('d/m/Y', strtotime($a['fecha_revision'])); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-clock me-1"></i> Pendiente
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['datos_anteriores'] || $a['datos_nuevos']): ?>
                                <button class="btn btn-sm btn-info" onclick="verCambios(<?php echo $a['id']; ?>)">
                                    <i class="fas fa-code"></i> Ver
                                </button>
                            <?php else: ?>
                                <span class="text-muted">---</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ip-badge"><?php echo $a['ip_address'] ?? 'N/A'; ?></span>
                        </td>
                        <td>
                            <small><?php echo date('d/m/Y H:i:s', strtotime($a['created_at'])); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========== MODAL VER/EDITAR CAMBIOS ========== -->
<div class="modal fade" id="modalCambios" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-code me-2"></i> Detalle de Cambios</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cambiosContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-warning"></div>
                    <p class="mt-2">Cargando...</p>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-gradient" onclick="guardarEdicionAuditoria()">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
                </button>
                <button type="button" class="btn btn-success" onclick="marcarRevisado()">
                    <i class="fas fa-check me-2"></i>Marcar como Revisado
                </button>
            </div>
        </div>
    </div>
</div>
<!-- ========== MODAL LIMPIAR ========== -->
<div class="modal fade" id="modalLimpiar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Limpiar Registros Antiguos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Eliminar registros de auditoría más antiguos que?</p>
                <select id="diasLimpiar" class="form-select">
                    <option value="30">30 días</option>
                    <option value="60">60 días</option>
                    <option value="90">90 días</option>
                    <option value="180">6 meses</option>
                    <option value="365">1 año</option>
                </select>
                <p class="text-danger mt-3"><i class="fas fa-exclamation-triangle me-2"></i> Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="limpiarRegistros()">
                    <i class="fas fa-trash-alt me-2"></i>Limpiar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
let currentAuditoriaId = null;
let currentDatos = null;

function verCambios(id) {
    currentAuditoriaId = id;
    const modal = new bootstrap.Modal(document.getElementById('modalCambios'));
    
    fetch(`get_cambios.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentDatos = data;
                
                let html = '';
                
                // Campos editables
                html += `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Usuario</label>
                            <input type="text" class="form-control" id="edit_usuario" value="${data.usuario_nombre || ''}">
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Acción</label>
                            <input type="text" class="form-control" id="edit_accion" value="${data.accion || ''}">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Tabla Afectada</label>
                            <input type="text" class="form-control" id="edit_tabla" value="${data.tabla_afectada || ''}">
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Registro ID</label>
                            <input type="text" class="form-control" id="edit_registro" value="${data.registro_id || ''}">
                        </div>
                    </div>
                `;
                
                // Datos Anteriores
                if (data.datos_anteriores) {
                    html += `
                        <h6 class="text-danger mt-3">📌 Datos Anteriores</h6>
                        <div class="detalle-cambio">
                            <textarea class="form-control" id="edit_anteriores" rows="6" style="background: rgba(255,255,255,0.05); color: #ef4444;">${JSON.stringify(JSON.parse(data.datos_anteriores), null, 2)}</textarea>
                        </div>
                    `;
                }
                
                // Datos Nuevos
                if (data.datos_nuevos) {
                    html += `
                        <h6 class="text-success mt-3">📌 Datos Nuevos</h6>
                        <div class="detalle-cambio">
                            <textarea class="form-control" id="edit_nuevos" rows="6" style="background: rgba(255,255,255,0.05); color: #10b981;">${JSON.stringify(JSON.parse(data.datos_nuevos), null, 2)}</textarea>
                        </div>
                    `;
                }
                
                // Notas
                html += `
                    <hr class="border-secondary">
                    <div class="mb-3">
                        <label class="text-muted small">📝 Notas / Comentarios</label>
                        <textarea class="form-control" id="edit_notas" rows="3" placeholder="Agregar notas o comentarios sobre este registro...">${data.notas || ''}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="text-muted small">Revisado</label>
                            <select class="form-select" id="edit_revisado">
                                <option value="0" ${data.revisado == 0 ? 'selected' : ''}>No</option>
                                <option value="1" ${data.revisado == 1 ? 'selected' : ''}>Sí</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Fecha Revisión</label>
                            <input type="text" class="form-control" value="${data.fecha_revision ? new Date(data.fecha_revision).toLocaleString() : 'No revisado'}" readonly>
                        </div>
                    </div>
                `;
                
                document.getElementById('cambiosContent').innerHTML = html;
                modal.show();
            } else {
                document.getElementById('cambiosContent').innerHTML = '<p class="text-center text-danger">Error al cargar los datos</p>';
                modal.show();
            }
        })
        .catch(() => {
            document.getElementById('cambiosContent').innerHTML = '<p class="text-center text-danger">Error de conexión</p>';
            modal.show();
        });
}

function guardarEdicionAuditoria() {
    const id = currentAuditoriaId;
    const data = {
        id: id,
        usuario_nombre: document.getElementById('edit_usuario').value,
        accion: document.getElementById('edit_accion').value,
        tabla_afectada: document.getElementById('edit_tabla').value,
        registro_id: document.getElementById('edit_registro').value,
        datos_anteriores: document.getElementById('edit_anteriores')?.value || null,
        datos_nuevos: document.getElementById('edit_nuevos')?.value || null,
        notas: document.getElementById('edit_notas').value,
        revisado: document.getElementById('edit_revisado').value
    };
    
    Swal.fire({
        title: '¿Guardar cambios?',
        text: 'Se actualizarán los datos de este registro de auditoría',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'Sí, guardar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('guardar_edicion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: 'Cambios guardados correctamente',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || 'No se pudo guardar',
                        confirmButtonColor: '#d33'
                    });
                }
            });
        }
    });
}

function marcarRevisado() {
    const id = currentAuditoriaId;
    
    Swal.fire({
        title: '¿Marcar como revisado?',
        text: 'Este registro será marcado como revisado',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Sí, marcar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('marcar_revisado.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + id
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Revisado',
                        text: 'Registro marcado como revisado',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }
    });
}

function limpiarRegistros() {
    const dias = document.getElementById('diasLimpiar').value;
    const fecha = new Date();
    fecha.setDate(fecha.getDate() - dias);
    const fechaStr = fecha.toISOString().slice(0,10);
    
    Swal.fire({
        title: '¿Estás seguro?',
        text: `Se eliminarán todos los registros anteriores a ${fechaStr}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `index.php?limpiar=si&fecha=${fechaStr}`;
        }
    });
}

document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
    const search = document.getElementById('searchInput').value;
    const usuario = document.getElementById('usuarioFilter').value;
    const accion = document.getElementById('accionFilter').value;
    const tabla = document.getElementById('tablaFilter').value;
    const fecha_desde = document.getElementById('fechaDesde').value;
    const fecha_hasta = document.getElementById('fechaHasta').value;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&usuario=${usuario}&accion=${accion}&tabla=${tabla}&fecha_desde=${fecha_desde}&fecha_hasta=${fecha_hasta}`;
});

document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaAuditoria');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Auditoria');
    XLSX.writeFile(wb, `auditoria_${new Date().toISOString().slice(0,19)}.xlsx`);
});

document.getElementById('btnExportPDF')?.addEventListener('click', function() {
    const tabla = document.getElementById('tablaAuditoria');
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head><title>Auditoría H&H MINERIA</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            .header { text-align: center; border-bottom: 2px solid #8b5cf6; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #8b5cf6; color: white; padding: 10px; }
            td { border: 1px solid #ddd; padding: 8px; }
        </style>
        </head><body>
        <div class="header"><h1>H&H MINERIA</h1><p>Reporte de Auditoría - ${new Date().toLocaleString()}</p></div>
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