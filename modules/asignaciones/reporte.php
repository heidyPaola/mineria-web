<?php
// modules/asignaciones/reporte.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Obtener parámetros
$tipo = $_GET['tipo'] ?? 'dia'; // dia, semana, mes
$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Determinar rango de fechas según tipo
switch ($tipo) {
    case 'dia':
        $fecha_inicio = $fecha;
        $fecha_fin = $fecha;
        $titulo = "Reporte del Día: " . date('d/m/Y', strtotime($fecha));
        break;
    case 'semana':
        $fecha_obj = new DateTime($fecha);
        $fecha_inicio = $fecha_obj->modify('monday this week')->format('Y-m-d');
        $fecha_obj = new DateTime($fecha);
        $fecha_fin = $fecha_obj->modify('sunday this week')->format('Y-m-d');
        $titulo = "Reporte de la Semana: " . date('d/m/Y', strtotime($fecha_inicio)) . " - " . date('d/m/Y', strtotime($fecha_fin));
        break;
    case 'mes':
        $fecha_obj = new DateTime($fecha);
        $fecha_inicio = $fecha_obj->modify('first day of this month')->format('Y-m-d');
        $fecha_obj = new DateTime($fecha);
        $fecha_fin = $fecha_obj->modify('last day of this month')->format('Y-m-d');
        $titulo = "Reporte del Mes: " . date('F Y', strtotime($fecha));
        break;
    default:
        $fecha_inicio = $fecha;
        $fecha_fin = $fecha;
        $titulo = "Reporte del Día";
}

// Obtener asignaciones en el rango
$query = "SELECT a.*, 
          v.placa, v.marca, v.modelo,
          c.nombre as conductor_nombre, c.telefono as conductor_telefono
          FROM asignaciones a
          LEFT JOIN vehiculos v ON a.vehiculo_id = v.id
          LEFT JOIN conductores c ON a.conductor_id = c.id
          WHERE a.fecha_asignacion <= :fecha_fin 
          AND a.fecha_fin >= :fecha_inicio
          ORDER BY a.fecha_asignacion ASC";
$stmt = $conn->prepare($query);
$stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
$asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total = count($asignaciones);
$activas = count(array_filter($asignaciones, fn($a) => $a['estado'] == 'activa'));
$completadas = count(array_filter($asignaciones, fn($a) => $a['estado'] == 'completada'));
$canceladas = count(array_filter($asignaciones, fn($a) => $a['estado'] == 'cancelada'));

// Vehículos únicos
$vehiculos_unicos = count(array_unique(array_column($asignaciones, 'vehiculo_id')));

// Días de asignación
$dias_totales = 0;
foreach ($asignaciones as $a) {
    $dias = (strtotime($a['fecha_fin']) - strtotime($a['fecha_asignacion'])) / (60 * 60 * 24);
    $dias_totales += ceil($dias);
}
?>
<?php include '../../includes/header.php'; ?>

<style>
    .reporte-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .stat-box {
        background: rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 15px 20px;
        text-align: center;
        flex: 1;
        min-width: 100px;
    }
    .stat-box h4 {
        font-size: 1.8rem;
        margin: 0;
        color: #f59e0b;
    }
    .stat-box small {
        color: #9ca3af;
        font-size: 12px;
    }
    .stat-box-activa h4 { color: #10b981; }
    .stat-box-completada h4 { color: #3b82f6; }
    .stat-box-cancelada h4 { color: #6b7280; }
    
    .btn-group-reporte {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .btn-reporte {
        padding: 8px 20px;
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.1);
        background: transparent;
        color: #9ca3af;
        transition: all 0.3s;
    }
    .btn-reporte:hover, .btn-reporte.active {
        background: #f59e0b;
        color: white;
        border-color: #f59e0b;
    }
    .fecha-input {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        color: white;
        padding: 8px 12px;
        border-radius: 8px;
    }
    .fecha-input:focus {
        border-color: #f59e0b;
        outline: none;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="reporte-header">
        <div>
            <h2><i class="fas fa-chart-bar me-2"></i> Reporte de Asignaciones</h2>
            <p class="text-muted mb-0"><?php echo $titulo; ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-gradient-outline" onclick="exportarPDF()">
                <i class="fas fa-file-pdf me-2"></i>PDF
            </button>
            <button class="btn btn-gradient-outline" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-2"></i>Excel
            </button>
            <a href="calendario.php" class="btn btn-secondary">
                <i class="fas fa-calendar-alt me-2"></i>Calendario
            </a>
        </div>
    </div>
    
    <!-- Selector de reporte -->
    <div class="card-glass p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <div class="btn-group-reporte">
                    <a href="?tipo=dia&fecha=<?php echo date('Y-m-d'); ?>" class="btn-reporte <?php echo $tipo == 'dia' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-day me-1"></i>Día
                    </a>
                    <a href="?tipo=semana&fecha=<?php echo date('Y-m-d'); ?>" class="btn-reporte <?php echo $tipo == 'semana' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week me-1"></i>Semana
                    </a>
                    <a href="?tipo=mes&fecha=<?php echo date('Y-m-d'); ?>" class="btn-reporte <?php echo $tipo == 'mes' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt me-1"></i>Mes
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <input type="date" id="fechaSelector" class="fecha-input w-100" value="<?php echo $fecha; ?>">
            </div>
            <div class="col-md-2">
                <button onclick="cambiarFecha()" class="btn btn-gradient w-100">
                    <i class="fas fa-search me-1"></i>Ver
                </button>
            </div>
            <div class="col-md-4 text-end">
                <span class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    <?php echo $total; ?> asignaciones encontradas
                </span>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-2">
            <div class="stat-box stat-box-total">
                <h4><?php echo $total; ?></h4>
                <small>Total Asignaciones</small>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="stat-box stat-box-activa">
                <h4><?php echo $activas; ?></h4>
                <small>Activas</small>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="stat-box stat-box-completada">
                <h4><?php echo $completadas; ?></h4>
                <small>Completadas</small>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="stat-box stat-box-cancelada">
                <h4><?php echo $canceladas; ?></h4>
                <small>Canceladas</small>
            </div>
        </div>
    </div>
    
    <!-- Resumen adicional -->
    <div class="row mb-4">
        <div class="col-md-4 mb-2">
            <div class="card-glass p-3">
                <small class="text-muted">Vehículos utilizados</small>
                <h4 class="mb-0"><?php echo $vehiculos_unicos; ?></h4>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card-glass p-3">
                <small class="text-muted">Días de asignación</small>
                <h4 class="mb-0"><?php echo $dias_totales; ?></h4>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card-glass p-3">
                <small class="text-muted">Promedio por asignación</small>
                <h4 class="mb-0"><?php echo $total > 0 ? round($dias_totales / $total, 1) : 0; ?> <small>días</small></h4>
            </div>
        </div>
    </div>
    
    <!-- Tabla -->
    <div class="card-glass p-3" id="reporteTabla">
        <div class="table-responsive">
            <table class="table table-glass" id="tablaReporte">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vehículo</th>
                        <th>Conductor</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Días</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($asignaciones)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x d-block mb-2"></i>
                                No hay asignaciones en este período
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($asignaciones as $a): ?>
                            <?php 
                            $dias = ceil((strtotime($a['fecha_fin']) - strtotime($a['fecha_asignacion'])) / (60 * 60 * 24));
                            $estado_class = match($a['estado']) {
                                'activa' => 'estado-activa',
                                'completada' => 'estado-completada',
                                'cancelada' => 'estado-cancelada',
                                default => ''
                            };
                            ?>
                            <tr>
                                <td><?php echo $a['id']; ?></td>
                                <td>
                                    <strong><?php echo $a['placa']; ?></strong>
                                    <br><small class="text-muted"><?php echo $a['marca'] . ' ' . $a['modelo']; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($a['conductor_nombre']); ?></strong>
                                    <br><small><?php echo $a['conductor_telefono']; ?></small>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($a['fecha_asignacion'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($a['fecha_fin'])); ?></td>
                                <td><?php echo $dias; ?> días</td>
                                <td>
                                    <span class="badge <?php echo $estado_class; ?>">
                                        <?php echo ucfirst($a['estado']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($a['motivo'] ?? '---'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
function cambiarFecha() {
    var fecha = document.getElementById('fechaSelector').value;
    var tipo = '<?php echo $tipo; ?>';
    if (fecha) {
        window.location.href = 'reporte.php?tipo=' + tipo + '&fecha=' + fecha;
    }
}

document.getElementById('fechaSelector')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') cambiarFecha();
});

function exportarPDF() {
    var contenido = document.getElementById('reporteTabla').innerHTML;
    var titulo = '<?php echo $titulo; ?>';
    var ventana = window.open('', '_blank');
    ventana.document.write(`
        <html>
        <head>
            <title>Reporte Asignaciones</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 30px; }
                .header { text-align: center; border-bottom: 2px solid #f59e0b; padding-bottom: 15px; margin-bottom: 20px; }
                .header h1 { color: #f59e0b; margin: 0; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f59e0b; color: white; padding: 10px; text-align: left; }
                td { border: 1px solid #ddd; padding: 8px; }
                tr:nth-child(even) { background: #f9f9f9; }
                .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>H&H MINERIA</h1>
                <p>${titulo}</p>
                <p>Generado: ${new Date().toLocaleString()}</p>
            </div>
            ${contenido}
            <div class="footer">
                <p>Sistema de Gestión Minera - Reporte generado automáticamente</p>
            </div>
        </body>
        </html>
    `);
    ventana.document.close();
    ventana.print();
}

function exportarExcel() {
    var tabla = document.getElementById('tablaReporte');
    var ws = XLSX.utils.table_to_sheet(tabla);
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Asignaciones');
    XLSX.writeFile(wb, `reporte_asignaciones_${new Date().toISOString().slice(0,10)}.xlsx`);
}
</script>

<?php include '../../includes/footer.php'; ?>