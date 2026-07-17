<?php
// config/conexion.php
// Este archivo maneja la conexión a la base de datos MySQL

class Database {
    // Configuración de la base de datos (usa variables de entorno o valores por defecto)
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    // Constructor que lee variables de entorno
    public function __construct() {
        // Usar variables de entorno si existen, si no usar valores por defecto
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'mineria_control';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
    }

    // Método para obtener la conexión
    public function getConnection() {
        $this->conn = null;
        
        try {
            // Crear nueva conexión PDO
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
        } catch(PDOException $exception) {
            // Si hay error, registrar en logs y mostrar mensaje
            error_log("Error de conexión a la base de datos: " . $exception->getMessage());
            die("Error de conexión: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}

// Función global para obtener conexión fácilmente
function getConnection() {
    $database = new Database();
    return $database->getConnection();
}

// Función para probar la conexión (útil para debugging)
function testConnection() {
    $conn = getConnection();
    if ($conn) {
        echo "✅ Conexión exitosa a la base de datos<br>";
        return true;
    } else {
        echo "❌ Error de conexión<br>";
        return false;
    }
}
?>