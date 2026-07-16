<?php
// modules/alertas/guardar.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $nivel = $_POST['nivel'];
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $viaje_id = $_POST['viaje_id'] ?? null;
    $estado = $_POST['estado'] ?? 'activa';
    $categoria = $_POST['categoria'] ?? 'General';
    $prioridad = $_POST['prioridad'] ?? 1;
    
    if ($id) {
        $query = "UPDATE alertas SET 
                  nivel = :nivel, titulo = :titulo, descripcion = :descripcion,
                  viaje_id = :viaje_id, estado = :estado, categoria = :categoria,
                  prioridad = :prioridad
                  WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nivel' => $nivel, ':titulo' => $titulo, ':descripcion' => $descripcion,
            ':viaje_id' => $viaje_id, ':estado' => $estado, ':categoria' => $categoria,
            ':prioridad' => $prioridad, ':id' => $id
        ]);
        registrarAuditoria($conn, 'ACTUALIZAR', 'alertas', $id);
        header('Location: index.php?msg=actualizado');
    } else {
        $query = "INSERT INTO alertas (nivel, titulo, descripcion, viaje_id, estado, categoria, prioridad, usuario_creo)
                  VALUES (:nivel, :titulo, :descripcion, :viaje_id, :estado, :categoria, :prioridad, :usuario_creo)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nivel' => $nivel, ':titulo' => $titulo, ':descripcion' => $descripcion,
            ':viaje_id' => $viaje_id, ':estado' => $estado, ':categoria' => $categoria,
            ':prioridad' => $prioridad, ':usuario_creo' => $_SESSION['user_id']
        ]);
        $nuevo_id = $conn->lastInsertId();
        registrarAuditoria($conn, 'CREAR', 'alertas', $nuevo_id);
        header('Location: index.php?msg=creado');
    }
    exit();
}
header('Location: index.php');
?>