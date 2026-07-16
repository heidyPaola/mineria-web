<?php
// modules/alertas/alertas_automaticas.php
// Script para generar alertas automáticas
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();
$alertas_generadas = 0;

// ========== 1. RETRASO EN VIAJES ==========
$query = "SELECT v.*, 
          TIMESTAMPDIFF(HOUR, v.created_at, NOW()) as horas_sin_actualizar
          FROM viajes v
          WHERE v.estado IN ('pendiente', 'en_progreso')
          AND TIMESTAMPDIFF(HOUR, v.updated_at, NOW()) > 2
          AND NOT EXISTS (SELECT 1 FROM alertas WHERE viaje_id = v.id AND categoria = 'Retraso Viaje' AND estado = 'activa')";
$stmt = $conn->query($query);
$viajes_retrasados = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($viajes_retrasados as $viaje) {
    $horas = ceil($viaje['horas_sin_actualizar']);
    $nivel = $horas > 4 ? 'critica' : ($horas > 2 ? 'alta' : 'media');
    
    $query = "INSERT INTO alertas (nivel, titulo, descripcion, viaje_id, categoria, estado, prioridad) 
              VALUES (:nivel, :titulo, :descripcion, :viaje_id, 'Retraso Viaje', 'activa', :prioridad)";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':nivel' => $nivel,
        ':titulo' => "⏰ Retraso en viaje {$viaje['codigo']}",
        ':descripcion' => "El viaje {$viaje['codigo']} lleva {$horas} horas sin actualización. Estado actual: {$viaje['estado']}.",
        ':viaje_id' => $viaje['id'],
        ':prioridad' => $horas > 4 ? 5 : 3
    ]);
    $alertas_generadas++;
}

// ========== 2. STOCK BAJO DE MATERIALES ==========
$query = "SELECT * FROM materiales 
          WHERE stock_actual <= stock_minimo 
          AND stock_minimo > 0
          AND NOT EXISTS (SELECT 1 FROM alertas WHERE viaje_id = materiales.id AND categoria = 'Stock Bajo' AND estado = 'activa')";
$stmt = $conn->query($query);
$materiales_bajos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($materiales_bajos as $material) {
    $query = "INSERT INTO alertas (nivel, titulo, descripcion, viaje_id, categoria, estado, prioridad) 
              VALUES (:nivel, :titulo, :descripcion, NULL, 'Stock Bajo', 'activa', :prioridad)";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':nivel' => 'alta',
        ':titulo' => "📦 Stock bajo de {$material['nombre']}",
        ':descripcion' => "El stock de {$material['nombre']} está en {$material['stock_actual']} ({$material['unidad_medida']}). Mínimo: {$material['stock_minimo']}.",
        ':prioridad' => 4
    ]);
    $alertas_generadas++;
}

// ========== 3. VEHÍCULOS EN MANTENIMIENTO > 7 DÍAS ==========
$query = "SELECT * FROM vehiculos 
          WHERE estado = 'mantenimiento' 
          AND DATEDIFF(NOW(), updated_at) > 7
          AND NOT EXISTS (SELECT 1 FROM alertas WHERE viaje_id = vehiculos.id AND categoria = 'Mantenimiento Largo' AND estado = 'activa')";
$stmt = $conn->query($query);
$vehiculos_mantenimiento = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($vehiculos_mantenimiento as $vehiculo) {
    $dias = ceil((time() - strtotime($vehiculo['updated_at'])) / (60 * 60 * 24));
    $query = "INSERT INTO alertas (nivel, titulo, descripcion, viaje_id, categoria, estado, prioridad) 
              VALUES (:nivel, :titulo, :descripcion, NULL, 'Mantenimiento Largo', 'activa', :prioridad)";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':nivel' => 'media',
        ':titulo' => "🚛 Vehículo {$vehiculo['placa']} en mantenimiento prolongado",
        ':descripcion' => "El vehículo {$vehiculo['placa']} lleva {$dias} días en mantenimiento.",
        ':prioridad' => 2
    ]);
    $alertas_generadas++;
}

// ========== 4. DOCUMENTOS VENCIDOS ==========
// SOAT vencido
$query = "SELECT * FROM vehiculos 
          WHERE soat_vencimiento < CURDATE() 
          AND soat_vencimiento IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM alertas WHERE viaje_id = vehiculos.id AND categoria = 'SOAT Vencido' AND estado = 'activa')";
$stmt = $conn->query($query);
$vehiculos_soat = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($vehiculos_soat as $vehiculo) {
    $query = "INSERT INTO alertas (nivel, titulo, descripcion, viaje_id, categoria, estado, prioridad) 
              VALUES (:nivel, :titulo, :descripcion, NULL, 'SOAT Vencido', 'activa', :prioridad)";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':nivel' => 'alta',
        ':titulo' => "📄 SOAT vencido - Vehículo {$vehiculo['placa']}",
        ':descripcion' => "El SOAT del vehículo {$vehiculo['placa']} venció el " . date('d/m/Y', strtotime($vehiculo['soat_vencimiento'])),
        ':prioridad' => 4
    ]);
    $alertas_generadas++;
}

// Revisión técnica vencida
$query = "SELECT * FROM vehiculos 
          WHERE revision_tecnica < CURDATE() 
          AND revision_tecnica IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM alertas WHERE viaje_id = vehiculos.id AND categoria = 'Revision Técnica Vencida' AND estado = 'activa')";
$stmt = $conn->query($query);
$vehiculos_revision = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($vehiculos_revision as $vehiculo) {
    $query = "INSERT INTO alertas (nivel, titulo, descripcion, viaje_id, categoria, estado, prioridad) 
              VALUES (:nivel, :titulo, :descripcion, NULL, 'Revision Técnica Vencida', 'activa', :prioridad)";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':nivel' => 'alta',
        ':titulo' => "📄 Revisión técnica vencida - Vehículo {$vehiculo['placa']}",
        ':descripcion' => "La revisión técnica del vehículo {$vehiculo['placa']} venció el " . date('d/m/Y', strtotime($vehiculo['revision_tecnica'])),
        ':prioridad' => 3
    ]);
    $alertas_generadas++;
}

// ========== 5. CONDUCTORES INACTIVOS ==========
$query = "SELECT c.*, 
          DATEDIFF(NOW(), MAX(v.fecha_viaje)) as dias_sin_viaje
          FROM conductores c
          LEFT JOIN viajes v ON c.id = v.conductor_id
          WHERE c.estado = 'disponible'
          GROUP BY c.id
          HAVING dias_sin_viaje > 30 OR dias_sin_viaje IS NULL
          AND NOT EXISTS (SELECT 1 FROM alertas WHERE viaje_id = c.id AND categoria = 'Conductor Inactivo' AND estado = 'activa')";
$stmt = $conn->query($query);
$conductores_inactivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($conductores_inactivos as $conductor) {
    $dias = $conductor['dias_sin_viaje'] ?? 'más de 30';
    $query = "INSERT INTO alertas (nivel, titulo, descripcion, viaje_id, categoria, estado, prioridad) 
              VALUES (:nivel, :titulo, :descripcion, NULL, 'Conductor Inactivo', 'activa', :prioridad)";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':nivel' => 'baja',
        ':titulo' => "👤 Conductor inactivo: {$conductor['nombre']}",
        ':descripcion' => "El conductor {$conductor['nombre']} no tiene viajes registrados en los últimos {$dias} días.",
        ':prioridad' => 1
    ]);
    $alertas_generadas++;
}

// Registrar en auditoría
if ($alertas_generadas > 0) {
    registrarAuditoria($conn, 'ALERTAS_AUTOMATICAS', 'alertas', 0, null, ['generadas' => $alertas_generadas]);
}

echo "✅ Se generaron {$alertas_generadas} alertas automáticas.";
?>