<?php
// modules/rutas/get.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM rutas WHERE id = :id");
$stmt->execute([':id' => $_GET['id']]);
$ruta = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'ruta' => $ruta]);
?>