<?php
// modules/usuarios/cambiar_password.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

// Verificar que el usuario tenga permiso (admin o supervisor)
$user_rol = $_SESSION['user_rol'];
if ($user_rol != 'admin' && $user_rol != 'supervisor') {
    $_SESSION['error_msg'] = "No tienes permiso para cambiar contraseñas.";
    header('Location: index.php?msg=error');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario_id = $_POST['usuario_id'] ?? null;
    $usuario_nombre = $_POST['usuario_nombre'] ?? '';
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    $mi_password = $_POST['mi_password'] ?? '';
    
    // Validaciones
    if (!$usuario_id) {
        $_SESSION['error_msg'] = "ID de usuario no proporcionado.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    if (empty($nueva_password) || empty($confirmar_password) || empty($mi_password)) {
        $_SESSION['error_msg'] = "Todos los campos son obligatorios.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    if ($nueva_password !== $confirmar_password) {
        $_SESSION['error_msg'] = "Las contraseñas no coinciden.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    if (strlen($nueva_password) < 6) {
        $_SESSION['error_msg'] = "La contraseña debe tener al menos 6 caracteres.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    // Verificar la contraseña del usuario que está haciendo el cambio
    $query = "SELECT password FROM usuarios WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($mi_password, $user['password'])) {
        $_SESSION['error_msg'] = "Tu contraseña actual es incorrecta.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    // Verificar que el usuario a modificar existe
    $query = "SELECT id, nombre, rol FROM usuarios WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $usuario_id]);
    $usuario_a_modificar = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario_a_modificar) {
        $_SESSION['error_msg'] = "Usuario no encontrado.";
        header('Location: index.php?msg=error');
        exit();
    }
    
    // Restricciones según rol
    if ($user_rol == 'supervisor') {
        // Supervisor solo puede cambiar contraseña de operadores y clientes
        if (!in_array($usuario_a_modificar['rol'], ['operador', 'cliente'])) {
            $_SESSION['error_msg'] = "No puedes cambiar la contraseña de este usuario.";
            header('Location: index.php?msg=error');
            exit();
        }
    }
    
    // Si el usuario a modificar es el mismo que está haciendo el cambio, permitir siempre
    $is_self = ($usuario_id == $_SESSION['user_id']);
    
    // Actualizar contraseña
    $hashed_password = password_hash($nueva_password, PASSWORD_DEFAULT);
    $query = "UPDATE usuarios SET password = :password WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':password' => $hashed_password,
        ':id' => $usuario_id
    ]);
    
    // Registrar en auditoría
    registrarAuditoria($conn, 'CAMBIAR_PASSWORD', 'usuarios', $usuario_id, null, [
        'usuario' => $usuario_a_modificar['nombre'],
        'cambiado_por' => $_SESSION['user_nombre']
    ]);
    
    // Si es el mismo usuario, cerrar sesión y pedir login nuevamente
    if ($is_self) {
        session_destroy();
        header('Location: /MINERIA/login.php?msg=password_cambiado');
        exit();
    }
    
    header('Location: index.php?msg=password_cambiado');
    exit();
}

header('Location: index.php');
?>