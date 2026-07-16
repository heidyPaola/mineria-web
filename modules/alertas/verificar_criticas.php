<?php
// modules/alertas/verificar_criticas.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

header('Content-Type: application/json');

$conn = getConnection();

$query = "SELECT * FROM alertas WHERE nivel IN ('critica', 'alta') AND estado = 'activa' ORDER BY nivel DESC, created_at DESC";
$stmt = $conn->query($query);
$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['alertas_criticas' => $alertas]);
?>