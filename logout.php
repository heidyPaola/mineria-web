<?php
// logout.php
// Cierra la sesión del usuario

// Iniciar sesión
session_start();

// Incluir conexión para auditoría
require_once 'config/conexion.php';

// Registrar cierre de sesión en auditoría
if (isset($_SESSION['user_id'])) {
    $conn = getConnection();
    require_once 'config/auth.php';
    registrarAuditoria($conn, 'CIERRE_SESION', 'usuarios', $_SESSION['user_id'], null, [
        'username' => $_SESSION['user_username']
    ]);
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destruir la sesión
session_destroy();

// Redirigir al login con mensaje
header('Location: login.php?msg=logout');
exit();
?>