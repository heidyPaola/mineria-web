<?php
// modules/asignaciones/calendario.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Obtener asignaciones para el calendario
$query = "SELECT a.*, 
          v.placa, v.marca, v.modelo,
          c.nombre as conductor_nombre
          FROM asignaciones a
          LEFT JOIN vehiculos v ON a.vehiculo_id = v.id
          LEFT JOIN conductores c ON a.conductor_id = c.id
          ORDER BY a.fecha_asignacion DESC";
$asignaciones = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Datos para selects (crear/editar)
$vehiculos = $conn->query("SELECT id, placa, marca, modelo FROM vehiculos WHERE estado = 'activo' ORDER BY placa")->fetchAll();
$conductores = $conn->query("SELECT id, nombre FROM conductores WHERE estado = 'disponible' ORDER BY nombre")->fetchAll();
?>
<?php include '../../includes/header.php'; ?>

<style>
    .fc-daygrid-day {
        cursor: pointer;
        transition: background 0.2s;
    }
    .fc-daygrid-day:hover {
        background: rgba(245, 158, 11, 0.08);
    }
    .fc-event {
        border-radius: 4px;
        padding: 2px 6px;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        font-weight: 500;
    }
    .fc-event:hover {
        transform: scale(1.03);
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    }
    .fc-event-title {
        font-weight: 500;
    }
    .event-activa {
        background: #10b981;
        border-left: 3px solid #059669;
    }
    .event-completada {
        background: #3b82f6;
        border-left: 3px solid #1d4ed8;
    }
    .event-cancelada {
        background: #6b7280;
        border-left: 3px solid #4b5563;
    }
    .calendario-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .calendario-leyenda {
        display: flex;
        gap: 20px;
        padding: 15px 20px;
        background: rgba(255,255,255,0.05);
        border-radius: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        align-items: center;
    }
    .leyenda-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }
    .leyenda-color {
        width: 18px;
        height: 18px;
        border-radius: 4px;
    }
    .leyenda-activa { background: #10b981; }
    .leyenda-completada { background: #3b82f6; }
    .leyenda-cancelada { background: #6b7280; }
    
    .fc .fc-button-primary {
        background: #f59e0b !important;
        border-color: #f59e0b !important;
    }
    .fc .fc-button-primary:hover {
        background: #d97706 !important;
        border-color: #d97706 !important;
    }
    .fc .fc-button-primary:disabled {
        background: #6b7280 !important;
        border-color: #6b7280 !important;
    }
    .fc .fc-button-active {
        background: #d97706 !important;
        border-color: #d97706 !important;
    }

    .quick-modal .modal-content {
        background: #121623;
        border: 1px solid rgba(255,255,255,0.1);
    }
    .quick-modal .modal-header {
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .quick-modal .modal-footer {
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    .quick-modal .form-control, .quick-modal .form-select {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        color: white;
    }
    .quick-modal .form-control:focus, .quick-modal .form-select:focus {
        background: rgba(255,255,255,0.12);
        border-color: #f59e0b;
        box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.25);
    }
    .quick-modal .form-label {
        color: #d1d5db;
        font-weight: 500;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="calendario-header">
        <div>
            <h2><i class="fas fa-calendar-alt me-2"></i> Calendario de Asignaciones</h2>
            <p class="text-muted mb-0">Visualización mensual - Haz clic en un día para crear asignación</p>
        </div>
        <div class="d-flex gap-2">
            <a href="reporte.php" class="btn btn-gradient-outline">
                <i class="fas fa-chart-bar me-2"></i>Reportes
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-list me-2"></i>Listado
            </a>
            <button class="btn btn-gradient" onclick="abrirNuevo()">
                <i class="fas fa-plus me-2"></i>Nueva Asignación
            </button>
        </div>
    </div>
    
    <!-- Leyenda -->
    <div class="calendario-leyenda">
        <div class="leyenda-item">
            <div class="leyenda-color leyenda-activa"></div>
            <span>Activa</span>
        </div>
        <div class="leyenda-item">
            <div class="leyenda-color leyenda-completada"></div>
            <span>Completada</span>
        </div>
        <div class="leyenda-item">
            <div class="leyenda-color leyenda-cancelada"></div>
            <span>Cancelada</span>
        </div>
        <div class="leyenda-item ms-auto text-muted">
            <i class="fas fa-mouse-pointer me-1"></i> Click en día → Crear | Click en evento → Editar
        </div>
    </div>
    
    <!-- Calendario -->
    <div class="card-glass p-3">
        <div id="calendario"></div>
    </div>
</div>

<!-- ========== MODAL CREACIÓN RÁPIDA ========== -->
<div class="modal fade quick-modal" id="modalCrearRapido" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCrearTitulo">
                    <i class="fas fa-plus-circle me-2"></i> Nueva Asignación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>


            <form id="formCrearRapido" method="POST" action="/MINERIA/modules/asignaciones/guardar.php">


                <input type="hidden" name="origen" value="calendario">
                <div class="modal-body">
                    <input type="hidden" name="id" id="rapido_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
    <label class="form-label">Vehículo *</label>
    <div class="d-flex gap-2">
        <select class="form-select" name="vehiculo_id" id="rapido_vehiculo" required style="flex:1;">
            <option value="">Seleccione vehículo</option>
            <?php foreach ($vehiculos as $v): ?>
                <option value="<?php echo $v['id']; ?>"><?php echo $v['placa']; ?> - <?php echo $v['marca'] . ' ' . $v['modelo']; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-success" onclick="abrirNuevoVehiculo()" title="Agregar nuevo vehículo">
            <i class="fas fa-plus"></i>
        </button>
    </div>
</div>
                        <div class="col-md-6 mb-3">
    <label class="form-label">Conductor *</label>
    <div class="d-flex gap-2">
        <select class="form-select" name="conductor_id" id="rapido_conductor" required style="flex:1;">
            <option value="">Seleccione conductor</option>
            <?php foreach ($conductores as $c): ?>
                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-success" onclick="abrirNuevoConductor()" title="Agregar nuevo conductor">
            <i class="fas fa-plus"></i>
        </button>
    </div>
</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Inicio *</label>
                            <input type="date" class="form-control" name="fecha_asignacion" id="rapido_fecha_inicio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Fin *</label>
                            <input type="date" class="form-control" name="fecha_fin" id="rapido_fecha_fin" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <input type="text" class="form-control" name="motivo" id="rapido_motivo" placeholder="Ej: Asignación para ruta minera">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" id="rapido_observaciones" rows="2" placeholder="Notas adicionales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient" id="btnGuardarRapido">
                        <i class="fas fa-save me-2"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== MODAL EDICIÓN RÁPIDA ========== -->
<div class="modal fade quick-modal" id="modalEditarRapido" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarTitulo">
                    <i class="fas fa-edit me-2"></i> Editar Asignación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarRapido" method="POST" action="/MINERIA/modules/asignaciones/guardar.php">
                <input type="hidden" name="origen" value="calendario">
                <div class="modal-body">
                    <input type="hidden" name="id" id="editar_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehículo *</label>
                            <select class="form-select" name="vehiculo_id" id="editar_vehiculo" required>
                                <option value="">Seleccione vehículo</option>
                                <?php foreach ($vehiculos as $v): ?>
                                    <option value="<?php echo $v['id']; ?>"><?php echo $v['placa']; ?> - <?php echo $v['marca'] . ' ' . $v['modelo']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Conductor *</label>
                            <select class="form-select" name="conductor_id" id="editar_conductor" required>
                                <option value="">Seleccione conductor</option>
                                <?php foreach ($conductores as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Inicio *</label>
                            <input type="date" class="form-control" name="fecha_asignacion" id="editar_fecha_inicio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Fin *</label>
                            <input type="date" class="form-control" name="fecha_fin" id="editar_fecha_fin" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <input type="text" class="form-control" name="motivo" id="editar_motivo">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" id="editar_observaciones" rows="2"></textarea>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado" id="editar_estado">
                                <option value="activa">Activa</option>
                                <option value="completada">Completada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Acción adicional</label>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarDesdeEditar()">
                                    <i class="fas fa-trash me-1"></i>Eliminar
                                </button>
                                <button type="button" class="btn btn-info btn-sm" onclick="verDesdeEditar()">
                                    <i class="fas fa-eye me-1"></i>Ver
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save me-2"></i>Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- ========== MODAL NUEVO CONDUCTOR ========== -->
<div class="modal fade quick-modal" id="modalNuevoConductor" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Nuevo Conductor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoConductor" method="POST" action="/MINERIA/modules/conductores/guardar.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" id="nuevo_conductor_nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Licencia *</label>
                        <input type="text" class="form-control" name="licencia" id="nuevo_conductor_licencia" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" name="telefono" id="nuevo_conductor_telefono">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="nuevo_conductor_email">
                    </div>
                    <input type="hidden" name="estado" value="disponible">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Conductor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== MODAL NUEVO VEHÍCULO ========== -->
<div class="modal fade quick-modal" id="modalNuevoVehiculo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-truck-plus me-2"></i> Nuevo Vehículo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoVehiculo" method="POST" action="/MINERIA/modules/vehiculos/guardar.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Placa *</label>
                        <input type="text" class="form-control" name="placa" id="nuevo_vehiculo_placa" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Marca *</label>
                        <input type="text" class="form-control" name="marca" id="nuevo_vehiculo_marca" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Modelo *</label>
                        <input type="text" class="form-control" name="modelo" id="nuevo_vehiculo_modelo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Año</label>
                        <input type="number" class="form-control" name="año" id="nuevo_vehiculo_año">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacidad (TN)</label>
                        <input type="number" class="form-control" name="capacidad" id="nuevo_vehiculo_capacidad" step="0.01">
                    </div>
                    <input type="hidden" name="estado" value="activo">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gradient">Guardar Vehículo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendario');
    
    var eventos = <?php 
        $eventos = [];
        foreach ($asignaciones as $a) {
            $color = match($a['estado']) {
                'activa' => '#10b981',
                'completada' => '#3b82f6',
                'cancelada' => '#6b7280',
                default => '#6b7280'
            };
            
            $eventos[] = [
                'id' => $a['id'],
                'title' => $a['placa'] . ' - ' . ($a['conductor_nombre'] ?? 'Sin conductor'),
                'start' => $a['fecha_asignacion'],
                'end' => date('Y-m-d', strtotime($a['fecha_fin'] . ' +1 day')),
                'color' => $color,
                'className' => 'event-' . $a['estado'],
                'extendedProps' => [
                    'id' => $a['id'],
                    'estado' => $a['estado'],
                    'placa' => $a['placa'],
                    'conductor' => $a['conductor_nombre'],
                    'fecha_inicio' => $a['fecha_asignacion'],
                    'fecha_fin' => $a['fecha_fin'],
                    'motivo' => $a['motivo'] ?? '',
                    'observaciones' => $a['observaciones'] ?? '',
                    'marca' => $a['marca'] ?? '',
                    'modelo' => $a['modelo'] ?? '',
                    'vehiculo_id' => $a['vehiculo_id'],
                    'conductor_id' => $a['conductor_id']
                ]
            ];
        }
        echo json_encode($eventos);
    ?>;
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek,listMonth'
        },
        events: eventos,
        
        dateClick: function(info) {
            abrirCrearEnFecha(info.dateStr);
        },
        
        eventClick: function(info) {
            abrirEditarEvento(info.event);
        },
        
        eventDrop: function(info) {
            var newStart = info.event.start.toISOString().slice(0,10);
            var newEnd = info.event.end ? info.event.end.toISOString().slice(0,10) : newStart;
            
            var diffDays = Math.ceil((new Date(newEnd) - new Date(newStart)) / (1000 * 60 * 60 * 24));
            var newEndDate = new Date(newStart);
            newEndDate.setDate(newEndDate.getDate() + diffDays);
            var newEndStr = newEndDate.toISOString().slice(0,10);
            
            actualizarFechas(info.event.id, newStart, newEndStr);
        },
        
        eventResize: function(info) {
            var newStart = info.event.start.toISOString().slice(0,10);
            var newEnd = info.event.end ? info.event.end.toISOString().slice(0,10) : newStart;
            
            var endDate = new Date(newEnd);
            endDate.setDate(endDate.getDate() - 1);
            var newEndStr = endDate.toISOString().slice(0,10);
            
            actualizarFechas(info.event.id, newStart, newEndStr);
        },
        
        eventDidMount: function(info) {
            var tooltip = new bootstrap.Tooltip(info.el, {
                title: function() {
                    var props = info.event.extendedProps;
                    return props.placa + ' - ' + props.conductor + 
                           '\nEstado: ' + props.estado +
                           '\nDel: ' + props.fecha_inicio +
                           '\nAl: ' + props.fecha_fin;
                },
                placement: 'top',
                trigger: 'hover',
                container: 'body'
            });
        },
        
        height: 650,
        contentHeight: 'auto',
        nowIndicator: true,
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false
        }
    });
    
    calendar.render();
});

// ===== FUNCIONES =====

function abrirCrearEnFecha(fecha) {
    document.getElementById('formCrearRapido').reset();
    document.getElementById('rapido_id').value = '';
    document.getElementById('rapido_fecha_inicio').value = fecha;
    document.getElementById('rapido_fecha_fin').value = fecha;
    document.getElementById('modalCrearTitulo').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Nueva Asignación - ' + fecha;
    
    var fechaObj = new Date(fecha);
    fechaObj.setDate(fechaObj.getDate() + 7);
    document.getElementById('rapido_fecha_fin').value = fechaObj.toISOString().slice(0,10);
    
    new bootstrap.Modal(document.getElementById('modalCrearRapido')).show();
}

function abrirNuevo() {
    var hoy = new Date().toISOString().slice(0,10);
    abrirCrearEnFecha(hoy);
}

function abrirEditarEvento(event) {
    var props = event.extendedProps;
    
    document.getElementById('editar_id').value = props.id;
    document.getElementById('editar_vehiculo').value = props.vehiculo_id || '';
    document.getElementById('editar_conductor').value = props.conductor_id || '';
    document.getElementById('editar_fecha_inicio').value = props.fecha_inicio;
    document.getElementById('editar_fecha_fin').value = props.fecha_fin;
    document.getElementById('editar_motivo').value = props.motivo || '';
    document.getElementById('editar_observaciones').value = props.observaciones || '';
    document.getElementById('editar_estado').value = props.estado || 'activa';
    
    document.getElementById('modalEditarTitulo').innerHTML = 
        '<i class="fas fa-edit me-2"></i> Editar: ' + props.placa + ' - ' + props.conductor;
    
    window.currentEditId = props.id;
    window.currentEditPlaca = props.placa;
    
    new bootstrap.Modal(document.getElementById('modalEditarRapido')).show();
}
// ===== FUNCIONES PARA ABRIR MODALES =====
function abrirNuevoConductor() {
    document.getElementById('formNuevoConductor').reset();
    new bootstrap.Modal(document.getElementById('modalNuevoConductor')).show();
}

function abrirNuevoVehiculo() {
    document.getElementById('formNuevoVehiculo').reset();
    new bootstrap.Modal(document.getElementById('modalNuevoVehiculo')).show();
}

function actualizarFechas(id, start, end) {
    fetch('actualizar_fechas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id + '&fecha_inicio=' + start + '&fecha_fin=' + end
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Actualizado',
                text: 'Fechas actualizadas correctamente',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudo actualizar',
                confirmButtonColor: '#d33'
            });
            location.reload();
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexión',
            confirmButtonColor: '#d33'
        });
    });
}

function eliminarDesdeEditar() {
    var id = document.getElementById('editar_id').value;
    var placa = window.currentEditPlaca || 'vehículo';
    
    Swal.fire({
        title: '¿Estás seguro?',
        text: '¿Eliminar asignación del vehículo ' + placa + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?delete_id=' + id;
        }
    });
}

function verDesdeEditar() {
    var id = document.getElementById('editar_id').value;
    window.location.href = 'ver.php?id=' + id;
}
</script>

<?php include '../../includes/footer.php'; ?>