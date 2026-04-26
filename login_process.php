<?php
session_start(); // Iniciamos el motor de sesiones de PHP
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // Buscamos el usuario por su correo
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        // Verificamos si existe el usuario y si la contraseña coincide con el Hash
        if ($usuario && password_verify($password, $usuario['password'])) {
            
            // Login Exitoso: Guardamos sus datos en la sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];

            // Redirigimos al Dashboard
            header("Location: index.php");
            exit;
        } else {
            // Login Fallido: Contraseña o correo incorrecto
            header("Location: login.php?error=credenciales");
            exit;
        }
    } catch (Exception $e) {
        die("Error en el sistema: " . $e->getMessage());
    }
} else {
    header("Location: login.php");
    exit;
}
?>