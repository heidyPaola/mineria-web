<?php
// modules/materiales/get.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM materiales WHERE id = :id");
$stmt->execute([':id' => $_GET['id']]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'material' => $material]);
?>