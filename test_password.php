<?php
require_once 'config/conexion.php';

$conn = getConnection();

$username = 'admin';
$password_input = 'admin123';

$query = "SELECT * FROM usuarios WHERE username = :username";
$stmt = $conn->prepare($query);
$stmt->execute([':username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Prueba de verificación</h2>";
echo "Usuario: " . $user['username'] . "<br>";
echo "Hash en BD: " . $user['password'] . "<br>";
echo "Longitud hash: " . strlen($user['password']) . "<br>";

if (password_verify($password_input, $user['password'])) {
    echo "✅ CONTRASEÑA CORRECTA<br>";
    echo "Puedes iniciar sesión correctamente.";
} else {
    echo "❌ CONTRASEÑA INCORRECTA<br>";
    echo "Necesitas actualizar la contraseña.";
}
?>