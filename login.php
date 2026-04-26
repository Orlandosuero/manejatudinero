<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceder | Maneja Tu Dinero</title>
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
<body class="bg-[#020409] text-slate-200 font-sans min-h-screen flex items-center justify-center p-6 relative overflow-hidden">

    <div class="absolute top-[-10%] right-[-5%] w-[600px] h-[600px] bg-[#002D62]/20 rounded-full blur-[120px] -z-10 pointer-events-none"></div>
    <div class="absolute bottom-[-10%] left-[-5%] w-[500px] h-[500px] bg-[#CE1126]/15 rounded-full blur-[120px] -z-10 pointer-events-none"></div>

    <a href="landing.php" class="absolute top-6 left-6 z-50 flex items-center gap-2 text-slate-400 hover:text-white transition-colors bg-[#0a1122]/80 backdrop-blur-md px-4 py-2 rounded-full border border-slate-800 shadow-lg">
        <i class="ph ph-arrow-left text-lg"></i> <span class="text-sm font-medium">Volver</span>
    </a>

    <div class="w-full max-w-md relative z-10 mt-12 md:mt-0">
        
        <div class="glass-panel rounded-3xl p-8 shadow-2xl shadow-black/80">
            
            <div class="text-center mb-8">
                <div class="flex justify-center mb-6">
                    <svg viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 drop-shadow-md">
                        <rect width="60" height="60" rx="14" fill="#002D62" />
                        <path d="M16 42V18L30 30L44 18V42" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="square" stroke-linejoin="miter"/>
                        <path d="M44 18H44.5C51.5 18 54 23 54 30C54 37 51.5 42 44.5 42H44" stroke="#CE1126" stroke-width="4.5" stroke-linecap="square" stroke-linejoin="miter"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">Bienvenido de vuelta</h2>
                <p class="text-sm text-slate-400">Ingresa a tu cuenta para manejar tus números.</p>
            </div>

            <?php if(isset($_GET['error']) && $_GET['error'] == 'credenciales'): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-400 text-sm px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <i class="ph ph-warning-circle text-lg"></i>
                    Correo o contraseña incorrectos.
                </div>
            <?php endif; ?>

            <div class="space-y-3 mb-8">
                <button class="w-full bg-[#121829]/50 hover:bg-[#121829] border border-slate-700/50 text-white font-medium py-3 px-4 rounded-xl flex items-center justify-center gap-3 transition-colors">
                    <i class="ph-fill ph-google-logo text-xl text-white"></i>
                    Continuar con Google
                </button>
                <button class="w-full bg-[#121829]/50 hover:bg-[#121829] border border-slate-700/50 text-white font-medium py-3 px-4 rounded-xl flex items-center justify-center gap-3 transition-colors">
                    <i class="ph-fill ph-apple-logo text-xl text-white"></i>
                    Continuar con Apple
                </button>
            </div>

            <div class="flex items-center mb-8">
                <div class="flex-1 border-t border-slate-800"></div>
                <span class="px-3 text-xs font-medium text-slate-500 uppercase tracking-wider">o con correo</span>
                <div class="flex-1 border-t border-slate-800"></div>
            </div>

            <form action="login_process.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wide">Correo Electrónico</label>
                    <input type="email" name="email" placeholder="orlando@ejemplo.com" required class="w-full bg-[#040812]/50 border border-slate-700/50 text-white rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 focus:bg-[#040812] transition-colors placeholder-slate-600">
                </div>
                
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wide">Contraseña</label>
                       <a href="recuperar.php" class="text-xs font-bold text-blue-400 hover:text-blue-300 transition-colors">¿Olvidaste tu contraseña?</a>
                    </div>
                    <input type="password" name="password" placeholder="••••••••" required class="w-full bg-[#040812]/50 border border-slate-700/50 text-white rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 focus:bg-[#040812] transition-colors placeholder-slate-600">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-gradient-to-r from-[#002D62] to-blue-700 hover:to-blue-600 text-white font-bold py-3.5 rounded-xl transition-all shadow-[0_0_15px_rgba(0,45,98,0.4)]">
                        Entrar a mi cuenta
                    </button>
                </div>
            </form>

        </div>
        
        <p class="text-center text-sm text-slate-500 mt-6">
            ¿No tienes una cuenta? <a href="registro_cuenta.php" class="text-[#CE1126] font-semibold hover:text-red-400 transition-colors">Regístrate aquí</a>
        </p>
    </div>

</body>
</html>