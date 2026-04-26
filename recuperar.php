<?php
session_start();
require_once 'config/database.php';

$mensaje = ''; $tipo_mensaje = ''; $enlace_recuperacion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Verificar si el correo existe
    $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 1. Generar un Token único y su fecha de expiración (1 hora)
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // 2. Guardar el token en la base de datos
        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET reset_token = ?, token_expira = ? WHERE id = ?");
        $stmtUpdate->execute([$token, $expira, $user['id']]);

        // 3. Simular el envío del correo (Mostramos el link en pantalla para pruebas locales)
        $enlace_recuperacion = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/resetear.php?token=" . $token;
        
        $mensaje = "Hemos 'enviado' un correo de recuperación.";
        $tipo_mensaje = "success";
    } else {
        // Por seguridad, no decimos si el correo existe o no a atacantes, damos un mensaje genérico.
        $mensaje = "Si el correo existe en nuestro sistema, enviaremos las instrucciones.";
        $tipo_mensaje = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="bg-[#040812] font-sans h-screen flex items-center justify-center relative overflow-hidden selection:bg-[#CE1126]/30">
    
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-[500px] bg-[#002D62]/20 rounded-full blur-[120px] pointer-events-none -z-10"></div>

    <div class="w-full max-w-md p-8 md:p-10 bg-[#0a1122]/60 backdrop-blur-2xl border border-blue-900/50 rounded-[2.5rem] shadow-2xl relative z-10 mx-4">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-[#040812] border border-blue-500/30 rounded-2xl flex items-center justify-center mx-auto mb-4 text-blue-400 shadow-inner">
                <i class="ph ph-key text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Recuperar Acceso</h1>
            <p class="text-sm text-slate-400">Ingresa tu correo y te enviaremos un enlace para crear una nueva contraseña.</p>
        </div>

        <?php if($mensaje): ?>
            <div class="mb-6 p-4 rounded-xl text-center border shadow-lg <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-200' : 'bg-red-900/40 border-red-500/50 text-red-200' ?>">
                <p class="font-medium text-sm"><?= $mensaje ?></p>
            </div>
        <?php endif; ?>

        <?php if($enlace_recuperacion): ?>
            <div class="mb-6 p-5 rounded-2xl bg-[#040812] border border-dashed border-emerald-500/50 text-center">
                <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-2"><i class="ph ph-envelope-simple"></i> Simulación de Correo</p>
                <p class="text-sm text-slate-300 mb-4">Haz clic en el siguiente enlace para restablecer tu contraseña:</p>
                <a href="<?= $enlace_recuperacion ?>" class="inline-block bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2 px-6 rounded-xl text-sm transition-colors">Restablecer Contraseña</a>
            </div>
        <?php endif; ?>

        <form action="recuperar.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Correo Electrónico</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"><i class="ph ph-envelope-simple text-lg"></i></span>
                    <input type="email" name="email" required placeholder="tu@correo.com" class="w-full bg-[#040812] border border-slate-700 rounded-xl pl-12 pr-4 py-3.5 text-white focus:border-blue-500 focus:outline-none transition-colors">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-blue-900/30">Enviar Enlace</button>
        </form>

        <div class="mt-8 text-center">
            <a href="login.php" class="text-sm font-bold text-slate-400 hover:text-white transition-colors flex items-center justify-center gap-2">
                <i class="ph ph-arrow-left"></i> Volver al Login
            </a>
        </div>
    </div>
</body>
</html>