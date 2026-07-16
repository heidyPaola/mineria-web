<?php
// modules/usuarios/index.php
require_once '../../config/auth.php';
requireLogin();
requireRole('admin');

require_once '../../config/conexion.php';

$conn = getConnection();

// Procesar eliminación (soft delete - desactivar)
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error_msg'] = "No puedes desactivar tu propio usuario.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    $query = "UPDATE usuarios SET estado = 0 WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'DESACTIVAR', 'usuarios', $id);
    header('Location: index.php?msg=desactivado');
    exit();
}

// Activar usuario
if (isset($_GET['activar_id'])) {
    $id = $_GET['activar_id'];
    $query = "UPDATE usuarios SET estado = 1 WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    registrarAuditoria($conn, 'ACTIVAR', 'usuarios', $id);
    header('Location: index.php?msg=activado');
    exit();
}

// Reiniciar intentos fallidos
if (isset($_GET['reset_intentos'])) {
    $id = $_GET['reset_intentos'];
    $query = "UPDATE usuarios SET intentos_fallidos = 0 WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);
    header('Location: index.php?msg=reset');
    exit();
}

// Filtros
$search = $_GET['search'] ?? '';
$rol_filtro = $_GET['rol'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';

$query = "SELECT * FROM usuarios WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (username LIKE :search OR nombre LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($rol_filtro)) {
    $query .= " AND rol = :rol";
    $params[':rol'] = $rol_filtro;
}
if ($estado_filtro !== '') {
    $query .= " AND estado = :estado";
    $params[':estado'] = $estado_filtro;
}

$query .= " ORDER BY id DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($usuarios);
$activos = count(array_filter($usuarios, fn($u) => $u['estado'] == 1));
$inactivos = count(array_filter($usuarios, fn($u) => $u['estado'] == 0));

$admins = count(array_filter($usuarios, fn($u) => $u['rol'] == 'admin'));
$supervisores = count(array_filter($usuarios, fn($u) => $u['rol'] == 'supervisor'));
$operadores = count(array_filter($usuarios, fn($u) => $u['rol'] == 'operador'));
$clientes = count(array_filter($usuarios, fn($u) => $u['rol'] == 'cliente'));

// Obtener roles únicos
$roles = $conn->query("SELECT DISTINCT rol FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
?>
<?php include '../../includes/header.php'; ?>

<style>
    .stat-card-usuario {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .stat-card-usuario:hover {
        transform: translateY(-3px);
    }
    .rol-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    .rol-admin { background: #ef444420; color: #ef4444; border: 1px solid #ef4444; }
    .rol-supervisor { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b; }
    .rol-operador { background: #3b82f620; color: #3b82f6; border: 1px solid #3b82f6; }
    .rol-cliente { background: #10b98120; color: #10b981; border: 1px solid #10b981; }
    
    .estado-activo { background: #10b98120; color: #10b981; border: 1px solid #10b981; }
    .estado-inactivo { background: #6b728020; color: #6b7280; border: 1px solid #6b7280; }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 16px;
    }
    .user-avatar-admin { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .user-avatar-supervisor { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .user-avatar-operador { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    .user-avatar-cliente { background: linear-gradient(135deg, #10b981, #059669); }
    
    .password-toggle {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #9ca3af;
    }
    .password-toggle:hover {
        color: #f59e0b;
    }
    .input-group-position {
        position: relative;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-user-shield me-2"></i> Usuarios</h2>
            <p class="text-muted mb-0">Gestión de usuarios del sistema</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-gradient-outline" id="btnExportExcel"><i class="fas fa-file-excel me-2"></i>Excel</button>
            <button class="btn btn-gradient-outline" id="btnExportPDF"><i class="fas fa-file-pdf me-2"></i>PDF</button>
            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalUsuario" onclick="limpiarFormulario()">
                <i class="fas fa-plus me-2"></i>Nuevo Usuario
            </button>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
                if ($_GET['msg'] == 'creado') echo '✅ Usuario creado exitosamente';
                elseif ($_GET['msg'] == 'actualizado') echo '✅ Usuario actualizado exitosamente';
                elseif ($_GET['msg'] == 'desactivado') echo '🗑️ Usuario desactivado exitosamente';
                elseif ($_GET['msg'] == 'activado') echo '✅ Usuario activado exitosamente';
                elseif ($_GET['msg'] == 'reset') echo '🔄 Intentos fallidos reiniciados';
                elseif ($_GET['msg'] == 'password_cambiado') echo '🔑 Contraseña cambiada exitosamente';
                elseif ($_GET['msg'] == 'error') echo '❌ ' . ($_SESSION['error_msg'] ?? 'Error en la operación');
                unset($_SESSION['error_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card-usuario">
                <i class="fas fa-users fa-2x mb-2" style="color: #3b82f6;"></i>
                <h3><?php echo $total; ?></h3>
                <small>TOTAL USUARIOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-usuario" style="border-color: rgba(16, 185, 129, 0.3);">
                <i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i>
                <h3 style="color: #10b981;"><?php echo $activos; ?></h3>
                <small>ACTIVOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-usuario" style="border-color: rgba(107, 114, 128, 0.3);">
                <i class="fas fa-user-slash fa-2x mb-2" style="color: #6b7280;"></i>
                <h3 style="color: #6b7280;"><?php echo $inactivos; ?></h3>
                <small>INACTIVOS</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card-usuario" style="border-color: rgba(239, 68, 68, 0.3);">
                <i class="fas fa-user-tie fa-2x mb-2" style="color: #ef4444;"></i>
                <h3 style="color: #ef4444;"><?php echo $admins; ?></h3>
                <small>ADMINISTRADORES</small>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card-glass p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 Buscar por usuario, nombre o email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select id="rolFilter" class="form-select">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $rol_filtro == $r ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="estadoFilter" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="1" <?php echo $estado_filtro === '1' ? 'selected' : ''; ?>>Activo</option>
                    <option value="0" <?php echo $estado_filtro === '0' ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>
            <div class="col-md-2">
                <button id="applyFiltersBtn" class="btn btn-gradient w-100">Aplicar</button>
            </div>
        </div>
    </div>
    
    <!-- Tabla de Usuarios -->
    <div class="card-glass p-3">
        <div class="table-responsive">
            <table class="table table-glass" id="tablaUsuarios">
                <thead>
                    <tr>
                        <th style="width: 50px">ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th style="width: 180px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay usuarios registrados</td></tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar user-avatar-<?php echo $u['rol']; ?>">
                                        <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                    </div>
                                    <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="rol-badge rol-<?php echo $u['rol']; ?>">
                                    <?php echo ucfirst($u['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['estado'] == 1): ?>
                                    <span class="estado-activo">✅ Activo</span>
                                <?php else: ?>
                                    <span class="estado-inactivo">⛔ Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['ultimo_acceso']): ?>
                                    <small><?php echo date('d/m/Y H:i', strtotime($u['ultimo_acceso'])); ?></small>
                                    <?php if ($u['ultimo_ip']): ?>
                                        <br><span class="ip-badge"><?php echo $u['ultimo_ip']; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                <?php endif; ?>
                                <?php if ($u['intentos_fallidos'] > 0): ?>
                                    <br><span class="text-danger">⚠️ <?php echo $u['intentos_fallidos']; ?> intentos</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-info" onclick="verDetalle(<?php echo $u['id']; ?>)" title="Ver detalle"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-warning" onclick="editarUsuario(<?php echo $u['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-primary" onclick="cambiarPassword(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nombre']); ?>')" title="Cambiar contraseña">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($u['estado'] == 1): ?>
                                        <button class="btn btn-danger" onclick="desactivarUsuario(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nombre']); ?>')" title="Desactivar"><i class="fas fa-user-slash"></i></button>
                                    <?php else: ?>
                                        <button class="btn btn-success" onclick="activarUsuario(<?php echo $u['id']; ?>)" title="Activar"><i class="fas fa-user-check"></i></button>
                                    <?php endif; ?>
                                    <?php if ($u['intentos_fallidos'] > 0): ?>
                                        <button class="btn btn-secondary" onclick="resetIntentos(<?php echo $u['id']; ?>)" title="Reiniciar intentos"><i class="fas fa-redo"></i></button>
                                    <?php endif; ?>
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

<!-- ========== MODAL CREAR/EDITAR ========== -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-user-plus me-2"></i> Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUsuario" method="POST" action="guardar.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="usuario_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" class="form-control" name="username" id="username" required placeholder="Nombre de usuario">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" name="nombre" id="nombre" required placeholder="Nombre completo">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="email" required placeholder="correo@ejemplo.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol *</label>
                            <select class="form-select" name="rol" id="rol" required>
                                <option value="admin">Administrador</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="operador">Operador</option>
                                <option value="cliente">Cliente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="passwordFields">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña *</label>
                                <div class="input-group-position">
                                    <input type="password" class="form-control" name="password" id="password" placeholder="Mínimo 6 caracteres">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirmar Contraseña *</label>
                                <div class="input-group-position">
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Repite la contraseña">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado" id="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== MODAL CAMBIAR CONTRASEÑA ========== -->
<div class="modal fade" id="modalCambiarPassword" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-key me-2"></i> Cambiar Contraseña
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCambiarPassword" method="POST" action="cambiar_password.php">
                <div class="modal-body">
                    <input type="hidden" name="usuario_id" id="cambiar_usuario_id">
                    <input type="hidden" name="usuario_nombre" id="cambiar_usuario_nombre">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cambiando contraseña para: <strong id="cambiar_usuario_display"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña *</label>
                        <div class="input-group-position">
                            <input type="password" class="form-control" name="nueva_password" id="nueva_password" 
                                   placeholder="Mínimo 6 caracteres" required minlength="6">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('nueva_password')"></i>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmar Contraseña *</label>
                        <div class="input-group-position">
                            <input type="password" class="form-control" name="confirmar_password" id="confirmar_password" 
                                   placeholder="Repite la contraseña" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirmar_password')"></i>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tu Contraseña (confirmación) *</label>
                        <div class="input-group-position">
                            <input type="password" class="form-control" name="mi_password" id="mi_password" 
                                   placeholder="Ingresa tu contraseña para confirmar" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('mi_password')"></i>
                        </div>
                        <small class="text-muted">Debes ingresar tu contraseña para realizar este cambio</small>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save me-2"></i>Cambiar Contraseña
                    </button>
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
                <h5 class="modal-title"><i class="fas fa-user me-2"></i> Detalle de Usuario</h5>
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
let currentUsuarioId = null;

function limpiarFormulario() {
    document.getElementById('formUsuario').reset();
    document.getElementById('usuario_id').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-user-plus me-2"></i> Nuevo Usuario';
    document.getElementById('passwordFields').style.display = 'block';
    document.getElementById('password').required = true;
    document.getElementById('confirm_password').required = true;
}

function editarUsuario(id) {
    fetch(`get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                for(let key in data.usuario) {
                    let el = document.getElementById(key);
                    if(el) el.value = data.usuario[key] || '';
                }
                document.getElementById('usuario_id').value = data.usuario.id;
                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Usuario';
                document.getElementById('passwordFields').style.display = 'none';
                document.getElementById('password').required = false;
                document.getElementById('confirm_password').required = false;
                new bootstrap.Modal(document.getElementById('modalUsuario')).show();
            }
        });
}

function verDetalle(id) {
    currentUsuarioId = id;
    fetch(`detalle.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalleContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
}

function cambiarPassword(id, nombre) {
    document.getElementById('cambiar_usuario_id').value = id;
    document.getElementById('cambiar_usuario_nombre').value = nombre;
    document.getElementById('cambiar_usuario_display').innerText = nombre;
    document.getElementById('formCambiarPassword').reset();
    new bootstrap.Modal(document.getElementById('modalCambiarPassword')).show();
}

function togglePassword(id) {
    const input = document.getElementById(id);
    const icon = input.parentElement.querySelector('.password-toggle');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function desactivarUsuario(id, nombre) {
    if (id == <?php echo $_SESSION['user_id']; ?>) {
        Swal.fire({
            icon: 'error',
            title: 'No permitido',
            text: 'No puedes desactivar tu propio usuario.',
            confirmButtonColor: '#d33'
        });
        return;
    }
    
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Desactivar usuario "${nombre}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, desactivar'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `index.php?delete_id=${id}`;
    });
}

function activarUsuario(id) {
    Swal.fire({
        title: '¿Activar usuario?',
        text: 'El usuario podrá acceder al sistema',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Sí, activar'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `index.php?activar_id=${id}`;
    });
}

function resetIntentos(id) {
    Swal.fire({
        title: '¿Reiniciar intentos?',
        text: 'Se reiniciarán los intentos fallidos de inicio de sesión',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'Sí, reiniciar'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `index.php?reset_intentos=${id}`;
    });
}

document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
    const search = document.getElementById('searchInput').value;
    const rol = document.getElementById('rolFilter').value;
    const estado = document.getElementById('estadoFilter').value;
    window.location.href = `index.php?search=${encodeURIComponent(search)}&rol=${rol}&estado=${estado}`;
});

document.getElementById('btnExportExcel')?.addEventListener('click', () => {
    const table = document.getElementById('tablaUsuarios');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Usuarios');
    XLSX.writeFile(wb, `usuarios_${new Date().toISOString().slice(0,19)}.xlsx`);
});

document.getElementById('btnExportPDF')?.addEventListener('click', function() {
    const tabla = document.getElementById('tablaUsuarios');
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head><title>Usuarios H&H MINERIA</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            .header { text-align: center; border-bottom: 2px solid #3b82f6; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #3b82f6; color: white; padding: 10px; }
            td { border: 1px solid #ddd; padding: 8px; }
        </style>
        </head><body>
        <div class="header"><h1>H&H MINERIA</h1><p>Listado de Usuarios - ${new Date().toLocaleString()}</p></div>
        ${tabla.outerHTML}
        </body></html>
    `);
    ventana.document.close();
    ventana.print();
});

document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') document.getElementById('applyFiltersBtn').click();
});

// Validar contraseñas en creación
document.getElementById('formUsuario')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const id = document.getElementById('usuario_id').value;
    
    if (!id && password.length > 0 && password !== confirm) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Las contraseñas no coinciden.',
            confirmButtonColor: '#d33'
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>