<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .glass-panel {
            background: rgba(10, 17, 34, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="bg-[#020409] text-slate-200 font-sans min-h-screen flex flex-col md:flex-row selection:bg-[#CE1126]/30 relative overflow-hidden">

    <a href="landing.php" class="absolute top-6 left-6 z-50 flex items-center gap-2 text-slate-400 hover:text-white transition-colors bg-[#0a1122]/80 backdrop-blur-md px-4 py-2 rounded-full border border-slate-800 shadow-lg">
        <i class="ph ph-arrow-left text-lg"></i> <span class="text-sm font-medium">Volver al inicio</span>
    </a>

    <div class="hidden md:flex md:w-1/2 border-r border-blue-900/20 p-12 flex-col justify-between relative">
        <div class="absolute top-0 left-0 w-full h-full bg-[#002D62]/10 mix-blend-overlay"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[600px] h-[600px] bg-[#002D62]/30 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="relative z-10 mt-16">
            <div class="flex items-center gap-3">
                <svg viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 drop-shadow-md">
                    <rect width="60" height="60" rx="14" fill="#002D62" />
                    <path d="M16 42V18L30 30L44 18V42" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="square" stroke-linejoin="miter"/>
                    <path d="M44 18H44.5C51.5 18 54 23 54 30C54 37 51.5 42 44.5 42H44" stroke="#CE1126" stroke-width="4.5" stroke-linecap="square" stroke-linejoin="miter"/>
                </svg>
                <div class="flex flex-col">
                    <span class="text-lg font-bold text-white tracking-wide leading-tight">Maneja Tu</span>
                    <span class="text-xs font-semibold text-[#CE1126] tracking-widest uppercase">Dinero</span>
                </div>
            </div>
        </div>

        <div class="relative z-10 max-w-md">
            <h2 class="text-4xl font-extrabold text-white leading-tight mb-6">El primer paso hacia tu libertad financiera.</h2>
            <div class="space-y-4 text-slate-400">
                <p class="flex items-center gap-3"><i class="ph-fill ph-check-circle text-[#CE1126] text-xl"></i> Tus datos son privados y seguros.</p>
                <p class="flex items-center gap-3"><i class="ph-fill ph-check-circle text-[#CE1126] text-xl"></i> Acceso desde cualquier dispositivo.</p>
                <p class="flex items-center gap-3"><i class="ph-fill ph-check-circle text-[#CE1126] text-xl"></i> Rigor contable en cada movimiento.</p>
            </div>
        </div>
    </div>

    <div class="w-full md:w-1/2 flex items-center justify-center p-6 relative">
        <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-[#CE1126]/15 rounded-full blur-[120px] pointer-events-none z-0"></div>

        <div class="w-full max-w-md glass-panel p-8 rounded-3xl shadow-2xl relative z-10 mt-16 md:mt-0">
            <div class="text-center md:text-left mb-8">
                <h2 class="text-3xl font-bold text-white mb-2">Crear una cuenta</h2>
                <p class="text-sm text-slate-400">Únete hoy y ponle orden a tus números.</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-400 text-sm px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <i class="ph ph-warning-circle text-lg"></i>
                    <?php 
                        if($_GET['error'] == 'existe') echo "Ese correo ya está registrado.";
                        if($_GET['error'] == 'clave_corta') echo "La contraseña debe tener al menos 6 caracteres.";
                        if($_GET['error'] == 'vacio') echo "Por favor, completa todos los campos.";
                    ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-4 mb-8">
                <button class="bg-[#121829]/50 hover:bg-[#121829] border border-slate-700/50 text-white font-medium py-3 px-4 rounded-xl flex items-center justify-center gap-2 transition-colors">
                    <i class="ph-fill ph-google-logo text-lg text-white"></i> Google
                </button>
                <button class="bg-[#121829]/50 hover:bg-[#121829] border border-slate-700/50 text-white font-medium py-3 px-4 rounded-xl flex items-center justify-center gap-2 transition-colors">
                    <i class="ph-fill ph-apple-logo text-lg text-white"></i> Apple
                </button>
            </div>

            <div class="flex items-center mb-8">
                <div class="flex-1 border-t border-slate-800"></div>
                <span class="px-3 text-xs font-medium text-slate-500 uppercase tracking-wider">o con tu correo</span>
                <div class="flex-1 border-t border-slate-800"></div>
            </div>

            <form action="registro_process.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wide">Nombre Completo</label>
                    <input type="text" name="nombre" placeholder="Ej. Orlando Suero" required class="w-full bg-[#040812]/50 border border-slate-700/50 text-white rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 focus:bg-[#040812] transition-colors placeholder-slate-600">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wide">Correo Electrónico</label>
                    <input type="email" name="email" placeholder="orlando@ejemplo.com" required class="w-full bg-[#040812]/50 border border-slate-700/50 text-white rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 focus:bg-[#040812] transition-colors placeholder-slate-600">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wide">Contraseña</label>
                    <input type="password" name="password" placeholder="Mínimo 6 caracteres" minlength="6" required class="w-full bg-[#040812]/50 border border-slate-700/50 text-white rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 focus:bg-[#040812] transition-colors placeholder-slate-600">
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-gradient-to-r from-[#CE1126] to-[#a30d1e] hover:to-red-500 text-white font-bold py-3.5 rounded-xl transition-all shadow-[0_0_15px_rgba(206,17,38,0.3)] hover:shadow-[0_0_25px_rgba(206,17,38,0.6)]">
                        Crear cuenta ahora
                    </button>
                </div>
            </form>

            <p class="text-center text-sm text-slate-500 mt-6">
                ¿Ya tienes una cuenta? <a href="login.php" class="text-blue-400 font-semibold hover:text-blue-300 transition-colors">Inicia sesión aquí</a>
            </p>
        </div>
    </div>

</body>
</html>