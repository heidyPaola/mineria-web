<?php
// modules/usuarios/guardar.php
require_once '../../config/auth.php';
requireLogin();
requireRole('admin');
require_once '../../config/conexion.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $username = trim($_POST['username']);
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol = $_POST['rol'];
    $estado = $_POST['estado'] ?? 1;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validaciones
    if (empty($username) || empty($nombre) || empty($email) || empty($rol)) {
        $_SESSION['error_msg'] = "Todos los campos son obligatorios.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_msg'] = "Email inválido.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    if ($id) {
        // ACTUALIZAR
        $query = "UPDATE usuarios SET 
                  username = :username, nombre = :nombre, email = :email,
                  rol = :rol, estado = :estado
                  WHERE id = :id";
        $params = [
            ':username' => $username, ':nombre' => $nombre, ':email' => $email,
            ':rol' => $rol, ':estado' => $estado, ':id' => $id
        ];
        
        // Si se proporcionó contraseña, actualizarla
        if (!empty($password)) {
            if ($password !== $confirm_password) {
                $_SESSION['error_msg'] = "Las contraseñas no coinciden.";
                header('Location: index.php?msg=error');
                exit();
            }
            if (strlen($password) < 6) {
                $_SESSION['error_msg'] = "La contraseña debe tener al menos 6 caracteres.";
                header('Location: index.php?msg=error');
                exit();
            }
            $query = "UPDATE usuarios SET 
                      username = :username, nombre = :nombre, email = :email,
                      rol = :rol, estado = :estado, password = :password
                      WHERE id = :id";
            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        registrarAuditoria($conn, 'ACTUALIZAR', 'usuarios', $id);
        header('Location: index.php?msg=actualizado');
    } else {
        // CREAR NUEVO
        if (empty($password) || empty($confirm_password)) {
            $_SESSION['error_msg'] = "La contraseña es obligatoria para nuevos usuarios.";
            header('Location: index.php?msg=error');
            exit();
        }
        
        if ($password !== $confirm_password) {
            $_SESSION['error_msg'] = "Las contraseñas no coinciden.";
            header('Location: index.php?msg=error');
            exit();
        }
        
        if (strlen($password) < 6) {
            $_SESSION['error_msg'] = "La contraseña debe tener al menos 6 caracteres.";
            header('Location: index.php?msg=error');
            exit();
        }
        
        // Verificar usuario único
        $check = "SELECT id FROM usuarios WHERE username = :username";
        $stmt = $conn->prepare($check);
        $stmt->execute([':username' => $username]);
        if ($stmt->fetch()) {
            $_SESSION['error_msg'] = "El usuario ya existe.";
            header('Location: index.php?msg=error');
            exit();
        }
        
        // Verificar email único
        $check = "SELECT id FROM usuarios WHERE email = :email";
        $stmt = $conn->prepare($check);
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $_SESSION['error_msg'] = "El email ya está registrado.";
            header('Location: index.php?msg=error');
            exit();
        }
        
        $query = "INSERT INTO usuarios (username, nombre, email, password, rol, estado)
                  VALUES (:username, :nombre, :email, :password, :rol, :estado)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':username' => $username, ':nombre' => $nombre, ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':rol' => $rol, ':estado' => $estado
        ]);
        registrarAuditoria($conn, 'CREAR', 'usuarios', $conn->lastInsertId());
        header('Location: index.php?msg=creado');
    }
    exit();
}
header('Location: index.php');
?>