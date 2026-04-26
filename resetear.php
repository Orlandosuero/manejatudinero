<?php
session_start();
require_once 'config/database.php';

$mensaje = ''; $tipo_mensaje = '';
$token_valido = false;
$usuario_id = null;

// 1. Validar si el token viene en la URL y si es válido
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Buscar el usuario con este token que NO haya expirado
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND token_expira > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $token_valido = true;
        $usuario_id = $user['id'];
    } else {
        $mensaje = "El enlace de recuperación es inválido o ha expirado.";
        $tipo_mensaje = "error";
    }
} else {
    header("Location: login.php");
    exit;
}

// 2. Procesar la nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $nueva_password = $_POST['password'];
    $confirmar_password = $_POST['confirm_password'];

    if ($nueva_password === $confirmar_password) {
        if (strlen($nueva_password) >= 6) {
            // Encriptar la nueva contraseña
            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            
            // Actualizar la contraseña en la base de datos y destruir el token para que no se use de nuevo
            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, token_expira = NULL WHERE id = ?");
            $stmtUpdate->execute([$password_hash, $usuario_id]);
            
            $mensaje = "¡Tu contraseña ha sido actualizada con éxito!";
            $tipo_mensaje = "success";
            $token_valido = false; // Ocultar el formulario
        } else {
            $mensaje = "La contraseña debe tener al menos 6 caracteres.";
            $tipo_mensaje = "warning";
        }
    } else {
        $mensaje = "Las contraseñas no coinciden.";
        $tipo_mensaje = "warning";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Contraseña | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="bg-[#040812] font-sans h-screen flex items-center justify-center relative overflow-hidden selection:bg-emerald-500/30">
    
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-[500px] bg-emerald-900/20 rounded-full blur-[120px] pointer-events-none -z-10"></div>

    <div class="w-full max-w-md p-8 md:p-10 bg-[#0a1122]/60 backdrop-blur-2xl border border-emerald-900/30 rounded-[2.5rem] shadow-2xl relative z-10 mx-4">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-[#040812] border border-emerald-500/30 rounded-2xl flex items-center justify-center mx-auto mb-4 text-emerald-400 shadow-inner">
                <i class="ph ph-lock-key text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Nueva Contraseña</h1>
            <p class="text-sm text-slate-400">Asegúrate de usar una contraseña fuerte y que puedas recordar.</p>
        </div>

        <?php if($mensaje): ?>
            <div class="mb-6 p-4 rounded-xl text-center border shadow-lg <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-200' : 'bg-red-900/40 border-red-500/50 text-red-200' ?>">
                <p class="font-medium text-sm flex items-center justify-center gap-2">
                    <i class="ph <?= $tipo_mensaje === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-xl"></i>
                    <?= $mensaje ?>
                </p>
            </div>
            <?php if($tipo_mensaje === 'success'): ?>
                <a href="login.php" class="block w-full text-center bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-blue-900/30 mt-4">Ir a Iniciar Sesión</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if($token_valido): ?>
            <form action="resetear.php?token=<?= htmlspecialchars($token) ?>" method="POST" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nueva Contraseña</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"><i class="ph ph-lock text-lg"></i></span>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full bg-[#040812] border border-slate-700 rounded-xl pl-12 pr-4 py-3.5 text-white focus:border-emerald-500 focus:outline-none transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Confirmar Contraseña</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"><i class="ph ph-check-circle text-lg"></i></span>
                        <input type="password" name="confirm_password" required placeholder="••••••••" class="w-full bg-[#040812] border border-slate-700 rounded-xl pl-12 pr-4 py-3.5 text-white focus:border-emerald-500 focus:outline-none transition-colors">
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-emerald-900/30">Guardar Contraseña</button>
            </form>
        <?php endif; ?>
        
        <?php if(!$token_valido && $tipo_mensaje !== 'success'): ?>
             <div class="mt-8 text-center">
                <a href="recuperar.php" class="text-sm font-bold text-blue-400 hover:text-white transition-colors flex items-center justify-center gap-2">
                    <i class="ph ph-arrow-left"></i> Solicitar otro enlace
                </a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>