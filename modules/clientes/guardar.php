<?php
// modules/clientes/guardar.php
// Guardar nuevo cliente o actualizar existente

require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre']);
    $ruc = trim($_POST['ruc']);
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    
    // Validaciones
    $errors = [];
    
    if (empty($nombre)) {
        $errors[] = 'El nombre es obligatorio';
    }
    
    if (empty($ruc)) {
        $errors[] = 'El RUC es obligatorio';
    }
    
    if (!empty($errors)) {
        $_SESSION['error_msg'] = implode(', ', $errors);
        header('Location: index.php');
        exit();
    }
    
    if ($id) {
        // ACTUALIZAR cliente existente
        $query = "UPDATE clientes SET 
                  nombre = :nombre, 
                  ruc = :ruc, 
                  telefono = :telefono, 
                  email = :email, 
                  direccion = :direccion 
                  WHERE id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nombre' => $nombre,
            ':ruc' => $ruc,
            ':telefono' => $telefono,
            ':email' => $email,
            ':direccion' => $direccion,
            ':id' => $id
        ]);
        
        registrarAuditoria($conn, 'ACTUALIZAR', 'clientes', $id, null, [
            'nombre' => $nombre,
            'ruc' => $ruc
        ]);
        
        header('Location: index.php?msg=actualizado');
    } else {
        // CREAR nuevo cliente
        // Verificar si RUC ya existe
        $check = "SELECT id FROM clientes WHERE ruc = :ruc";
        $stmt = $conn->prepare($check);
        $stmt->execute([':ruc' => $ruc]);
        
        if ($stmt->fetch()) {
            $_SESSION['error_msg'] = 'El RUC ya está registrado';
            header('Location: index.php');
            exit();
        }
        
        $query = "INSERT INTO clientes (nombre, ruc, telefono, email, direccion) 
                  VALUES (:nombre, :ruc, :telefono, :email, :direccion)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nombre' => $nombre,
            ':ruc' => $ruc,
            ':telefono' => $telefono,
            ':email' => $email,
            ':direccion' => $direccion
        ]);
        
        $nuevoId = $conn->lastInsertId();
        registrarAuditoria($conn, 'CREAR', 'clientes', $nuevoId, null, [
            'nombre' => $nombre,
            'ruc' => $ruc
        ]);
        
        header('Location: index.php?msg=creado');
    }
    exit();
}

header('Location: index.php');
exit();
?>