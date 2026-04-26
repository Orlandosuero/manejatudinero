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

// --- MAGIA: ASEGURAR QUE LA COLUMNA DE "PERIODO DE LA TASA" EXISTA EN LA BD ---
try {
    $pdo->exec("ALTER TABLE deudas ADD COLUMN periodo_tasa VARCHAR(20) DEFAULT 'anual'");
} catch (PDOException $e) {
    // Si ya existe, ignoramos el error silenciosamente
}

// --- LÓGICA DE PROCESAMIENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];
    $nombre = trim($_POST['nombre'] ?? '');
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

    try {
        // REGISTRAR PAGO (Aislado por Usuario)
        if ($accion === 'registrar_pago') {
            $deuda_id = (int)$_POST['deuda_id'];
            $monto_total = (float)$_POST['monto_total'];
            $monto_interes = (float)$_POST['monto_interes'];
            $fecha = $_POST['fecha_pago'];

            $monto_capital = $monto_total - $monto_interes;

            // Verificamos que la deuda le pertenezca a este usuario antes de abonar
            $stmtVerificar = $pdo->prepare("SELECT id FROM deudas WHERE id = ? AND usuario_id = ?");
            $stmtVerificar->execute([$deuda_id, $uid]);
            if ($stmtVerificar->fetchColumn() && $monto_total != 0) {
                if ($monto_interes != 0) {
                    $stmt = $pdo->prepare("INSERT INTO pagos_deudas (usuario_id, deuda_id, monto, tipo_abono, fecha) VALUES (?, ?, ?, 'interes', ?)");
                    $stmt->execute([$uid, $deuda_id, $monto_interes, $fecha]);
                }
                if ($monto_capital != 0) {
                    $stmt = $pdo->prepare("INSERT INTO pagos_deudas (usuario_id, deuda_id, monto, tipo_abono, fecha) VALUES (?, ?, ?, 'capital', ?)");
                    $stmt->execute([$uid, $deuda_id, $monto_capital, $fecha]);

                    $stmtUpdate = $pdo->prepare("UPDATE deudas SET balance_actual = balance_actual - ? WHERE id = ? AND usuario_id = ?");
                    $stmtUpdate->execute([$monto_capital, $deuda_id, $uid]);
                }

                $mensaje = "¡Movimiento registrado! " . ($monto_capital > 0 ? "Le restaste RD$ " . number_format($monto_capital, 2) . " al capital real." : "Ajuste aplicado.");
                $tipo_mensaje = "success";
            }
        }
        // ELIMINAR DEUDA (Aislado por Usuario)
        elseif ($accion === 'eliminar') {
            $stmt = $pdo->prepare("DELETE FROM deudas WHERE id = :id AND usuario_id = :uid");
            $stmt->execute([':id' => $id, ':uid' => $uid]);
            $mensaje = 'Deuda eliminada correctamente.';
            $tipo_mensaje = 'success';
        }
        // GUARDAR/EDITAR DEUDA (Aislado por Usuario)
        elseif ($accion === 'guardar_deuda') {
            $tipo = $_POST['tipo'];
            $limite = (float)$_POST['limite'];
            $balance = (float)$_POST['balance'];
            $interes = (float)$_POST['interes'];
            $periodo_tasa = $_POST['periodo_tasa'] ?? 'anual';
            $dia_pago = !empty($_POST['dia_pago']) ? (int)$_POST['dia_pago'] : null;
            $dia_cierre = ($tipo === 'tarjeta' && !empty($_POST['dia_cierre'])) ? (int)$_POST['dia_cierre'] : null;
            $frecuencia = $_POST['frecuencia_pago'] ?? 'mensual';

            if ($id) {
                $sql = "UPDATE deudas SET tipo=:tipo, nombre=:nombre, limite_o_principal=:limite, balance_actual=:balance, tasa_interes=:interes, periodo_tasa=:periodo_tasa, dia_cierre=:cierre, dia_pago=:pago, frecuencia_pago=:frecuencia WHERE id=:id AND usuario_id=:uid";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':tipo' => $tipo, ':nombre' => $nombre, ':limite' => $limite, ':balance' => $balance, ':interes' => $interes, ':periodo_tasa' => $periodo_tasa, ':cierre' => $dia_cierre, ':pago' => $dia_pago, ':frecuencia' => $frecuencia, ':id' => $id, ':uid' => $uid]);
                $mensaje = 'Deuda actualizada con éxito.';
            } else {
                $sql = "INSERT INTO deudas (usuario_id, tipo, nombre, limite_o_principal, balance_actual, tasa_interes, periodo_tasa, dia_cierre, dia_pago, frecuencia_pago) VALUES (:uid, :tipo, :nombre, :limite, :balance, :interes, :periodo_tasa, :cierre, :pago, :frecuencia)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':uid' => $uid, ':tipo' => $tipo, ':nombre' => $nombre, ':limite' => $limite, ':balance' => $balance, ':interes' => $interes, ':periodo_tasa' => $periodo_tasa, ':cierre' => $dia_cierre, ':pago' => $dia_pago, ':frecuencia' => $frecuencia]);
                $mensaje = 'Deuda registrada. ¡A planificar el pago!';
            }
            $tipo_mensaje = 'success';
        }
    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// --- CONSULTAS (Aisladas por Usuario) ---
$stmtDeudas = $pdo->prepare("SELECT * FROM deudas WHERE usuario_id = ? ORDER BY balance_actual DESC");
$stmtDeudas->execute([$uid]);
$deudas = $stmtDeudas->fetchAll();

$total_adeudado = 0;
foreach ($deudas as $d) {
    if ($d['balance_actual'] > 0) {
        $total_adeudado += $d['balance_actual'];
    }
}

$nombre_completo = $_SESSION['usuario_nombre'] ?? 'Usuario';
$primer_nombre = explode(' ', trim($nombre_completo))[0];
$inicial = strtoupper(substr($primer_nombre, 0, 1));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Deudas | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .glass-panel { background: rgba(10, 17, 34, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        input[type="number"]::-webkit-inner-spin-button, input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    </style>
</head>

<body class="bg-[#040812] text-slate-200 font-sans h-screen flex overflow-hidden selection:bg-[#CE1126]/30">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-[#1a0808] via-[#040812] to-[#020409] hide-scrollbar relative">
        
        <header class="py-6 md:h-24 px-6 md:px-10 flex flex-col md:flex-row items-start md:items-center justify-between shrink-0 gap-4 mt-2 md:mt-0">
            <div>
                <h1 class="text-2xl md:text-3xl font-semibold text-white">Centro de Pasivos</h1>
                <p class="text-sm text-red-400/80 mt-1">Conoce tus deudas para poder matarlas.</p>
            </div>

            <div class="text-left md:text-right w-full md:w-auto bg-red-900/10 md:bg-transparent p-4 md:p-0 rounded-2xl md:rounded-none border border-red-500/20 md:border-none">
                <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">Total Adeudado</p>
                <p class="text-3xl font-bold text-red-400 font-mono drop-shadow-md">RD$ <?= number_format($total_adeudado, 2) ?></p>
            </div>
        </header>

        <div class="px-6 md:px-10 pb-28 md:pb-12">
            <?php if ($mensaje): ?>
                <div class="mb-8 p-4 rounded-xl flex items-center gap-3 border shadow-lg <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-200' : 'bg-red-900/40 border-red-500/50 text-red-200' ?>">
                    <i class="ph <?= $tipo_mensaje === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-2xl"></i>
                    <span><?= $mensaje ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">

                <div class="xl:col-span-4 space-y-6">
                    <div class="glass-panel p-6 rounded-3xl border-t border-red-500/30 relative overflow-hidden shadow-2xl">
                        <div class="absolute -right-10 -top-10 w-40 h-40 bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>

                        <h3 id="titulo_form" class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                            <i class="ph ph-receipt text-red-400 text-xl"></i> Registrar Deuda
                        </h3>

                        <form action="deudas.php" method="POST" id="form_deuda" class="space-y-4 relative z-10">
                            <input type="hidden" name="accion" value="guardar_deuda">
                            <input type="hidden" name="id" id="deuda_id" value="">

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Tipo de Deuda</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="tipo" value="tarjeta" class="peer sr-only" checked onchange="toggleCamposDeuda()">
                                        <div class="p-2 border border-slate-700 rounded-xl text-center text-slate-400 peer-checked:bg-red-500/10 peer-checked:border-red-500 peer-checked:text-red-400 transition-all flex flex-col items-center gap-1">
                                            <i class="ph ph-credit-card text-2xl"></i>
                                            <span class="text-[9px] font-bold uppercase">Tarjeta</span>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="tipo" value="banco" class="peer sr-only" onchange="toggleCamposDeuda()">
                                        <div class="p-2 border border-slate-700 rounded-xl text-center text-slate-400 peer-checked:bg-blue-500/10 peer-checked:border-blue-500 peer-checked:text-blue-400 transition-all flex flex-col items-center gap-1">
                                            <i class="ph ph-bank text-2xl"></i>
                                            <span class="text-[9px] font-bold uppercase">Banco</span>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="tipo" value="prestamista" class="peer sr-only" onchange="toggleCamposDeuda()">
                                        <div class="p-2 border border-slate-700 rounded-xl text-center text-slate-400 peer-checked:bg-orange-500/10 peer-checked:border-orange-500 peer-checked:text-orange-400 transition-all flex flex-col items-center gap-1">
                                            <i class="ph ph-user-focus text-2xl"></i>
                                            <span class="text-[9px] font-bold uppercase">Presta...</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nombre (Ej. Visa BHD)</label>
                                <input type="text" name="nombre" id="deuda_nombre" required class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-2.5 text-white focus:border-red-500 focus:outline-none">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label id="label_limite" class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Límite (RD$)</label>
                                    <input type="number" name="limite" id="deuda_limite" step="any" required class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-2.5 text-white focus:border-red-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Balance Actual</label>
                                    <input type="number" name="balance" id="deuda_balance" step="any" required class="w-full bg-red-900/20 border border-red-900/50 rounded-xl px-4 py-2.5 text-red-200 font-bold focus:border-red-500 focus:outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="col-span-2 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Tasa y Periodo</label>
                                    <div class="flex">
                                        <input type="number" name="interes" id="deuda_interes" step="any" value="0.00" class="w-1/2 bg-[#040812]/80 border border-slate-700 border-r-0 rounded-l-xl px-3 py-2.5 text-white focus:border-red-500 focus:outline-none text-sm text-center">
                                        <select name="periodo_tasa" id="deuda_periodo_tasa" class="w-1/2 bg-slate-800 border border-slate-700 rounded-r-xl px-2 py-2.5 text-slate-300 focus:outline-none text-[10px] font-bold appearance-none text-center">
                                            <option value="anual">Anual %</option>
                                            <option value="mensual">Mensual %</option>
                                            <option value="quincenal">Quinc. %</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div id="container_cierre">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Corte</label>
                                    <input type="number" name="dia_cierre" id="deuda_cierre" min="1" max="31" placeholder="Día" class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-2 py-2.5 text-white focus:border-red-500 focus:outline-none text-xs text-center">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Pago</label>
                                    <input type="number" name="dia_pago" id="deuda_pago" min="1" max="31" placeholder="Día" class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-2 py-2.5 text-white focus:border-red-500 focus:outline-none text-xs text-center">
                                </div>
                                <div id="container_frecuencia" class="col-span-2 md:col-span-4">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Frecuencia de Pago de Cuota</label>
                                    <div class="relative">
                                        <select name="frecuencia_pago" id="deuda_frecuencia" class="w-full bg-[#040812]/80 border border-slate-700 rounded-xl px-4 py-2.5 text-white focus:border-red-500 focus:outline-none text-xs appearance-none font-bold">
                                            <option value="diario">Diario</option>
                                            <option value="semanal">Semanal</option>
                                            <option value="quincenal">Quincenal</option>
                                            <option value="mensual" selected>Mensual</option>
                                            <option value="anual">Anual</option>
                                        </select>
                                        <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2 pt-4">
                                <button type="button" onclick="cancelarEdicion()" class="px-4 bg-slate-800 rounded-xl hidden hover:bg-slate-700 transition-colors text-sm" id="btn_cancelar">Cancelar</button>
                                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-500 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-red-900/20">Guardar Deuda</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="xl:col-span-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <?php if (empty($deudas)): ?>
                            <div class="col-span-full text-center py-12 glass-panel rounded-3xl border-dashed border-slate-700">
                                <div class="w-16 h-16 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-4"><i class="ph ph-check-circle text-3xl"></i></div>
                                <h3 class="text-xl font-bold text-white">¡Estás libre de deudas!</h3>
                                <p class="text-slate-400 mt-2">No tienes pasivos registrados. Ojalá siga así.</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($deudas as $d):
                            if ($d['tipo'] == 'tarjeta') {
                                $icon = 'ph-credit-card';
                                $color = 'red';
                                $lbl_limite = 'Límite Crédito';
                            } elseif ($d['tipo'] == 'banco') {
                                $icon = 'ph-bank';
                                $color = 'blue';
                                $lbl_limite = 'Préstamo Original';
                            } else {
                                $icon = 'ph-user-focus';
                                $color = 'orange';
                                $lbl_limite = 'Monto Tomado';
                            }

                            $frecuencia = $d['frecuencia_pago'] ?? 'mensual';
                            $periodo_tasa = $d['periodo_tasa'] ?? 'anual';
                            $balance_real = $d['balance_actual'];
                            $limite = $d['limite_o_principal'];

                            // CALCULOS INTELIGENTES (Sobregiros y Saldos a favor)
                            $sobregiro = 0;
                            if ($balance_real < 0) {
                                $porcentaje = 0; 
                            } elseif ($d['tipo'] == 'tarjeta' && $balance_real > $limite && $limite > 0) {
                                $sobregiro = $balance_real - $limite;
                                $porcentaje = 100;
                            } else {
                                $porcentaje = ($limite > 0) ? ($balance_real / $limite) * 100 : 0;
                                $porcentaje = min(100, max(0, $porcentaje));
                            }

                            // CÁLCULO DEL INTERÉS PROYECTADO 100% PRECISO
                            $tasa_bruta = $d['tasa_interes'] ?? 0;
                            $tasa_anual = $tasa_bruta;
                            
                            // 1. Llevamos todo a Tasa Anual para normalizar
                            if ($periodo_tasa == 'mensual') $tasa_anual = $tasa_bruta * 12;
                            if ($periodo_tasa == 'quincenal') $tasa_anual = $tasa_bruta * 24;
                            if ($periodo_tasa == 'semanal') $tasa_anual = $tasa_bruta * 52;
                            if ($periodo_tasa == 'diario') $tasa_anual = $tasa_bruta * 365;

                            // 2. Dividimos la Tasa Anual entre la Frecuencia de Pago Real
                            $divisor = 12; 
                            if($frecuencia == 'quincenal') $divisor = 24;
                            elseif($frecuencia == 'semanal') $divisor = 52;
                            elseif($frecuencia == 'diario') $divisor = 365;
                            elseif($frecuencia == 'anual') $divisor = 1;

                            $interes_proyectado = ($balance_real > 0 && $tasa_anual > 0) ? ($balance_real * ($tasa_anual / 100)) / $divisor : 0;
                        ?>
                            <div class="glass-panel p-6 rounded-3xl border-t border-<?= $color ?>-500/30 group hover:bg-[#0a1122]/80 transition-all relative overflow-hidden shadow-lg flex flex-col justify-between">
                                
                                <div class="absolute bottom-0 left-0 h-1 transition-all <?= ($sobregiro > 0) ? 'bg-red-500 animate-pulse' : 'bg-'.$color.'-500/80' ?>" style="width: <?= $porcentaje ?>%"></div>

                                <div>
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 rounded-xl bg-<?= $color ?>-500/10 flex items-center justify-center text-<?= $color ?>-400 border border-<?= $color ?>-500/20 shadow-inner shrink-0">
                                                <i class="ph <?= $icon ?> text-2xl"></i>
                                            </div>
                                            <div class="overflow-hidden">
                                                <h4 class="text-lg font-bold text-white leading-tight truncate"><?= htmlspecialchars($d['nombre']) ?></h4>
                                                <span class="text-[10px] font-bold tracking-widest text-<?= $color ?>-400 uppercase"><?= strtoupper($d['tipo']) ?></span>
                                            </div>
                                        </div>
                                        <div class="flex gap-1 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button onclick="editarDeuda(<?= $d['id'] ?>, '<?= $d['tipo'] ?>', '<?= addslashes($d['nombre']) ?>', <?= $d['limite_o_principal'] ?>, <?= $d['balance_actual'] ?>, <?= $d['tasa_interes'] ?>, '<?= $periodo_tasa ?>', '<?= $d['dia_cierre'] ?>', '<?= $d['dia_pago'] ?>', '<?= $frecuencia ?>')" class="p-2 text-slate-400 hover:text-white rounded-lg bg-[#040812] md:bg-slate-800/50"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <button onclick="eliminarDeuda(<?= $d['id'] ?>)" class="p-2 text-slate-400 hover:text-red-400 rounded-lg bg-[#040812] md:bg-slate-800/50"><i class="ph ph-trash text-lg"></i></button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4 mb-4 border-b border-slate-800 pb-4">
                                        <div>
                                            <p class="text-[10px] text-slate-500 uppercase font-bold mb-0.5">Balance Actual</p>
                                            <p class="text-xl font-bold font-mono drop-shadow-sm <?= ($balance_real < 0) ? 'text-emerald-400' : 'text-white' ?>">RD$ <?= number_format($balance_real, 2) ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[10px] text-slate-500 uppercase font-bold mb-0.5"><?= $lbl_limite ?></p>
                                            <p class="text-sm font-semibold text-slate-400 font-mono">RD$ <?= number_format($limite, 2) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center justify-between gap-y-2 text-xs mb-2">
                                        <div class="flex items-center gap-4">
                                            <span class="flex items-center gap-1 text-slate-400 font-bold bg-slate-800/50 px-2 py-1 rounded">
                                                <i class="ph ph-percent text-slate-500"></i> <?= number_format($tasa_bruta, 1) ?>% <?= ucfirst($periodo_tasa) ?>
                                            </span>
                                            <?php if ($d['tipo'] == 'tarjeta' && $d['dia_cierre']): ?>
                                                <span class="flex items-center gap-1 text-slate-400">
                                                    <i class="ph ph-scissors text-slate-500"></i> Corte el <?= $d['dia_cierre'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($d['dia_pago']): ?>
                                            <span class="flex items-center gap-1 font-bold <?= ($d['dia_pago'] == date('j')) ? 'text-red-400' : 'text-slate-300' ?>">
                                                <i class="ph ph-calendar-check text-<?= ($d['dia_pago'] == date('j')) ? 'red' : 'blue' ?>-400"></i> Pago el <?= $d['dia_pago'] ?> 
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-600 italic">Pago <?= ucfirst($frecuencia) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="bg-[#040812]/50 p-3 rounded-xl mt-3 mb-4 border border-slate-800">
                                        <div class="flex justify-between items-center">
                                            <span class="text-[10px] text-slate-400 uppercase font-bold flex items-center gap-1"><i class="ph ph-trend-up text-orange-400 text-sm"></i> Interés (Cuota <?= ucfirst($frecuencia) ?>)</span>
                                            <span class="text-xs font-bold text-orange-400 font-mono">RD$ <?= number_format($interes_proyectado, 2) ?></span>
                                        </div>
                                        
                                        <?php if($sobregiro > 0): ?>
                                            <div class="flex justify-between items-center mt-2 pt-2 border-t border-red-900/30">
                                                <span class="text-[10px] text-red-400 uppercase font-bold flex items-center gap-1"><i class="ph ph-warning-circle text-sm animate-pulse"></i> Tarjeta Sobregirada</span>
                                                <span class="text-xs font-bold text-red-500 font-mono">RD$ <?= number_format($sobregiro, 2) ?></span>
                                            </div>
                                        <?php elseif($balance_real < 0): ?>
                                            <div class="flex justify-between items-center mt-2 pt-2 border-t border-emerald-900/30">
                                                <span class="text-[10px] text-emerald-400 uppercase font-bold flex items-center gap-1"><i class="ph ph-check-circle text-sm"></i> Balance a Favor</span>
                                                <span class="text-xs font-bold text-emerald-500 font-mono">RD$ <?= number_format(abs($balance_real), 2) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <button onclick="abrirPago(<?= $d['id'] ?>, '<?= addslashes($d['nombre']) ?>')" class="w-full bg-emerald-600/10 hover:bg-emerald-600/20 text-emerald-500 border border-emerald-500/30 text-sm font-bold py-2.5 rounded-xl transition-all shadow-md flex justify-center items-center gap-2">
                                        <i class="ph ph-piggy-bank text-lg"></i> ABONAR A DEUDA
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

            </div>
        </div>
    </main>

    <div id="modal_pago" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-[#040812]/90 backdrop-blur-md transition-all">
        <div class="glass-panel w-full max-w-md p-6 md:p-8 rounded-[2.5rem] border-t border-emerald-500/30 shadow-2xl shadow-emerald-900/20 bg-[#0a1122]">
            <h3 id="pago_titulo" class="text-xl font-bold text-white mb-6 flex items-center gap-2"><i class="ph ph-money text-emerald-400 text-2xl"></i> Abonar a Deuda</h3>

            <form action="deudas.php" method="POST" class="space-y-5">
                <input type="hidden" name="accion" value="registrar_pago">
                <input type="hidden" name="deuda_id" id="pago_deuda_id">

                <div class="relative">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Monto del Movimiento</label>
                    <span class="absolute left-4 top-[38px] text-slate-500 font-bold text-xl">RD$</span>
                    <input type="number" name="monto_total" id="pago_total" oninput="calcularDesglose()" step="any" placeholder="0.00" required class="w-full bg-[#040812] border border-slate-700 rounded-2xl pl-14 pr-4 py-4 text-2xl font-bold text-white focus:border-emerald-500 focus:outline-none transition-colors">
                    <p class="text-[9px] text-slate-500 mt-1 italic">*Usa números negativos (-) si es un cargo extra o penalidad.</p>
                </div>

                <div class="relative">
                    <label class="block text-[10px] font-bold text-orange-400/80 uppercase mb-1.5 flex items-center gap-1"><i class="ph ph-fire"></i> De eso, ¿Cuánto es interés?</label>
                    <span class="absolute left-4 top-[34px] text-slate-500 font-bold text-sm">RD$</span>
                    <input type="number" name="monto_interes" id="pago_interes" oninput="calcularDesglose()" step="any" value="0.00" required class="w-full bg-orange-900/10 border border-orange-500/30 rounded-xl pl-12 pr-4 py-3 text-lg font-bold text-orange-200 focus:border-orange-500 focus:outline-none transition-colors">
                </div>

                <div class="bg-emerald-900/20 border border-emerald-500/30 rounded-2xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><i class="ph ph-trend-down text-lg"></i></div>
                        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest leading-tight">Impacto Real<br>al Capital</span>
                    </div>
                    <span class="text-xl font-bold text-white font-mono">RD$ <span id="pago_capital_display">0.00</span></span>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Fecha</label>
                    <input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 focus:outline-none cursor-pointer">
                </div>

                <div class="flex gap-2 pt-2 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('modal_pago').classList.add('hidden')" class="px-6 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-2xl transition-colors md:hidden">Cerrar</button>
                    <button type="button" onclick="document.getElementById('modal_pago').classList.add('hidden')" class="px-6 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-2xl transition-colors hidden md:block">Cancelar</button>
                    <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-emerald-900/30">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <form id="form_eliminar" action="deudas.php" method="POST"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="eliminar_id"></form>

    <script>
        const lblLimite = document.getElementById('label_limite');
        const contCierre = document.getElementById('container_cierre');
        const inCierre = document.getElementById('deuda_cierre');

        function toggleCamposDeuda() {
            const tipo = document.querySelector('input[name="tipo"]:checked').value;
            if (tipo === 'tarjeta') {
                lblLimite.textContent = 'Límite de Crédito (RD$)';
                contCierre.style.display = 'block';
            } else if (tipo === 'banco') {
                lblLimite.textContent = 'Préstamo Original (RD$)';
                contCierre.style.display = 'none';
                inCierre.value = '';
            } else {
                lblLimite.textContent = 'Monto Tomado (RD$)';
                contCierre.style.display = 'none';
                inCierre.value = '';
            }
        }

        function editarDeuda(id, tipo, nombre, limite, balance, interes, periodo_tasa, cierre, pago, frecuencia) {
            document.getElementById('deuda_id').value = id;
            document.querySelector(`input[name="tipo"][value="${tipo}"]`).checked = true;
            toggleCamposDeuda();

            document.getElementById('deuda_nombre').value = nombre;
            document.getElementById('deuda_limite').value = limite;
            document.getElementById('deuda_balance').value = balance;
            document.getElementById('deuda_interes').value = interes;
            document.getElementById('deuda_periodo_tasa').value = periodo_tasa || 'anual';
            document.getElementById('deuda_cierre').value = cierre || '';
            document.getElementById('deuda_pago').value = pago || '';
            document.getElementById('deuda_frecuencia').value = frecuencia || 'mensual';

            document.getElementById('titulo_form').innerHTML = '<i class="ph ph-pencil text-red-400 text-xl"></i> Editar Deuda';
            document.getElementById('btn_cancelar').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function cancelarEdicion() {
            document.getElementById('form_deuda').reset();
            document.getElementById('deuda_id').value = '';
            document.querySelector('input[name="tipo"][value="tarjeta"]').checked = true;
            document.getElementById('deuda_frecuencia').value = 'mensual';
            document.getElementById('deuda_periodo_tasa').value = 'anual';
            toggleCamposDeuda();
            document.getElementById('titulo_form').innerHTML = '<i class="ph ph-receipt text-red-400 text-xl"></i> Registrar Deuda';
            document.getElementById('btn_cancelar').classList.add('hidden');
        }

        function eliminarDeuda(id) {
            if (confirm('¿Estás seguro de que deseas eliminar esta deuda de tu registro?')) {
                document.getElementById('eliminar_id').value = id;
                document.getElementById('form_eliminar').submit();
            }
        }

        function abrirPago(id, nombre) {
            document.getElementById('pago_deuda_id').value = id;
            document.getElementById('pago_titulo').innerHTML = `<i class="ph ph-money text-emerald-400 text-2xl"></i> Abono: <span class="text-white text-base">${nombre}</span>`;
            document.getElementById('modal_pago').classList.remove('hidden');

            document.getElementById('pago_total').value = '';
            document.getElementById('pago_interes').value = '0.00';
            document.getElementById('pago_capital_display').innerText = '0.00';
        }

        function calcularDesglose() {
            let total = parseFloat(document.getElementById('pago_total').value) || 0;
            let interes = parseFloat(document.getElementById('pago_interes').value) || 0;
            let capital = total - interes;

            document.getElementById('pago_capital_display').innerText = capital.toLocaleString('en-US', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        }

        document.addEventListener('DOMContentLoaded', toggleCamposDeuda);
    </script>
</body>
</html>