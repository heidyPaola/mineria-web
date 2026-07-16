<?php
// modules/asignaciones/guardar.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $vehiculo_id = $_POST['vehiculo_id'];
    $conductor_id = $_POST['conductor_id'];
    $fecha_asignacion = $_POST['fecha_asignacion'];
    $fecha_fin = $_POST['fecha_fin'];
    $motivo = $_POST['motivo'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Verificar que el vehículo no esté asignado
    if ($id) {
        $check = "SELECT id FROM asignaciones WHERE vehiculo_id = :vehiculo_id AND id != :id AND estado = 'activa'";
        $stmt = $conn->prepare($check);
        $stmt->execute([':vehiculo_id' => $vehiculo_id, ':id' => $id]);
    } else {
        $check = "SELECT id FROM asignaciones WHERE vehiculo_id = :vehiculo_id AND estado = 'activa'";
        $stmt = $conn->prepare($check);
        $stmt->execute([':vehiculo_id' => $vehiculo_id]);
    }
    
    if ($stmt->fetch()) {
        $_SESSION['error_msg'] = "El vehículo ya está asignado a otro conductor.";
        header('Location: /MINERIA/modules/asignaciones/index.php?msg=error');
        exit();
    }
    
    // Verificar que el conductor no esté asignado
    if ($id) {
        $check = "SELECT id FROM asignaciones WHERE conductor_id = :conductor_id AND id != :id AND estado = 'activa'";
        $stmt = $conn->prepare($check);
        $stmt->execute([':conductor_id' => $conductor_id, ':id' => $id]);
    } else {
        $check = "SELECT id FROM asignaciones WHERE conductor_id = :conductor_id AND estado = 'activa'";
        $stmt = $conn->prepare($check);
        $stmt->execute([':conductor_id' => $conductor_id]);
    }
    
    if ($stmt->fetch()) {
        $_SESSION['error_msg'] = "El conductor ya está asignado a otro vehículo.";
        header('Location: /MINERIA/modules/asignaciones/index.php?msg=error');
        exit();
    }
    
    if ($id) {
        // ACTUALIZAR
        $query = "UPDATE asignaciones SET 
                  vehiculo_id = :vehiculo_id, conductor_id = :conductor_id,
                  fecha_asignacion = :fecha_asignacion, fecha_fin = :fecha_fin,
                  motivo = :motivo, observaciones = :observaciones
                  WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':vehiculo_id' => $vehiculo_id, ':conductor_id' => $conductor_id,
            ':fecha_asignacion' => $fecha_asignacion, ':fecha_fin' => $fecha_fin,
            ':motivo' => $motivo, ':observaciones' => $observaciones, ':id' => $id
        ]);
        registrarAuditoria($conn, 'ACTUALIZAR', 'asignaciones', $id);
        
        // Redirigir según origen (calendario o listado)
        if (isset($_POST['origen']) && $_POST['origen'] == 'calendario') {
            header('Location: /MINERIA/modules/asignaciones/calendario.php?msg=actualizado');
        } else {
            header('Location: /MINERIA/modules/asignaciones/index.php?msg=actualizado');
        }
        exit();
    } else {
        // CREAR NUEVO
        $query = "INSERT INTO asignaciones (vehiculo_id, conductor_id, fecha_asignacion, fecha_fin, motivo, observaciones, estado)
                  VALUES (:vehiculo_id, :conductor_id, :fecha_asignacion, :fecha_fin, :motivo, :observaciones, 'activa')";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':vehiculo_id' => $vehiculo_id, ':conductor_id' => $conductor_id,
            ':fecha_asignacion' => $fecha_asignacion, ':fecha_fin' => $fecha_fin,
            ':motivo' => $motivo, ':observaciones' => $observaciones
        ]);
        
        // Actualizar estado del conductor a ocupado
        $conn->prepare("UPDATE conductores SET estado = 'ocupado' WHERE id = :id")->execute([':id' => $conductor_id]);
        
        registrarAuditoria($conn, 'CREAR', 'asignaciones', $conn->lastInsertId());
        
        // Redirigir según origen (calendario o listado)
        if (isset($_POST['origen']) && $_POST['origen'] == 'calendario') {
            header('Location: /MINERIA/modules/asignaciones/calendario.php?msg=creado');
        } else {
            header('Location: /MINERIA/modules/asignaciones/index.php?msg=creado');
        }
        exit();
    }
}

header('Location: /MINERIA/modules/asignaciones/index.php');
exit();
?>