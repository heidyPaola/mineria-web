<?php
// config/conexion.php
// Este archivo maneja la conexión a la base de datos MySQL

class Database {
    // Configuración de la base de datos
    private $host = "localhost";      // Servidor de base de datos
    private $db_name = "mineria_control";  // Nombre de la base de datos
    private $username = "root";       // Usuario de MySQL
    private $password = "";           // Contraseña de MySQL (vacío en XAMPP por defecto)
    public $conn;

    // Método para obtener la conexión
    public function getConnection() {
        $this->conn = null;
        
        try {
            // Crear nueva conexión PDO
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            
            // Configurar errores de PDO
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Configurar codificación UTF-8
            $this->conn->exec("set names utf8");
            
        } catch(PDOException $exception) {
            // Si hay error, mostrar mensaje
            echo "Error de conexión: " . $exception->getMessage();
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