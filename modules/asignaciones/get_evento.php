<?php
// modules/asignaciones/get_evento.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit();
}

$conn = getConnection();
$id = $_GET['id'];

$query = "SELECT a.*, 
          v.placa, v.marca, v.modelo,
          c.nombre as conductor_nombre
          FROM asignaciones a
          LEFT JOIN vehiculos v ON a.vehiculo_id = v.id
          LEFT JOIN conductores c ON a.conductor_id = c.id
          WHERE a.id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$asignacion = $stmt->fetch(PDO::FETCH_ASSOC);

if ($asignacion) {
    echo json_encode(['success' => true, 'evento' => $asignacion]);
} else {
    echo json_encode(['success' => false, 'message' => 'Asignación no encontrada']);
}
?>