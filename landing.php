<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maneja Tu Dinero | Que la quincena no se te vuelva sal y agua</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .glow-button { box-shadow: 0 0 25px rgba(206, 17, 38, 0.5); }
        .glow-button:hover { box-shadow: 0 0 40px rgba(206, 17, 38, 0.8); }
        .glass-card {
            background: rgba(10, 17, 34, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .float-animation { animation: float 6s ease-in-out infinite; }
        .float-animation-delayed { animation: float 6s ease-in-out 3s infinite; }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body class="bg-[#020409] text-slate-200 font-sans min-h-screen flex flex-col selection:bg-[#CE1126]/30 overflow-x-hidden relative">

    <div class="absolute top-[-10%] left-[-10%] w-[600px] h-[600px] bg-[#002D62]/30 rounded-full blur-[120px] -z-10 pointer-events-none"></div>
    <div class="absolute top-[20%] right-[-5%] w-[500px] h-[500px] bg-[#CE1126]/15 rounded-full blur-[120px] -z-10 pointer-events-none"></div>

    <nav class="flex items-center justify-between px-8 py-6 max-w-7xl mx-auto w-full relative z-20">
        <div class="flex items-center gap-3">
            <svg viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 drop-shadow-md">
                <rect width="60" height="60" rx="14" fill="#002D62" />
                <path d="M16 42V18L30 30L44 18V42" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="square" stroke-linejoin="miter"/>
                <path d="M44 18H44.5C51.5 18 54 23 54 30C54 37 51.5 42 44.5 42H44" stroke="#CE1126" stroke-width="4.5" stroke-linecap="square" stroke-linejoin="miter"/>
            </svg>
            <div class="flex flex-col">
                <span class="text-base font-bold text-white tracking-wide leading-tight">Maneja Tu</span>
                <span class="text-xs font-semibold text-[#CE1126] tracking-widest uppercase">Dinero</span>
            </div>
        </div>
        <div class="flex items-center gap-6">
            <a href="login.php" class="text-sm font-medium text-slate-300 hover:text-white transition-colors hidden sm:block">Entrar</a>
            <a href="registro_cuenta.php" class="bg-white text-[#040812] px-6 py-2.5 rounded-full text-sm font-bold transition-all hover:scale-105 shadow-[0_0_20px_rgba(255,255,255,0.3)]">
                Empezar de cero
            </a>
        </div>
    </nav>

    <main class="flex flex-col lg:flex-row items-center justify-between px-8 max-w-7xl mx-auto relative z-10 pt-16 pb-24 gap-16">
        
        <div class="flex-1 text-left">
            <div class="inline-flex items-center gap-2 bg-[#0a1122]/80 border border-blue-900/40 rounded-full px-4 py-2 mb-6 backdrop-blur-sm">
                <span class="text-lg">🇩🇴</span>
                <span class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Adiós al "no sé en qué se me fue"</span>
            </div>

            <h1 class="text-5xl md:text-6xl lg:text-7xl font-extrabold text-white tracking-tight leading-[1.1] mb-6">
                Para que el 15 y el 30 <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#CE1126] to-rose-400">no sean un mito.</span>
            </h1>
            
            <p class="text-lg text-slate-400 mb-10 leading-relaxed max-w-xl font-medium">
                Sabe exactamente a dónde van tus cuartos. Una plataforma con rigor contable, pero tan fácil de usar que hasta el colmadero te pediría la cuenta.
            </p>

            <div class="flex flex-col sm:flex-row gap-4">
                <a href="registro_cuenta.php" class="inline-flex items-center justify-center gap-3 bg-gradient-to-r from-[#CE1126] to-[#a30d1e] text-white px-8 py-4 rounded-2xl text-lg font-bold transition-all hover:-translate-y-1 glow-button group">
                    Organizar mis cheles
                    <i class="ph ph-arrow-right text-2xl group-hover:translate-x-2 transition-transform"></i>
                </a>
                <a href="#demo" class="inline-flex items-center justify-center gap-3 bg-slate-800/50 hover:bg-slate-800 border border-slate-700 text-white px-8 py-4 rounded-2xl text-lg font-semibold transition-all">
                    Ver cómo funciona
                </a>
            </div>
            <p class="text-sm text-slate-500 mt-6 flex items-center gap-2">
                <i class="ph-fill ph-lock-key text-emerald-500"></i> No te pedimos tu tarjeta. Es 100% tuyo.
            </p>
        </div>

        <div class="flex-1 w-full max-w-lg lg:max-w-none relative perspective-1000">
            <div class="glass-card rounded-3xl p-6 shadow-2xl shadow-black/80 w-full max-w-md mx-auto float-animation relative z-10 border-t border-l border-white/10">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <p class="text-xs text-slate-400 font-semibold uppercase tracking-widest mb-1">Balance Real</p>
                        <h2 class="text-4xl font-bold text-white font-mono">RD$ 12,450<span class="text-slate-500">.00</span></h2>
                    </div>
                    <div class="w-12 h-12 bg-[#002D62] rounded-2xl flex items-center justify-center shadow-inner">
                        <i class="ph ph-wallet text-2xl text-white"></i>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center text-emerald-400"><i class="ph ph-trend-up text-xl"></i></div>
                            <div>
                                <p class="text-sm font-bold text-slate-200">Nómina Quincena</p>
                                <p class="text-xs text-slate-500">BHD Banco</p>
                            </div>
                        </div>
                        <p class="text-sm font-bold text-emerald-400">+ RD$ 25,000</p>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-orange-500/20 rounded-lg flex items-center justify-center text-orange-400"><i class="ph ph-hamburger text-xl"></i></div>
                            <div>
                                <p class="text-sm font-bold text-slate-200">Pica Pollo Expreso</p>
                                <p class="text-xs text-slate-500">Tarjeta Visa</p>
                            </div>
                        </div>
                        <p class="text-sm font-bold text-white">- RD$ 450</p>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-sky-500/20 rounded-lg flex items-center justify-center text-sky-400"><i class="ph ph-shopping-cart text-xl"></i></div>
                            <div>
                                <p class="text-sm font-bold text-slate-200">Bravo - Compra</p>
                                <p class="text-xs text-slate-500">Nacional</p>
                            </div>
                        </div>
                        <p class="text-sm font-bold text-white">- RD$ 3,200</p>
                    </div>
                </div>
            </div>

            <div class="glass-card absolute -right-4 -bottom-10 p-4 rounded-2xl shadow-2xl float-animation-delayed z-20 flex items-center gap-4">
                <div class="w-12 h-12 bg-[#CE1126]/20 rounded-full flex items-center justify-center text-[#CE1126] border border-[#CE1126]/30">
                    <i class="ph ph-warning-circle text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-semibold">Alerta de presupuesto</p>
                    <p class="text-sm font-bold text-white">Le bajaste duro al coro 🍻</p>
                </div>
            </div>
        </div>
    </main>

    <section id="demo" class="py-24 relative z-10 border-t border-slate-800/50 bg-[#040812]/50">
        <div class="max-w-7xl mx-auto px-8">
            <div class="text-center mb-20">
                <h2 class="text-3xl md:text-5xl font-bold text-white mb-6">Porque la mente olvida,<br> pero el sistema no.</h2>
                <p class="text-slate-400 text-lg max-w-2xl mx-auto">Deja de llevar las cuentas en la cabeza o en un cuaderno que se pierde. Profesionaliza tu bolsillo.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <div class="glass-card p-8 rounded-3xl hover:bg-[#0a1122] transition-colors group">
                    <div class="w-14 h-14 bg-blue-500/10 text-blue-400 rounded-2xl flex items-center justify-center mb-6 border border-blue-500/20 group-hover:scale-110 transition-transform">
                        <i class="ph ph-lightning text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Anota hasta el pasaje</h3>
                    <p class="text-slate-400 leading-relaxed text-sm">
                        La app es tan rápida que puedes registrar el gasto del motoconcho, la empanada o el peaje antes de que te subas al vehículo. Cero excusas.
                    </p>
                </div>

                <div class="glass-card p-8 rounded-3xl border-t border-[#CE1126]/30 bg-gradient-to-b from-[#CE1126]/5 to-transparent hover:bg-[#0a1122] transition-colors group relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-[#CE1126]/10 rounded-full blur-2xl"></div>
                    <div class="w-14 h-14 bg-red-500/10 text-[#CE1126] rounded-2xl flex items-center justify-center mb-6 border border-red-500/20 group-hover:scale-110 transition-transform relative z-10">
                        <i class="ph ph-scales text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 relative z-10">El cuadre perfecto</h3>
                    <p class="text-slate-400 leading-relaxed text-sm relative z-10">
                        Usamos principios de partida doble reales. Si sacaste plata del cajero para pagar efectivo, la plataforma te lo cuadra. Rigor contable de verdad.
                    </p>
                </div>

                <div class="glass-card p-8 rounded-3xl hover:bg-[#0a1122] transition-colors group">
                    <div class="w-14 h-14 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center mb-6 border border-emerald-500/20 group-hover:scale-110 transition-transform">
                        <i class="ph ph-target text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Pa'l resort y el pote</h3>
                    <p class="text-slate-400 leading-relaxed text-sm">
                        Crea "Retos de Ahorro". Separa mentalmente el dinero de los gastos fijos del dinero para gozar, así no te quedas asando batata el fin de mes.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <section class="py-24 px-8 relative overflow-hidden">
        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-full max-w-5xl h-[500px] bg-[#CE1126]/15 rounded-full blur-[120px] -z-10"></div>
        
        <div class="max-w-4xl mx-auto text-center glass-card p-12 md:p-16 rounded-[3rem] shadow-2xl relative overflow-hidden border border-[#CE1126]/20">
            <i class="ph-fill ph-sparkle absolute -top-10 -right-10 text-9xl text-white/5 transform rotate-12"></i>

            <h2 class="text-4xl md:text-5xl font-black text-white mb-6 relative z-10">Suelta la mascota y el lapicero.</h2>
            <p class="text-slate-400 mb-10 text-lg md:text-xl relative z-10 max-w-2xl mx-auto">
                No dejes que tu dinero mande en ti. Ponle reglas, dale seguimiento y mira cómo de repente, la plata te empieza a rendir.
            </p>
            <a href="registro_cuenta.php" class="inline-flex items-center justify-center gap-3 bg-white text-[#040812] px-10 py-5 rounded-2xl text-xl font-bold transition-all hover:scale-105 shadow-[0_0_30px_rgba(255,255,255,0.4)] relative z-10">
                Crear mi cuenta gratis <i class="ph-bold ph-rocket-launch text-2xl text-[#CE1126]"></i>
            </a>
            <p class="text-sm text-slate-500 mt-6 relative z-10 font-medium">Toma 30 segundos. Sin cuentos.</p>
        </div>
    </section>

</body>
</html>