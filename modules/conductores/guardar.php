<?php
// modules/conductores/guardar.php - VERSIÓN MEJORADA
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre']);
    $licencia = trim($_POST['licencia']);
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $estado = $_POST['estado'] ?? 'disponible';
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    $fecha_vencimiento_licencia = $_POST['fecha_vencimiento_licencia'] ?? null;
    $numero_emergencia = $_POST['numero_emergencia'] ?? '';
    $experiencia_anos = $_POST['experiencia_anos'] ?? 0;
    $calificacion = $_POST['calificacion'] ?? 0;
    $puntuacion = $_POST['puntuacion'] ?? 0;
    $ultimo_servicio = $_POST['ultimo_servicio'] ?? null;
    
    if ($id) {
        // ACTUALIZAR
        $query = "UPDATE conductores SET 
                  nombre = :nombre, 
                  licencia = :licencia, 
                  telefono = :telefono, 
                  email = :email, 
                  direccion = :direccion, 
                  estado = :estado,
                  fecha_nacimiento = :fecha_nacimiento,
                  fecha_vencimiento_licencia = :fecha_vencimiento_licencia,
                  numero_emergencia = :numero_emergencia,
                  experiencia_anos = :experiencia_anos,
                  calificacion = :calificacion,
                  puntuacion = :puntuacion,
                  ultimo_servicio = :ultimo_servicio
                  WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nombre' => $nombre,
            ':licencia' => $licencia,
            ':telefono' => $telefono,
            ':email' => $email,
            ':direccion' => $direccion,
            ':estado' => $estado,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':fecha_vencimiento_licencia' => $fecha_vencimiento_licencia,
            ':numero_emergencia' => $numero_emergencia,
            ':experiencia_anos' => $experiencia_anos,
            ':calificacion' => $calificacion,
            ':puntuacion' => $puntuacion,
            ':ultimo_servicio' => $ultimo_servicio,
            ':id' => $id
        ]);
        registrarAuditoria($conn, 'ACTUALIZAR', 'conductores', $id);
        header('Location: index.php?msg=actualizado');
    } else {
        // CREAR NUEVO
        $query = "INSERT INTO conductores (
                    nombre, licencia, telefono, email, direccion, estado,
                    fecha_nacimiento, fecha_vencimiento_licencia, numero_emergencia,
                    experiencia_anos, calificacion, puntuacion, ultimo_servicio
                  ) VALUES (
                    :nombre, :licencia, :telefono, :email, :direccion, :estado,
                    :fecha_nacimiento, :fecha_vencimiento_licencia, :numero_emergencia,
                    :experiencia_anos, :calificacion, :puntuacion, :ultimo_servicio
                  )";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nombre' => $nombre,
            ':licencia' => $licencia,
            ':telefono' => $telefono,
            ':email' => $email,
            ':direccion' => $direccion,
            ':estado' => $estado,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':fecha_vencimiento_licencia' => $fecha_vencimiento_licencia,
            ':numero_emergencia' => $numero_emergencia,
            ':experiencia_anos' => $experiencia_anos,
            ':calificacion' => $calificacion,
            ':puntuacion' => $puntuacion,
            ':ultimo_servicio' => $ultimo_servicio
        ]);
        registrarAuditoria($conn, 'CREAR', 'conductores', $conn->lastInsertId());
        header('Location: index.php?msg=creado');
    }
    exit();
}

header('Location: index.php');
exit();
?>