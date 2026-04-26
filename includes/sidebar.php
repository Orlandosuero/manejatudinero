<?php
// Obtener el nombre del archivo actual para marcar el menú activo
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Extraer foto de perfil del usuario activo para el sidebar
$stmtUserSidebar = $pdo->prepare("SELECT nombre, foto_perfil FROM usuarios WHERE id = ?");
$stmtUserSidebar->execute([$_SESSION['usuario_id']]);
$userSidebar = $stmtUserSidebar->fetch();

$nombre_sb = $userSidebar['nombre'] ?? 'Usuario';
$primer_nombre_sb = explode(' ', trim($nombre_sb))[0];
$inicial_sb = strtoupper(substr($primer_nombre_sb, 0, 1));
$foto_sb = !empty($userSidebar['foto_perfil']) ? $userSidebar['foto_perfil'] : null;

// Función para definir si el botón está activo o inactivo en Desktop
function claseActiva($pagina, $actual) {
    if ($pagina === $actual) {
        return "bg-gradient-to-r from-blue-600/20 to-transparent border-l-4 border-blue-500 text-white shadow-lg shadow-blue-900/10";
    }
    return "text-slate-400 hover:bg-[#040812]/50 hover:text-white hover:translate-x-1.5 transition-all duration-300 border-l-4 border-transparent";
}
?>

<style>
    /* AJUSTE MÁGICO GLOBAL: Evita que en móviles el contenido quede detrás del Bottom Nav */
    @media (max-width: 768px) {
        main { padding-bottom: 6rem !important; }
    }
</style>

<div class="hidden bg-slate-500/10 bg-slate-500/20 text-slate-400 border-slate-500 bg-red-500/10 bg-red-500/20 text-red-400 border-red-500 bg-orange-500/10 bg-orange-500/20 text-orange-400 border-orange-500 bg-emerald-500/10 bg-emerald-500/20 text-emerald-400 border-emerald-500 bg-blue-500/10 bg-blue-500/20 text-blue-400 border-blue-500 bg-indigo-500/10 bg-indigo-500/20 text-indigo-400 border-indigo-500 bg-fuchsia-500/10 bg-fuchsia-500/20 text-fuchsia-400 border-fuchsia-500 bg-yellow-500/10 bg-yellow-500/20 text-yellow-400 border-yellow-500"></div>

<aside class="w-72 bg-[#0a1122]/90 backdrop-blur-2xl border-r border-blue-900/30 flex flex-col hidden md:flex z-10 shrink-0 shadow-[4px_0_24px_rgba(0,0,0,0.4)]">
    <div class="h-28 flex items-center px-8 border-b border-blue-900/30">
        <svg viewBox="0 0 60 60" fill="none" class="w-12 h-12 mr-4 drop-shadow-[0_0_12px_rgba(206,17,38,0.5)] transition-transform hover:scale-105 duration-300">
            <rect width="60" height="60" rx="14" fill="#002D62" />
            <path d="M16 42V18L30 30L44 18V42" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="square"/>
            <path d="M44 18H44.5C51.5 18 54 23 54 30C54 37 51.5 42 44.5 42H44" stroke="#CE1126" stroke-width="4.5" stroke-linecap="square"/>
        </svg>
        <div class="flex flex-col">
            <span class="text-xl font-extrabold text-white tracking-wide leading-tight">Maneja Tu</span>
            <span class="text-sm font-bold text-[#CE1126] tracking-[0.2em] uppercase mt-0.5">Dinero</span>
        </div>
    </div>
    
    <nav class="flex-1 py-6 space-y-2 overflow-y-auto hide-scrollbar flex flex-col">
        <a href="index.php" class="flex items-center gap-4 px-8 py-3.5 rounded-r-2xl mr-4 <?= claseActiva('index.php', $pagina_actual) ?>">
            <i class="ph ph-squares-four text-2xl"></i> <span class="font-bold text-base">Inicio</span>
        </a>
        <div class="px-6 py-4">
            <a href="registro.php" class="relative flex items-center justify-center gap-3 w-full py-4 rounded-2xl bg-gradient-to-r from-[#CE1126] to-[#ff1e38] text-white shadow-[0_0_20px_rgba(206,17,38,0.4)] hover:shadow-[0_0_35px_rgba(206,17,38,0.7)] hover:-translate-y-1 transition-all duration-300 group overflow-hidden border border-red-500/50 <?= $pagina_actual == 'registro.php' ? 'ring-4 ring-red-500/50 scale-[0.98]' : '' ?>">
                <span class="absolute w-0 h-0 transition-all duration-500 ease-out bg-white rounded-full group-hover:w-64 group-hover:h-64 opacity-10"></span>
                <i class="ph ph-plus-circle text-2xl group-hover:rotate-90 transition-transform duration-300"></i> 
                <span class="font-extrabold text-sm tracking-[0.15em] uppercase">Nuevo Registro</span>
            </a>
        </div>
        <div class="w-full px-8 pb-2"><div class="h-px w-full bg-blue-900/30"></div></div>
        <a href="estadisticas.php" class="flex items-center gap-4 px-8 py-3.5 rounded-r-2xl mr-4 <?= claseActiva('estadisticas.php', $pagina_actual) ?>">
            <i class="ph ph-chart-line-up text-2xl"></i> <span class="font-bold text-base">Estadísticas</span>
        </a>
        <a href="retos.php" class="flex items-center gap-4 px-8 py-3.5 rounded-r-2xl mr-4 <?= claseActiva('retos.php', $pagina_actual) ?>">
            <i class="ph ph-target text-2xl"></i> <span class="font-bold text-base">Retos</span>
        </a>
        <a href="cuentas.php" class="flex items-center gap-4 px-8 py-3.5 rounded-r-2xl mr-4 <?= claseActiva('cuentas.php', $pagina_actual) ?>">
            <i class="ph ph-bank text-2xl"></i> <span class="font-bold text-base">Cuentas</span>
        </a>
        <a href="deudas.php" class="flex items-center gap-4 px-8 py-3.5 rounded-r-2xl mr-4 <?= claseActiva('deudas.php', $pagina_actual) ?>">
            <i class="ph ph-credit-card text-2xl"></i> <span class="font-bold text-base">Deudas</span>
        </a>
    </nav>
    
    <div class="border-t border-blue-900/30 bg-[#040812]/40 flex flex-col">
        <div class="p-5 flex items-center justify-between">
            <a href="perfil.php" class="flex items-center gap-3 hover:bg-slate-800/80 p-2.5 rounded-2xl transition-all <?= $pagina_actual == 'perfil.php' ? 'bg-slate-800 border border-slate-600 shadow-md' : '' ?> flex-1 overflow-hidden group">
                <div class="relative w-12 h-12 shrink-0 rounded-full border-2 border-slate-700 group-hover:border-blue-500 transition-colors">
                    <?php if($foto_sb): ?> 
                        <img src="<?= htmlspecialchars($foto_sb) ?>" class="w-full h-full rounded-full object-cover absolute inset-0 z-10 bg-[#040812]" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="hidden w-full h-full rounded-full bg-[#CE1126] items-center justify-center text-white text-base font-bold absolute inset-0"><?= $inicial_sb ?></div>
                    <?php else: ?> 
                        <div class="w-full h-full rounded-full bg-[#CE1126] flex items-center justify-center text-white text-base font-bold absolute inset-0"><?= $inicial_sb ?></div> 
                    <?php endif; ?>
                </div>
                <div class="flex flex-col truncate">
                    <span class="text-base font-bold text-white truncate group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($primer_nombre_sb) ?></span>
                    <span class="text-[10px] <?= $pagina_actual == 'perfil.php' ? 'text-emerald-400 font-extrabold' : 'text-slate-500 font-bold' ?> uppercase tracking-widest mt-0.5">Ver Perfil</span>
                </div>
            </a>
            <a href="logout.php" class="p-3 text-slate-500 hover:text-white hover:bg-red-600 rounded-xl transition-all ml-2 shadow-sm hover:shadow-red-900/50" title="Cerrar Sesión">
                <i class="ph ph-sign-out text-2xl"></i>
            </a>
        </div>
        
        <div class="px-5 pb-5 text-center">
            <p class="text-[10px] text-slate-500 font-medium">
                &copy; <?= date('Y') ?> Orlando Suero.<br>
                <a href="https://instagram.com/orlandosuero" target="_blank" class="hover:text-[#CE1126] transition-colors inline-flex items-center gap-1 mt-1.5">
                    <i class="ph ph-instagram-logo text-sm"></i> @orlandosuero
                </a>
            </p>
        </div>
    </div>
</aside>


<nav class="md:hidden fixed bottom-0 left-0 w-full bg-[#0a1122]/95 backdrop-blur-xl border-t border-slate-800 z-[60] flex justify-between items-center px-6 pb-safe pt-1 h-[5rem] shadow-[0_-4px_20px_rgba(0,0,0,0.5)]">
    
    <a href="index.php" class="flex flex-col items-center justify-center w-12 transition-colors <?= $pagina_actual == 'index.php' ? 'text-blue-400 scale-110' : 'text-slate-500 hover:text-slate-300' ?>">
        <i class="ph <?= $pagina_actual == 'index.php' ? 'ph-squares-four-fill drop-shadow-[0_0_8px_rgba(59,130,246,0.5)]' : 'ph-squares-four' ?> text-[26px]"></i>
        <span class="text-[9px] font-bold mt-1">Inicio</span>
    </a>
    
    <a href="estadisticas.php" class="flex flex-col items-center justify-center w-12 transition-colors <?= $pagina_actual == 'estadisticas.php' ? 'text-blue-400 scale-110' : 'text-slate-500 hover:text-slate-300' ?>">
        <i class="ph <?= $pagina_actual == 'estadisticas.php' ? 'ph-chart-line-up-fill drop-shadow-[0_0_8px_rgba(59,130,246,0.5)]' : 'ph-chart-line-up' ?> text-[26px]"></i>
        <span class="text-[9px] font-bold mt-1">Stats</span>
    </a>

    <div class="relative -top-6">
        <a href="registro.php" class="flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-r from-[#CE1126] to-[#ff1e38] text-white shadow-[0_4px_20px_rgba(206,17,38,0.5)] border-[5px] border-[#040812] active:scale-95 transition-transform <?= $pagina_actual == 'registro.php' ? 'ring-2 ring-red-500/50' : '' ?>">
            <i class="ph ph-plus text-2xl font-bold"></i>
        </a>
    </div>

    <a href="cuentas.php" class="flex flex-col items-center justify-center w-12 transition-colors <?= $pagina_actual == 'cuentas.php' ? 'text-blue-400 scale-110' : 'text-slate-500 hover:text-slate-300' ?>">
        <i class="ph <?= $pagina_actual == 'cuentas.php' ? 'ph-bank-fill drop-shadow-[0_0_8px_rgba(59,130,246,0.5)]' : 'ph-bank' ?> text-[26px]"></i>
        <span class="text-[9px] font-bold mt-1">Cuentas</span>
    </a>
    
    <button type="button" onclick="toggleMobileMenu()" class="flex flex-col items-center justify-center w-12 text-slate-500 hover:text-slate-300 transition-colors" id="btn_mobile_menu">
        <i class="ph ph-list text-[26px]" id="icon_mobile_menu"></i>
        <span class="text-[9px] font-bold mt-1">Menú</span>
    </button>

</nav>

<div id="mobileMenuOverlay" class="md:hidden fixed inset-0 bg-[#040812]/80 backdrop-blur-sm z-[55] opacity-0 pointer-events-none transition-opacity duration-300" onclick="toggleMobileMenu()"></div>

<div id="mobileMenu" class="md:hidden fixed inset-x-0 bottom-[5rem] bg-[#0a1122] border-t border-slate-800 rounded-t-3xl z-[55] transform translate-y-[150%] transition-transform duration-300 ease-out shadow-[0_-10px_40px_rgba(0,0,0,0.8)] flex flex-col p-6 pb-6">
    <div class="w-12 h-1.5 bg-slate-700 rounded-full mx-auto mb-6"></div>
    
    <div class="grid grid-cols-4 gap-4 mb-6">
        <a href="retos.php" class="flex flex-col items-center gap-2 text-slate-300 active:scale-95 transition-transform">
            <div class="w-14 h-14 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-[28px] border border-blue-500/20"><i class="ph ph-target"></i></div>
            <span class="text-[10px] font-bold uppercase tracking-widest mt-1">Retos</span>
        </a>
        <a href="deudas.php" class="flex flex-col items-center gap-2 text-slate-300 active:scale-95 transition-transform">
            <div class="w-14 h-14 rounded-2xl bg-red-500/10 text-red-400 flex items-center justify-center text-[28px] border border-red-500/20"><i class="ph ph-credit-card"></i></div>
            <span class="text-[10px] font-bold uppercase tracking-widest mt-1">Deudas</span>
        </a>
        <a href="perfil.php" class="flex flex-col items-center gap-2 text-slate-300 active:scale-95 transition-transform">
            <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-[28px] border border-emerald-500/20 overflow-hidden">
                <?php if($foto_sb): ?> 
                    <img src="<?= htmlspecialchars($foto_sb) ?>" class="w-full h-full object-cover">
                <?php else: ?> 
                    <i class="ph ph-user"></i>
                <?php endif; ?>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest mt-1">Perfil</span>
        </a>
        <a href="logout.php" class="flex flex-col items-center gap-2 text-slate-300 active:scale-95 transition-transform">
            <div class="w-14 h-14 rounded-2xl bg-slate-800 text-slate-400 flex items-center justify-center text-[28px] border border-slate-700"><i class="ph ph-sign-out"></i></div>
            <span class="text-[10px] font-bold uppercase tracking-widest mt-1">Salir</span>
        </a>
    </div>

    <div class="text-center border-t border-slate-800 pt-5">
        <p class="text-[10px] text-slate-500 font-medium">
            &copy; <?= date('Y') ?> Orlando Suero.<br>
            <a href="https://instagram.com/orlandosuero" target="_blank" class="hover:text-[#CE1126] transition-colors inline-flex items-center gap-1 mt-1.5">
                <i class="ph ph-instagram-logo text-sm"></i> @orlandosuero
            </a>
        </p>
    </div>
</div>

<script>
    // Lógica para abrir y cerrar el panel de menú adicional en móviles
    let isMobileMenuOpen = false;
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('mobileMenuOverlay');
        const icon = document.getElementById('icon_mobile_menu');
        
        isMobileMenuOpen = !isMobileMenuOpen;
        
        if (isMobileMenuOpen) {
            menu.classList.remove('translate-y-[150%]');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            icon.classList.remove('ph-list');
            icon.classList.add('ph-x', 'text-white');
        } else {
            menu.classList.add('translate-y-[150%]');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            icon.classList.add('ph-list');
            icon.classList.remove('ph-x', 'text-white');
        }
    }
</script>