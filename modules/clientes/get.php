<?php
// modules/clientes/get.php
// Obtener datos de un cliente por ID (para editar)

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

$query = "SELECT * FROM clientes WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if ($cliente) {
    echo json_encode(['success' => true, 'cliente' => $cliente]);
} else {
    echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
}
?>