<?php
// config/auth.php
// Este archivo maneja la autenticación, sesiones y auditoría

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario ha iniciado sesión
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Requerir que el usuario esté logueado (si no, redirigir al login)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Verificar si el usuario tiene un rol específico
function hasRole($role) {
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] == $role;
}

// Requerir un rol específico (si no, mostrar error 403)
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('HTTP/1.0 403 Forbidden');
        echo "<h1>Acceso Denegado (403)</h1>";
        echo "<p>No tienes permiso para acceder a esta sección.</p>";
        echo "<a href='index.php'>Volver al Dashboard</a>";
        exit();
    }
}

// Obtener datos del usuario actual
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['user_username'] ?? null,
        'nombre' => $_SESSION['user_nombre'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'rol' => $_SESSION['user_rol'] ?? null
    ];
}

// Registrar acciones en la tabla de auditoría
function registrarAuditoria($conn, $accion, $tabla, $registro_id, $datos_anteriores = null, $datos_nuevos = null) {
    // Obtener datos del usuario actual
    $user_id = $_SESSION['user_id'] ?? null;
    $user_nombre = $_SESSION['user_nombre'] ?? 'Sistema';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Preparar consulta SQL
    $query = "INSERT INTO auditoria (usuario_id, usuario_nombre, accion, tabla_afectada, 
              registro_id, datos_anteriores, datos_nuevos, ip_address) 
              VALUES (:usuario_id, :usuario_nombre, :accion, :tabla, :registro_id, 
              :datos_anteriores, :datos_nuevos, :ip_address)";
    
    $stmt = $conn->prepare($query);
    
    // Ejecutar consulta
    $stmt->execute([
        ':usuario_id' => $user_id,
        ':usuario_nombre' => $user_nombre,
        ':accion' => $accion,
        ':tabla' => $tabla,
        ':registro_id' => $registro_id,
        ':datos_anteriores' => $datos_anteriores ? json_encode($datos_anteriores) : null,
        ':datos_nuevos' => $datos_nuevos ? json_encode($datos_nuevos) : null,
        ':ip_address' => $ip_address
    ]);
}
?>