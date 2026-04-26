<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit; }
require_once 'config/database.php';

$mensaje = ''; $tipo_mensaje = '';
$uid = $_SESSION['usuario_id']; // EL IDENTIFICADOR MAESTRO DEL USUARIO

// =========================================================================================
// 🚀 MAGIA: INYECTOR DEL "KIT DE INICIO" PARA USUARIOS NUEVOS
// Si el usuario no tiene ninguna cuenta o categoría, le creamos las más importantes.
// =========================================================================================
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM cuentas WHERE usuario_id = ?");
$stmtCheck->execute([$uid]);
if ($stmtCheck->fetchColumn() == 0) {
    try {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM cuentas LIKE 'icono'");
        $tiene_iconos = $stmtCol->fetch() !== false;

        if ($tiene_iconos) {
            $sqlSeed = "INSERT INTO cuentas (usuario_id, clasificacion_id, nombre, balance, icono, color) VALUES 
                (?, 1, 'Efectivo', 0, 'ph-money', 'emerald'),
                (?, 1, 'Cuenta Principal', 0, 'ph-bank', 'blue'),
                (?, 2, 'Comida y Supermercado', 0, 'ph-shopping-cart', 'orange'),
                (?, 2, 'Transporte y Gasolina', 0, 'ph-car', 'blue'),
                (?, 2, 'Servicios (Luz, Agua, Internet)', 0, 'ph-plug', 'yellow'),
                (?, 2, 'Salud y Farmacia', 0, 'ph-pill', 'emerald'),
                (?, 2, 'Entretenimiento y Salidas', 0, 'ph-popcorn', 'fuchsia'),
                (?, 3, 'Salario / Sueldo', 0, 'ph-briefcase', 'emerald'),
                (?, 3, 'Ingresos Extras', 0, 'ph-trend-up', 'blue')";
            $stmtSeed = $pdo->prepare($sqlSeed);
            $stmtSeed->execute([$uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid]);
        } else {
            // Fallback por si la tabla aún no tiene los campos de diseño
            $sqlSeed = "INSERT INTO cuentas (usuario_id, clasificacion_id, nombre, balance) VALUES 
                (?, 1, 'Efectivo', 0), (?, 1, 'Cuenta Principal', 0),
                (?, 2, 'Comida y Supermercado', 0), (?, 2, 'Transporte y Gasolina', 0),
                (?, 2, 'Servicios (Luz, Agua, Internet)', 0), (?, 2, 'Salud y Farmacia', 0),
                (?, 2, 'Entretenimiento y Salidas', 0), (?, 3, 'Salario / Sueldo', 0),
                (?, 3, 'Ingresos Extras', 0)";
            $stmtSeed = $pdo->prepare($sqlSeed);
            $stmtSeed->execute([$uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid]);
        }
    } catch (Exception $e) {
        // Ignorar errores silenciosamente si algo falla en la siembra inicial
    }
}
// =========================================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        $pdo->beginTransaction();

        // REGISTRO RÁPIDO
        if ($accion === 'registro_rapido') {
            $tipo = $_POST['tipo'];
            $monto = (float)$_POST['monto'];
            $descripcion = trim($_POST['descripcion']);
            $fecha = $_POST['fecha'];
            $cuenta_bancaria = (int)$_POST['cuenta_bancaria'];
            
            $categoria_val = ($tipo === 'gasto') ? ($_POST['categoria_gasto'] ?? '') : ($_POST['categoria_ingreso'] ?? '');

            // 1. CREACIÓN DINÁMICA DE LA CATEGORÍA AISLADA AL USUARIO
            if ($categoria_val === 'nueva') {
                $nombre_nueva_cat = trim($_POST['nueva_cat_nombre']);
                $padre_id = !empty($_POST['nueva_cat_padre']) ? (int)$_POST['nueva_cat_padre'] : null;
                $clasificacion = ($tipo === 'gasto') ? 2 : 3;
                
                $cat_icono = $_POST['nueva_cat_icono'] ?? 'ph-tag';
                $cat_color = $_POST['nueva_cat_color'] ?? 'slate';

                if ($padre_id) {
                    $stmtPadre = $pdo->prepare("SELECT icono, color FROM cuentas WHERE id = ? AND usuario_id = ?");
                    $stmtPadre->execute([$padre_id, $uid]);
                    $padre = $stmtPadre->fetch();
                    if ($padre) {
                        $cat_icono = $padre['icono'];
                        $cat_color = $padre['color'];
                    }
                }
                
                $stmtNueva = $pdo->prepare("INSERT INTO cuentas (usuario_id, clasificacion_id, nombre, balance, parent_id, icono, color) VALUES (?, ?, ?, 0, ?, ?, ?)");
                $stmtNueva->execute([$uid, $clasificacion, $nombre_nueva_cat, $padre_id, $cat_icono, $cat_color]);
                $categoria_id = (int)$pdo->lastInsertId();
            } else {
                $categoria_id = (int)$categoria_val;
            }

            // 2. REGISTRO DEL MOVIMIENTO AISLADO AL USUARIO
            if ($monto > 0 && !empty($descripcion) && $cuenta_bancaria > 0 && $categoria_id > 0) {
                if ($tipo === 'gasto') {
                    $pdo->prepare("UPDATE cuentas SET balance = balance - ? WHERE id = ? AND usuario_id = ?")->execute([$monto, $cuenta_bancaria, $uid]);
                    $pdo->prepare("INSERT INTO transacciones (usuario_id, cuenta_origen_id, cuenta_destino_id, monto, fecha, descripcion) VALUES (?, ?, ?, ?, ?, ?)")->execute([$uid, $cuenta_bancaria, $categoria_id, $monto, $fecha, $descripcion]);
                } else {
                    $pdo->prepare("UPDATE cuentas SET balance = balance + ? WHERE id = ? AND usuario_id = ?")->execute([$monto, $cuenta_bancaria, $uid]);
                    $pdo->prepare("INSERT INTO transacciones (usuario_id, cuenta_origen_id, cuenta_destino_id, monto, fecha, descripcion) VALUES (?, ?, ?, ?, ?, ?)")->execute([$uid, $categoria_id, $cuenta_bancaria, $monto, $fecha, $descripcion]);
                }
                $mensaje = 'Movimiento registrado al instante.'; $tipo_mensaje = 'success';
            } else {
                throw new Exception('Faltan datos importantes. Revisa la cantidad, banco y categoría.');
            }
        }
        // EDITAR TRANSACCIÓN SEGURA
        elseif ($accion === 'editar_transaccion') {
            $tx_id = (int)$_POST['tx_id'];
            $nuevo_monto = (float)$_POST['monto'];
            $nueva_desc = trim($_POST['descripcion']);
            $nueva_fecha = $_POST['fecha'];
            $tipo = $_POST['tipo_tx']; 
            $nueva_cuenta = (int)$_POST['cuenta_bancaria'];
            $nueva_categoria = (int)$_POST['categoria'];

            $stmtTx = $pdo->prepare("SELECT monto, cuenta_origen_id, cuenta_destino_id FROM transacciones WHERE id = ? AND usuario_id = ?");
            $stmtTx->execute([$tx_id, $uid]);
            $tx_vieja = $stmtTx->fetch();

            if ($tx_vieja && $nuevo_monto > 0 && $nueva_cuenta > 0 && $nueva_categoria > 0) {
                $pdo->prepare("UPDATE cuentas SET balance = balance + ? WHERE id = ? AND usuario_id = ?")->execute([$tx_vieja['monto'], $tx_vieja['cuenta_origen_id'], $uid]);
                $pdo->prepare("UPDATE cuentas SET balance = balance - ? WHERE id = ? AND usuario_id = ?")->execute([$tx_vieja['monto'], $tx_vieja['cuenta_destino_id'], $uid]);
                if ($tipo === 'gasto') { $nuevo_origen = $nueva_cuenta; $nuevo_destino = $nueva_categoria; } 
                else { $nuevo_origen = $nueva_categoria; $nuevo_destino = $nueva_cuenta; }
                $pdo->prepare("UPDATE cuentas SET balance = balance - ? WHERE id = ? AND usuario_id = ?")->execute([$nuevo_monto, $nuevo_origen, $uid]);
                $pdo->prepare("UPDATE cuentas SET balance = balance + ? WHERE id = ? AND usuario_id = ?")->execute([$nuevo_monto, $nuevo_destino, $uid]);
                
                $stmtUpdate = $pdo->prepare("UPDATE transacciones SET cuenta_origen_id = ?, cuenta_destino_id = ?, monto = ?, descripcion = ?, fecha = ? WHERE id = ? AND usuario_id = ?");
                $stmtUpdate->execute([$nuevo_origen, $nuevo_destino, $nuevo_monto, $nueva_desc, $nueva_fecha, $tx_id, $uid]);
                $mensaje = 'Transacción movida y actualizada.'; $tipo_mensaje = 'success';
            }
        }
        // ELIMINAR TRANSACCIÓN SEGURA
        elseif ($accion === 'eliminar_transaccion') {
            $tx_id = (int)$_POST['tx_id'];
            $stmtTx = $pdo->prepare("SELECT monto, cuenta_origen_id, cuenta_destino_id FROM transacciones WHERE id = ? AND usuario_id = ?");
            $stmtTx->execute([$tx_id, $uid]);
            $tx = $stmtTx->fetch();
            if ($tx) {
                $pdo->prepare("UPDATE cuentas SET balance = balance + ? WHERE id = ? AND usuario_id = ?")->execute([$tx['monto'], $tx['cuenta_origen_id'], $uid]);
                $pdo->prepare("UPDATE cuentas SET balance = balance - ? WHERE id = ? AND usuario_id = ?")->execute([$tx['monto'], $tx['cuenta_destino_id'], $uid]);
                $pdo->prepare("DELETE FROM transacciones WHERE id = ? AND usuario_id = ?")->execute([$tx_id, $uid]);
                $mensaje = 'Movimiento eliminado. El balance ha sido restaurado.'; $tipo_mensaje = 'success';
            }
        }

        $pdo->commit();
    } catch (Exception $e) { if($pdo->inTransaction()) $pdo->rollBack(); if(!$mensaje) { $mensaje = 'Error: ' . $e->getMessage(); $tipo_mensaje = 'error'; } }
}

// --- EXTRACCIÓN DE DATOS (TODO FILTRADO POR $uid) ---
$stmtBancos = $pdo->prepare("SELECT * FROM cuentas WHERE clasificacion_id = 1 AND usuario_id = ? ORDER BY balance DESC");
$stmtBancos->execute([$uid]);
$bancos = $stmtBancos->fetchAll();
$balance_total = array_sum(array_column($bancos, 'balance'));

function obtenerArbolCategorias($pdo, $clasificacion_id, $uid) {
    $stmt = $pdo->prepare("SELECT id, nombre, parent_id, icono, color FROM cuentas WHERE clasificacion_id = ? AND usuario_id = ? ORDER BY nombre ASC");
    $stmt->execute([$clasificacion_id, $uid]);
    $todas = $stmt->fetchAll();
    
    $padres = array_filter($todas, fn($c) => is_null($c['parent_id']));
    $arbol = [];
    foreach($padres as $p) {
        $subs = array_filter($todas, fn($c) => $c['parent_id'] == $p['id']);
        $arbol[] = ['padre' => $p, 'subs' => $subs];
    }
    return $arbol;
}
$arbol_gastos = obtenerArbolCategorias($pdo, 2, $uid);
$arbol_ingresos = obtenerArbolCategorias($pdo, 3, $uid);

$sqlTransacciones = "SELECT t.id, t.fecha, t.descripcion, t.monto, t.creado_en, 
                     t.cuenta_origen_id, t.cuenta_destino_id,
                     c.nombre AS cuenta_destino, o.nombre AS cuenta_origen, cl.id as clasificacion_destino_id,
                     COALESCE(pc.icono, c.icono) AS c_icono, COALESCE(pc.color, c.color) AS c_color,
                     COALESCE(po.icono, o.icono) AS o_icono, COALESCE(po.color, o.color) AS o_color
                     FROM transacciones t 
                     JOIN cuentas c ON t.cuenta_destino_id = c.id LEFT JOIN cuentas pc ON c.parent_id = pc.id
                     JOIN cuentas o ON t.cuenta_origen_id = o.id LEFT JOIN cuentas po ON o.parent_id = po.id
                     JOIN clasificaciones cl ON c.clasificacion_id = cl.id
                     WHERE t.usuario_id = ?
                     ORDER BY t.fecha DESC, t.creado_en DESC LIMIT 50";
$stmtTx = $pdo->prepare($sqlTransacciones);
$stmtTx->execute([$uid]);
$transacciones_crudas = $stmtTx->fetchAll();

$meses = ['January'=>'Enero', 'February'=>'Febrero', 'March'=>'Marzo', 'April'=>'Abril', 'May'=>'Mayo', 'June'=>'Junio', 'July'=>'Julio', 'August'=>'Agosto', 'September'=>'Septiembre', 'October'=>'Octubre', 'November'=>'Noviembre', 'December'=>'Diciembre'];
$transacciones_agrupadas = [];
foreach ($transacciones_crudas as $tx) { $fecha_es = date('d', strtotime($tx['fecha'])) . " de " . $meses[date('F', strtotime($tx['fecha']))]; $transacciones_agrupadas[$fecha_es][] = $tx; }

function obtenerLogoBanco($nombre) {
    $n = mb_strtolower($nombre); $domain = '';
    if (strpos($n, 'bhd') !== false) $domain = 'bhd.com.do';
    elseif (strpos($n, 'banreservas') !== false || strpos($n, 'reservas') !== false) $domain = 'banreservas.com';
    elseif (strpos($n, 'popular') !== false) $domain = 'popularenlinea.com';
    elseif (strpos($n, 'scotia') !== false) $domain = 'scotiabank.com.do';
    elseif (strpos($n, 'santa cruz') !== false) $domain = 'bsc.com.do';
    elseif (strpos($n, 'qik') !== false) $domain = 'qik.com.do';
    elseif (strpos($n, 'apap') !== false) $domain = 'apap.com.do';
    elseif (strpos($n, 'paypal') !== false) $domain = 'paypal.com';
    return $domain ? "https://www.google.com/s2/favicons?domain=" . $domain . "&sz=128" : false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Maneja Tu Dinero | Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; } 
        .glass-panel { background: rgba(10, 17, 34, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .card-selected { border-color: #ffffff !important; background-color: rgba(255,255,255,0.08) !important; box-shadow: 0 0 15px rgba(255,255,255,0.1); }
        .cat-selected { box-shadow: 0 0 0 2px #ffffff; transform: scale(1.05); }
    </style>
</head>
<body class="bg-[#040812] text-slate-200 font-sans h-screen flex overflow-hidden selection:bg-blue-500/30">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-[#0a1122] via-[#040812] to-[#020409]">
        <header class="py-6 md:h-28 px-6 md:px-10 flex flex-row items-center justify-between z-10 shrink-0 mt-2 md:mt-0">
            <div>
                <h1 class="text-2xl md:text-3xl font-semibold text-white">¡Dímelo, <?= htmlspecialchars($primer_nombre_sb ?? 'Jefe') ?>! 🇩🇴</h1>
                <p class="text-xs md:text-sm text-slate-400 mt-1">Tu resumen financiero en tiempo real.</p>
            </div>
            
            <button onclick="abrirModalRegistro()" class="hidden md:inline-flex relative items-center justify-center gap-2 px-8 py-3.5 text-base font-bold text-white transition-all duration-200 bg-gradient-to-r from-[#CE1126] to-[#ff1e38] border border-transparent rounded-full shadow-[0_0_25px_rgba(206,17,38,0.4)] hover:shadow-[0_0_35px_rgba(206,17,38,0.7)] hover:-translate-y-1 focus:outline-none overflow-hidden group">
                <span class="absolute w-0 h-0 transition-all duration-500 ease-out bg-white rounded-full group-hover:w-56 group-hover:h-56 opacity-10"></span>
                <i class="ph ph-plus-circle text-3xl group-hover:rotate-90 transition-transform duration-300"></i>
                <span class="tracking-widest uppercase">Registrar Ahora</span>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto px-6 md:px-10 pb-28 md:pb-12 hide-scrollbar">
            <?php if($mensaje): ?>
                <div class="mb-6 p-4 rounded-xl flex items-center gap-3 border shadow-lg <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-200' : 'bg-red-900/40 border-red-500/50 text-red-200' ?>">
                    <i class="ph <?= $tipo_mensaje === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-2xl"></i><span class="font-medium"><?= $mensaje ?></span>
                </div>
            <?php endif; ?>

            <div class="max-w-6xl mx-auto space-y-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 bg-gradient-to-br from-[#002D62] to-[#0a1122] border border-blue-800/50 rounded-[2rem] p-8 relative overflow-hidden shadow-2xl flex flex-col justify-center">
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#CE1126]/20 rounded-full blur-3xl pointer-events-none"></div> 
                        <div class="relative z-10">
                            <div class="flex items-center gap-2 mb-2"><i class="ph ph-wallet text-blue-300 text-xl"></i><h2 class="text-xs font-bold text-blue-200 uppercase tracking-widest">Balance Total</h2></div>
                            <p class="text-4xl xl:text-5xl font-bold text-white tracking-tight font-mono drop-shadow-md mt-2"><span class="text-blue-400/50 text-2xl">RD$</span> <?= number_format($balance_total, 2) ?></p>
                        </div>
                    </div>

                    <div class="lg:col-span-2 flex gap-4 overflow-x-auto hide-scrollbar snap-x py-2">
                        <?php foreach($bancos as $b): 
                            $logo = obtenerLogoBanco($b['nombre']); $bIco = $b['icono'] ?? 'ph-wallet'; $bCol = $b['color'] ?? 'blue';
                        ?>
                            <div class="glass-panel p-5 rounded-[2rem] min-w-[220px] snap-center flex flex-col justify-between border-t border-<?= $bCol ?>-500/30 hover:bg-[#0a1122]/80 transition-colors shrink-0">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 rounded-full overflow-hidden bg-[#040812] flex items-center justify-center border border-<?= $bCol ?>-500/30 shadow-inner shrink-0 p-1">
                                        <?php if($logo): ?> <img src="<?= $logo ?>" class="w-full h-full object-contain rounded-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="hidden w-full h-full bg-<?= $bCol ?>-500/20 flex items-center justify-center text-<?= $bCol ?>-400 rounded-full"><i class="ph <?= $bIco ?> text-xl"></i></div>
                                        <?php else: ?> <div class="w-full h-full bg-<?= $bCol ?>-500/20 flex items-center justify-center text-<?= $bCol ?>-400 rounded-full"><i class="ph <?= $bIco ?> text-xl"></i></div> <?php endif; ?>
                                    </div>
                                    <h4 class="text-white font-bold text-sm truncate w-full flex flex-col items-start">
                                        <?= htmlspecialchars($b['nombre']) ?>
                                        <?php if(!empty($b['numero_cuenta'])): ?><span class="text-slate-500 text-[9px] font-mono mt-0.5"><?= htmlspecialchars($b['numero_cuenta']) ?></span><?php endif; ?>
                                    </h4>
                                </div>
                                <div><p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Disponible</p><p class="text-xl font-bold text-blue-100 font-mono">RD$ <?= number_format($b['balance'], 2) ?></p></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <a href="cuentas.php" class="p-5 rounded-[2rem] min-w-[120px] snap-center flex flex-col items-center justify-center border border-dashed border-slate-700 hover:border-blue-500 hover:text-blue-400 text-slate-500 transition-all shrink-0"><i class="ph ph-plus-circle text-3xl mb-2"></i><span class="text-xs font-bold uppercase tracking-widest">Añadir</span></a>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-6"><h3 class="text-lg font-bold text-white flex items-center gap-2"><i class="ph ph-list-dashes text-slate-400"></i> Últimos Movimientos</h3></div>
                    <div class="glass-panel rounded-[2.5rem] p-4 md:p-8 shadow-xl">
                        <?php if (empty($transacciones_agrupadas)): ?>
                            <div class="text-center py-12"><p class="text-slate-400">Aún no hay movimientos.</p></div>
                        <?php else: ?>
                            <div class="space-y-8">
                                <?php foreach($transacciones_agrupadas as $fecha => $movimientos): ?>
                                    <div class="relative">
                                        <div class="sticky top-0 z-10 bg-[#040812]/90 backdrop-blur-md py-2 mb-4"><span class="inline-block bg-[#0a1122] border-l-2 border-[#CE1126] text-slate-300 text-xs font-bold px-4 py-1.5 rounded-r-full shadow-sm uppercase tracking-wider"><?= $fecha ?></span></div>
                                        <div class="space-y-2">
                                            <?php foreach($movimientos as $tx): 
                                                $es_ingreso = ($tx['clasificacion_destino_id'] == 3);
                                                $tipo_tx = $es_ingreso ? 'ingreso' : 'gasto';
                                                $id_banco = $es_ingreso ? $tx['cuenta_destino_id'] : $tx['cuenta_origen_id'];
                                                $id_categoria = $es_ingreso ? $tx['cuenta_origen_id'] : $tx['cuenta_destino_id'];
                                                
                                                $cIco = $es_ingreso ? ($tx['o_icono'] ?? 'ph-tag') : ($tx['c_icono'] ?? 'ph-tag');
                                                $cCol = $es_ingreso ? ($tx['o_color'] ?? 'emerald') : ($tx['c_color'] ?? 'red');
                                            ?>
                                            <div class="group flex flex-col md:flex-row md:items-center justify-between p-4 rounded-2xl bg-[#0a1122]/40 border border-transparent hover:bg-[#0a1122]/80 hover:border-slate-700 transition-all gap-4">
                                                <div class="flex items-center gap-4 overflow-hidden w-full">
                                                    <div class="w-12 h-12 shrink-0 rounded-[14px] flex items-center justify-center border border-<?= $cCol ?>-500/30 bg-<?= $cCol ?>-500/10 text-<?= $cCol ?>-400 shadow-inner"><i class="ph <?= $cIco ?> text-2xl"></i></div>
                                                    <div class="truncate flex-1">
                                                        <p class="text-sm font-bold text-slate-200 mb-0.5 truncate"><?= htmlspecialchars($tx['descripcion']) ?></p>
                                                        <div class="flex items-center gap-2 text-[10px] md:text-[11px] text-slate-500 font-medium overflow-hidden"><span class="bg-slate-800 px-2 py-0.5 rounded text-slate-300 truncate max-w-[100px]"><?= htmlspecialchars($tx['cuenta_origen']) ?></span><i class="ph ph-arrow-right shrink-0"></i><span class="truncate max-w-[100px]"><?= htmlspecialchars($tx['cuenta_destino']) ?></span></div>
                                                    </div>
                                                    <div class="text-right md:hidden shrink-0"><p class="text-base font-bold <?= $es_ingreso ? 'text-emerald-400' : 'text-slate-100' ?> font-mono"><?= $es_ingreso ? '+' : '-' ?>RD$ <?= number_format($tx['monto'], 0) ?></p></div>
                                                </div>

                                                <div class="hidden md:flex items-center gap-4 shrink-0 pl-4">
                                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <button onclick="abrirEditar(<?= $tx['id'] ?>, '<?= addslashes($tx['descripcion']) ?>', <?= $tx['monto'] ?>, '<?= date('Y-m-d', strtotime($tx['fecha'])) ?>', '<?= $tipo_tx ?>', <?= $id_banco ?>, <?= $id_categoria ?>)" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-blue-600 text-slate-400 hover:text-white flex items-center justify-center transition-colors" title="Mover / Editar"><i class="ph ph-pencil-simple"></i></button>
                                                        <form action="index.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta transacción? Se restaurará el balance.');"><input type="hidden" name="accion" value="eliminar_transaccion"><input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"><button type="submit" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-red-600 text-slate-400 hover:text-white flex items-center justify-center transition-colors"><i class="ph ph-trash"></i></button></form>
                                                    </div>
                                                    <div class="text-right w-[120px]"><p class="text-lg font-bold <?= $es_ingreso ? 'text-emerald-400' : 'text-slate-100' ?> font-mono"><?= $es_ingreso ? '+' : '-' ?>RD$ <?= number_format($tx['monto'], 2) ?></p></div>
                                                </div>

                                                <div class="flex gap-2 justify-end md:hidden pt-2 border-t border-slate-800/50 w-full">
                                                    <button onclick="abrirEditar(<?= $tx['id'] ?>, '<?= addslashes($tx['descripcion']) ?>', <?= $tx['monto'] ?>, '<?= date('Y-m-d', strtotime($tx['fecha'])) ?>', '<?= $tipo_tx ?>', <?= $id_banco ?>, <?= $id_categoria ?>)" class="text-[11px] bg-slate-800 text-slate-300 px-4 py-1.5 rounded hover:bg-blue-600 transition-colors font-bold"><i class="ph ph-pencil-simple"></i> Editar</button>
                                                    <form action="index.php" method="POST" onsubmit="return confirm('¿Eliminar?');"><input type="hidden" name="accion" value="eliminar_transaccion"><input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"><button type="submit" class="text-[11px] bg-red-900/30 text-red-400 px-4 py-1.5 rounded hover:bg-red-600 hover:text-white transition-colors font-bold"><i class="ph ph-trash"></i> Borrar</button></form>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="modal_registro" class="hidden fixed inset-0 z-[70] flex items-end md:items-center justify-center p-0 md:p-4 bg-[#040812]/80 backdrop-blur-md transition-all overflow-hidden">
        <div class="glass-panel w-full max-w-2xl h-auto max-h-[90vh] flex flex-col rounded-t-[2.5rem] md:rounded-[2.5rem] border-t border-blue-500/30 shadow-2xl relative bg-[#0a1122]/95 pb-safe">
            <div class="flex justify-between items-center p-6 border-b border-slate-800 shrink-0">
                <h3 class="text-xl font-bold text-white flex items-center gap-2"><i class="ph ph-lightning text-yellow-400"></i> Registro Rápido</h3>
                <button onclick="document.getElementById('modal_registro').classList.add('hidden')" class="text-slate-500 hover:text-white bg-slate-800 rounded-full w-8 h-8 flex items-center justify-center"><i class="ph ph-x text-lg"></i></button>
            </div>
            
            <div class="overflow-y-auto p-6 md:p-8 hide-scrollbar flex-1">
                <form action="index.php" method="POST" id="form_quick_record">
                    <input type="hidden" name="accion" value="registro_rapido">
                    <input type="hidden" name="tipo" id="modal_input_tipo" value="gasto">
                    <input type="hidden" name="cuenta_bancaria" id="hidden_cuenta" value="">
                    <input type="hidden" name="categoria_gasto" id="hidden_gasto" value="">
                    <input type="hidden" name="categoria_ingreso" id="hidden_ingreso" value="">

                    <input type="hidden" name="nueva_cat_nombre" id="nueva_cat_nombre">
                    <input type="hidden" name="nueva_cat_padre" id="nueva_cat_padre">
                    <input type="hidden" name="nueva_cat_icono" id="nueva_cat_icono">
                    <input type="hidden" name="nueva_cat_color" id="nueva_cat_color">

                    <div class="flex bg-[#040812] rounded-2xl p-1 mb-8 border border-slate-800 w-full max-w-xs mx-auto">
                        <button type="button" id="btn_gasto" class="flex-1 bg-white text-black font-bold py-2.5 rounded-xl text-sm transition-all shadow-sm">Gasto</button>
                        <button type="button" id="btn_ingreso" class="flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-sm transition-all hover:text-white bg-transparent">Ingreso</button>
                    </div>

                    <div class="text-center mb-8">
                        <div class="flex items-center justify-center">
                            <span class="text-4xl font-bold text-slate-600 mr-2">RD$</span>
                            <input type="number" name="monto" id="monto_input" step="0.01" placeholder="0.00" required class="bg-transparent border-none text-5xl font-bold text-white focus:outline-none focus:ring-0 placeholder-slate-700 w-[200px] p-0 text-left transition-colors duration-300">
                        </div>
                    </div>

                    <div class="mb-8">
                        <h3 class="text-slate-500 text-[11px] font-bold tracking-[0.15em] uppercase mb-3 pl-1">Cuenta de Origen</h3>
                        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2">
                            <?php foreach($bancos as $banco): 
                                $logo = obtenerLogoBanco($banco['nombre']); $bCol = $banco['color'] ?? 'blue'; $bIco = $banco['icono'] ?? 'ph-wallet';
                            ?>
                                <button type="button" class="btn-cuenta bg-[#040812] border border-slate-800 rounded-2xl p-3 flex flex-col items-center justify-center transition-all opacity-70 hover:opacity-100 hover:border-blue-500 focus:outline-none w-20 shrink-0" data-id="<?= $banco['id'] ?>">
                                    <div class="w-8 h-8 rounded-full bg-[#040812] flex items-center justify-center mb-2 overflow-hidden border border-<?= $bCol ?>-500/30 p-0.5 shadow-inner">
                                        <?php if($logo): ?> <img src="<?= $logo ?>" class="w-full h-full object-contain rounded-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"><div class="hidden w-full h-full bg-<?= $bCol ?>-500/20 flex items-center justify-center text-<?= $bCol ?>-400 rounded-full"><i class="ph <?= $bIco ?> text-sm"></i></div>
                                        <?php else: ?> <div class="w-full h-full bg-<?= $bCol ?>-500/20 flex items-center justify-center text-<?= $bCol ?>-400 rounded-full"><i class="ph <?= $bIco ?> text-sm"></i></div> <?php endif; ?>
                                    </div>
                                    <span class="text-[9px] font-bold text-slate-200 truncate w-full text-center"><?= htmlspecialchars($banco['nombre']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="seccion_gastos" class="mb-8 transition-opacity duration-300">
                        <div class="flex items-center justify-between mb-3 pl-1">
                            <h3 class="text-slate-500 text-[11px] font-bold tracking-[0.15em] uppercase">Categoría</h3>
                            <a href="cuentas.php" class="text-[10px] font-bold text-blue-400 uppercase"><i class="ph ph-gear"></i> EDITAR CARPETAS</a>
                        </div>
                        
                        <div id="grid_padres_gasto" class="grid grid-cols-4 gap-y-4 gap-x-2 transition-all">
                            <?php foreach($arbol_gastos as $ag): 
                                $p = $ag['padre']; $cCol = $p['color'] ?? 'slate'; $cIco = $p['icono'] ?? 'ph-tag';
                            ?>
                                <button type="button" onclick="abrirSubcategorias(<?= $p['id'] ?>, 'gasto')" class="flex flex-col items-center gap-2 group focus:outline-none relative">
                                    <div class="w-12 h-12 rounded-xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner">
                                        <i class="ph <?= $cIco ?> text-xl"></i>
                                        <div class="absolute bottom-5 -right-1 w-4 h-4 bg-[#040812] rounded-full border border-slate-700 flex items-center justify-center text-slate-400"><i class="ph ph-caret-down text-[8px]"></i></div>
                                    </div>
                                    <span class="text-[9px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white transition-colors"><?= htmlspecialchars($p['nombre']) ?></span>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" onclick="abrirModalNuevaCat('gasto', null, null, null)" class="flex flex-col items-center gap-2 focus:outline-none group">
                                <div class="w-12 h-12 rounded-xl border border-dashed border-slate-600 flex items-center justify-center text-slate-500 group-hover:text-white group-hover:border-emerald-500 transition-all bg-[#040812]"><i class="ph ph-plus text-xl"></i></div>
                                <span class="text-[9px] text-slate-500 font-bold w-full text-center truncate group-hover:text-emerald-400">Nueva</span>
                            </button>
                        </div>

                        <?php foreach($arbol_gastos as $ag): 
                            $p = $ag['padre']; $cCol = $p['color'] ?? 'slate'; $cIco = $p['icono'] ?? 'ph-tag';
                        ?>
                            <div id="grid_hijos_gasto_<?= $p['id'] ?>" class="subgrid-gasto hidden transition-all bg-[#040812]/50 p-4 rounded-3xl border border-slate-800">
                                <div class="flex items-center gap-3 mb-4 border-b border-slate-800 pb-3">
                                    <button type="button" onclick="volverCategorias('gasto')" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700"><i class="ph ph-arrow-left"></i></button>
                                    <h4 class="text-white font-bold text-sm flex items-center gap-2"><i class="ph <?= $cIco ?> text-<?= $cCol ?>-400"></i> <?= htmlspecialchars($p['nombre']) ?></h4>
                                </div>
                                <div class="grid grid-cols-4 gap-y-4 gap-x-2 container-grid-botones">
                                    <button type="button" class="btn-cat-gasto flex flex-col items-center gap-2 group focus:outline-none" data-id="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                                        <div class="w-12 h-12 rounded-xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-xl"></i></div>
                                        <span class="text-[9px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white">General</span>
                                    </button>
                                    <?php foreach($ag['subs'] as $sub): ?>
                                        <button type="button" class="btn-cat-gasto flex flex-col items-center gap-2 group focus:outline-none" data-id="<?= $sub['id'] ?>" data-nombre="<?= htmlspecialchars($sub['nombre']) ?>">
                                            <div class="w-12 h-12 rounded-xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-xl"></i></div>
                                            <span class="text-[9px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white"><?= htmlspecialchars($sub['nombre']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                    <button type="button" onclick="abrirModalNuevaCat('gasto', <?= $p['id'] ?>, '<?= $cCol ?>', '<?= $cIco ?>')" class="flex flex-col items-center gap-2 focus:outline-none group">
                                        <div class="w-12 h-12 rounded-xl border border-dashed border-<?= $cCol ?>-500/50 flex items-center justify-center text-<?= $cCol ?>-500 hover:text-white hover:bg-<?= $cCol ?>-500/20 transition-all bg-[#040812]"><i class="ph ph-plus text-xl"></i></div>
                                        <span class="text-[9px] text-slate-500 font-bold w-full text-center truncate group-hover:text-<?= $cCol ?>-400">+ Subcat.</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="seccion_ingresos" class="mb-8 hidden transition-opacity duration-300">
                        <div class="flex items-center justify-between mb-3 pl-1">
                            <h3 class="text-emerald-500 text-[11px] font-bold tracking-[0.15em] uppercase">Fuente de Ingreso</h3>
                            <a href="cuentas.php" class="text-[10px] font-bold text-blue-400 uppercase"><i class="ph ph-gear"></i> EDITAR CARPETAS</a>
                        </div>
                        
                        <div id="grid_padres_ingreso" class="grid grid-cols-4 gap-y-4 gap-x-2 transition-all">
                            <?php foreach($arbol_ingresos as $ai): 
                                $p = $ai['padre']; $cCol = $p['color'] ?? 'emerald'; $cIco = $p['icono'] ?? 'ph-money';
                            ?>
                                <button type="button" onclick="abrirSubcategorias(<?= $p['id'] ?>, 'ingreso')" class="flex flex-col items-center gap-2 group focus:outline-none relative">
                                    <div class="w-12 h-12 rounded-xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner">
                                        <i class="ph <?= $cIco ?> text-xl"></i>
                                        <div class="absolute bottom-5 -right-1 w-4 h-4 bg-[#040812] rounded-full border border-slate-700 flex items-center justify-center text-slate-400"><i class="ph ph-caret-down text-[8px]"></i></div>
                                    </div>
                                    <span class="text-[9px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white transition-colors"><?= htmlspecialchars($p['nombre']) ?></span>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" onclick="abrirModalNuevaCat('ingreso', null, null, null)" class="flex flex-col items-center gap-2 focus:outline-none group">
                                <div class="w-12 h-12 rounded-xl border border-dashed border-slate-600 flex items-center justify-center text-slate-500 group-hover:text-white group-hover:border-emerald-500 transition-all bg-[#040812]"><i class="ph ph-plus text-xl"></i></div>
                                <span class="text-[9px] text-slate-500 font-bold w-full text-center truncate group-hover:text-emerald-400">Nueva</span>
                            </button>
                        </div>

                        <?php foreach($arbol_ingresos as $ai): 
                            $p = $ai['padre']; $cCol = $p['color'] ?? 'emerald'; $cIco = $p['icono'] ?? 'ph-money';
                        ?>
                            <div id="grid_hijos_ingreso_<?= $p['id'] ?>" class="subgrid-ingreso hidden transition-all bg-[#040812]/50 p-4 rounded-3xl border border-slate-800">
                                <div class="flex items-center gap-3 mb-4 border-b border-slate-800 pb-3">
                                    <button type="button" onclick="volverCategorias('ingreso')" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700"><i class="ph ph-arrow-left"></i></button>
                                    <h4 class="text-white font-bold text-sm flex items-center gap-2"><i class="ph <?= $cIco ?> text-<?= $cCol ?>-400"></i> <?= htmlspecialchars($p['nombre']) ?></h4>
                                </div>
                                <div class="grid grid-cols-4 gap-y-4 gap-x-2 container-grid-botones">
                                    <button type="button" class="btn-cat-ingreso flex flex-col items-center gap-2 group focus:outline-none" data-id="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                                        <div class="w-12 h-12 rounded-xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-xl"></i></div>
                                        <span class="text-[9px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white">General</span>
                                    </button>
                                    <?php foreach($ai['subs'] as $sub): ?>
                                        <button type="button" class="btn-cat-ingreso flex flex-col items-center gap-2 group focus:outline-none" data-id="<?= $sub['id'] ?>" data-nombre="<?= htmlspecialchars($sub['nombre']) ?>">
                                            <div class="w-12 h-12 rounded-xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-xl"></i></div>
                                            <span class="text-[9px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white"><?= htmlspecialchars($sub['nombre']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                    <button type="button" onclick="abrirModalNuevaCat('ingreso', <?= $p['id'] ?>, '<?= $cCol ?>', '<?= $cIco ?>')" class="flex flex-col items-center gap-2 focus:outline-none group">
                                        <div class="w-12 h-12 rounded-xl border border-dashed border-<?= $cCol ?>-500/50 flex items-center justify-center text-<?= $cCol ?>-500 hover:text-white hover:bg-<?= $cCol ?>-500/20 transition-all bg-[#040812]"><i class="ph ph-plus text-xl"></i></div>
                                        <span class="text-[9px] text-slate-500 font-bold w-full text-center truncate group-hover:text-<?= $cCol ?>-400">+ Subcat.</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-6 flex gap-3">
                        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required class="w-1/3 bg-[#040812] border border-slate-700 rounded-xl px-3 text-slate-300 outline-none text-xs cursor-pointer">
                        <input type="text" name="descripcion" id="input_notas" placeholder="Descripción / Notas..." required class="w-2/3 bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 focus:outline-none text-sm">
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-slate-800">
                        <button type="button" onclick="document.getElementById('modal_registro').classList.add('hidden')" class="px-6 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl transition-colors md:hidden">Cancelar</button>
                        <button type="button" id="btn_submit_final" class="flex-1 bg-gradient-to-r from-[#CE1126] to-[#a30d1e] text-white font-bold py-4 rounded-xl text-lg transition-all active:scale-[0.98] shadow-lg shadow-red-900/50">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modal_editar" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-[#040812]/80 backdrop-blur-md transition-all">
        <div class="glass-panel w-full max-w-sm p-6 md:p-8 rounded-[2.5rem] border-t border-blue-500/30 shadow-2xl">
            <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-2"><i class="ph ph-pencil-simple text-blue-400"></i> Editar Movimiento</h3>
            <p class="text-xs text-slate-400 mb-6">Aquí puedes mover la transacción de categoría si te equivocaste.</p>
            
            <form action="index.php" method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="editar_transaccion">
                <input type="hidden" name="tx_id" id="edit_id">
                <input type="hidden" name="tipo_tx" id="edit_tipo_tx">
                
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-bold text-lg">RD$</span>
                    <input type="number" name="monto" id="edit_monto" step="0.01" required class="w-full bg-[#040812] border border-slate-700 rounded-xl pl-12 pr-4 py-3 text-xl font-bold text-white focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Descripción</label>
                    <input type="text" name="descripcion" id="edit_desc" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none text-sm">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Cuenta / Banco</label>
                        <select name="cuenta_bancaria" id="edit_cuenta_bancaria" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-3 py-3 text-white focus:border-blue-500 outline-none text-xs">
                            <?php foreach($bancos as $b): ?> <option value="<?= $b['id'] ?>"><?= $b['nombre'] ?></option> <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Mover a Categoría:</label>
                        <select name="categoria" id="edit_select_gasto" class="w-full bg-[#040812] border border-slate-700 rounded-xl px-2 py-3 text-white focus:border-red-500 outline-none text-xs font-bold">
                            <?php foreach($arbol_gastos as $ag): ?>
                                <optgroup label="<?= htmlspecialchars($ag['padre']['nombre']) ?>" class="bg-slate-900 text-slate-400">
                                    <option value="<?= $ag['padre']['id'] ?>"><?= htmlspecialchars($ag['padre']['nombre']) ?> (General)</option>
                                    <?php foreach($ag['subs'] as $sub): ?> <option value="<?= $sub['id'] ?>">  ↳ <?= htmlspecialchars($sub['nombre']) ?></option> <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <select name="categoria" id="edit_select_ingreso" disabled class="hidden w-full bg-[#040812] border border-slate-700 rounded-xl px-2 py-3 text-white focus:border-emerald-500 outline-none text-xs font-bold">
                            <?php foreach($arbol_ingresos as $ai): ?>
                                <optgroup label="<?= htmlspecialchars($ai['padre']['nombre']) ?>" class="bg-slate-900 text-slate-400">
                                    <option value="<?= $ai['padre']['id'] ?>"><?= htmlspecialchars($ai['padre']['nombre']) ?> (General)</option>
                                    <?php foreach($ai['subs'] as $sub): ?> <option value="<?= $sub['id'] ?>">  ↳ <?= htmlspecialchars($sub['nombre']) ?></option> <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Fecha</label>
                    <input type="date" name="fecha" id="edit_fecha" required class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-slate-300 outline-none text-sm">
                </div>

                <div class="flex gap-2 pt-4">
                    <button type="button" onclick="document.getElementById('modal_editar').classList.add('hidden')" class="px-5 bg-slate-800 text-white rounded-xl hover:bg-slate-700 transition-colors">Cancelar</button>
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition-all">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal_nueva_cat" class="hidden fixed inset-0 z-[80] flex items-center justify-center p-4 bg-[#040812]/90 backdrop-blur-sm">
        <div class="bg-[#0a1122] border border-emerald-500/50 rounded-3xl p-6 w-full max-w-sm shadow-2xl">
            <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="ph ph-sparkle text-emerald-400"></i> Nueva Categoría</h3>
            
            <div class="space-y-4">
                <input type="hidden" id="modal_cat_tipo">
                <input type="hidden" id="modal_cat_padre">
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Nombre</label>
                    <input type="text" id="modal_cat_nombre" placeholder="Ej. Delivery, Suscripciones..." class="w-full bg-[#040812] border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 outline-none">
                </div>

                <div id="seccion_diseno_cat_modal">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Ícono</label>
                        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2">
                            <?php $iconos_cat = ['ph-tag', 'ph-shopping-cart', 'ph-hamburger', 'ph-pizza', 'ph-coffee', 'ph-car-profile', 'ph-gas-pump', 'ph-house', 'ph-lightbulb', 'ph-wifi-high', 'ph-barbell', 'ph-pill', 'ph-film-strip', 'ph-t-shirt', 'ph-paw-print', 'ph-star']; ?>
                            <?php foreach($iconos_cat as $ico): ?>
                                <button type="button" onclick="seleccionarIconoModal('<?= $ico ?>', this)" class="btn-icono-modal w-10 h-10 rounded-xl bg-[#040812] border border-slate-700 flex items-center justify-center text-xl text-slate-400 shrink-0"><i class="ph <?= $ico ?>"></i></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="modal_cat_icono" value="ph-tag">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Color</label>
                        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2">
                            <?php $colores = ['slate', 'red', 'orange', 'yellow', 'emerald', 'blue', 'indigo', 'fuchsia']; ?>
                            <?php foreach($colores as $col): ?>
                                <button type="button" onclick="seleccionarColorModal('<?= $col ?>', this)" class="btn-color-modal w-8 h-8 rounded-full bg-<?= $col ?>-500/20 border border-<?= $col ?>-500 shrink-0"></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="modal_cat_color" value="slate">
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="cerrarModalNuevaCat()" class="px-4 bg-slate-800 hover:bg-slate-700 text-white rounded-xl transition-colors">Atrás</button>
                    <button type="button" onclick="aplicarNuevaCat()" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-emerald-900/30">Crear y Seleccionar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const btnGasto = document.getElementById('btn_gasto'); const btnIngreso = document.getElementById('btn_ingreso');
        const inputTipo = document.getElementById('modal_input_tipo'); const secGastos = document.getElementById('seccion_gastos'); const secIngresos = document.getElementById('seccion_ingresos');
        const btnCuentas = document.querySelectorAll('.btn-cuenta'); const hiddenCuenta = document.getElementById('hidden_cuenta'); const hiddenGasto = document.getElementById('hidden_gasto'); const hiddenIngreso = document.getElementById('hidden_ingreso');
        const montoInput = document.getElementById('monto_input');
        const btnSubmitFinal = document.getElementById('btn_submit_final');
        const form = document.getElementById('form_quick_record'); const inputNotas = document.getElementById('input_notas');

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('ultimo_tipo_registro') === 'ingreso') btnIngreso.click(); else btnGasto.click(); 
        });

        function abrirSubcategorias(id, tipo) { document.getElementById(`grid_padres_${tipo}`).classList.add('hidden'); document.getElementById(`grid_hijos_${tipo}_${id}`).classList.remove('hidden'); }
        function volverCategorias(tipo) { document.querySelectorAll(`.subgrid-${tipo}`).forEach(el => el.classList.add('hidden')); document.getElementById(`grid_padres_${tipo}`).classList.remove('hidden'); }

        btnCuentas.forEach(btn => {
            btn.addEventListener('click', () => {
                btnCuentas.forEach(b => { b.classList.remove('card-selected'); b.classList.add('opacity-70'); });
                btn.classList.add('card-selected'); btn.classList.remove('opacity-70');
                hiddenCuenta.value = btn.getAttribute('data-id');
            });
        });

        document.body.addEventListener('click', function(e) {
            const btnG = e.target.closest('.btn-cat-gasto');
            if (btnG) {
                document.querySelectorAll('.btn-cat-gasto').forEach(b => { if(b.firstElementChild) b.firstElementChild.classList.remove('cat-selected'); });
                btnG.firstElementChild.classList.add('cat-selected'); hiddenGasto.value = btnG.getAttribute('data-id');
                const nombreCat = btnG.getAttribute('data-nombre'); if(inputNotas.value === '' || inputNotas.value === 'General') inputNotas.value = nombreCat;
            }
            const btnI = e.target.closest('.btn-cat-ingreso');
            if (btnI) {
                document.querySelectorAll('.btn-cat-ingreso').forEach(b => { if(b.firstElementChild) b.firstElementChild.classList.remove('cat-selected'); });
                btnI.firstElementChild.classList.add('cat-selected'); hiddenIngreso.value = btnI.getAttribute('data-id');
                const nombreCat = btnI.getAttribute('data-nombre'); if(inputNotas.value === '' || inputNotas.value === 'General') inputNotas.value = nombreCat;
            }
        });

        btnGasto.addEventListener('click', () => {
            inputTipo.value = 'gasto'; localStorage.setItem('ultimo_tipo_registro', 'gasto');
            btnGasto.className = 'flex-1 bg-white text-black font-bold py-2.5 rounded-xl text-sm transition-all shadow-sm';
            btnIngreso.className = 'flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-sm transition-all hover:text-white bg-transparent';
            secGastos.classList.remove('hidden'); secIngresos.classList.add('hidden'); volverCategorias('gasto');
            btnSubmitFinal.className = 'flex-1 bg-gradient-to-r from-[#CE1126] to-[#a30d1e] text-white font-bold py-4 rounded-xl text-lg transition-all active:scale-[0.98] shadow-lg shadow-red-900/50';
            btnSubmitFinal.textContent = 'Guardar'; montoInput.classList.replace('text-emerald-400', 'text-white');
        });

        btnIngreso.addEventListener('click', () => {
            inputTipo.value = 'ingreso'; localStorage.setItem('ultimo_tipo_registro', 'ingreso');
            btnIngreso.className = 'flex-1 bg-[#18181b] border border-slate-700 text-emerald-400 font-bold py-2.5 rounded-xl text-sm transition-all shadow-sm';
            btnGasto.className = 'flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-sm transition-all hover:text-white bg-transparent';
            secIngresos.classList.remove('hidden'); secGastos.classList.add('hidden'); volverCategorias('ingreso'); 
            btnSubmitFinal.className = 'flex-1 bg-gradient-to-r from-emerald-600 to-emerald-700 text-white font-bold py-4 rounded-xl text-lg transition-all active:scale-[0.98] shadow-lg shadow-emerald-900/50';
            btnSubmitFinal.textContent = 'Guardar'; montoInput.classList.replace('text-white', 'text-emerald-400');
        });

        btnSubmitFinal.addEventListener('click', () => {
            if(!montoInput.value || montoInput.value <= 0) { alert("Por favor ingresa una cantidad."); return; }
            if(!hiddenCuenta.value) { alert("Por favor selecciona la cuenta de origen."); return; }
            if(inputTipo.value === 'gasto' && (!hiddenGasto.value || hiddenGasto.value === 'null')) { alert("Por favor selecciona una categoría de gasto."); return; }
            if(inputTipo.value === 'ingreso' && (!hiddenIngreso.value || hiddenIngreso.value === 'null')) { alert("Por favor selecciona una fuente de ingreso."); return; }
            form.submit();
        });

        function abrirModalRegistro() { document.getElementById('modal_registro').classList.remove('hidden'); }
        function cerrarModalNuevaCat() { document.getElementById('modal_nueva_cat').classList.add('hidden'); }

        function seleccionarIconoModal(ico, el) { document.getElementById('modal_cat_icono').value = ico; document.querySelectorAll('.btn-icono-modal').forEach(b => b.classList.remove('border-emerald-500', 'text-emerald-400')); if(el) el.classList.add('border-emerald-500', 'text-emerald-400'); }
        function seleccionarColorModal(col, el) { document.getElementById('modal_cat_color').value = col; document.querySelectorAll('.btn-color-modal').forEach(b => b.classList.remove('border-white', 'scale-125', 'border-2')); if(el) el.classList.add('border-white', 'scale-125', 'border-2'); }

        function abrirModalNuevaCat(tipo, idPadre, colorPadre, iconoPadre) {
            document.getElementById('modal_nueva_cat').classList.remove('hidden');
            document.getElementById('modal_cat_nombre').value = ''; document.getElementById('modal_cat_tipo').value = tipo; document.getElementById('modal_cat_padre').value = idPadre || '';
            if(idPadre) { document.getElementById('seccion_diseno_cat_modal').classList.add('hidden'); document.getElementById('modal_cat_icono').value = iconoPadre; document.getElementById('modal_cat_color').value = colorPadre; } 
            else { document.getElementById('seccion_diseno_cat_modal').classList.remove('hidden'); seleccionarIconoModal('ph-tag', document.querySelector('.btn-icono-modal')); seleccionarColorModal('slate', document.querySelector('.btn-color-modal')); }
            document.getElementById('modal_cat_nombre').focus();
        }

        function aplicarNuevaCat() {
            const nombre = document.getElementById('modal_cat_nombre').value.trim();
            if(!nombre) return alert('Debes ponerle un nombre.');
            const tipo = document.getElementById('modal_cat_tipo').value; const padreId = document.getElementById('modal_cat_padre').value; const icono = document.getElementById('modal_cat_icono').value; const color = document.getElementById('modal_cat_color').value;
            document.getElementById('nueva_cat_nombre').value = nombre; document.getElementById('nueva_cat_padre').value = padreId; document.getElementById('nueva_cat_icono').value = icono; document.getElementById('nueva_cat_color').value = color;

            if(tipo === 'gasto') { hiddenGasto.value = 'nueva'; document.querySelectorAll('.btn-cat-gasto').forEach(b => { if(b.firstElementChild) b.firstElementChild.classList.remove('cat-selected'); }); } 
            else { hiddenIngreso.value = 'nueva'; document.querySelectorAll('.btn-cat-ingreso').forEach(b => { if(b.firstElementChild) b.firstElementChild.classList.remove('cat-selected'); }); }
            if(inputNotas.value === '' || inputNotas.value === 'General') inputNotas.value = nombre;

            const gridDestino = padreId ? document.getElementById(`grid_hijos_${tipo}_${padreId}`).querySelector('.container-grid-botones') : document.getElementById(`grid_padres_${tipo}`);
            const btnHtml = document.createElement('button'); btnHtml.type = 'button'; btnHtml.className = `btn-cat-${tipo} flex flex-col items-center gap-2 group focus:outline-none`; btnHtml.setAttribute('data-id', 'nueva'); btnHtml.setAttribute('data-nombre', nombre);
            btnHtml.innerHTML = `<div class="w-12 h-12 rounded-xl bg-${color}-500/10 border border-${color}-500/30 flex items-center justify-center text-${color}-400 cat-selected shadow-inner"><i class="ph ${icono} text-xl"></i></div><span class="text-[9px] text-white font-bold w-full text-center truncate">${nombre} (Nueva)</span>`;
            gridDestino.insertBefore(btnHtml, gridDestino.lastElementChild);
            cerrarModalNuevaCat();
        }

        function abrirEditar(id, desc, monto, fecha, tipo, idBanco, idCategoria) {
            document.getElementById('edit_id').value = id; document.getElementById('edit_desc').value = desc; document.getElementById('edit_monto').value = monto; document.getElementById('edit_fecha').value = fecha; document.getElementById('edit_tipo_tx').value = tipo; document.getElementById('edit_cuenta_bancaria').value = idBanco;
            const selGasto = document.getElementById('edit_select_gasto'); const selIngreso = document.getElementById('edit_select_ingreso');
            if(tipo === 'gasto') { selGasto.classList.remove('hidden'); selGasto.disabled = false; selGasto.value = idCategoria; selIngreso.classList.add('hidden'); selIngreso.disabled = true; } 
            else { selIngreso.classList.remove('hidden'); selIngreso.disabled = false; selIngreso.value = idCategoria; selGasto.classList.add('hidden'); selGasto.disabled = true; }
            document.getElementById('modal_editar').classList.remove('hidden');
        }
    </script>
</body>
</html>