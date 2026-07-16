<?php
// modules/asignaciones/actualizar_fechas.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$id = $_POST['id'] ?? null;
$fecha_inicio = $_POST['fecha_inicio'] ?? null;
$fecha_fin = $_POST['fecha_fin'] ?? null;

if (!$id || !$fecha_inicio || !$fecha_fin) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$conn = getConnection();

$check = "SELECT id FROM asignaciones WHERE id = :id";
$stmt = $conn->prepare($check);
$stmt->execute([':id' => $id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Asignación no encontrada']);
    exit();
}

$query = "UPDATE asignaciones SET 
          fecha_asignacion = :fecha_inicio, 
          fecha_fin = :fecha_fin 
          WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin,
    ':id' => $id
]);

registrarAuditoria($conn, 'ACTUALIZAR_FECHAS', 'asignaciones', $id);

echo json_encode(['success' => true]);
?>