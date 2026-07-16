<?php
// modules/usuarios/get.php
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
$stmt = $conn->prepare("SELECT id, username, nombre, email, rol, estado FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $_GET['id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'usuario' => $usuario]);
?>