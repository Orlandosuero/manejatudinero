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

// --- SISTEMA DE EXPORTACIÓN CSV (AISLADO) ---
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ManejaTuDinero_Respaldo_' . date('Ymd_His') . '.csv');
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['fecha', 'descripcion', 'monto', 'cuenta_origen_id', 'cuenta_destino_id']);
    
    $stmtExport = $pdo->prepare("SELECT fecha, descripcion, monto, cuenta_origen_id, cuenta_destino_id FROM transacciones WHERE usuario_id = ? ORDER BY fecha ASC");
    $stmtExport->execute([$uid]);
    
    while ($row = $stmtExport->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($salida, $row);
    }
    fclose($salida);
    exit;
}

// --- SISTEMA DE IMPORTACIÓN CSV (AISLADO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'importar_csv') {
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['archivo_csv']['tmp_name'];
        $handle = fopen($file, "r");
        $header = fgetcsv($handle, 1000, ",");
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO transacciones (usuario_id, fecha, descripcion, monto, cuenta_origen_id, cuenta_destino_id) VALUES (?, ?, ?, ?, ?, ?)");
            $filas = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) >= 5) {
                    $fecha = $data[0];
                    $desc = $data[1];
                    $monto = (float)$data[2];
                    $origen = (int)$data[3];
                    $destino = (int)$data[4];
                    $stmt->execute([$uid, $fecha, $desc, $monto, $origen, $destino]);
                    $pdo->prepare("UPDATE cuentas SET balance = balance - ? WHERE id = ? AND usuario_id = ?")->execute([$monto, $origen, $uid]);
                    $pdo->prepare("UPDATE cuentas SET balance = balance + ? WHERE id = ? AND usuario_id = ?")->execute([$monto, $destino, $uid]);
                    $filas++;
                }
            }
            $pdo->commit();
            $mensaje = "¡Éxito! Se inyectaron $filas transacciones.";
            $tipo_mensaje = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error al importar. Verifica el CSV.";
            $tipo_mensaje = 'error';
        }
        fclose($handle);
    } else {
        $mensaje = "Selecciona un archivo CSV válido.";
        $tipo_mensaje = 'warning';
    }
}

// --- MAGIA: SISTEMA DE FILTROS POR PERIODOS ---
$periodo = $_GET['periodo'] ?? 'este_mes';

switch ($periodo) {
    case 'hoy':
        $where_clause = "DATE(t.fecha) = CURRENT_DATE()";
        $where_deuda = "DATE(fecha) = CURRENT_DATE()";
        $titulo_periodo = "Hoy";
        break;
    case 'ayer':
        $where_clause = "DATE(t.fecha) = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)";
        $where_deuda = "DATE(fecha) = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)";
        $titulo_periodo = "Ayer";
        break;
    case 'mes_pasado':
        $where_clause = "MONTH(t.fecha) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(t.fecha) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
        $where_deuda = "MONTH(fecha) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(fecha) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
        $titulo_periodo = "Mes Pasado";
        break;
    case 'este_anio':
        $where_clause = "YEAR(t.fecha) = YEAR(CURRENT_DATE())";
        $where_deuda = "YEAR(fecha) = YEAR(CURRENT_DATE())";
        $titulo_periodo = "Este Año";
        break;
    case 'anio_pasado':
        $where_clause = "YEAR(t.fecha) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR))";
        $where_deuda = "YEAR(fecha) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR))";
        $titulo_periodo = "Año Pasado";
        break;
    case 'todo':
        $where_clause = "1=1";
        $where_deuda = "1=1";
        $titulo_periodo = "Todo el Historial";
        break;
    case 'este_mes':
    default:
        $where_clause = "MONTH(t.fecha) = MONTH(CURRENT_DATE()) AND YEAR(t.fecha) = YEAR(CURRENT_DATE())";
        $where_deuda = "MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE())";
        $titulo_periodo = "Este Mes";
        break;
}

// =========================================================================
// CONSULTAS DINÁMICAS (Todas blindadas con t.usuario_id = ? o similar)
// =========================================================================

$sqlGastos = "SELECT COALESCE(SUM(t.monto), 0) FROM transacciones t JOIN cuentas d ON t.cuenta_destino_id = d.id WHERE d.clasificacion_id = 2 AND t.usuario_id = ? AND $where_clause";
$stmtG = $pdo->prepare($sqlGastos);
$stmtG->execute([$uid]);
$gastos_periodo = $stmtG->fetchColumn();

$sqlIngresos = "SELECT COALESCE(SUM(t.monto), 0) FROM transacciones t JOIN cuentas o ON t.cuenta_origen_id = o.id WHERE o.clasificacion_id = 3 AND t.usuario_id = ? AND $where_clause";
$stmtI = $pdo->prepare($sqlIngresos);
$stmtI->execute([$uid]);
$ingresos_periodo = $stmtI->fetchColumn();

$balance_neto = $ingresos_periodo - $gastos_periodo;
$porcentaje_ahorro = $ingresos_periodo > 0 ? ($balance_neto / $ingresos_periodo) * 100 : 0;

$sqlPagosDeudas = "SELECT tipo_abono, SUM(monto) as total FROM pagos_deudas WHERE usuario_id = ? AND $where_deuda GROUP BY tipo_abono";
$stmtPD = $pdo->prepare($sqlPagosDeudas);
$stmtPD->execute([$uid]);
$pagos_agrupados = $stmtPD->fetchAll(PDO::FETCH_KEY_PAIR);

$total_capital = $pagos_agrupados['capital'] ?? 0;
$total_interes = $pagos_agrupados['interes'] ?? 0;
$total_pagos_deuda = $total_capital + $total_interes;
$porc_capital = $total_pagos_deuda > 0 ? ($total_capital / $total_pagos_deuda) * 100 : 0;
$porc_interes = $total_pagos_deuda > 0 ? ($total_interes / $total_pagos_deuda) * 100 : 0;

$stmtDV = $pdo->prepare("SELECT COALESCE(SUM(balance_actual), 0) FROM deudas WHERE usuario_id = ?");
$stmtDV->execute([$uid]);
$total_deuda_viva = $stmtDV->fetchColumn();

$sqlHistorialDeudas = "SELECT p.monto, p.tipo_abono, p.fecha, d.nombre FROM pagos_deudas p JOIN deudas d ON p.deuda_id = d.id WHERE p.usuario_id = ? ORDER BY p.fecha DESC LIMIT 6";
$stmtHD = $pdo->prepare($sqlHistorialDeudas);
$stmtHD->execute([$uid]);
$historial_deudas = $stmtHD->fetchAll();

// Gráficas Fijas de Evolución (Siempre muestran 6 meses para contexto)
$sqlDeudaTrend = "SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(CASE WHEN tipo_abono = 'capital' THEN monto ELSE 0 END) as capital, SUM(CASE WHEN tipo_abono = 'interes' THEN monto ELSE 0 END) as interes FROM pagos_deudas WHERE usuario_id = ? AND fecha >= DATE_SUB(LAST_DAY(CURRENT_DATE()), INTERVAL 6 MONTH) GROUP BY mes ORDER BY mes ASC";
$stmtDT = $pdo->prepare($sqlDeudaTrend);
$stmtDT->execute([$uid]);
$trend_deuda_data = $stmtDT->fetchAll();

$sqlTrend = "SELECT DATE_FORMAT(t.fecha, '%Y-%m') as mes, SUM(CASE WHEN d.clasificacion_id = 2 THEN t.monto ELSE 0 END) as gastos, SUM(CASE WHEN o.clasificacion_id = 3 THEN t.monto ELSE 0 END) as ingresos FROM transacciones t LEFT JOIN cuentas d ON t.cuenta_destino_id = d.id LEFT JOIN cuentas o ON t.cuenta_origen_id = o.id WHERE t.usuario_id = ? AND t.fecha >= DATE_SUB(LAST_DAY(CURRENT_DATE()), INTERVAL 6 MONTH) GROUP BY mes ORDER BY mes ASC";
$stmtT = $pdo->prepare($sqlTrend);
$stmtT->execute([$uid]);
$trend_data = $stmtT->fetchAll();

// Dona de Categorías y Top 5 Dinámicos con el Filtro
$sqlCat = "SELECT d.nombre, d.color, SUM(t.monto) as total FROM transacciones t JOIN cuentas d ON t.cuenta_destino_id = d.id WHERE d.clasificacion_id = 2 AND t.usuario_id = ? AND $where_clause GROUP BY d.id ORDER BY total DESC LIMIT 7";
$stmtCat = $pdo->prepare($sqlCat);
$stmtCat->execute([$uid]);
$cat_data = $stmtCat->fetchAll();

$sqlTop = "SELECT t.descripcion, t.monto, d.nombre as categoria, d.icono, d.color, t.fecha FROM transacciones t JOIN cuentas d ON t.cuenta_destino_id = d.id WHERE d.clasificacion_id = 2 AND t.usuario_id = ? AND $where_clause ORDER BY t.monto DESC LIMIT 5";
$stmtTop = $pdo->prepare($sqlTop);
$stmtTop->execute([$uid]);
$top_gastos = $stmtTop->fetchAll();

$nombres_meses = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
$labels_barras = []; $data_ingresos_barras = []; $data_gastos_barras = [];
foreach ($trend_data as $td) {
    $partes = explode('-', $td['mes']);
    $labels_barras[] = $nombres_meses[$partes[1]];
    $data_gastos_barras[] = $td['gastos'];
    $data_ingresos_barras[] = $td['ingresos'];
}
$labels_deuda = []; $data_capital_barras = []; $data_interes_barras = [];
foreach ($trend_deuda_data as $tdd) {
    $partes = explode('-', $tdd['mes']);
    $labels_deuda[] = $nombres_meses[$partes[1]];
    $data_capital_barras[] = $tdd['capital'];
    $data_interes_barras[] = $tdd['interes'];
}

// Mapa de colores exactos para Chart.js
$tailwind_to_hex = ['slate' => '#94a3b8', 'red' => '#ef4444', 'orange' => '#f97316', 'yellow' => '#eab308', 'emerald' => '#10b981', 'blue' => '#3b82f6', 'indigo' => '#6366f1', 'fuchsia' => '#d946ef', 'teal' => '#14b8a6', 'amber' => '#f59e0b', 'sky' => '#0ea5e9', 'rose' => '#f43f5e'];

$labels_dona = []; $data_dona = []; $colores_dona = [];
foreach ($cat_data as $cd) {
    $labels_dona[] = $cd['nombre'];
    $data_dona[] = $cd['total'];
    $colores_dona[] = $tailwind_to_hex[$cd['color'] ?? 'slate'] ?? '#94a3b8';
}

$stmtUser = $pdo->prepare("SELECT nombre, foto_perfil FROM usuarios WHERE id = ?");
$stmtUser->execute([$uid]);
$user = $stmtUser->fetch();
$primer_nombre = explode(' ', trim($user['nombre'] ?? 'Usuario'))[0];
$inicial = strtoupper(substr($primer_nombre, 0, 1));
$foto_perfil = $user['foto_perfil'] ?? null;

// --- ESTADÍSTICAS DE RETOS DE AHORRO ---
$ahorro_total = 0; $meta_global = 0; $retos_activos = 0;
try {
    $sql_resumen_retos = "
        SELECT SUM(monto_objetivo) as meta_total, 
               SUM(CASE WHEN cuenta_id IS NOT NULL THEN c.balance ELSE r.monto_actual END) as ahorro_total,
               COUNT(*) as total_retos
        FROM retos_ahorro r LEFT JOIN cuentas c ON r.cuenta_id = c.id
        WHERE r.usuario_id = ?
    ";
    $stmtRR = $pdo->prepare($sql_resumen_retos);
    $stmtRR->execute([$uid]);
    $resumen_retos = $stmtRR->fetch();
    $ahorro_total = $resumen_retos['ahorro_total'] ?? 0;
    $meta_global = $resumen_retos['meta_total'] ?? 0;
    $retos_activos = $resumen_retos['total_retos'] ?? 0;
} catch (PDOException $e) {}

$porcentaje_ahorro_global = ($meta_global > 0) ? ($ahorro_total / $meta_global) * 100 : 0;
$porcentaje_ahorro_global = min(100, max(0, $porcentaje_ahorro_global));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .glass-panel { background: rgba(10, 17, 34, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .progress-glow { filter: drop-shadow(0 0 8px currentColor); }
    </style>
</head>
<body class="bg-[#040812] text-slate-200 font-sans h-screen flex overflow-hidden">

    <div class="hidden bg-slate-500/10 bg-slate-500/20 text-slate-400 border-slate-500 bg-red-500/10 bg-red-500/20 text-red-400 border-red-500 bg-orange-500/10 bg-orange-500/20 text-orange-400 border-orange-500 bg-emerald-500/10 bg-emerald-500/20 text-emerald-400 border-emerald-500 bg-blue-500/10 bg-blue-500/20 text-blue-400 border-blue-500 bg-indigo-500/10 bg-indigo-500/20 text-indigo-400 border-indigo-500 bg-fuchsia-500/10 bg-fuchsia-500/20 text-fuchsia-400 border-fuchsia-500 bg-yellow-500/10 bg-yellow-500/20 text-yellow-400 border-yellow-500"></div>

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-[#00173d] via-[#040812] to-[#020409] hide-scrollbar">

        <header class="py-6 md:py-8 px-6 md:px-10 flex flex-col md:flex-row items-start md:items-end justify-between shrink-0 border-b border-slate-800 gap-5 mt-4 md:mt-0">
            <div>
                <h1 class="text-2xl font-semibold text-white">Radiografía Financiera</h1>
                <p class="text-sm text-blue-400/80 mt-1">Los números no mienten.</p>
            </div>

            <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
                <div class="flex bg-[#0a1122] rounded-xl p-1 border border-slate-800 shadow-inner w-full md:w-auto">
                    <button onclick="switchTabStats('general')" id="tab_general" class="flex-1 md:flex-none px-6 py-2 rounded-lg text-sm font-bold bg-blue-600 text-white shadow-sm transition-all flex items-center justify-center gap-2"><i class="ph ph-chart-polar"></i> General</button>
                    <button onclick="switchTabStats('deudas')" id="tab_deudas" class="flex-1 md:flex-none px-6 py-2 rounded-lg text-sm font-bold text-slate-400 hover:text-white transition-all flex items-center justify-center gap-2"><i class="ph ph-sword"></i> Deudas</button>
                </div>

                <form method="GET" id="formPeriodo" class="relative w-full md:w-auto">
                    <select name="periodo" onchange="document.getElementById('formPeriodo').submit()" class="w-full md:w-auto bg-blue-900/20 border border-blue-500/50 text-blue-300 font-bold text-sm py-2.5 pl-4 pr-10 rounded-xl appearance-none cursor-pointer hover:bg-blue-900/40 hover:text-white focus:outline-none transition-colors shadow-inner">
                        <option value="hoy" <?= $periodo == 'hoy' ? 'selected' : '' ?>>📅 Hoy</option>
                        <option value="ayer" <?= $periodo == 'ayer' ? 'selected' : '' ?>>📅 Ayer</option>
                        <option value="este_mes" <?= $periodo == 'este_mes' ? 'selected' : '' ?>>🗓️ Este Mes</option>
                        <option value="mes_pasado" <?= $periodo == 'mes_pasado' ? 'selected' : '' ?>>🗓️ Mes Pasado</option>
                        <option value="este_anio" <?= $periodo == 'este_anio' ? 'selected' : '' ?>>📅 Este Año</option>
                        <option value="anio_pasado" <?= $periodo == 'anio_pasado' ? 'selected' : '' ?>>📅 Año Pasado</option>
                        <option value="todo" <?= $periodo == 'todo' ? 'selected' : '' ?>>🌎 Todo el Historial</option>
                    </select>
                    <i class="ph ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none"></i>
                </form>
            </div>
        </header>
 
        <div class="px-6 md:px-10 py-8">

            <?php if ($mensaje): ?>
                <div class="mb-8 p-4 rounded-xl flex items-center gap-3 border shadow-lg <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-200' : 'bg-red-900/40 border-red-500/50 text-red-200' ?>">
                    <i class="ph <?= $tipo_mensaje === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-2xl"></i><span><?= $mensaje ?></span>
                </div>
            <?php endif; ?>

            <div class="mb-8 glass-panel p-6 rounded-[2rem] border-t border-fuchsia-500/30 bg-gradient-to-r from-fuchsia-900/20 to-transparent flex flex-col md:flex-row items-center justify-between gap-6 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-fuchsia-500/10 rounded-full blur-3xl pointer-events-none"></div>
                
                <div class="flex items-center gap-4 w-full md:w-auto relative z-10">
                    <div class="w-14 h-14 rounded-2xl bg-fuchsia-500/10 flex items-center justify-center text-fuchsia-400 border border-fuchsia-500/30 shadow-inner shrink-0">
                        <i class="ph ph-piggy-bank text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-[11px] font-bold text-slate-300 uppercase tracking-widest">Total Ahorrado en Retos</h3>
                        <p class="text-3xl font-bold text-white font-mono mt-1">RD$ <?= number_format($ahorro_total, 2) ?></p>
                    </div>
                </div>

                <div class="w-full md:w-1/3 flex flex-col gap-2 relative z-10">
                    <div class="flex justify-between items-end">
                        <span class="text-xs font-bold text-slate-400 uppercase">Progreso Global (<?= $retos_activos ?> retos)</span>
                        <span class="text-sm font-bold text-fuchsia-400 font-mono"><?= number_format($porcentaje_ahorro_global, 1) ?>%</span>
                    </div>
                    <div class="w-full h-3 bg-[#040812] border border-slate-800 rounded-full overflow-hidden shadow-inner">
                        <div class="h-full bg-fuchsia-500 transition-all duration-1000 progress-glow relative" style="width: <?= $porcentaje_ahorro_global ?>%">
                            <div class="absolute inset-0 bg-white/20 w-full h-full" style="background-image: linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent); background-size: 1rem 1rem;"></div>
                        </div>
                    </div>
                    <p class="text-right text-[10px] text-slate-500 uppercase font-bold mt-1">Meta Global: RD$ <?= number_format($meta_global, 2) ?></p>
                </div>
            </div>

            <div id="panel_general" class="space-y-8 block">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="glass-panel p-6 rounded-3xl border-t border-emerald-500/30 flex items-center gap-5">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20"><i class="ph ph-trend-up text-3xl"></i></div>
                        <div>
                            <p class="text-[11px] text-slate-400 uppercase tracking-widest font-bold">Ingresos (<?= $titulo_periodo ?>)</p>
                            <p class="text-2xl font-bold text-white font-mono mt-1">RD$ <?= number_format($ingresos_periodo, 0) ?></p>
                        </div>
                    </div>
                    <div class="glass-panel p-6 rounded-3xl border-t border-red-500/30 flex items-center gap-5">
                        <div class="w-14 h-14 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-400 border border-red-500/20"><i class="ph ph-trend-down text-3xl"></i></div>
                        <div>
                            <p class="text-[11px] text-slate-400 uppercase tracking-widest font-bold">Gastos (<?= $titulo_periodo ?>)</p>
                            <p class="text-2xl font-bold text-white font-mono mt-1">RD$ <?= number_format($gastos_periodo, 0) ?></p>
                        </div>
                    </div>
                    <div class="glass-panel p-6 rounded-3xl border-t <?= $balance_neto >= 0 ? 'border-blue-500/30' : 'border-orange-500/30' ?> flex items-center justify-between">
                        <div>
                            <p class="text-[11px] text-slate-400 uppercase tracking-widest font-bold">Flujo Neto</p>
                            <p class="text-2xl font-bold <?= $balance_neto >= 0 ? 'text-blue-400' : 'text-orange-400' ?> font-mono mt-1"><?= $balance_neto >= 0 ? '+' : '' ?>RD$ <?= number_format($balance_neto, 0) ?></p>
                        </div>
                        <div class="text-right"><span class="inline-block px-3 py-1 rounded-full <?= $balance_neto >= 0 ? 'bg-blue-500/10 text-blue-400' : 'bg-orange-500/10 text-orange-400' ?> text-xs font-bold"><?= number_format($porcentaje_ahorro, 1) ?>% Ahorro</span></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <div class="lg:col-span-7 glass-panel p-8 rounded-[2.5rem] border-t border-slate-700/50 shadow-2xl relative overflow-hidden">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2"><i class="ph ph-chart-bar text-blue-400"></i> Tendencia de 6 Meses</h3>
                        <div class="h-[300px] w-full relative z-10"><canvas id="barChart"></canvas></div>
                    </div>
                    <div class="lg:col-span-5 glass-panel p-8 rounded-[2.5rem] border-t border-slate-700/50 shadow-2xl">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2"><i class="ph ph-chart-pie-slice text-fuchsia-400"></i> Gastos (<?= $titulo_periodo ?>)</h3>
                        <div class="h-[250px] w-full flex justify-center items-center relative">
                            <canvas id="doughnutChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-2">
                                <span class="text-xs text-slate-500 font-bold uppercase">Total</span>
                                <span class="text-lg font-bold text-white font-mono">RD$ <?= number_format($gastos_periodo, 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2"><i class="ph ph-warning-octagon text-orange-400"></i> Los Hoyos Negros (Top 5 - <?= $titulo_periodo ?>)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if(empty($top_gastos)): ?>
                            <p class="text-slate-500 italic col-span-full">No hay gastos registrados en este periodo.</p>
                        <?php else: foreach ($top_gastos as $tg):
                            $cCol = $tg['color'] ?? 'slate';
                            $cIco = $tg['icono'] ?? 'ph-tag';
                        ?>
                            <div class="glass-panel p-4 rounded-2xl flex items-center justify-between border border-transparent">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 text-<?= $cCol ?>-400 flex items-center justify-center"><i class="ph <?= $cIco ?> text-xl"></i></div>
                                    <div class="overflow-hidden">
                                        <p class="text-white font-bold text-sm truncate" title="<?= htmlspecialchars($tg['descripcion']) ?>"><?= htmlspecialchars($tg['descripcion']) ?></p>
                                        <p class="text-[10px] text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($tg['categoria']) ?></p>
                                    </div>
                                </div>
                                <span class="text-red-400 font-mono font-bold shrink-0 pl-2">-RD$ <?= number_format($tg['monto'], 0) ?></span>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="glass-panel p-8 rounded-[2.5rem] border-t border-indigo-500/30 bg-gradient-to-br from-indigo-900/10 to-transparent mt-8">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                        <div>
                            <h3 class="text-lg font-bold text-white flex items-center gap-2 mb-2"><i class="ph ph-database text-indigo-400"></i> Respaldo y Migración (CSV)</h3>
                            <p class="text-sm text-slate-400">Descarga tu historial financiero para Excel o sube un respaldo anterior.</p>
                        </div>
                        <div class="flex gap-4 w-full md:w-auto">
                            <a href="estadisticas.php?exportar=csv" class="flex-1 md:flex-none text-center bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-indigo-900/30 flex items-center justify-center gap-2"><i class="ph ph-download-simple text-lg"></i> Descargar</a>
                            <form action="estadisticas.php" method="POST" enctype="multipart/form-data" class="flex-1 md:flex-none">
                                <input type="hidden" name="accion" value="importar_csv">
                                <label class="cursor-pointer bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white font-bold py-3 px-6 rounded-xl transition-all flex items-center justify-center gap-2"><i class="ph ph-upload-simple text-lg"></i> Subir<input type="file" name="archivo_csv" accept=".csv" class="hidden" onchange="if(confirm('¿Seguro que deseas inyectar estos datos?')) this.form.submit();"></label>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div id="panel_deudas" class="space-y-8 hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="glass-panel p-6 rounded-3xl border-t border-emerald-500/30">
                        <div class="flex items-center gap-3 mb-4"><i class="ph ph-money text-emerald-400 text-2xl"></i>
                            <h3 class="text-sm font-bold text-slate-300 uppercase tracking-widest">Esfuerzo (<?= $titulo_periodo ?>)</h3>
                        </div>
                        <p class="text-4xl font-bold text-white font-mono mb-4">RD$ <?= number_format($total_pagos_deuda, 2) ?></p>

                        <div class="w-full h-3 bg-slate-800 rounded-full overflow-hidden flex mb-2 shadow-inner">
                            <div class="h-full bg-emerald-500" style="width: <?= $porc_capital ?>%"></div>
                            <div class="h-full bg-orange-500" style="width: <?= $porc_interes ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs font-bold"><span class="text-emerald-400">Capital: <?= round($porc_capital) ?>%</span><span class="text-orange-400">Interés: <?= round($porc_interes) ?>%</span></div>
                    </div>

                    <div class="glass-panel p-6 rounded-3xl border-t border-red-500/30 flex flex-col justify-center relative overflow-hidden">
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="flex items-center gap-3 mb-2"><i class="ph ph-warning text-red-400 text-2xl"></i>
                            <h3 class="text-sm font-bold text-slate-300 uppercase tracking-widest">Deuda Viva Total</h3>
                        </div>
                        <p class="text-4xl font-bold text-red-400 font-mono">RD$ <?= number_format($total_deuda_viva, 2) ?></p>
                        <p class="text-xs text-slate-500 mt-2 italic">Este es el monstruo que debes vencer.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <div class="lg:col-span-7 glass-panel p-8 rounded-[2.5rem] border-t border-slate-700/50 shadow-2xl relative overflow-hidden">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2"><i class="ph ph-chart-line-up text-emerald-400"></i> Historial de Pagos (6 Meses)</h3>
                        <div class="h-[300px] w-full relative z-10">
                            <?php if (empty($labels_deuda)): ?>
                                <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-500"><i class="ph ph-empty text-4xl mb-2"></i>
                                    <p>Aún no has registrado pagos a deudas.</p>
                                </div>
                            <?php else: ?>
                                <canvas id="debtChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="lg:col-span-5 glass-panel p-6 rounded-[2.5rem] border-t border-slate-700/50 shadow-xl">
                        <h4 class="text-sm font-bold text-white mb-6 flex items-center gap-2"><i class="ph ph-clock-counter-clockwise text-blue-400"></i> Últimos Abonos</h4>
                        <div class="space-y-3">
                            <?php if (empty($historial_deudas)): ?>
                                <p class="text-slate-500 text-sm text-center py-4">Sin registros recientes.</p>
                                <?php else: foreach ($historial_deudas as $hd): ?>
                                    <div class="flex items-center justify-between p-3 bg-[#0a1122]/50 rounded-2xl border border-slate-700/50 hover:bg-[#0a1122] transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl <?= $hd['tipo_abono'] == 'capital' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-orange-500/10 text-orange-400 border-orange-500/30' ?> flex items-center justify-center border shadow-inner">
                                                <i class="ph <?= $hd['tipo_abono'] == 'capital' ? 'ph-trend-down' : 'ph-fire' ?> text-lg"></i>
                                            </div>
                                            <div>
                                                <p class="text-white font-bold text-sm"><?= htmlspecialchars($hd['nombre']) ?></p>
                                                <p class="text-[10px] text-slate-500 uppercase"><?= date('d M Y', strtotime($hd['fecha'])) ?> • Pago a <?= $hd['tipo_abono'] ?></p>
                                            </div>
                                        </div>
                                        <span class="font-mono font-bold <?= $hd['tipo_abono'] == 'capital' ? 'text-emerald-400' : 'text-orange-400' ?>">RD$ <?= number_format($hd['monto'], 2) ?></span>
                                    </div>
                            <?php endforeach;
                            endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = "'Inter', 'sans-serif'";

        function switchTabStats(tab) {
            const btnG = document.getElementById('tab_general');
            const btnD = document.getElementById('tab_deudas');
            const panelG = document.getElementById('panel_general');
            const panelD = document.getElementById('panel_deudas');

            if (tab === 'general') {
                btnG.className = 'flex-1 md:flex-none px-6 py-2 rounded-lg text-sm font-bold bg-blue-600 text-white shadow-sm transition-all flex items-center justify-center gap-2';
                btnD.className = 'flex-1 md:flex-none px-6 py-2 rounded-lg text-sm font-bold text-slate-400 hover:text-white transition-all flex items-center justify-center gap-2 bg-transparent';
                panelG.classList.remove('hidden');
                panelD.classList.add('hidden');
            } else {
                btnD.className = 'flex-1 md:flex-none px-6 py-2 rounded-lg text-sm font-bold bg-blue-600 text-white shadow-sm transition-all flex items-center justify-center gap-2';
                btnG.className = 'flex-1 md:flex-none px-6 py-2 rounded-lg text-sm font-bold text-slate-400 hover:text-white transition-all flex items-center justify-center gap-2 bg-transparent';
                panelD.classList.remove('hidden');
                panelG.classList.add('hidden');
            }
        }

        <?php if (!empty($labels_barras)): ?>
            new Chart(document.getElementById('barChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($labels_barras) ?>,
                    datasets: [{
                        label: 'Ingresos',
                        data: <?= json_encode($data_ingresos_barras) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }, {
                        label: 'Gastos',
                        data: <?= json_encode($data_gastos_barras) ?>,
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', align: 'end' } },
                    scales: { y: { beginAtZero: true, grid: { color: 'rgba(51, 65, 85, 0.2)' } }, x: { grid: { display: false } } }
                }
            });
        <?php endif; ?>

        <?php if (!empty($data_dona)): ?>
            new Chart(document.getElementById('doughnutChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($labels_dona) ?>,
                    datasets: [{
                        data: <?= json_encode($data_dona) ?>,
                        backgroundColor: <?= json_encode($colores_dona) ?>,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: function(context) { return ' RD$ ' + context.parsed.toLocaleString(); } } }
                    }
                }
            });
        <?php endif; ?>

        <?php if (!empty($labels_deuda)): ?>
            new Chart(document.getElementById('debtChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($labels_deuda) ?>,
                    datasets: [{
                        label: 'Pago a Capital',
                        data: <?= json_encode($data_capital_barras) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }, {
                        label: 'Pago de Interés',
                        data: <?= json_encode($data_interes_barras) ?>,
                        backgroundColor: '#f97316',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', align: 'end' } },
                    scales: { y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(51, 65, 85, 0.2)' } }, x: { stacked: true, grid: { display: false } } }
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>