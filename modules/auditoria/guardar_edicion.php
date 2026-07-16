<?php
// modules/auditoria/guardar_edicion.php
require_once '../../config/auth.php';
requireLogin();
requireRole('admin');
require_once '../../config/conexion.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$conn = getConnection();

$query = "UPDATE auditoria SET 
          usuario_nombre = :usuario_nombre,
          accion = :accion,
          tabla_afectada = :tabla_afectada,
          registro_id = :registro_id,
          datos_anteriores = :datos_anteriores,
          datos_nuevos = :datos_nuevos,
          notas = :notas,
          revisado = :revisado,
          editado_por = :editado_por,
          fecha_edicion = NOW()
          WHERE id = :id";

$stmt = $conn->prepare($query);
$stmt->execute([
    ':usuario_nombre' => $data['usuario_nombre'],
    ':accion' => $data['accion'],
    ':tabla_afectada' => $data['tabla_afectada'],
    ':registro_id' => $data['registro_id'],
    ':datos_anteriores' => $data['datos_anteriores'],
    ':datos_nuevos' => $data['datos_nuevos'],
    ':notas' => $data['notas'],
    ':revisado' => $data['revisado'],
    ':editado_por' => $_SESSION['user_id'],
    ':id' => $data['id']
]);

echo json_encode(['success' => true]);
?>