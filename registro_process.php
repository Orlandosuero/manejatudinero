<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Validaciones básicas
    if (empty($nombre) || empty($email) || empty($password)) {
        header("Location: registro_cuenta.php?error=vacio");
        exit;
    }
    
    if (strlen($password) < 6) {
        header("Location: registro_cuenta.php?error=clave_corta");
        exit;
    }

    try {
        // 2. Verificar si el correo ya existe
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $stmtCheck->execute([':email' => $email]);
        
        if ($stmtCheck->fetch()) {
            // El usuario ya existe
            header("Location: registro_cuenta.php?error=existe");
            exit;
        }

        // 3. Encriptar la contraseña (HASH seguro)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 4. Insertar el nuevo usuario en la base de datos
        $sql = "INSERT INTO usuarios (nombre, email, password) VALUES (:nombre, :email, :password)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':email' => $email,
            ':password' => $hashed_password
        ]);

        // 5. Iniciar sesión automáticamente
        $_SESSION['usuario_id'] = $pdo->lastInsertId();
        $_SESSION['usuario_nombre'] = $nombre;
        $_SESSION['usuario_email'] = $email;

        // 6. ¡Redirigir al Dashboard!
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        die("Error crítico al registrar: " . $e->getMessage());
    }
} else {
    header("Location: registro_cuenta.php");
    exit;
}
?>