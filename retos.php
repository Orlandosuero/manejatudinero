<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

$mensaje = '';
$tipo_mensaje = '';
$uid = $_SESSION['usuario_id']; // EL IDENTIFICADOR MAESTRO DEL USUARIO

// --- MAGIA: ASEGURAR QUE LA COLUMNA DE VINCULACIÓN EXISTA EN LA BD ---
try {
    $pdo->exec("ALTER TABLE retos_ahorro ADD COLUMN cuenta_id INT NULL");
} catch (PDOException $e) {
    // Si la columna ya existe, simplemente ignora el error y continúa
}

// --- LÓGICA: GUARDAR/ELIMINAR/ABONAR/EDITAR RETOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        $pdo->beginTransaction();

        if ($accion === 'nuevo_reto') {
            $nombre = trim($_POST['nombre']);
            $objetivo = (float)$_POST['objetivo'];
            
            // Verificar que la cuenta le pertenezca a este usuario antes de vincular
            $cuenta_id = null;
            if (!empty($_POST['cuenta_id'])) {
                $stmtCheckCuenta = $pdo->prepare("SELECT id FROM cuentas WHERE id = ? AND usuario_id = ?");
                $stmtCheckCuenta->execute([(int)$_POST['cuenta_id'], $uid]);
                if ($stmtCheckCuenta->fetchColumn()) {
                    $cuenta_id = (int)$_POST['cuenta_id'];
                }
            }
            
            $actual = ($cuenta_id) ? 0.00 : (!empty($_POST['actual']) ? (float)$_POST['actual'] : 0.00);
            $fecha = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : date('Y-m-d', strtotime('+1 year'));
            $color = $_POST['color'] ?? 'emerald';
            
            try {
                $sql = "INSERT INTO retos_ahorro (usuario_id, nombre, monto_objetivo, monto_actual, fecha_limite, color, cuenta_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$uid, $nombre, $objetivo, $actual, $fecha, $color, $cuenta_id]);
            } catch (PDOException $e) {
                // Fallback por si la tabla no actualizó el color
                $sql = "INSERT INTO retos_ahorro (usuario_id, nombre, monto_objetivo, monto_actual, fecha_limite) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$uid, $nombre, $objetivo, $actual, $fecha]);
            }
            
            $mensaje = "Reto creado. ¡Dale pa'llá!";
            $tipo_mensaje = "success";
        } 
        elseif ($accion === 'editar_reto') {
            $id = (int)$_POST['id'];
            $nombre = trim($_POST['nombre']);
            $objetivo = (float)$_POST['objetivo'];
            
            // Verificar que la cuenta le pertenezca a este usuario
            $cuenta_id = null;
            if (!empty($_POST['cuenta_id'])) {
                $stmtCheckCuenta = $pdo->prepare("SELECT id FROM cuentas WHERE id = ? AND usuario_id = ?");
                $stmtCheckCuenta->execute([(int)$_POST['cuenta_id'], $uid]);
                if ($stmtCheckCuenta->fetchColumn()) {
                    $cuenta_id = (int)$_POST['cuenta_id'];
                }
            }
            
            $actual = ($cuenta_id) ? 0.00 : (!empty($_POST['actual']) ? (float)$_POST['actual'] : 0.00);
            $fecha = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : date('Y-m-d', strtotime('+1 year'));
            $color = $_POST['color'] ?? 'emerald';
            
            try {
                $sql = "UPDATE retos_ahorro SET nombre=?, monto_objetivo=?, monto_actual=?, fecha_limite=?, color=?, cuenta_id=? WHERE id=? AND usuario_id=?";
                $pdo->prepare($sql)->execute([$nombre, $objetivo, $actual, $fecha, $color, $cuenta_id, $id, $uid]);
            } catch (PDOException $e) {
                $sql = "UPDATE retos_ahorro SET nombre=?, monto_objetivo=?, monto_actual=?, fecha_limite=? WHERE id=? AND usuario_id=?";
                $pdo->prepare($sql)->execute([$nombre, $objetivo, $actual, $fecha, $id, $uid]);
            }
            
            $mensaje = "Reto actualizado con éxito.";
            $tipo_mensaje = "success";
        }
        elseif ($accion === 'abonar') {
            $id = (int)$_POST['id'];
            $monto_abono = (float)$_POST['monto_abono'];
            $pdo->prepare("UPDATE retos_ahorro SET monto_actual = monto_actual + ? WHERE id = ? AND usuario_id = ?")->execute([$monto_abono, $id, $uid]);
            $mensaje = "¡Abono registrado! Estás un paso más cerca de tu meta.";
            $tipo_mensaje = "success";
        }
        elseif ($accion === 'eliminar') {
            $id = (int)$_POST['id'];
            $pdo->prepare("DELETE FROM retos_ahorro WHERE id = ? AND usuario_id = ?")->execute([$id, $uid]);
            $mensaje = "Reto eliminado. Tus cuentas bancarias siguen intactas.";
            $tipo_mensaje = "success";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// --- CONSULTAS (AISLADAS POR USUARIO) ---
$stmtBancos = $pdo->prepare("SELECT id, nombre, balance FROM cuentas WHERE clasificacion_id = 1 AND usuario_id = ? ORDER BY balance DESC");
$stmtBancos->execute([$uid]);
$bancos = $stmtBancos->fetchAll();

try {
    $sql_retos = "SELECT r.*, c.nombre as cuenta_nombre, c.balance as cuenta_balance 
                  FROM retos_ahorro r 
                  LEFT JOIN cuentas c ON r.cuenta_id = c.id 
                  WHERE r.usuario_id = ?
                  ORDER BY r.creado_en DESC";
    $stmtR = $pdo->prepare($sql_retos);
    $stmtR->execute([$uid]);
    $retos = $stmtR->fetchAll();
} catch (PDOException $e) {
    $retos = [];
}

// Para "Misión: Salir del Hoyo"
try {
    $stmtD = $pdo->prepare("SELECT *, (limite_o_principal - balance_actual) as pagado FROM deudas WHERE balance_actual > 0 AND usuario_id = ? ORDER BY balance_actual ASC");
    $stmtD->execute([$uid]);
    $deudas = $stmtD->fetchAll();
    
    $stmtDI = $pdo->prepare("SELECT * FROM deudas WHERE balance_actual > 0 AND usuario_id = ? ORDER BY tasa_interes DESC");
    $stmtDI->execute([$uid]);
    $deudas_interes = $stmtDI->fetchAll();
} catch (PDOException $e) {
    $deudas = [];
    $deudas_interes = [];
}

$frases = [
    "«No ahorres lo que te sobra después de gastar, gasta lo que te sobra después de ahorrar.»",
    "«El que paga lo que debe, sabe lo que tiene.»",
    "«Manejar el dinero es 20% conocimiento y 80% comportamiento.»",
    "«Si Don Carlos del colmado pudo comprar su camioneta, tú puedes con ese ahorro.»"
];
$frase_dia = $frases[array_rand($frases)];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Retos y Estrategia | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .glass-panel { background: rgba(10, 17, 34, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .progress-glow { filter: drop-shadow(0 0 8px currentColor); }
    </style>
</head>

<body class="bg-[#040812] text-slate-200 font-sans h-screen flex overflow-hidden selection:bg-emerald-500/30">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-[#081a14] via-[#040812] to-[#020409] hide-scrollbar relative">
        
        <header class="py-6 md:h-32 px-6 md:px-10 flex flex-col justify-center shrink-0 mt-4 md:mt-0 border-b border-slate-800 md:border-none">
            <h1 class="text-2xl md:text-3xl font-bold text-white">Laboratorio de Metas</h1>
            <p class="text-sm text-emerald-400/80 italic mt-1"><?= $frase_dia ?></p>
        </header>

        <div class="px-6 md:px-10 pb-28 md:pb-12 mt-6 md:mt-0 grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-7 space-y-10">

                <?php if($mensaje): ?>
                    <div class="p-4 rounded-xl flex items-center gap-3 border shadow-lg <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-200' : 'bg-red-900/40 border-red-500/50 text-red-200' ?>">
                        <i class="ph <?= $tipo_mensaje === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-2xl"></i><span><?= $mensaje ?></span>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2"><i class="ph ph-piggy-bank text-emerald-400"></i> Mis Retos de Ahorro</h2>
                        <button onclick="document.getElementById('modal_reto').classList.remove('hidden')" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-4 py-2 rounded-full transition-all shadow-md shadow-emerald-900/40 border border-emerald-500">+ Nuevo Reto</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if (empty($retos)): ?>
                            <div class="col-span-full py-10 glass-panel rounded-[2rem] text-center border-dashed border-slate-700">
                                <i class="ph ph-shooting-star text-4xl text-slate-600 mb-3"></i>
                                <p class="text-slate-500">¿Qué meta tienes en mente? Créala ahora.</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($retos as $r):
                            $monto_actual_real = $r['cuenta_id'] ? $r['cuenta_balance'] : $r['monto_actual'];
                            $porc = ($r['monto_objetivo'] > 0) ? ($monto_actual_real / $r['monto_objetivo']) * 100 : 0;
                            $porc = max(0, min(100, round($porc)));
                            $color = $r['color'] ?? 'emerald';
                        ?>
                            <div class="glass-panel p-6 rounded-[2rem] relative overflow-hidden group hover:border-<?= $color ?>-500/30 transition-colors flex flex-col justify-between">
                                <div>
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 rounded-2xl bg-<?= $color ?>-500/10 flex items-center justify-center text-<?= $color ?>-400 border border-<?= $color ?>-500/20 shadow-inner shrink-0">
                                                <i class="ph ph-target text-2xl"></i>
                                            </div>
                                            <h3 class="text-lg font-bold text-white mb-1 leading-tight truncate"><?= htmlspecialchars($r['nombre']) ?></h3>
                                        </div>
                                        <div class="flex gap-1 opacity-100 md:opacity-0 group-hover:opacity-100 transition-all">
                                            <button onclick="abrirModalEditarReto(<?= $r['id'] ?>, '<?= addslashes($r['nombre']) ?>', <?= $r['monto_objetivo'] ?>, <?= $r['monto_actual'] ?>, '<?= $r['fecha_limite'] ?>', '<?= $color ?>', '<?= $r['cuenta_id'] ?? '' ?>')" class="text-slate-600 hover:text-blue-400 p-2 bg-[#040812] md:bg-transparent rounded-lg" title="Editar Reto"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <form action="retos.php" method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar este reto?')">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="text-slate-600 hover:text-red-400 p-2 bg-[#040812] md:bg-transparent rounded-lg" title="Eliminar Reto"><i class="ph ph-trash text-lg"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wider font-bold mb-4 flex items-center justify-between">
                                        <span>Meta: RD$ <?= number_format($r['monto_objetivo'], 0) ?></span>
                                        <?php if($r['cuenta_id']): ?>
                                            <span class="text-blue-400 flex items-center gap-1 bg-blue-900/30 px-2 py-0.5 rounded border border-blue-800/50" title="Sincronizado con <?= htmlspecialchars($r['cuenta_nombre']) ?>">
                                                <i class="ph ph-bank"></i> <?= htmlspecialchars($r['cuenta_nombre']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>

                                    <div class="flex justify-between items-end mb-2">
                                        <span class="text-2xl font-mono font-bold text-<?= $color ?>-400">RD$ <?= number_format($monto_actual_real, 0) ?></span>
                                        <span class="text-xs font-bold text-slate-500"><?= $porc ?>%</span>
                                    </div>
                                    <div class="w-full h-2 bg-[#040812] border border-slate-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-<?= $color ?>-500 transition-all duration-1000 progress-glow" style="width: <?= $porc ?>%"></div>
                                    </div>
                                </div>

                                <?php if(!$r['cuenta_id']): ?>
                                    <button onclick="abrirModalAbono(<?= $r['id'] ?>, '<?= addslashes($r['nombre']) ?>')" class="w-full mt-5 bg-slate-800/50 hover:bg-<?= $color ?>-500/20 text-slate-300 hover:text-<?= $color ?>-400 border border-slate-700 hover:border-<?= $color ?>-500 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2">
                                        <i class="ph ph-piggy-bank text-lg"></i> Abonar a esta meta
                                    </button>
                                <?php else: ?>
                                    <div class="w-full mt-5 bg-blue-500/10 border border-blue-500/30 py-2.5 rounded-xl text-blue-400 text-[11px] font-bold text-center flex items-center justify-center gap-2">
                                        <i class="ph ph-arrows-clockwise text-lg"></i> Sincronizado
                                    </div>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2"><i class="ph ph-sword text-red-400"></i> Misión: Salir del Hoyo</h2>
                    <div class="space-y-4">
                        <?php foreach ($deudas as $d):
                            $progreso = ($d['limite_o_principal'] > 0) ? ($d['pagado'] / $d['limite_o_principal']) * 100 : 0;
                            $progreso = max(0, min(100, $progreso));
                        ?>
                            <div class="glass-panel p-5 rounded-3xl border-l-4 border-red-500 hover:bg-[#0a1122]/80 transition-colors">
                                <div class="flex justify-between items-end mb-3">
                                    <div>
                                        <span class="font-bold text-white text-lg block leading-tight"><?= htmlspecialchars($d['nombre']) ?></span>
                                        <span class="text-red-400 font-mono text-sm font-bold">RD$ <?= number_format($d['balance_actual']) ?> <span class="text-[10px] text-slate-500 uppercase">pendiente</span></span>
                                    </div>
                                    <span class="text-xs font-bold text-slate-500"><?= round($progreso) ?>% Liquidado</span>
                                </div>
                                <div class="w-full h-2 bg-[#040812] border border-slate-800 rounded-full overflow-hidden mb-3">
                                    <div class="h-full bg-red-500 progress-glow" style="width: <?= $progreso ?>%"></div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <p class="text-[10px] text-slate-400 italic hidden md:block">Cada peso extra es libertad.</p>
                                    <a href="deudas.php" class="text-[10px] bg-red-500/10 border border-red-500/30 text-red-400 px-3 py-1.5 rounded-lg font-bold hover:bg-red-500 hover:text-white transition-all shadow-sm">ATACAR AHORA</a>
                                </div>
                            </div>
                        <?php endforeach;
                        if (empty($deudas)) echo "<p class='text-emerald-400 bg-emerald-500/10 p-4 rounded-2xl border border-emerald-500/30 font-bold flex items-center gap-2'><i class='ph ph-confetti text-xl'></i> ¡Felicidades! No tienes misiones de deuda activas.</p>"; ?>
                    </div>
                </div>

            </div>

            <div class="lg:col-span-5 space-y-8">

                <div class="glass-panel p-8 rounded-[2.5rem] border-t border-blue-500/20 shadow-2xl">
                    <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2"><i class="ph ph-strategy text-blue-400"></i> Plan de Ataque</h2>

                    <div class="flex bg-[#040812] rounded-2xl p-1 mb-6 border border-slate-800 shadow-inner">
                        <button onclick="switchTab('nieve')" id="tab_nieve" class="flex-1 bg-white text-black font-bold py-2.5 rounded-xl text-xs shadow-sm transition-colors">Bola de Nieve</button>
                        <button onclick="switchTab('avalancha')" id="tab_avalancha" class="flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-xs hover:text-white transition-colors">Avalancha</button>
                    </div>

                    <div id="content_nieve" class="space-y-5">
                        <p class="text-xs text-slate-400 uppercase tracking-wider leading-relaxed bg-blue-500/10 p-3 rounded-xl border border-blue-500/20">
                            <span class="text-blue-400 font-bold"><i class="ph ph-info"></i> Estrategia:</span> Paga el balance más pequeño primero. Esto te da <span class="text-white font-bold">"Victorias Rápidas"</span> para que no te desanimes.
                        </p>
                        <div class="space-y-3">
                            <?php $i = 1;
                            foreach ($deudas as $d): ?>
                                <div class="flex items-center gap-4 p-4 bg-[#0a1122]/60 rounded-2xl border border-slate-700/50 hover:border-blue-500/50 transition-colors">
                                    <span class="w-8 h-8 shrink-0 rounded-full bg-blue-500/20 border border-blue-500 text-blue-400 flex items-center justify-center text-xs font-bold shadow-inner"><?= $i++ ?></span>
                                    <div class="flex-1 overflow-hidden">
                                        <p class="text-sm font-bold text-white truncate"><?= htmlspecialchars($d['nombre']) ?></p>
                                        <p class="text-xs text-slate-500 font-mono">Balance: RD$ <?= number_format($d['balance_actual'], 0) ?></p>
                                    </div>
                                    <i class="ph ph-arrow-right text-slate-600 shrink-0"></i>
                                </div>
                            <?php endforeach;
                            if (empty($deudas)) echo "<p class='text-slate-500 text-sm italic'>Nada que mostrar.</p>"; ?>
                        </div>
                    </div>

                    <div id="content_avalancha" class="hidden space-y-5">
                        <p class="text-xs text-slate-400 uppercase tracking-wider leading-relaxed bg-red-500/10 p-3 rounded-xl border border-red-500/20">
                            <span class="text-red-400 font-bold"><i class="ph ph-info"></i> Estrategia:</span> Ataca la deuda con <span class="text-white font-bold">mayor tasa de interés</span> primero. Matemáticamente te ahorra más dinero.
                        </p>
                        <div class="space-y-3">
                            <?php $i = 1;
                            foreach ($deudas_interes as $d): ?>
                                <div class="flex items-center gap-4 p-4 bg-[#0a1122]/60 rounded-2xl border border-slate-700/50 hover:border-red-500/50 transition-colors">
                                    <span class="w-8 h-8 shrink-0 rounded-full bg-red-500/20 border border-red-500 text-red-400 flex items-center justify-center text-xs font-bold shadow-inner"><?= $i++ ?></span>
                                    <div class="flex-1 overflow-hidden">
                                        <p class="text-sm font-bold text-white truncate"><?= htmlspecialchars($d['nombre']) ?></p>
                                        <p class="text-xs text-slate-500 font-mono">Tasa: <?= $d['tasa_interes'] ?>%</p>
                                    </div>
                                    <i class="ph ph-fire text-orange-400 text-xl shrink-0"></i>
                                </div>
                            <?php endforeach;
                            if (empty($deudas_interes)) echo "<p class='text-slate-500 text-sm italic'>Nada que mostrar.</p>"; ?>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-8 rounded-[2.5rem] bg-gradient-to-br from-emerald-600/10 to-transparent border-t border-emerald-500/20 relative overflow-hidden">
                    <i class="ph ph-quotes absolute -right-4 -top-4 text-7xl text-emerald-500/10"></i>
                    <h3 class="font-bold text-white mb-5 flex items-center gap-2 text-sm uppercase tracking-widest"><i class="ph ph-lightbulb-filament text-yellow-400 text-xl"></i> Tip de Sobrevivencia</h3>
                    <ul class="space-y-5 relative z-10">
                        <li class="flex gap-4 items-start">
                            <i class="ph ph-check-circle text-emerald-400 text-xl mt-0.5 shrink-0"></i>
                            <p class="text-sm text-slate-300 leading-relaxed">Si te llega el doble sueldo o unas vacaciones, métele el 50% a la tarjeta más alta. <span class="text-emerald-400 font-bold">Ese es tu mejor regalo.</span></p>
                        </li>
                        <li class="flex gap-4 items-start">
                            <i class="ph ph-check-circle text-emerald-400 text-xl mt-0.5 shrink-0"></i>
                            <p class="text-sm text-slate-300 leading-relaxed">El ahorro no es castigo. Es comprar tu <span class="text-blue-400 font-bold">Tranquilidad</span> a plazos.</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <div id="modal_reto" class="hidden fixed inset-0 z-[70] flex items-end md:items-center justify-center p-0 md:p-6 bg-[#040812]/80 backdrop-blur-md transition-all">
        <div class="glass-panel w-full max-w-md p-8 rounded-t-[2.5rem] md:rounded-[2.5rem] border border-emerald-500/30 shadow-2xl shadow-emerald-900/20 bg-[#0a1122]">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white flex items-center gap-2"><i class="ph ph-target text-emerald-400 text-2xl"></i> Nuevo Reto de Ahorro</h3>
                <button onclick="document.getElementById('modal_reto').classList.add('hidden')" class="text-slate-500 hover:text-white bg-slate-800 rounded-full w-8 h-8 flex items-center justify-center"><i class="ph ph-x text-lg"></i></button>
            </div>
            
            <form action="retos.php" method="POST" class="space-y-5">
                <input type="hidden" name="accion" value="nuevo_reto">

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nombre de la Meta</label>
                    <input type="text" name="nombre" placeholder="Ej. El Resort, Fondo Emergencia" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">¿Dónde se guarda el dinero?</label>
                    <div class="relative">
                        <select name="cuenta_id" id="cuenta_vinculada" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 outline-none transition-colors appearance-none font-bold">
                            <option value="">-- Ingreso Manual (No vinculado) --</option>
                            <?php foreach($bancos as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?> (RD$ <?= number_format($b['balance'], 2) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Objetivo (RD$)</label>
                        <input type="number" name="objetivo" step="0.01" placeholder="Monto Meta" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 outline-none transition-colors font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Ya tengo (RD$)</label>
                        <input type="number" name="actual" id="monto_actual_manual" step="0.01" value="0.00" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 outline-none transition-colors font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Fecha Límite (Opcional)</label>
                    <input type="date" name="fecha_limite" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-slate-300 focus:border-emerald-500 outline-none transition-colors cursor-pointer">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Color del Reto</label>
                    <div class="relative">
                        <select name="color" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 outline-none transition-colors appearance-none font-bold">
                            <option value="emerald" class="text-emerald-400">Verde Esperanza</option>
                            <option value="blue" class="text-blue-400">Azul Victoria</option>
                            <option value="orange" class="text-orange-400">Naranja Energía</option>
                            <option value="fuchsia" class="text-fuchsia-400">Fucsia Brillante</option>
                            <option value="yellow" class="text-yellow-400">Amarillo Riqueza</option>
                        </select>
                        <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('modal_reto').classList.add('hidden')" class="px-6 py-4 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-2xl transition-colors md:hidden w-full mb-2">Cancelar</button>
                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-emerald-900/40 text-lg">¡Crear Meta!</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal_editar_reto" class="hidden fixed inset-0 z-[70] flex items-end md:items-center justify-center p-0 md:p-6 bg-[#040812]/80 backdrop-blur-md transition-all">
        <div class="glass-panel w-full max-w-md p-8 rounded-t-[2.5rem] md:rounded-[2.5rem] border border-blue-500/30 shadow-2xl shadow-blue-900/20 bg-[#0a1122]">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white flex items-center gap-2"><i class="ph ph-pencil-simple text-blue-400 text-2xl"></i> Editar Reto</h3>
                <button type="button" onclick="document.getElementById('modal_editar_reto').classList.add('hidden')" class="text-slate-500 hover:text-white bg-slate-800 rounded-full w-8 h-8 flex items-center justify-center"><i class="ph ph-x text-lg"></i></button>
            </div>
            
            <form action="retos.php" method="POST" class="space-y-5">
                <input type="hidden" name="accion" value="editar_reto">
                <input type="hidden" name="id" id="edit_reto_id">

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nombre de la Meta</label>
                    <input type="text" name="nombre" id="edit_reto_nombre" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">¿Dónde se guarda el dinero?</label>
                    <div class="relative">
                        <select name="cuenta_id" id="edit_cuenta_vinculada" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-colors appearance-none font-bold">
                            <option value="">-- Ingreso Manual (No vinculado) --</option>
                            <?php foreach($bancos as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?> (RD$ <?= number_format($b['balance'], 2) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Objetivo (RD$)</label>
                        <input type="number" name="objetivo" id="edit_reto_objetivo" step="0.01" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-colors font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Ya tengo (RD$)</label>
                        <input type="number" name="actual" id="edit_monto_actual_manual" step="0.01" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-colors font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Fecha Límite (Opcional)</label>
                    <input type="date" name="fecha_limite" id="edit_reto_fecha" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-slate-300 focus:border-blue-500 outline-none transition-colors cursor-pointer">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Color del Reto</label>
                    <div class="relative">
                        <select name="color" id="edit_reto_color" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-colors appearance-none font-bold">
                            <option value="emerald" class="text-emerald-400">Verde Esperanza</option>
                            <option value="blue" class="text-blue-400">Azul Victoria</option>
                            <option value="orange" class="text-orange-400">Naranja Energía</option>
                            <option value="fuchsia" class="text-fuchsia-400">Fucsia Brillante</option>
                            <option value="yellow" class="text-yellow-400">Amarillo Riqueza</option>
                        </select>
                        <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-800 flex gap-2">
                    <button type="button" onclick="document.getElementById('modal_editar_reto').classList.add('hidden')" class="px-6 py-4 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-2xl transition-colors md:hidden w-1/3">Cancelar</button>
                    <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-blue-900/40 text-lg">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal_abono" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-[#040812]/90 backdrop-blur-sm transition-all">
        <div class="glass-panel w-full max-w-sm p-8 rounded-[2.5rem] border border-emerald-500/30 shadow-2xl shadow-emerald-900/20 bg-[#0a1122]">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white flex items-center gap-2"><i class="ph ph-piggy-bank text-emerald-400"></i> Abonar al Reto</h3>
                <button type="button" onclick="document.getElementById('modal_abono').classList.add('hidden')" class="text-slate-500 hover:text-white bg-slate-800 rounded-full w-8 h-8 flex items-center justify-center"><i class="ph ph-x text-lg"></i></button>
            </div>
            
            <p id="abono_nombre_reto" class="text-sm font-bold text-emerald-400 mb-5 border-b border-slate-800 pb-3">Reto seleccionado</p>
            
            <form action="retos.php" method="POST" class="space-y-5">
                <input type="hidden" name="accion" value="abonar">
                <input type="hidden" name="id" id="abono_reto_id">
                
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-bold text-lg">RD$</span>
                    <input type="number" name="monto_abono" step="0.01" placeholder="0.00" required class="w-full bg-[#040812] border border-slate-700 rounded-xl pl-12 pr-4 py-4 text-2xl font-bold text-white focus:border-emerald-500 outline-none font-mono">
                </div>

                <div class="pt-2 border-t border-slate-800 flex gap-2">
                    <button type="button" onclick="document.getElementById('modal_abono').classList.add('hidden')" class="px-6 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-2xl transition-colors md:hidden">Cancelar</button>
                    <button type="submit" class="flex-1 bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-emerald-900/40 text-lg">Guardar Abono</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const btnNieve = document.getElementById('tab_nieve');
            const btnAval = document.getElementById('tab_avalancha');
            const contentNieve = document.getElementById('content_nieve');
            const contentAval = document.getElementById('content_avalancha');

            if (tab === 'nieve') {
                btnNieve.className = 'flex-1 bg-white text-black font-bold py-2.5 rounded-xl text-xs shadow-sm transition-colors';
                btnAval.className = 'flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-xs hover:text-white transition-colors';
                contentNieve.classList.remove('hidden');
                contentAval.classList.add('hidden');
            } else {
                btnAval.className = 'flex-1 bg-white text-black font-bold py-2.5 rounded-xl text-xs shadow-sm transition-colors';
                btnNieve.className = 'flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-xs hover:text-white transition-colors';
                contentAval.classList.remove('hidden');
                contentNieve.classList.add('hidden');
            }
        }

        const cuentaSelect = document.getElementById('cuenta_vinculada');
        const manualInput = document.getElementById('monto_actual_manual');
        cuentaSelect.addEventListener('change', () => {
            if(cuentaSelect.value !== '') { manualInput.value = '0.00'; manualInput.readOnly = true; manualInput.classList.add('opacity-50'); } 
            else { manualInput.readOnly = false; manualInput.classList.remove('opacity-50'); }
        });

        const editCuentaSelect = document.getElementById('edit_cuenta_vinculada');
        const editManualInput = document.getElementById('edit_monto_actual_manual');
        editCuentaSelect.addEventListener('change', () => {
            if(editCuentaSelect.value !== '') { editManualInput.value = '0.00'; editManualInput.readOnly = true; editManualInput.classList.add('opacity-50'); } 
            else { editManualInput.readOnly = false; editManualInput.classList.remove('opacity-50'); }
        });

        function abrirModalEditarReto(id, nombre, objetivo, actual, fecha, color, cuenta_id) {
            document.getElementById('edit_reto_id').value = id;
            document.getElementById('edit_reto_nombre').value = nombre;
            document.getElementById('edit_reto_objetivo').value = objetivo;
            document.getElementById('edit_monto_actual_manual').value = actual;
            document.getElementById('edit_reto_fecha').value = fecha;
            document.getElementById('edit_reto_color').value = color;
            document.getElementById('edit_cuenta_vinculada').value = cuenta_id;
            
            editCuentaSelect.dispatchEvent(new Event('change'));
            document.getElementById('modal_editar_reto').classList.remove('hidden');
        }

        function abrirModalAbono(id, nombre) {
            document.getElementById('abono_reto_id').value = id;
            document.getElementById('abono_nombre_reto').textContent = "Destino: " + nombre;
            document.getElementById('modal_abono').classList.remove('hidden');
        }
    </script>
</body>
</html>