<?php
// modules/alertas/index.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Cambiar estado de alerta
if (isset($_GET['cambiar_estado']) && isset($_GET['estado'])) {
    $id = $_GET['cambiar_estado'];
    $nuevo_estado = $_GET['estado'];
    
    $query = "UPDATE alertas SET estado = :estado";
    if ($nuevo_estado == 'resuelta') {
        $query .= ", fecha_resolucion = NOW(), usuario_resolvio = :usuario_id";
    }
    $query .= " WHERE id = :id";
    
    $stmt = $conn->prepare($query);
    $params = [':estado' => $nuevo_estado, ':id' => $id];
    if ($nuevo_estado == 'resuelta') {
        $params[':usuario_id'] = $_SESSION['user_id'];
    }
    $stmt->execute($params);
    
    registrarAuditoria($conn, 'CAMBIAR_ESTADO', 'alertas', $id);
    header('Location: index.php?msg=estado');
    exit();
}

// Eliminar alerta
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $query = "DELETE FROM alertas WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'ELIMINAR', 'alertas', $id);
    header('Location: index.php?msg=eliminado');
    exit();
}

// Verificar alertas automáticas (solo si se ejecuta manualmente o por cron)
if (isset($_GET['verificar_alertas'])) {
    require_once 'alertas_automaticas.php';
    header('Location: index.php?msg=verificado');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$nivel_filtro = $_GET['nivel'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';

$query = "SELECT a.*, 
          v.codigo as viaje_codigo,
          u.nombre as usuario_creo_nombre,
          u2.nombre as usuario_resolvio_nombre
          FROM alertas a
          LEFT JOIN viajes v ON a.viaje_id = v.id
          LEFT JOIN usuarios u ON a.usuario_creo = u.id
          LEFT JOIN usuarios u2 ON a.usuario_resolvio = u2.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (a.titulo LIKE :search OR a.descripcion LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($nivel_filtro)) {
    $query .= " AND a.nivel = :nivel";
    $params[':nivel'] = $nivel_filtro;
}
if (!empty($estado_filtro)) {
    $query .= " AND a.estado = :estado";
    $params[':estado'] = $estado_filtro;
}
if (!empty($categoria_filtro)) {
    $query .= " AND a.categoria = :categoria";
    $params[':categoria'] = $categoria_filtro;
}

$query .= " ORDER BY a.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($alertas);
$activas = count(array_filter($alertas, fn($a) => $a['estado'] == 'activa'));
$resueltas = count(array_filter($alertas, fn($a) => $a['estado'] == 'resuelta'));
$ignoradas = count(array_filter($alertas, fn($a) => $a['estado'] == 'ignorada'));

$criticas = count(array_filter($alertas, fn($a) => $a['nivel'] == 'critica' && $a['estado'] == 'activa'));
$altas = count(array_filter($alertas, fn($a) => $a['nivel'] == 'alta' && $a['estado'] == 'activa'));

// Categorías para filtro
$categorias = $conn->query("SELECT DISTINCT categoria FROM alertas WHERE categoria IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

// Guardar alertas activas en sesión para el badge
$_SESSION['alertas_pendientes'] = $activas;

// Obtener viajes para el select
$viajes = $conn->query("SELECT id, codigo FROM viajes ORDER BY codigo")->fetchAll();
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-alerta {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .stat-card-alerta:hover {
        transform: translateY(-3px);
    }
    .nivel-critica { background: #8b5cf620; color: #8b5cf6; border: 1px solid #8b5cf6; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .nivel-alta { background: #ef444420; color: #ef4444; border: 1px solid #ef4444; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .nivel-media { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .nivel-baja { background: #10b98120; color: #10b981; border: 1px solid #10b981; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    
    .estado-activa { background: #ef444420; color: #ef4444; border: 1px solid #ef4444; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .estado-resuelta { background: #10b98120; color: #10b981; border: 1px solid #10b981; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .estado-ignorada { background: #6b728020; color: #6b7280; border: 1px solid #6b7280; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    
    .categoria-badge {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 10px;
    }
    
    .alerta-item {
        display: flex;
        align-items: flex-start;
        padding: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        transition: all 0.3s;
    }
    .alerta-item:hover {
        background: rgba(255,255,255,0.03);
    }
    .alerta-titulo {
        font-weight: 600;
        margin-bottom: 4px;
    }
    .alerta-descripcion {
        color: #9ca3af;
        font-size: 13px;
    }
    .alerta-icon {
        font-size: 2rem;
        margin-right: 15px;
    }
    
    .btn-verificar {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s;
    }
    .btn-verificar:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        color: white;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-bell me-2"></i> Alertas</h2>
            <p class="text-muted mb-0">Gestión de alertas y notificaciones del sistema</p>
        </div>
        <div class="d-flex gap-2">
            <a href="?verificar_alertas=1" class="btn btn-verificar">
                <i class="fas fa-sync me-2"></i>Verificar Alertas
            </a>
            <button class="btn btn-gradient-outline" id="btnExportExcel"><i class="fas fa-file-excel me-2"></i>Excel</button>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalAlerta" onclick="limpiarFormulario()">
                <i class="fas fa-plus me-2"></i>Nueva Alerta
            </button>
        </div>
    </div>
    
    <!-- Alertas de confirmación -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'creado') echo '✅ Alerta creada exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo '✅ Alerta actualizada exitosamente';
                elseif ($_GET['msg'] == 'eliminado') echo '🗑️ Alerta eliminada exitosamente';
                elseif ($_GET['msg'] == 'estado') echo '🔄 Estado actualizado exitosamente';
                elseif ($_GET['msg'] == 'verificado') echo '🔍 Alertas verificadas automáticamente';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-alerta">
                <i class="fas fa-bell fa-2x mb-2" style="color: #3b82f6;"></i>
                <h3><?php echo $total; ?></h3>
                <small>TOTAL ALERTAS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-alerta" style="border-color: rgba(239, 68, 68, 0.3);">
                <i class="fas fa-exclamation-triangle fa-2x mb-2" style="color: #ef4444;"></i>
                <h3 style="color: #ef4444;"><?php echo $activas; ?></h3>
                <small>ACTIVAS</small>
                <?php if ($criticas > 0): ?>
                    <br><small class="text-danger">⚠️ <?php echo $criticas; ?> críticas</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-alerta" style="border-color: rgba(16, 185, 129, 0.3);">
                <i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i>
                <h3 style="color: #10b981;"><?php echo $resueltas; ?></h3>
                <small>RESUELTAS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-alerta" style="border-color: rgba(107, 114, 128, 0.3);">
                <i class="fas fa-eye-slash fa-2x mb-2" style="color: #6b7280;"></i>
                <h3 style="color: #6b7280;"><?php echo $ignoradas; ?></h3>
                <small>IGNORADAS</small>
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
                <select id="nivelFilter" class="form-select">
                    <option value="">Todos los niveles</option>
                    <option value="baja" <?php echo $nivel_filtro == 'baja' ? 'selected' : ''; ?>>Baja</option>
                    <option value="media" <?php echo $nivel_filtro == 'media' ? 'selected' : ''; ?>>Media</option>
                    <option value="alta" <?php echo $nivel_filtro == 'alta' ? 'selected' : ''; ?>>Alta</option>
                    <option value="critica" <?php echo $nivel_filtro == 'critica' ? 'selected' : ''; ?>>Crítica</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="estadoFilter" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="activa" <?php echo $estado_filtro == 'activa' ? 'selected' : ''; ?>>Activa</option>
                    <option value="resuelta" <?php echo $estado_filtro == 'resuelta' ? 'selected' : ''; ?>>Resuelta</option>
                    <option value="ignorada" <?php echo $estado_filtro == 'ignorada' ? 'selected' : ''; ?>>Ignorada</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="categoriaFilter" class="form-select">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo $categoria_filtro == $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button id="applyFiltersBtn" class="btn btn-gradient w-100">Aplicar</button>
            </div>
        </div>
    </div>
    
    <!-- Lista de Alertas -->
    <div class="card-glass p-3">
        <?php if (empty($alertas)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-bell-slash fa-4x mb-3 d-block"></i>
                <h5>No hay alertas registradas</h5>
                <p>Las alertas se mostrarán aquí cuando sean generadas por el sistema o creadas manualmente.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-glass" id="tablaAlertas">
                    <thead>
                        <tr>
                            <th style="width: 50px">ID</th>
                            <th>Nivel</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Viaje</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th style="width: 150px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alertas as $a): ?>
                        <tr>
                            <td><?php echo $a['id']; ?></td>
                            <td>
                                <span class="nivel-<?php echo $a['nivel']; ?>">
                                    <?php echo ucfirst($a['nivel']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($a['titulo']); ?></strong>
                                <?php if ($a['descripcion']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($a['descripcion'], 0, 50)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="categoria-badge"><?php echo $a['categoria'] ?? 'General'; ?></span>
                            </td>
                            <td>
                                <?php if ($a['viaje_id']): ?>
                                    <a href="/MINERIA/modules/viajes/ver.php?id=<?php echo $a['viaje_id']; ?>" class="text-warning">
                                        <?php echo $a['viaje_codigo']; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">---</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm estado-<?php echo $a['estado']; ?> dropdown-toggle" data-bs-toggle="dropdown">
                                        <?php echo ucfirst($a['estado']); ?>
                                    </button>
                                    <ul class="dropdown-menu bg-dark">
                                        <li><a class="dropdown-item text-danger" href="?cambiar_estado=<?php echo $a['id']; ?>&estado=activa">🔴 Activa</a></li>
                                        <li><a class="dropdown-item text-success" href="?cambiar_estado=<?php echo $a['id']; ?>&estado=resuelta">✅ Resuelta</a></li>
                                        <li><a class="dropdown-item text-secondary" href="?cambiar_estado=<?php echo $a['id']; ?>&estado=ignorada">⏭️ Ignorada</a></li>
                                    </ul>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-info" onclick="verDetalle(<?php echo $a['id']; ?>)" title="Ver detalle"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-warning" onclick="editarAlerta(<?php echo $a['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger" onclick="eliminarAlerta(<?php echo $a['id']; ?>, '<?php echo htmlspecialchars($a['titulo']); ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== MODAL CREAR/EDITAR ========== -->
<div class="modal fade" id="modalAlerta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-bell me-2"></i> Nueva Alerta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAlerta" method="POST" action="guardar.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="alerta_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nivel *</label>
                            <select class="form-select" name="nivel" id="nivel" required>
                                <option value="baja">🟢 Baja</option>
                                <option value="media">🟡 Media</option>
                                <option value="alta">🟠 Alta</option>
                                <option value="critica">🔴 Crítica</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoría</label>
                            <select class="form-select" name="categoria" id="categoria">
                                <option value="General">General</option>
                                <option value="Seguridad">Seguridad</option>
                                <option value="Operaciones">Operaciones</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Logística">Logística</option>
                                <option value="Almacén">Almacén</option>
                                <option value="RRHH">RRHH</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="titulo" id="titulo" required placeholder="Ej: Retraso en viaje VIA-001">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="descripcion" rows="3" placeholder="Descripción detallada de la alerta..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Viaje Relacionado</label>
                            <select class="form-select" name="viaje_id" id="viaje_id">
                                <option value="">Ninguno</option>
                                <?php foreach ($viajes as $v): ?>
                                    <option value="<?php echo $v['id']; ?>"><?php echo $v['codigo']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado" id="estado">
                                <option value="activa">Activa</option>
                                <option value="resuelta">Resuelta</option>
                                <option value="ignorada">Ignorada</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Prioridad</label>
                        <input type="number" class="form-control" name="prioridad" id="prioridad" min="1" max="5" value="1">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Alerta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== MODAL DETALLE ========== -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-bell me-2"></i> Detalle de Alerta</h5>
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
let currentAlertaId = null;

function limpiarFormulario() {
    document.getElementById('formAlerta').reset();
    document.getElementById('alerta_id').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-bell me-2"></i> Nueva Alerta';
}

function editarAlerta(id) {
    fetch(`get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                for(let key in data.alerta) {
                    let el = document.getElementById(key);
                    if(el) el.value = data.alerta[key] || '';
                }
                document.getElementById('alerta_id').value = data.alerta.id;
                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Alerta';
                new bootstrap.Modal(document.getElementById('modalAlerta')).show();
            }
        });
}

function verDetalle(id) {
    currentAlertaId = id;
    fetch(`detalle.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalleContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
}

function eliminarAlerta(id, titulo) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Eliminar alerta "${titulo}"?`,
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
    const nivel = document.getElementById('nivelFilter').value;
    const estado = document.getElementById('estadoFilter').value;
    const categoria = document.getElementById('categoriaFilter').value;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&nivel=${nivel}&estado=${estado}&categoria=${categoria}`;
});

// Exportar Excel
document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaAlertas');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Alertas');
    XLSX.writeFile(wb, `alertas_${new Date().toISOString().slice(0,19)}.xlsx`);
});

document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') document.getElementById('applyFiltersBtn').click();
});

// ========== NOTIFICACIONES PUSH ==========
function solicitarPermisoNotificaciones() {
    if ('Notification' in window) {
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                console.log('📢 Notificaciones permitidas');
            }
        });
    }
}

function enviarNotificacion(titulo, mensaje, nivel = 'info') {
    if ('Notification' in window && Notification.permission === 'granted') {
        const opciones = {
            body: mensaje,
            icon: '/MINERIA/assets/img/logo.png',
            requireInteraction: true,
            vibrate: [200, 100, 200]
        };
        
        if (nivel === 'critica' || nivel === 'alta') {
            opciones.requireInteraction = true;
        }
        
        const notificacion = new Notification('🔔 H&H MINERIA - ' + titulo, opciones);
        
        // Cerrar después de 10 segundos
        setTimeout(() => notificacion.close(), 10000);
    }
}

// Verificar alertas críticas cada 60 segundos
setInterval(() => {
    fetch('verificar_criticas.php')
        .then(response => response.json())
        .then(data => {
            if (data.alertas_criticas && data.alertas_criticas.length > 0) {
                data.alertas_criticas.forEach(alerta => {
                    enviarNotificacion(
                        '⚠️ Alerta ' + alerta.nivel,
                        alerta.titulo + '\n' + (alerta.descripcion || ''),
                        alerta.nivel
                    );
                });
            }
        })
        .catch(error => console.log('Error verificando alertas:', error));
}, 60000);

// Solicitar permiso al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    solicitarPermisoNotificaciones();
});

// ========== RECARGAR ALERTAS CADA 30 SEGUNDOS ==========
// setInterval(() => { location.reload(); }, 30000);
</script>

<?php include '../../includes/footer.php'; ?>