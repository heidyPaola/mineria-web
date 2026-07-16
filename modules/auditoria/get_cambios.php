<?php
// modules/auditoria/get_cambios.php
require_once '../../config/auth.php';
requireLogin();
requireRole('admin');
require_once '../../config/conexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM auditoria WHERE id = :id");
$stmt->execute([':id' => $_GET['id']]);
$auditoria = $stmt->fetch(PDO::FETCH_ASSOC);

if ($auditoria) {
    echo json_encode([
        'success' => true,
        'id' => $auditoria['id'],
        'usuario_nombre' => $auditoria['usuario_nombre'],
        'accion' => $auditoria['accion'],
        'tabla_afectada' => $auditoria['tabla_afectada'],
        'registro_id' => $auditoria['registro_id'],
        'datos_anteriores' => $auditoria['datos_anteriores'],
        'datos_nuevos' => $auditoria['datos_nuevos'],
        'notas' => $auditoria['notas'],
        'revisado' => $auditoria['revisado'],
        'fecha_revision' => $auditoria['fecha_revision']
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>