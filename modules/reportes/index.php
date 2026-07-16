<?php
// modules/reportes/index.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Filtros de fecha
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-6 months'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// ========== KPI's PRINCIPALES ==========

// Total viajes
$query = "SELECT COUNT(*) as total FROM viajes";
$total_viajes = $conn->query($query)->fetch(PDO::FETCH_ASSOC)['total'];

// Viajes completados
$query = "SELECT COUNT(*) as total FROM viajes WHERE estado = 'completado'";
$viajes_completados = $conn->query($query)->fetch(PDO::FETCH_ASSOC)['total'];

// Total clientes
$query = "SELECT COUNT(*) as total FROM clientes WHERE estado = 1";
$total_clientes = $conn->query($query)->fetch(PDO::FETCH_ASSOC)['total'];

// Total conductores
$query = "SELECT COUNT(*) as total FROM conductores WHERE estado != 'inactivo'";
$total_conductores = $conn->query($query)->fetch(PDO::FETCH_ASSOC)['total'];

// Total vehículos
$query = "SELECT COUNT(*) as total FROM vehiculos WHERE estado = 'activo'";
$total_vehiculos = $conn->query($query)->fetch(PDO::FETCH_ASSOC)['total'];

// Ingresos totales
$query = "SELECT SUM(ingreso_total) as total FROM viajes";
$ingresos_totales = $conn->query($query)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Peso total transportado
$query = "SELECT SUM(peso) as total FROM viajes";
$peso_total = $conn->query($query)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ========== GRÁFICO 1: Viajes por Mes ==========
$query = "SELECT DATE_FORMAT(fecha_viaje, '%Y-%m') as mes, 
          COUNT(*) as total,
          SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
          SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
          SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
          SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados
          FROM viajes 
          WHERE fecha_viaje BETWEEN :fecha_desde AND :fecha_hasta
          GROUP BY DATE_FORMAT(fecha_viaje, '%Y-%m')
          ORDER BY mes ASC";
$stmt = $conn->prepare($query);
$stmt->execute([':fecha_desde' => $fecha_desde, ':fecha_hasta' => $fecha_hasta]);
$viajes_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== GRÁFICO 2: Estado de Viajes ==========
$query = "SELECT estado, COUNT(*) as total FROM viajes GROUP BY estado";
$estado_viajes = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

// ========== GRÁFICO 3: Top Materiales ==========
$query = "SELECT m.nombre, SUM(v.peso) as total_peso, COUNT(v.id) as total_viajes
          FROM viajes v
          JOIN materiales m ON v.material_id = m.id
          WHERE v.fecha_viaje BETWEEN :fecha_desde AND :fecha_hasta
          GROUP BY v.material_id
          ORDER BY total_peso DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute([':fecha_desde' => $fecha_desde, ':fecha_hasta' => $fecha_hasta]);
$top_materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== GRÁFICO 4: Ingresos Mensuales ==========
$query = "SELECT DATE_FORMAT(fecha_viaje, '%Y-%m') as mes, 
          SUM(ingreso_total) as total_ingresos
          FROM viajes 
          WHERE fecha_viaje BETWEEN :fecha_desde AND :fecha_hasta
          GROUP BY DATE_FORMAT(fecha_viaje, '%Y-%m')
          ORDER BY mes ASC";
$stmt = $conn->prepare($query);
$stmt->execute([':fecha_desde' => $fecha_desde, ':fecha_hasta' => $fecha_hasta]);
$ingresos_mensuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== GRÁFICO 5: Top Conductores ==========
$query = "SELECT c.nombre, COUNT(v.id) as total_viajes, SUM(v.peso) as total_peso
          FROM viajes v
          JOIN conductores c ON v.conductor_id = c.id
          WHERE v.fecha_viaje BETWEEN :fecha_desde AND :fecha_hasta
          GROUP BY v.conductor_id
          ORDER BY total_viajes DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute([':fecha_desde' => $fecha_desde, ':fecha_hasta' => $fecha_hasta]);
$top_conductores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== GRÁFICO 6: Top Clientes ==========
$query = "SELECT c.nombre, COUNT(v.id) as total_viajes, SUM(v.peso) as total_peso
          FROM viajes v
          JOIN clientes c ON v.cliente_id = c.id
          WHERE v.fecha_viaje BETWEEN :fecha_desde AND :fecha_hasta
          GROUP BY v.cliente_id
          ORDER BY total_viajes DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute([':fecha_desde' => $fecha_desde, ':fecha_hasta' => $fecha_hasta]);
$top_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../../includes/header.php'; ?>

<style>
    .kpi-card {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
        border: 1px solid rgba(245, 158, 11, 0.2);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
    }
    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .kpi-card h2 {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 10px 0 5px;
    }
    .kpi-card .icon {
        font-size: 2.5rem;
        opacity: 0.7;
    }
    .kpi-card .label {
        color: #9ca3af;
        font-size: 0.9rem;
    }
    .kpi-card .sub {
        font-size: 0.8rem;
        color: #6b7280;
    }

    .chart-container {
        background: rgba(18, 22, 35, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .chart-container h6 {
        margin-bottom: 15px;
        color: #e5e7eb;
    }
    .chart-container canvas {
        max-height: 280px;
    }

    .filtros-reporte {
        background: rgba(18, 22, 35, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .btn-export {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s;
    }
    .btn-export:hover {
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    .btn-export-excel {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }
    .btn-export-excel:hover {
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }
    .btn-export-pdf {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }
    .btn-export-pdf:hover {
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    .color-indicator {
        width: 12px;
        height: 12px;
        border-radius: 3px;
        display: inline-block;
        margin-right: 5px;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2><i class="fas fa-chart-bar me-2"></i> Reportes y Estadísticas</h2>
            <p class="text-muted mb-0">Análisis completo de datos del sistema minero</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-export btn-export-excel" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-2"></i>Excel
            </button>
            <button class="btn btn-export btn-export-pdf" onclick="exportarPDF()">
                <i class="fas fa-file-pdf me-2"></i>PDF
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-reporte">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Fecha Desde</label>
                <input type="date" id="fechaDesde" class="form-control" value="<?php echo $fecha_desde; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha Hasta</label>
                <input type="date" id="fechaHasta" class="form-control" value="<?php echo $fecha_hasta; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button onclick="aplicarFiltros()" class="btn btn-gradient w-100">
                    <i class="fas fa-sync me-2"></i>Actualizar Reportes
                </button>
            </div>
            <div class="col-md-3 text-end">
                <span class="text-muted">
                    <i class="fas fa-calendar me-1"></i>
                    <?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - 
                    <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="kpi-card">
                <div class="icon" style="color: #3b82f6;"><i class="fas fa-route"></i></div>
                <h2 style="color: #3b82f6;"><?php echo number_format($total_viajes); ?></h2>
                <div class="label">Total Viajes</div>
                <div class="sub"><?php echo number_format($viajes_completados); ?> completados</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="kpi-card">
                <div class="icon" style="color: #10b981;"><i class="fas fa-dollar-sign"></i></div>
                <h2 style="color: #10b981;">S/ <?php echo number_format($ingresos_totales, 0); ?></h2>
                <div class="label">Ingresos Totales</div>
                <div class="sub"><?php echo number_format($peso_total, 0); ?> TN transportadas</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="kpi-card">
                <div class="icon" style="color: #8b5cf6;"><i class="fas fa-users"></i></div>
                <h2 style="color: #8b5cf6;"><?php echo number_format($total_clientes); ?></h2>
                <div class="label">Clientes Activos</div>
                <div class="sub"><?php echo number_format($total_conductores); ?> conductores</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="kpi-card">
                <div class="icon" style="color: #f59e0b;"><i class="fas fa-truck"></i></div>
                <h2 style="color: #f59e0b;"><?php echo number_format($total_vehiculos); ?></h2>
                <div class="label">Vehículos Activos</div>
                <div class="sub">Flota operativa</div>
            </div>
        </div>
    </div>

    <!-- Gráficos Fila 1 -->
    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="chart-container">
                <h6><i class="fas fa-chart-line me-2"></i>Viajes por Mes</h6>
                <canvas id="viajesPorMesChart"></canvas>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="chart-container">
                <h6><i class="fas fa-chart-pie me-2"></i>Estado de Viajes</h6>
                <canvas id="estadoViajesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Gráficos Fila 2 -->
    <div class="row">
        <div class="col-lg-6 mb-3">
            <div class="chart-container">
                <h6><i class="fas fa-cubes me-2"></i>Top Materiales Transportados</h6>
                <canvas id="topMaterialesChart"></canvas>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="chart-container">
                <h6><i class="fas fa-chart-line me-2"></i>Ingresos Mensuales</h6>
                <canvas id="ingresosMensualesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Gráficos Fila 3 -->
    <div class="row">
        <div class="col-lg-6 mb-3">
            <div class="chart-container">
                <h6><i class="fas fa-id-card me-2"></i>Top Conductores</h6>
                <canvas id="topConductoresChart"></canvas>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="chart-container">
                <h6><i class="fas fa-building me-2"></i>Top Clientes</h6>
                <canvas id="topClientesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
// Datos PHP a JavaScript
const viajesPorMesData = <?php echo json_encode($viajes_por_mes); ?>;
const estadoViajesData = <?php echo json_encode($estado_viajes); ?>;
const topMaterialesData = <?php echo json_encode($top_materiales); ?>;
const ingresosMensualesData = <?php echo json_encode($ingresos_mensuales); ?>;
const topConductoresData = <?php echo json_encode($top_conductores); ?>;
const topClientesData = <?php echo json_encode($top_clientes); ?>;

const colores = {
    pendiente: '#3b82f6',
    en_progreso: '#f59e0b',
    completado: '#10b981',
    cancelado: '#ef4444'
};

const coloresChart = [
    '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ef4444',
    '#f97316', '#14b8a6', '#6366f1', '#ec4899', '#06b6d4'
];

// ========== GRÁFICO 1: Viajes por Mes ==========
const ctx1 = document.getElementById('viajesPorMesChart').getContext('2d');
new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: viajesPorMesData.map(d => d.mes),
        datasets: [
            {
                label: 'Completados',
                data: viajesPorMesData.map(d => d.completados || 0),
                backgroundColor: '#10b981',
                borderRadius: 4
            },
            {
                label: 'En Progreso',
                data: viajesPorMesData.map(d => d.en_progreso || 0),
                backgroundColor: '#f59e0b',
                borderRadius: 4
            },
            {
                label: 'Pendientes',
                data: viajesPorMesData.map(d => d.pendientes || 0),
                backgroundColor: '#3b82f6',
                borderRadius: 4
            },
            {
                label: 'Cancelados',
                data: viajesPorMesData.map(d => d.cancelados || 0),
                backgroundColor: '#ef4444',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                labels: { color: '#e5e7eb' }
            }
        },
        scales: {
            y: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#9ca3af' }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#9ca3af' }
            }
        }
    }
});

// ========== GRÁFICO 2: Estado de Viajes ==========
const ctx2 = document.getElementById('estadoViajesChart').getContext('2d');
const estadoLabels = estadoViajesData.map(d => d.estado.charAt(0).toUpperCase() + d.estado.slice(1));
const estadoColors = estadoViajesData.map(d => colores[d.estado] || '#6b7280');

new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: estadoLabels,
        datasets: [{
            data: estadoViajesData.map(d => d.total),
            backgroundColor: estadoColors,
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#e5e7eb' }
            }
        }
    }
});

// ========== GRÁFICO 3: Top Materiales ==========
const ctx3 = document.getElementById('topMaterialesChart').getContext('2d');
new Chart(ctx3, {
    type: 'bar',
    data: {
        labels: topMaterialesData.map(d => d.nombre),
        datasets: [{
            label: 'Toneladas Transportadas',
            data: topMaterialesData.map(d => d.total_peso),
            backgroundColor: topMaterialesData.map((_, i) => coloresChart[i % coloresChart.length]),
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#9ca3af' }
            },
            x: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#9ca3af' }
            }
        }
    }
});

// ========== GRÁFICO 4: Ingresos Mensuales ==========
const ctx4 = document.getElementById('ingresosMensualesChart').getContext('2d');
new Chart(ctx4, {
    type: 'line',
    data: {
        labels: ingresosMensualesData.map(d => d.mes),
        datasets: [{
            label: 'Ingresos (S/)',
            data: ingresosMensualesData.map(d => d.total_ingresos || 0),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#10b981'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                labels: { color: '#e5e7eb' }
            }
        },
        scales: {
            y: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#9ca3af' }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#9ca3af' }
            }
        }
    }
});

// ========== GRÁFICO 5: Top Conductores ==========
const ctx5 = document.getElementById('topConductoresChart').getContext('2d');
new Chart(ctx5, {
    type: 'bar',
    data: {
        labels: topConductoresData.map(d => d.nombre),
        datasets: [{
            label: 'Viajes Realizados',
            data: topConductoresData.map(d => d.total_viajes),
            backgroundColor: topConductoresData.map((_, i) => coloresChart[i % coloresChart.length]),
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#9ca3af' }
            },
            x: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#9ca3af' }
            }
        }
    }
});

// ========== GRÁFICO 6: Top Clientes ==========
const ctx6 = document.getElementById('topClientesChart').getContext('2d');
new Chart(ctx6, {
    type: 'bar',
    data: {
        labels: topClientesData.map(d => d.nombre),
        datasets: [{
            label: 'Viajes Realizados',
            data: topClientesData.map(d => d.total_viajes),
            backgroundColor: topClientesData.map((_, i) => coloresChart[(i + 3) % coloresChart.length]),
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#9ca3af' }
            },
            x: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#9ca3af' }
            }
        }
    }
});

// ========== FUNCIONES ==========

function aplicarFiltros() {
    const fecha_desde = document.getElementById('fechaDesde').value;
    const fecha_hasta = document.getElementById('fechaHasta').value;
    window.location.href = `index.php?fecha_desde=${fecha_desde}&fecha_hasta=${fecha_hasta}`;
}

function exportarExcel() {
    const data = [
        ['Reporte H&H MINERIA'],
        ['Generado:', new Date().toLocaleString()],
        [],
        ['Viajes por Mes'],
        ['Mes', 'Total', 'Completados', 'Pendientes', 'En Progreso', 'Cancelados']
    ];
    
    viajesPorMesData.forEach(d => {
        data.push([
            d.mes, d.total, d.completados || 0, 
            d.pendientes || 0, d.en_progreso || 0, d.cancelados || 0
        ]);
    });
    
    data.push([]);
    data.push(['Top Materiales']);
    data.push(['Material', 'Toneladas', 'Viajes']);
    topMaterialesData.forEach(d => {
        data.push([d.nombre, d.total_peso, d.total_viajes]);
    });
    
    data.push([]);
    data.push(['Estado de Viajes']);
    data.push(['Estado', 'Cantidad']);
    estadoViajesData.forEach(d => {
        data.push([d.estado, d.total]);
    });
    
    const ws = XLSX.utils.aoa_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Reporte');
    XLSX.writeFile(wb, `reporte_${new Date().toISOString().slice(0,10)}.xlsx`);
}

function exportarPDF() {
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html>
        <head>
            <title>Reporte H&H MINERIA</title>
            <style>
                body { font-family: Arial; margin: 30px; }
                .header { text-align: center; border-bottom: 2px solid #f59e0b; padding-bottom: 15px; margin-bottom: 20px; }
                .header h1 { color: #f59e0b; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background: #f59e0b; color: white; padding: 10px; text-align: left; }
                td { border: 1px solid #ddd; padding: 8px; }
                .section { margin-bottom: 30px; }
                .section h3 { color: #f59e0b; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🏗️ H&H MINERIA</h1>
                <p>Reporte de Gestión - ${new Date().toLocaleString()}</p>
                <p>Período: ${document.getElementById('fechaDesde').value} a ${document.getElementById('fechaHasta').value}</p>
            </div>
            
            <div class="section">
                <h3>📊 Estadísticas Generales</h3>
                <table>
                    <tr><td><strong>Total Viajes</strong></td><td><?php echo number_format($total_viajes); ?></td></tr>
                    <tr><td><strong>Viajes Completados</strong></td><td><?php echo number_format($viajes_completados); ?></td></tr>
                    <tr><td><strong>Ingresos Totales</strong></td><td>S/ <?php echo number_format($ingresos_totales, 2); ?></td></tr>
                    <tr><td><strong>Toneladas Transportadas</strong></td><td><?php echo number_format($peso_total, 2); ?> TN</td></tr>
                    <tr><td><strong>Clientes Activos</strong></td><td><?php echo number_format($total_clientes); ?></td></tr>
                    <tr><td><strong>Conductores</strong></td><td><?php echo number_format($total_conductores); ?></td></tr>
                    <tr><td><strong>Vehículos Activos</strong></td><td><?php echo number_format($total_vehiculos); ?></td></tr>
                </table>
            </div>
            
            <div class="section">
                <h3>📦 Top Materiales Transportados</h3>
                <table>
                    <tr><th>Material</th><th>Toneladas</th><th>Viajes</th></tr>
                    ${topMaterialesData.map(d => `<tr><td>${d.nombre}</td><td>${d.total_peso}</td><td>${d.total_viajes}</td></tr>`).join('')}
                </table>
            </div>
            
            <div class="section">
                <h3>👤 Top Conductores</h3>
                <table>
                    <tr><th>Conductor</th><th>Viajes</th><th>Toneladas</th></tr>
                    ${topConductoresData.map(d => `<tr><td>${d.nombre}</td><td>${d.total_viajes}</td><td>${d.total_peso}</td></tr>`).join('')}
                </table>
            </div>
            
            <div class="section">
                <h3>🏢 Top Clientes</h3>
                <table>
                    <tr><th>Cliente</th><th>Viajes</th><th>Toneladas</th></tr>
                    ${topClientesData.map(d => `<tr><td>${d.nombre}</td><td>${d.total_viajes}</td><td>${d.total_peso}</td></tr>`).join('')}
                </table>
            </div>
            
            <div class="section">
                <h3>📊 Estado de Viajes</h3>
                <table>
                    <tr><th>Estado</th><th>Cantidad</th></tr>
                    ${estadoViajesData.map(d => `<tr><td>${d.estado}</td><td>${d.total}</td></tr>`).join('')}
                </table>
            </div>
            
            <div style="text-align:center; margin-top:30px; color:#999; font-size:11px;">
                <p>Reporte generado automáticamente por el sistema H&H MINERIA</p>
            </div>
        </body>
        </html>
    `);
    ventana.document.close();
    ventana.print();
}

// Enter en inputs de fecha
document.getElementById('fechaDesde')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') aplicarFiltros();
});
document.getElementById('fechaHasta')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') aplicarFiltros();
});
</script>

<?php include '../../includes/footer.php'; ?>