<?php
// login.php
// Página de inicio de sesión del sistema

// Iniciar sesión para verificar si ya está logueado
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Incluir conexión a la base de datos
require_once 'config/conexion.php';

$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getConnection();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, complete todos los campos';
    } else {
        // Buscar usuario por username
        $query = "SELECT * FROM usuarios WHERE username = :username AND estado = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar contraseña
        if ($user && password_verify($password, $user['password'])) {
            // Guardar datos en sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_rol'] = $user['rol'];
            
            // Actualizar último acceso e IP
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $query = "UPDATE usuarios SET ultimo_acceso = NOW(), ultimo_ip = :ip WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':ip' => $ip,
                ':id' => $user['id']
            ]);
            
            // Registrar en auditoría
            require_once 'config/auth.php';
            registrarAuditoria($conn, 'INICIO_SESION', 'usuarios', $user['id'], null, ['username' => $username]);
            
            // Redirigir al dashboard
            header('Location: index.php');
            exit();
        } else {
            $error = 'Usuario o contraseña incorrectos';
            
            // Registrar intento fallido
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            // Incrementar intentos fallidos
            $query = "UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE username = :username";
            $stmt = $conn->prepare($query);
            $stmt->execute([':username' => $username]);
            
            error_log("Intento de login fallido - Usuario: $username - IP: $ip");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - H&H MINERIA</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
        }
        .password-toggle:hover {
            color: #f59e0b;
        }
        .input-group-position {
            position: relative;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <i class="fas fa-hard-hat" style="font-size: 64px; color: #f59e0b;"></i>
                <h2 class="mt-3">H&H MINERIA</h2>
                <p class="text-muted">Sistema de Gestión Minera</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-user me-1"></i> Usuario
                    </label>
                    <input type="text" name="username" class="form-control" 
                           placeholder="Ingrese su usuario" required autofocus
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-lock me-1"></i> Contraseña
                    </label>
                    <div class="input-group-position">
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Ingrese su contraseña" required>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label text-muted" for="remember">
                        Recordarme
                    </label>
                </div>
                
                <button type="submit" class="btn btn-gradient w-100">
                    <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                </button>
            </form>
            
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            
            <div class="text-center">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> Credenciales de prueba:<br>
                    <strong>Usuario:</strong> admin | <strong>Contraseña:</strong> admin123
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar contraseña
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>