<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit; }
require_once 'config/database.php';

$mensaje = ''; $tipo_mensaje = '';
$uid = $_SESSION['usuario_id']; // EL IDENTIFICADOR MAESTRO DEL USUARIO

$stmtCol = $pdo->query("SHOW COLUMNS FROM cuentas LIKE 'icono'");
$tiene_iconos = $stmtCol->fetch() !== false;

// --- LÓGICA DE PROCESAMIENTO (BLINDADA POR USUARIO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];
    $nombre = trim($_POST['nombre'] ?? '');
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    
    try {
        if ($accion === 'eliminar') {
            // Solo elimina si el ID coincide Y le pertenece al usuario
            $pdo->prepare("DELETE FROM cuentas WHERE id = ? AND usuario_id = ?")->execute([$id, $uid]);
            $mensaje = 'Eliminado correctamente.'; $tipo_mensaje = 'success';
        } 
        elseif ($accion === 'guardar_cuenta') {
            $balance = (float)$_POST['balance']; $icono = $_POST['icono'] ?? 'ph-wallet'; $color = $_POST['color'] ?? 'blue'; $numero_cuenta = trim($_POST['numero_cuenta'] ?? '');
            if ($id) {
                if($tiene_iconos) { $pdo->prepare("UPDATE cuentas SET nombre=?, balance=?, numero_cuenta=?, icono=?, color=? WHERE id=? AND usuario_id=?")->execute([$nombre, $balance, $numero_cuenta, $icono, $color, $id, $uid]); } 
                else { $pdo->prepare("UPDATE cuentas SET nombre=?, balance=?, numero_cuenta=? WHERE id=? AND usuario_id=?")->execute([$nombre, $balance, $numero_cuenta, $id, $uid]); }
                $mensaje = 'Cuenta actualizada.';
            } else {
                if($tiene_iconos) { $pdo->prepare("INSERT INTO cuentas (usuario_id, clasificacion_id, nombre, balance, numero_cuenta, icono, color) VALUES (?, 1, ?, ?, ?, ?, ?)")->execute([$uid, $nombre, $balance, $numero_cuenta, $icono, $color]); } 
                else { $pdo->prepare("INSERT INTO cuentas (usuario_id, clasificacion_id, nombre, balance, numero_cuenta) VALUES (?, 1, ?, ?, ?)")->execute([$uid, $nombre, $balance, $numero_cuenta]); }
                $mensaje = 'Cuenta guardada exitosamente.';
            }
            $tipo_mensaje = 'success';
        }
        elseif ($accion === 'guardar_categoria') {
            $tipo_cat = (int)$_POST['tipo_categoria'];
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $icono = $_POST['icono'] ?? 'ph-tag';
            $color = $_POST['color'] ?? 'slate';

            if ($id) {
                if($tiene_iconos) { $pdo->prepare("UPDATE cuentas SET nombre=?, clasificacion_id=?, parent_id=?, icono=?, color=? WHERE id=? AND usuario_id=?")->execute([$nombre, $tipo_cat, $parent_id, $icono, $color, $id, $uid]); } 
                else { $pdo->prepare("UPDATE cuentas SET nombre=?, clasificacion_id=?, parent_id=? WHERE id=? AND usuario_id=?")->execute([$nombre, $tipo_cat, $parent_id, $id, $uid]); }
                $mensaje = 'Categoría actualizada. Se ha movido si cambiaste su carpeta.';
            } else {
                if($tiene_iconos) { $pdo->prepare("INSERT INTO cuentas (usuario_id, clasificacion_id, nombre, balance, parent_id, icono, color) VALUES (?, ?, ?, 0, ?, ?, ?)")->execute([$uid, $tipo_cat, $nombre, $parent_id, $icono, $color]); } 
                else { $pdo->prepare("INSERT INTO cuentas (usuario_id, clasificacion_id, nombre, balance, parent_id) VALUES (?, ?, ?, 0, ?)")->execute([$uid, $tipo_cat, $nombre, $parent_id]); }
                $mensaje = 'Categoría creada con éxito.';
            }
            $tipo_mensaje = 'success';
        }
    } catch (Exception $e) { $mensaje = 'Error: ' . $e->getMessage(); $tipo_mensaje = 'error'; }
}

function obtenerLogoBanco($nombre) {
    $n = mb_strtolower($nombre); $domain = '';
    if (strpos($n, 'bhd') !== false) $domain = 'bhd.com.do';
    elseif (strpos($n, 'banreservas') !== false || strpos($n, 'reservas') !== false) $domain = 'banreservas.com';
    elseif (strpos($n, 'popular') !== false) $domain = 'popularenlinea.com';
    elseif (strpos($n, 'scotia') !== false) $domain = 'scotiabank.com.do';
    elseif (strpos($n, 'santa cruz') !== false) $domain = 'bsc.com.do';
    elseif (strpos($n, 'qik') !== false) $domain = 'qik.com.do';
    elseif (strpos($n, 'apap') !== false) $domain = 'apap.com.do';
    elseif (strpos($n, 'cibao') !== false || strpos($n, 'acap') !== false) $domain = 'acap.com.do';
    elseif (strpos($n, 'nacional') !== false || strpos($n, 'aln') !== false) $domain = 'aln.com.do';
    elseif (strpos($n, 'bdi') !== false) $domain = 'bdi.com.do';
    elseif (strpos($n, 'caribe') !== false) $domain = 'bancocaribe.com.do';
    elseif (strpos($n, 'promerica') !== false) $domain = 'promerica.com.do';
    elseif (strpos($n, 'alaver') !== false) $domain = 'alaver.com.do';
    elseif (strpos($n, 'paypal') !== false) $domain = 'paypal.com';
    return $domain ? "https://www.google.com/s2/favicons?domain=" . $domain . "&sz=128" : false;
}

// --- EXTRACCIÓN DE DATOS AISLADA POR USUARIO ---
$stmtBancos = $pdo->prepare("SELECT * FROM cuentas WHERE clasificacion_id = 1 AND usuario_id = ? ORDER BY nombre ASC");
$stmtBancos->execute([$uid]);
$bancos = $stmtBancos->fetchAll();

$stmtCat = $pdo->prepare("SELECT * FROM cuentas WHERE clasificacion_id IN (2,3) AND usuario_id = ? ORDER BY nombre ASC");
$stmtCat->execute([$uid]);
$todas_cat = $stmtCat->fetchAll();

$cats_principales = array_filter($todas_cat, fn($c) => is_null($c['parent_id']));
$cats_agrupadas = [2 => [], 3 => []];
foreach($cats_principales as $cp) {
    $subs = array_filter($todas_cat, fn($c) => $c['parent_id'] == $cp['id']);
    $cats_agrupadas[$cp['clasificacion_id']][] = ['info' => $cp, 'subs' => $subs];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cuentas | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>.glass-panel { background: rgba(10, 17, 34, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); } .hide-scrollbar::-webkit-scrollbar { display: none; }</style>
</head>
<body class="bg-[#040812] text-slate-200 font-sans h-screen flex overflow-hidden selection:bg-[#CE1126]/30">
    
    <div class="hidden bg-slate-500/10 bg-slate-500/20 text-slate-400 border-slate-500 bg-red-500/10 bg-red-500/20 text-red-400 border-red-500 bg-orange-500/10 bg-orange-500/20 text-orange-400 border-orange-500 bg-emerald-500/10 bg-emerald-500/20 text-emerald-400 border-emerald-500 bg-blue-500/10 bg-blue-500/20 text-blue-400 border-blue-500 bg-indigo-500/10 bg-indigo-500/20 text-indigo-400 border-indigo-500 bg-fuchsia-500/10 bg-fuchsia-500/20 text-fuchsia-400 border-fuchsia-500 bg-yellow-500/10 bg-yellow-500/20 text-yellow-400 border-yellow-500"></div>

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-[#0a1122] via-[#040812] to-[#020409] hide-scrollbar relative">
        <header class="py-6 md:h-28 px-6 md:px-10 flex flex-row items-center justify-between z-10 shrink-0 mt-2 md:mt-0">
            <h1 class="text-2xl md:text-3xl font-semibold text-white">Centro de Control</h1>
        </header>

        <div class="px-6 md:px-10 pb-28 md:pb-12">
            <?php if($mensaje): ?>
                <div class="mb-8 p-4 rounded-xl flex items-center gap-3 border shadow-lg <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-200' : 'bg-red-900/40 border-red-500/50 text-red-200' ?>">
                    <i class="ph <?= $tipo_mensaje === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-2xl"></i><span><?= $mensaje ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <div class="lg:col-span-5 xl:col-span-4 space-y-6">
                    <div class="glass-panel p-6 rounded-3xl border-t border-blue-500/20">
                        <h3 id="titulo_cuenta" class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="ph ph-wallet text-blue-400"></i> Nueva Cuenta</h3>
                        <form action="cuentas.php" method="POST" id="form_cuenta">
                            <input type="hidden" name="accion" value="guardar_cuenta">
                            <input type="hidden" name="id" id="cuenta_id" value="">
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Entidad Bancaria / Cartera</label>
                                    <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2">
                                        <?php
                                        // LISTA EXPANDIDA DE BANCOS DOMINICANOS PARA BOTONES RÁPIDOS
                                        $bancos_comunes = [
                                            ['nombre' => 'Banreservas', 'logo' => 'banreservas.com'], 
                                            ['nombre' => 'Popular', 'logo' => 'popularenlinea.com'],
                                            ['nombre' => 'BHD', 'logo' => 'bhd.com.do'], 
                                            ['nombre' => 'APAP', 'logo' => 'apap.com.do'],
                                            ['nombre' => 'Santa Cruz', 'logo' => 'bsc.com.do'],
                                            ['nombre' => 'Qik', 'logo' => 'qik.com.do'],
                                            ['nombre' => 'Scotiabank', 'logo' => 'scotiabank.com.do'],
                                            ['nombre' => 'Asoc. Cibao', 'logo' => 'acap.com.do'],
                                            ['nombre' => 'Promerica', 'logo' => 'promerica.com.do'],
                                            ['nombre' => 'Efectivo', 'icono' => 'ph-money', 'color' => 'emerald'], 
                                            ['nombre' => 'PayPal', 'icono' => 'ph-paypal-logo', 'color' => 'blue']
                                        ];
                                        foreach($bancos_comunes as $bc): $defIco = $bc['icono'] ?? 'ph-bank'; $defCol = $bc['color'] ?? 'blue'; ?>
                                        <button type="button" onclick="seleccionarBanco('<?= $bc['nombre'] ?>', '<?= $defIco ?>', '<?= $defCol ?>', this)" class="btn-banco w-16 h-16 rounded-xl bg-[#040812] border border-slate-700 flex flex-col items-center justify-center shrink-0 transition-transform hover:border-blue-500 hover:bg-blue-500/10 gap-1.5 p-1">
                                            <?php if(isset($bc['logo'])): ?> <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center overflow-hidden border border-slate-800 shadow-sm"><img src="https://www.google.com/s2/favicons?domain=<?= $bc['logo'] ?>&sz=128" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"><i class="ph ph-bank text-slate-500 hidden"></i></div>
                                            <?php else: ?> <div class="w-8 h-8 rounded-full bg-<?= $defCol ?>-500/20 flex items-center justify-center text-<?= $defCol ?>-400 border border-<?= $defCol ?>-500/50"><i class="ph <?= $defIco ?> text-lg"></i></div> <?php endif; ?>
                                            <span class="text-[9px] font-semibold text-slate-300 truncate w-full text-center px-1" title="<?= $bc['nombre'] ?>"><?= $bc['nombre'] ?></span>
                                        </button>
                                        <?php endforeach; ?>
                                        <button type="button" id="btn_otro_banco" onclick="toggleOtroBanco(this)" class="btn-banco w-16 h-16 rounded-xl bg-[#040812] border border-slate-700 border-dashed flex flex-col items-center justify-center shrink-0 text-slate-500 hover:border-blue-500 hover:text-blue-400"><i class="ph ph-plus text-xl"></i><span class="text-[9px] font-semibold mt-1">Otro</span></button>
                                    </div>
                                    <div class="mt-3"><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Alias de la Cuenta</label><input type="text" name="nombre" id="cuenta_nombre" required class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 focus:outline-none"></div>
                                </div>
                                <div id="panel_personalizacion_cuenta" class="hidden bg-[#0a1122]/50 p-4 rounded-2xl border border-slate-700/50 space-y-4">
                                    <div>
                                        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1"><?php $iconos_cta = ['ph-wallet', 'ph-money', 'ph-piggy-bank', 'ph-safe', 'ph-briefcase', 'ph-paypal-logo', 'ph-currency-btc', 'ph-buildings']; foreach($iconos_cta as $ico): ?><button type="button" onclick="seleccionarIcoCta('<?= $ico ?>', this)" class="btn-ico-cta w-10 h-10 rounded-xl bg-[#040812] border border-slate-700 flex items-center justify-center text-lg text-slate-400 shrink-0 hover:bg-slate-800"><i class="ph <?= $ico ?>"></i></button><?php endforeach; ?></div>
                                        <input type="hidden" name="icono" id="cuenta_icono" value="ph-wallet">
                                    </div>
                                    <div>
                                        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1"><?php $colores = ['slate', 'red', 'orange', 'yellow', 'emerald', 'blue', 'indigo', 'fuchsia']; foreach($colores as $col): ?><button type="button" onclick="seleccionarColCta('<?= $col ?>', this)" class="btn-col-cta w-8 h-8 rounded-full bg-<?= $col ?>-500/20 border border-<?= $col ?>-500 shrink-0 hover:scale-110 transition-transform"></button><?php endforeach; ?></div>
                                        <input type="hidden" name="color" id="cuenta_color" value="blue">
                                    </div>
                                </div>
                                <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nº de Cuenta (Opcional)</label><input type="text" name="numero_cuenta" id="cuenta_numero" class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-3 text-slate-300 font-mono text-sm focus:border-blue-500 focus:outline-none"></div>
                                <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Balance Actual (RD$)</label><input type="number" name="balance" id="cuenta_balance" step="0.01" value="0.00" required class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 focus:outline-none text-lg font-bold"></div>
                                <div class="flex gap-2">
                                    <button type="button" onclick="cancelarEdicion('cuenta')" class="px-4 bg-slate-800 rounded-xl hidden text-white text-sm hover:bg-slate-700" id="btn_cancelar_cuenta">Cancelar</button>
                                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-blue-900/40">Guardar</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="glass-panel p-6 rounded-3xl border-t border-[#CE1126]/20">
                        <h3 id="titulo_cat" class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="ph ph-tag text-[#CE1126]"></i> Nueva Categoría</h3>
                        <form action="cuentas.php" method="POST" id="form_cat" class="space-y-4">
                            <input type="hidden" name="accion" value="guardar_categoria">
                            <input type="hidden" name="id" id="cat_id" value="">
                            
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Naturaleza</label>
                                <select name="tipo_categoria" id="cat_tipo" onchange="actualizarSelectPadres()" class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-[#CE1126] focus:outline-none appearance-none font-medium">
                                    <option value="2">Gasto</option><option value="3">Ingreso</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 flex items-center justify-between">
                                    Jerarquía
                                    <span class="text-[#CE1126] lowercase font-normal italic tracking-normal hidden" id="alerta_mover">(Moviendo subcategoría)</span>
                                </label>
                                <select name="parent_id" id="cat_parent" class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-[#CE1126] focus:outline-none appearance-none font-bold">
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nombre de Categoría</label>
                                <input type="text" name="nombre" id="cat_nombre" placeholder="Ej. Restaurantes" required class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-[#CE1126] focus:outline-none">
                            </div>
                            
                            <div id="seccion_diseno_cat" class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Selecciona un Ícono</label>
                                    <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto hide-scrollbar pb-2 pt-1 border border-slate-800 bg-[#040812]/50 p-2 rounded-xl">
                                        <?php 
                                        $iconos_cat = ['ph-wallet', 'ph-money', 'ph-bank', 'ph-piggy-bank', 'ph-credit-card', 'ph-calculator', 'ph-chart-line-up', 'ph-receipt', 'ph-shopping-cart', 'ph-hamburger', 'ph-pizza', 'ph-ice-cream', 'ph-coffee', 'ph-wine', 'ph-bowl-food', 'ph-car-profile', 'ph-gas-pump', 'ph-bus', 'ph-train', 'ph-bicycle', 'ph-airplane-tilt', 'ph-house', 'ph-armchair', 'ph-lamp', 'ph-couch', 'ph-lightbulb', 'ph-drop', 'ph-wifi-high', 'ph-plug', 'ph-barbell', 'ph-pill', 'ph-heartbeat', 'ph-fire', 'ph-fire-extinguisher', 'ph-siren', 'ph-scissors', 'ph-film-strip', 'ph-popcorn', 'ph-film-slate', 'ph-television', 'ph-headphones', 'ph-game-controller', 'ph-ticket', 'ph-t-shirt', 'ph-shopping-bag', 'ph-storefront', 'ph-laptop', 'ph-apple-logo', 'ph-code', 'ph-monitor', 'ph-device-mobile', 'ph-books', 'ph-graduation-cap', 'ph-briefcase', 'ph-pen-nib', 'ph-paw-print', 'ph-beach-tent', 'ph-camera', 'ph-tag', 'ph-star']; 
                                        foreach($iconos_cat as $ico): ?>
                                            <button type="button" onclick="seleccionarIcono('<?= $ico ?>', this)" class="btn-icono w-10 h-10 rounded-xl bg-[#0a1122] border border-slate-700 flex items-center justify-center text-xl text-slate-300 transition-transform hover:bg-slate-700"><i class="ph <?= $ico ?>"></i></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="icono" id="cat_icono" value="ph-tag">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Color</label>
                                    <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2">
                                        <?php foreach($colores as $col): ?>
                                            <button type="button" onclick="seleccionarColor('<?= $col ?>', this)" class="btn-color w-8 h-8 rounded-full bg-<?= $col ?>-500/20 border border-<?= $col ?>-500 shrink-0 transition-transform hover:scale-110"></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="color" id="cat_color" value="slate">
                                </div>
                            </div>

                            <div class="flex gap-2 pt-2">
                                <button type="button" onclick="cancelarEdicion('cat')" class="px-4 bg-slate-800 rounded-xl hidden transition-colors hover:bg-slate-700 text-white text-sm" id="btn_cancelar_cat">Cancelar</button>
                                <button type="submit" class="flex-1 bg-[#CE1126] hover:bg-red-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-red-900/50">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-7 xl:col-span-8 space-y-8">
                    
                    <div>
                        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-4">Mis Cuentas Bancarias</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach($bancos as $b): 
                                $logo = obtenerLogoBanco($b['nombre']); $bIco = $b['icono'] ?? 'ph-wallet'; $bCol = $b['color'] ?? 'blue';
                            ?>
                            <div class="group bg-[#0a1122]/60 border border-blue-900/30 p-4 rounded-2xl flex items-center justify-between hover:border-blue-500/50 transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full bg-[#040812] flex items-center justify-center border border-<?= $bCol ?>-500/30 shrink-0 p-1 overflow-hidden shadow-inner">
                                        <?php if($logo): ?> <img src="<?= $logo ?>" class="w-full h-full object-contain rounded-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="hidden w-full h-full bg-<?= $bCol ?>-500/20 flex items-center justify-center text-<?= $bCol ?>-400 rounded-full"><i class="ph <?= $bIco ?> text-xl"></i></div>
                                        <?php else: ?> <div class="w-full h-full bg-<?= $bCol ?>-500/20 flex items-center justify-center text-<?= $bCol ?>-400 rounded-full"><i class="ph <?= $bIco ?> text-xl"></i></div> <?php endif; ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h4 class="text-white font-bold text-sm truncate flex flex-col items-start gap-0.5">
                                            <?= htmlspecialchars($b['nombre']) ?> 
                                            <?php if(!empty($b['numero_cuenta'])): ?><span class="bg-blue-900/40 text-blue-300 text-[9px] px-2 py-0.5 rounded font-mono border border-blue-800/50 block"><?= htmlspecialchars($b['numero_cuenta']) ?></span><?php endif; ?>
                                        </h4>
                                        <p class="text-base text-slate-300 font-mono font-bold">RD$ <?= number_format($b['balance'], 2) ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-col md:flex-row gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button onclick="editarCuenta(<?= $b['id'] ?>, '<?= addslashes($b['nombre']) ?>', <?= $b['balance'] ?>, '<?= addslashes($b['numero_cuenta'] ?? '') ?>', '<?= $bIco ?>', '<?= $bCol ?>')" class="p-2 hover:bg-slate-800 text-blue-400 rounded-lg bg-[#040812] md:bg-transparent"><i class="ph ph-pencil-simple text-xl"></i></button>
                                    <button onclick="eliminarItem(<?= $b['id'] ?>)" class="p-2 hover:bg-slate-800 text-red-400 rounded-lg bg-[#040812] md:bg-transparent"><i class="ph ph-trash text-xl"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-8">
                        <?php foreach([2=>['Gastos','red'], 3=>['Ingresos','emerald']] as $tipo => $config): ?>
                        <div class="glass-panel rounded-3xl overflow-hidden border-t border-slate-700">
                            <div class="bg-[#040812]/50 px-5 py-4 border-b border-slate-700/50 flex items-center gap-2"><i class="ph ph-folder text-<?= $config[1] ?>-400 text-lg"></i> <h3 class="font-bold text-white"><?= $config[0] ?></h3></div>
                            <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto hide-scrollbar">
                                
                                <?php foreach($cats_agrupadas[$tipo] as $item): 
                                    $ico_padre = $item['info']['icono'] ?? 'ph-folder';
                                    $col_padre = $item['info']['color'] ?? 'slate';
                                ?>
                                    <div class="bg-slate-800/30 rounded-2xl border border-slate-700/50 p-1 group transition-all hover:border-slate-600">
                                        
                                        <div class="flex items-center justify-between p-3 cursor-pointer" onclick="document.getElementById('sub-<?= $item['info']['id'] ?>').classList.toggle('hidden')">
                                            <div class="flex items-center gap-4 font-semibold text-slate-200 overflow-hidden pr-2">
                                                <div class="w-12 h-12 shrink-0 rounded-xl bg-<?= $col_padre ?>-500/20 border border-<?= $col_padre ?>-500 flex items-center justify-center text-<?= $col_padre ?>-400">
                                                    <i class="ph <?= $ico_padre ?> text-2xl"></i>
                                                </div>
                                                <span class="truncate"><?= htmlspecialchars($item['info']['nombre']) ?></span>
                                            </div>
                                            <div class="flex items-center gap-1 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button onclick="event.stopPropagation(); editarCat(<?= $item['info']['id'] ?>, '<?= addslashes($item['info']['nombre']) ?>', <?= $tipo ?>, '', '<?= $ico_padre ?>', '<?= $col_padre ?>')" class="p-2 text-slate-500 hover:text-white bg-[#040812] md:bg-transparent rounded-lg"><i class="ph ph-pencil-simple text-lg"></i></button>
                                                <button onclick="event.stopPropagation(); eliminarItem(<?= $item['info']['id'] ?>)" class="p-2 text-slate-500 hover:text-red-400 bg-[#040812] md:bg-transparent rounded-lg"><i class="ph ph-trash text-lg"></i></button>
                                                <i class="ph ph-caret-down text-slate-500 ml-1"></i>
                                            </div>
                                        </div>

                                        <div id="sub-<?= $item['info']['id'] ?>" class="hidden mt-1 mb-2 ml-6 pl-4 border-l-2 border-slate-700/50 space-y-2">
                                            <?php foreach($item['subs'] as $s): 
                                                $ico_sub = $ico_padre; $col_sub = $col_padre;
                                            ?>
                                                <div class="flex items-center justify-between group/sub py-2 border-b border-slate-800/50 last:border-0">
                                                    <span class="text-sm text-slate-400 hover:text-slate-200 flex items-center gap-3 transition-colors truncate">
                                                        <div class="w-8 h-8 shrink-0 rounded-lg bg-<?= $col_sub ?>-500/10 border border-<?= $col_sub ?>-500/50 flex items-center justify-center text-<?= $col_sub ?>-400"><i class="ph <?= $ico_sub ?> text-base"></i></div>
                                                        <span class="truncate"><?= htmlspecialchars($s['nombre']) ?></span>
                                                    </span>
                                                    <div class="flex gap-1 pr-2 opacity-100 md:opacity-0 group-hover/sub:opacity-100 transition-opacity">
                                                        <button onclick="editarCat(<?= $s['id'] ?>, '<?= addslashes($s['nombre']) ?>', <?= $tipo ?>, <?= $item['info']['id'] ?>, '<?= $ico_sub ?>', '<?= $col_sub ?>')" class="p-1.5 text-slate-500 hover:text-white bg-[#040812] md:bg-transparent rounded"><i class="ph ph-pencil text-lg"></i></button>
                                                        <button onclick="eliminarItem(<?= $s['id'] ?>)" class="p-1.5 text-slate-500 hover:text-red-400 bg-[#040812] md:bg-transparent rounded"><i class="ph ph-trash text-lg"></i></button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>

                                            <div class="mt-2 pt-2 border-t border-slate-700/50">
                                                <button onclick="prepararNuevaSubcat(<?= $item['info']['id'] ?>, <?= $tipo ?>)" class="text-[10px] text-blue-400 font-bold uppercase tracking-widest flex items-center gap-1 hover:text-blue-300 transition-colors p-2 w-full justify-center md:justify-start">
                                                    <i class="ph ph-plus-circle text-sm"></i> Agregar Subcategoría aquí
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <form id="form_eliminar" action="cuentas.php" method="POST"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="eliminar_id"></form>

    <script>
        const padresGasto = [
            <?php foreach($cats_principales as $cp) { if($cp['clasificacion_id']==2) echo "{id: {$cp['id']}, nombre: '".addslashes($cp['nombre'])."'},\n"; } ?>
        ];
        const padresIngreso = [
            <?php foreach($cats_principales as $cp) { if($cp['clasificacion_id']==3) echo "{id: {$cp['id']}, nombre: '".addslashes($cp['nombre'])."'},\n"; } ?>
        ];

        const selectTipoCat = document.getElementById('cat_tipo');
        const selectPadreCat = document.getElementById('cat_parent');
        const seccionDisenoCat = document.getElementById('seccion_diseno_cat');
        const alertaMover = document.getElementById('alerta_mover');

        function actualizarSelectPadres(idExcluir = null) {
            const tipo = selectTipoCat.value;
            const oldValue = selectPadreCat.value;
            
            selectPadreCat.innerHTML = '<option value="">-- Es una Categoría Principal --</option>';
            const lista = (tipo == 2) ? padresGasto : padresIngreso;

            lista.forEach(p => {
                if (p.id != idExcluir) { selectPadreCat.innerHTML += `<option value="${p.id}">↳ Pertenece a: ${p.nombre}</option>`; }
            });

            const options = Array.from(selectPadreCat.options);
            if (options.some(o => o.value === oldValue)) { selectPadreCat.value = oldValue; } else { selectPadreCat.value = ""; }
            selectPadreCat.dispatchEvent(new Event('change'));
        }

        selectPadreCat.addEventListener('change', function() {
            if (this.value !== '') { seccionDisenoCat.classList.add('hidden'); } else { seccionDisenoCat.classList.remove('hidden'); }
        });

        actualizarSelectPadres();

        function prepararNuevaSubcat(idPadre, tipoCat) {
            document.getElementById('form_cat').reset(); document.getElementById('cat_id').value = ''; document.getElementById('cat_tipo').value = tipoCat;
            actualizarSelectPadres(); selectPadreCat.value = idPadre; selectPadreCat.dispatchEvent(new Event('change')); 
            document.getElementById('titulo_cat').innerHTML = '<i class="ph ph-tag text-[#CE1126]"></i> Nueva Subcategoría'; document.getElementById('btn_cancelar_cat').classList.remove('hidden'); alertaMover.classList.add('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' }); document.getElementById('cat_nombre').focus();
        }

        function editarCat(id, nombre, tipo, parent, icono = 'ph-tag', color = 'slate') {
            document.getElementById('cat_id').value = id; document.getElementById('cat_nombre').value = nombre; document.getElementById('cat_tipo').value = tipo;
            actualizarSelectPadres(id); selectPadreCat.value = parent || ''; selectPadreCat.dispatchEvent(new Event('change'));
            
            const btnIcono = document.querySelector(`.btn-icono i.ph.${icono}`); if(btnIcono) seleccionarIcono(icono, btnIcono.parentElement); else seleccionarIcono('ph-tag', document.querySelector('.btn-icono'));
            const btnColor = document.querySelector(`.btn-color.bg-${color}-500\\/20`); if(btnColor) seleccionarColor(color, btnColor); else seleccionarColor('slate', document.querySelector('.btn-color'));

            document.getElementById('titulo_cat').innerHTML = '<i class="ph ph-pencil text-[#CE1126]"></i> Editar Categoría'; document.getElementById('btn_cancelar_cat').classList.remove('hidden');
            if(parent) alertaMover.classList.remove('hidden'); else alertaMover.classList.add('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        const cuentaNombreInput = document.getElementById('cuenta_nombre'); const inputOtroBanco = document.getElementById('input_otro_banco'); const panelPersCta = document.getElementById('panel_personalizacion_cuenta');

        function seleccionarIcoCta(ico, el) { document.getElementById('cuenta_icono').value = ico; document.querySelectorAll('.btn-ico-cta').forEach(b => b.classList.remove('border-white', 'text-white')); if(el) el.classList.add('border-white', 'text-white'); }
        function seleccionarColCta(col, el) { document.getElementById('cuenta_color').value = col; document.querySelectorAll('.btn-col-cta').forEach(b => b.classList.remove('border-white', 'scale-125', 'border-2')); if(el) el.classList.add('border-white', 'scale-125', 'border-2'); }

        function seleccionarBanco(nombre, ico, col, el) {
            document.querySelectorAll('.btn-banco').forEach(b => b.classList.remove('border-blue-500', 'bg-blue-500/10'));
            if(el) el.classList.add('border-blue-500', 'bg-blue-500/10');
            panelPersCta.classList.add('hidden'); const currentVal = cuentaNombreInput.value;
            if (currentVal === '' || currentVal === 'Otro') { cuentaNombreInput.value = nombre; } else { cuentaNombreInput.value = nombre; }
            document.getElementById('cuenta_icono').value = ico; document.getElementById('cuenta_color').value = col;
        }

        function toggleOtroBanco(el) { document.querySelectorAll('.btn-banco').forEach(b => b.classList.remove('border-blue-500', 'bg-blue-500/10')); if(el) el.classList.add('border-blue-500'); panelPersCta.classList.remove('hidden'); cuentaNombreInput.value = ''; cuentaNombreInput.focus(); }
        inputOtroBanco.addEventListener('input', function() { cuentaNombreInput.value = this.value; });

        function editarCuenta(id, nombre, balance, numero_cuenta, icono = 'ph-wallet', color = 'blue') {
            document.getElementById('cuenta_id').value = id; document.getElementById('cuenta_balance').value = balance; document.getElementById('cuenta_numero').value = numero_cuenta; 
            let btnFound = false;
            document.querySelectorAll('.btn-banco').forEach(b => { const bankName = b.querySelector('span').textContent.trim(); if(nombre.includes(bankName) && bankName !== 'Otro') { b.click(); document.getElementById('cuenta_nombre').value = nombre; btnFound = true; } });
            if(!btnFound) { const btnOtro = document.getElementById('btn_otro_banco'); if(btnOtro) toggleOtroBanco(btnOtro); document.getElementById('cuenta_nombre').value = nombre; const bIco = document.querySelector(`.btn-ico-cta i.ph.${icono}`); if(bIco) seleccionarIcoCta(icono, bIco.parentElement); const bCol = document.querySelector(`.btn-col-cta.bg-${color}-500\\/20`); if(bCol) seleccionarColCta(color, bCol); }
            document.getElementById('titulo_cuenta').innerHTML = '<i class="ph ph-pencil text-blue-400"></i> Editar Cuenta'; document.getElementById('btn_cancelar_cuenta').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function seleccionarIcono(ico, el) { document.getElementById('cat_icono').value = ico; document.querySelectorAll('.btn-icono').forEach(b => b.classList.remove('border-white', 'scale-110', 'bg-slate-700')); if(el) el.classList.add('border-white', 'scale-110', 'bg-slate-700'); }
        function seleccionarColor(col, el) { document.getElementById('cat_color').value = col; document.querySelectorAll('.btn-color').forEach(b => b.classList.remove('border-white', 'scale-125', 'border-2')); if(el) el.classList.add('border-white', 'scale-125', 'border-2'); }

        function cancelarEdicion(modo) {
            if(modo === 'cuenta') {
                document.getElementById('form_cuenta').reset(); document.getElementById('cuenta_id').value = ''; document.getElementById('cuenta_numero').value = '';
                document.querySelectorAll('.btn-banco').forEach(b => b.classList.remove('border-blue-500', 'bg-blue-500/10')); panelPersCta.classList.add('hidden');
                document.getElementById('titulo_cuenta').innerHTML = '<i class="ph ph-wallet text-blue-400"></i> Nueva Cuenta'; document.getElementById('btn_cancelar_cuenta').classList.add('hidden');
            } else {
                document.getElementById('form_cat').reset(); document.getElementById('cat_id').value = ''; alertaMover.classList.add('hidden');
                actualizarSelectPadres(); seleccionarIcono('ph-tag', document.querySelector('.btn-icono')); seleccionarColor('slate', document.querySelector('.btn-color'));
                document.getElementById('titulo_cat').innerHTML = '<i class="ph ph-tag text-[#CE1126]"></i> Nueva Categoría'; document.getElementById('btn_cancelar_cat').classList.add('hidden');
            }
        }

        function eliminarItem(id) { if(confirm('¿Eliminar esto definitivamente?')) { document.getElementById('eliminar_id').value = id; document.getElementById('form_eliminar').submit(); } }

        document.addEventListener('DOMContentLoaded', () => { seleccionarIcono('ph-tag', document.querySelector('.btn-icono')); seleccionarColor('slate', document.querySelector('.btn-color')); seleccionarIcoCta('ph-wallet', document.querySelector('.btn-ico-cta')); seleccionarColCta('blue', document.querySelector('.btn-col-cta')); });
    </script>
</body>
</html>