<?php
// test_db.php - Archivo para probar la conexión a la base de datos

echo "<h1>🔍 Prueba de conexión a la base de datos</h1>";

// Datos de conexión (USANDO EL NUEVO USUARIO render_user)
$host = 'mysql-10b9a518-undac-bf50.k.aivencloud.com';
$port = '15798';
$dbname = 'defaultdb';
$user = 'render_user';
$password = 'AVNS_EtZYn04W0bIrnv3AMLV';

echo "<h2>📊 Datos de conexión:</h2>";
echo "<ul>";
echo "<li><strong>Host:</strong> $host</li>";
echo "<li><strong>Puerto:</strong> $port</li>";
echo "<li><strong>Base de datos:</strong> $dbname</li>";
echo "<li><strong>Usuario:</strong> $user</li>";
echo "</ul>";

echo "<h2>🔗 Intentando conectar...</h2>";

try {
    // Intentar conexión PDO con SSL
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
    
    echo "<h2 style='color: green;'>✅ ¡Conexión exitosa!</h2>";
    
    // Probar consulta simple
    $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch();
    echo "<p>📋 Total de usuarios en la base de datos: <strong>" . $result['total'] . "</strong></p>";
    
} catch(PDOException $e) {
    echo "<h2 style='color: red;'>❌ Error de conexión</h2>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    
    echo "<h3>🔍 Detalles del error:</h3>";
    echo "<ul>";
    echo "<li><strong>Código:</strong> " . $e->getCode() . "</li>";
    echo "<li><strong>Archivo:</strong> " . $e->getFile() . "</li>";
    echo "<li><strong>Línea:</strong> " . $e->getLine() . "</li>";
    echo "</ul>";
}
?>