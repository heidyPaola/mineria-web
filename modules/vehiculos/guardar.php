<?php
// modules/vehiculos/guardar.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $placa = strtoupper(trim($_POST['placa']));
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $año = $_POST['año'] ?? null;
    $capacidad = $_POST['capacidad'] ?? 0;
    $color = $_POST['color'] ?? '';
    $estado = $_POST['estado'] ?? 'activo';
    $numero_motor = $_POST['numero_motor'] ?? '';
    $numero_chasis = $_POST['numero_chasis'] ?? '';
    $soat_vencimiento = $_POST['soat_vencimiento'] ?? null;
    $revision_tecnica = $_POST['revision_tecnica'] ?? null;
    $seguro_vencimiento = $_POST['seguro_vencimiento'] ?? null;
    $ultimo_mantenimiento = $_POST['ultimo_mantenimiento'] ?? null;
    $proximo_mantenimiento = $_POST['proximo_mantenimiento'] ?? null;
    $kilometraje = $_POST['kilometraje'] ?? 0;
    $observaciones = $_POST['observaciones'] ?? '';
    
    if ($id) {
        $query = "UPDATE vehiculos SET 
                  placa=:placa, marca=:marca, modelo=:modelo, año=:año,
                  capacidad=:capacidad, color=:color, estado=:estado,
                  numero_motor=:numero_motor, numero_chasis=:numero_chasis,
                  soat_vencimiento=:soat_vencimiento, revision_tecnica=:revision_tecnica,
                  seguro_vencimiento=:seguro_vencimiento, ultimo_mantenimiento=:ultimo_mantenimiento,
                  proximo_mantenimiento=:proximo_mantenimiento, kilometraje=:kilometraje,
                  observaciones=:observaciones WHERE id=:id";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':placa' => $placa, ':marca' => $marca, ':modelo' => $modelo, ':año' => $año,
            ':capacidad' => $capacidad, ':color' => $color, ':estado' => $estado,
            ':numero_motor' => $numero_motor, ':numero_chasis' => $numero_chasis,
            ':soat_vencimiento' => $soat_vencimiento, ':revision_tecnica' => $revision_tecnica,
            ':seguro_vencimiento' => $seguro_vencimiento, ':ultimo_mantenimiento' => $ultimo_mantenimiento,
            ':proximo_mantenimiento' => $proximo_mantenimiento, ':kilometraje' => $kilometraje,
            ':observaciones' => $observaciones, ':id' => $id
        ]);
        registrarAuditoria($conn, 'ACTUALIZAR', 'vehiculos', $id);
        header('Location: index.php?msg=actualizado');
    } else {
        $query = "INSERT INTO vehiculos (placa, marca, modelo, año, capacidad, color, estado,
                  numero_motor, numero_chasis, soat_vencimiento, revision_tecnica, seguro_vencimiento,
                  ultimo_mantenimiento, proximo_mantenimiento, kilometraje, observaciones)
                  VALUES (:placa, :marca, :modelo, :año, :capacidad, :color, :estado,
                  :numero_motor, :numero_chasis, :soat_vencimiento, :revision_tecnica, :seguro_vencimiento,
                  :ultimo_mantenimiento, :proximo_mantenimiento, :kilometraje, :observaciones)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':placa' => $placa, ':marca' => $marca, ':modelo' => $modelo, ':año' => $año,
            ':capacidad' => $capacidad, ':color' => $color, ':estado' => $estado,
            ':numero_motor' => $numero_motor, ':numero_chasis' => $numero_chasis,
            ':soat_vencimiento' => $soat_vencimiento, ':revision_tecnica' => $revision_tecnica,
            ':seguro_vencimiento' => $seguro_vencimiento, ':ultimo_mantenimiento' => $ultimo_mantenimiento,
            ':proximo_mantenimiento' => $proximo_mantenimiento, ':kilometraje' => $kilometraje,
            ':observaciones' => $observaciones
        ]);
        registrarAuditoria($conn, 'CREAR', 'vehiculos', $conn->lastInsertId());
        header('Location: index.php?msg=creado');
    }
    exit();
}
header('Location: index.php');
?>