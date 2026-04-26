<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

$mensaje = '';
$tipo_mensaje = '';
$usuario_id = $_SESSION['usuario_id'];

// --- LÓGICA PARA GUARDAR PERFIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);

    $foto_ruta = null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $dir_subida = 'uploads/';
        if (!file_exists($dir_subida)) {
            mkdir($dir_subida, 0777, true);
        }

        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = 'perfil_' . $usuario_id . '_' . time() . '.' . $ext;
        $ruta_destino = $dir_subida . $nombre_archivo;

        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($ext), $tipos_permitidos)) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_destino)) {
                $foto_ruta = $ruta_destino;
            } else {
                $mensaje = "Error al subir la imagen.";
                $tipo_mensaje = 'error';
            }
        } else {
            $mensaje = "Formato de imagen no válido.";
            $tipo_mensaje = 'error';
        }
    }

    try {
        if ($foto_ruta) {
            $sql = "UPDATE usuarios SET nombre=?, email=?, telefono=?, direccion=?, foto_perfil=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $email, $telefono, $direccion, $foto_ruta, $usuario_id]);
            $_SESSION['usuario_foto'] = $foto_ruta;
        } else {
            $sql = "UPDATE usuarios SET nombre=?, email=?, telefono=?, direccion=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $email, $telefono, $direccion, $usuario_id]);
        }
        $_SESSION['usuario_nombre'] = $nombre;
        $mensaje = "Perfil actualizado con éxito.";
        $tipo_mensaje = 'success';
    } catch (Exception $e) {
        $mensaje = "Error al guardar: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$user = $stmt->fetch();

$primer_nombre = explode(' ', trim($user['nombre']))[0];
$inicial = strtoupper(substr($primer_nombre, 0, 1));
$foto_perfil = $user['foto_perfil'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .glass-panel {
            background: rgba(10, 17, 34, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>

<body class="bg-[#040812] text-slate-200 font-sans h-screen flex overflow-hidden">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-[#0a1122] via-[#040812] to-[#020409] p-10">
        <header class="mb-10">
            <h1 class="text-3xl font-bold text-white">Configuración de Perfil</h1>
            <p class="text-slate-400">Personaliza tu cuenta y mantén tus datos al día.</p>
        </header>

        <?php if ($mensaje): ?>
            <div class="mb-8 p-4 rounded-xl flex items-center gap-3 border shadow-lg <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-200' : 'bg-red-900/40 border-red-500/50 text-red-200' ?>">
                <i class="ph <?= $tipo_mensaje === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-2xl"></i>
                <span><?= $mensaje ?></span>
            </div>
        <?php endif; ?>

        <div class="max-w-4xl glass-panel rounded-[2.5rem] p-8 border-t border-blue-500/30">
            <form action="perfil.php" method="POST" enctype="multipart/form-data" class="space-y-8">

                <div class="flex items-center gap-6">
                    <div class="relative w-32 h-32 rounded-full overflow-hidden border-4 border-[#040812] shadow-xl bg-blue-900/30 flex items-center justify-center">
                        <?php if ($foto_perfil): ?>
                            <img src="<?= $foto_perfil ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-4xl font-bold text-blue-400"><?= $inicial ?></span>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/50 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer" onclick="document.getElementById('upload_foto').click()">
                            <i class="ph ph-camera text-3xl text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white mb-1"><?= htmlspecialchars($user['nombre']) ?></h3>
                        <p class="text-sm text-slate-400 mb-3">Sube una foto profesional para tu cuenta.</p>
                        <input type="file" name="foto" id="upload_foto" accept="image/*" class="text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-blue-500/10 file:text-blue-400 hover:file:bg-blue-500/20 cursor-pointer">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Nombre Completo</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($user['nombre']) ?>" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Correo Electrónico</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Teléfono</label>
                        <input type="text" name="telefono" value="<?= htmlspecialchars($user['telefono'] ?? '') ?>" placeholder="Ej. 809-555-5555" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Dirección</label>
                        <input type="text" name="direccion" value="<?= htmlspecialchars($user['direccion'] ?? '') ?>" placeholder="Santo Domingo, D.N." class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-800">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-blue-900/30 transition-all">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </main>
</body>

</html>