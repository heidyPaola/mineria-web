<?php
// modules/auditoria/marcar_revisado.php
require_once '../../config/auth.php';
requireLogin();
requireRole('admin');
require_once '../../config/conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit();
}

$conn = getConnection();

$query = "UPDATE auditoria SET 
          revisado = 1,
          revisado_por = :revisado_por,
          fecha_revision = NOW()
          WHERE id = :id";

$stmt = $conn->prepare($query);
$stmt->execute([
    ':revisado_por' => $_SESSION['user_id'],
    ':id' => $_POST['id']
]);

echo json_encode(['success' => true]);
?>