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

// --- LÓGICA DE GUARDADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? 'gasto'; 
    $monto = (float) $_POST['monto'];
    $descripcion = trim($_POST['descripcion']);
    $fecha = $_POST['fecha'];
    $cuenta_bancaria = (int) $_POST['cuenta_bancaria'];
    $categoria_val = ($tipo === 'gasto') ? ($_POST['categoria_gasto'] ?? '') : ($_POST['categoria_ingreso'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. CREAR CATEGORÍA SI DICE "NUEVA" (AISLADA AL USUARIO)
        if ($categoria_val === 'nueva') {
            $cat_nombre = trim($_POST['nueva_cat_nombre']);
            $cat_padre = !empty($_POST['nueva_cat_padre']) ? (int)$_POST['nueva_cat_padre'] : null;
            $cat_icono = $_POST['nueva_cat_icono'] ?? 'ph-tag';
            $cat_color = $_POST['nueva_cat_color'] ?? 'slate';
            $cat_clasif = ($tipo === 'gasto') ? 2 : 3;

            // Le inyectamos el usuario_id para que nadie más la vea
            $stmtCat = $pdo->prepare("INSERT INTO cuentas (usuario_id, clasificacion_id, nombre, balance, parent_id, icono, color) VALUES (?, ?, ?, 0, ?, ?, ?)");
            $stmtCat->execute([$uid, $cat_clasif, $cat_nombre, $cat_padre, $cat_icono, $cat_color]);
            $categoria_id = (int)$pdo->lastInsertId();
        } else {
            $categoria_id = (int)$categoria_val;
        }

        // 2. REGISTRAR TRANSACCIÓN (AISLADA AL USUARIO)
        if ($monto > 0 && !empty($descripcion) && $cuenta_bancaria > 0 && $categoria_id > 0) {
            if ($tipo === 'gasto') {
                $origen_id = $cuenta_bancaria; $destino_id = $categoria_id;
                // Resta del balance SOLO SI la cuenta pertenece al usuario
                $pdo->prepare("UPDATE cuentas SET balance = balance - :monto WHERE id = :id AND usuario_id = :uid")->execute([':monto' => $monto, ':id' => $cuenta_bancaria, ':uid' => $uid]);
            } else {
                $origen_id = $categoria_id; $destino_id = $cuenta_bancaria; 
                // Suma al balance SOLO SI la cuenta pertenece al usuario
                $pdo->prepare("UPDATE cuentas SET balance = balance + :monto WHERE id = :id AND usuario_id = :uid")->execute([':monto' => $monto, ':id' => $cuenta_bancaria, ':uid' => $uid]);
            }

            // Inyectamos el usuario_id al momento de registrar el movimiento
            $stmtInsert = $pdo->prepare("INSERT INTO transacciones (usuario_id, cuenta_origen_id, cuenta_destino_id, monto, fecha, descripcion) VALUES (:uid, :origen, :destino, :monto, :fecha, :desc)");
            $stmtInsert->execute([':uid' => $uid, ':origen' => $origen_id, ':destino' => $destino_id, ':monto' => $monto, ':fecha' => $fecha, ':desc' => $descripcion]);

            $pdo->commit();
            $mensaje = $tipo === 'gasto' ? 'Gasto registrado correctamente.' : 'Ingreso registrado con éxito.';
            $tipo_mensaje = 'success';
        } else {
            $pdo->rollBack();
            $mensaje = 'Por favor, selecciona o crea una categoría válida.';
            $tipo_mensaje = 'warning';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = 'Error al registrar: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// --- EXTRACCIÓN DE DATOS (AISLADA AL USUARIO) ---
$stmtBancos = $pdo->prepare("SELECT id, nombre, balance, numero_cuenta, icono, color FROM cuentas WHERE clasificacion_id = 1 AND usuario_id = ? ORDER BY balance DESC");
$stmtBancos->execute([$uid]);
$bancos = $stmtBancos->fetchAll();

function obtenerArbolCategorias($pdo, $clasificacion_id, $uid) {
    // Filtramos las categorías por el usuario activo
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

function obtenerLogoBanco($nombre) {
    $n = mb_strtolower($nombre); $domain = '';
    if (strpos($n, 'bhd') !== false) $domain = 'bhd.com.do';
    elseif (strpos($n, 'banreservas') !== false || strpos($n, 'reservas') !== false) $domain = 'banreservas.com';
    elseif (strpos($n, 'popular') !== false) $domain = 'popularenlinea.com';
    elseif (strpos($n, 'scotia') !== false) $domain = 'scotiabank.com.do';
    elseif (strpos($n, 'santa cruz') !== false) $domain = 'bsc.com.do';
    elseif (strpos($n, 'qik') !== false) $domain = 'qik.com.do';
    elseif (strpos($n, 'caribe') !== false) $domain = 'bancocaribe.com.do';
    elseif (strpos($n, 'promerica') !== false) $domain = 'promerica.com.do';
    elseif (strpos($n, 'apap') !== false) $domain = 'apap.com.do';
    elseif (strpos($n, 'cibao') !== false || strpos($n, 'acap') !== false) $domain = 'acap.com.do';
    elseif (strpos($n, 'nacional') !== false || strpos($n, 'aln') !== false) $domain = 'aln.com.do';
    elseif (strpos($n, 'paypal') !== false) $domain = 'paypal.com';
    return $domain ? "https://www.google.com/s2/favicons?domain=" . $domain . "&sz=128" : false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registro | Maneja Tu Dinero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        input[type="number"]::-webkit-inner-spin-button, input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .card-selected { border-color: #ffffff !important; background-color: rgba(255,255,255,0.08) !important; box-shadow: 0 0 15px rgba(255,255,255,0.1); }
        .cat-selected { box-shadow: 0 0 0 2px #ffffff; transform: scale(1.05); }
    </style>
</head>
<body class="bg-[#040812] text-slate-200 font-sans h-screen flex overflow-hidden selection:bg-[#CE1126]/30">

    <?php include 'includes/sidebar.php'; ?>

    <main id="main_container" class="flex-1 flex flex-col h-screen overflow-y-auto bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-red-950/30 via-[#040812] to-[#020409] hide-scrollbar relative transition-colors duration-1000">
        
        <?php if($mensaje): ?>
            <div class="absolute top-6 left-1/2 -translate-x-1/2 z-[90] min-w-[300px] p-4 rounded-xl flex items-center gap-3 border shadow-2xl <?= $tipo_mensaje === 'success' ? 'bg-emerald-900/90 border-emerald-500 text-emerald-100' : 'bg-red-900/90 border-red-500 text-red-100' ?>">
                <i class="ph <?= $tipo_mensaje === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-2xl"></i><span class="font-medium"><?= $mensaje ?></span>
            </div>
        <?php endif; ?>

        <div class="w-full max-w-2xl mx-auto my-auto pt-10 pb-28 md:pb-20 px-4 md:px-0 relative z-10">
            <div id="luz-fondo" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-[500px] bg-[#CE1126]/15 rounded-full blur-[100px] pointer-events-none -z-10 transition-colors duration-1000"></div>

            <div class="bg-[#0a1122]/60 backdrop-blur-2xl border border-blue-900/30 rounded-[2.5rem] p-8 md:p-12 shadow-2xl shadow-black/80">
                
                <form action="registro.php" method="POST" id="formTransaccion">
                    <input type="hidden" name="tipo" id="input_tipo" value="gasto">
                    <input type="hidden" name="cuenta_bancaria" id="hidden_cuenta" value="">
                    <input type="hidden" name="categoria_gasto" id="hidden_gasto" value="">
                    <input type="hidden" name="categoria_ingreso" id="hidden_ingreso" value="">

                    <input type="hidden" name="nueva_cat_nombre" id="nueva_cat_nombre">
                    <input type="hidden" name="nueva_cat_padre" id="nueva_cat_padre">
                    <input type="hidden" name="nueva_cat_icono" id="nueva_cat_icono">
                    <input type="hidden" name="nueva_cat_color" id="nueva_cat_color">

                    <div class="flex bg-[#040812] rounded-2xl p-1.5 mb-10 border border-slate-800 w-full max-w-xs mx-auto">
                        <button type="button" id="btn_gasto" class="flex-1 bg-white text-black font-bold py-2.5 rounded-xl text-sm transition-all shadow-sm">Gasto</button>
                        <button type="button" id="btn_ingreso" class="flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-sm transition-all hover:text-white">Ingreso</button>
                    </div>

                    <div class="text-center mb-10 relative">
                        <p class="text-slate-500 text-[11px] font-bold tracking-[0.2em] uppercase mb-3">Cantidad</p>
                        <div class="flex items-center justify-center">
                            <span class="text-5xl md:text-6xl font-bold text-slate-600 mr-2">RD$</span>
                            <input type="number" name="monto" id="monto_input" step="0.01" placeholder="0.00" required class="bg-transparent border-none text-6xl md:text-7xl font-bold text-white focus:outline-none focus:ring-0 placeholder-slate-700 w-[240px] p-0 text-left transition-colors duration-300">
                        </div>
                        <div class="h-6 mt-4">
                            <p id="display_balance" class="text-xs font-semibold text-blue-400 opacity-0 transition-opacity duration-300 bg-[#002D62]/20 inline-block px-4 py-1.5 rounded-full border border-blue-500/30">Disponible: RD$ 0.00</p>
                        </div>
                    </div>

                    <div class="flex justify-center mb-10">
                        <div class="relative bg-[#040812] border border-slate-800 rounded-xl px-5 py-3 flex items-center gap-3 shadow-inner">
                            <i class="ph ph-calendar-blank text-slate-400 text-xl"></i>
                            <div class="flex flex-col">
                                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider leading-none mb-1">Fecha</span>
                                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required class="bg-transparent text-sm font-semibold text-slate-200 focus:outline-none border-none p-0 cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <div class="mb-10">
                        <div class="flex items-center justify-between mb-4 pl-2">
                            <h3 class="text-slate-500 text-[11px] font-bold tracking-[0.15em] uppercase">Cuenta</h3>
                            <a href="cuentas.php" class="text-[10px] font-bold text-blue-400 uppercase"><i class="ph ph-gear"></i> GESTIONAR</a>
                        </div>
                        <div class="grid grid-cols-3 gap-3 md:gap-4 overflow-x-auto hide-scrollbar pb-2">
                            <?php foreach($bancos as $banco): 
                                $logo = obtenerLogoBanco($banco['nombre']);
                                $bCol = $banco['color'] ?? 'blue';
                                $bIco = $banco['icono'] ?? 'ph-wallet';
                            ?>
                                <button type="button" class="btn-cuenta bg-[#040812] border border-slate-800 rounded-2xl p-4 flex flex-col items-center justify-center transition-all opacity-70 hover:opacity-100 hover:border-blue-500 focus:outline-none" data-id="<?= $banco['id'] ?>" data-balance="<?= number_format($banco['balance'], 2) ?>">
                                    <div class="w-10 h-10 rounded-full bg-[#040812] flex items-center justify-center mb-2 overflow-hidden border border-<?= $bCol ?>-500/30 p-1 shadow-inner">
                                        <?php if($logo): ?> <img src="<?= $logo ?>" class="w-full h-full object-contain rounded-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="hidden w-full h-full bg-<?= $bCol ?>-500/20 flex items-center justify-center text-<?= $bCol ?>-400 rounded-full"><i class="ph <?= $bIco ?> text-lg"></i></div>
                                        <?php else: ?> <div class="w-full h-full bg-<?= $bCol ?>-500/20 flex items-center justify-center text-<?= $bCol ?>-400 rounded-full"><i class="ph <?= $bIco ?> text-lg"></i></div> <?php endif; ?>
                                    </div>
                                    <span class="text-[11px] font-bold text-slate-200 truncate w-full text-center"><?= htmlspecialchars($banco['nombre']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="seccion_gastos" class="mb-10 transition-opacity duration-300">
                        <div class="flex items-center justify-between mb-5 pl-2"><h3 class="text-slate-500 text-[11px] font-bold tracking-[0.15em] uppercase">Categoría</h3></div>
                        
                        <div id="grid_padres_gasto" class="grid grid-cols-4 gap-y-6 gap-x-2 transition-all">
                            <?php foreach($arbol_gastos as $ag): 
                                $p = $ag['padre']; $cCol = $p['color'] ?? 'slate'; $cIco = $p['icono'] ?? 'ph-tag';
                            ?>
                                <button type="button" onclick="abrirSubcategorias(<?= $p['id'] ?>, 'gasto')" class="flex flex-col items-center gap-2.5 group focus:outline-none relative">
                                    <div class="w-14 h-14 rounded-2xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner">
                                        <i class="ph <?= $cIco ?> text-2xl"></i>
                                        <div class="absolute bottom-6 -right-1 w-5 h-5 bg-[#040812] rounded-full border border-slate-700 flex items-center justify-center text-slate-400"><i class="ph ph-caret-down text-[10px]"></i></div>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white transition-colors"><?= htmlspecialchars($p['nombre']) ?></span>
                                </button>
                            <?php endforeach; ?>
                            
                            <button type="button" onclick="abrirModalNuevaCat('gasto', null, null, null)" class="flex flex-col items-center gap-2.5 focus:outline-none group">
                                <div class="w-14 h-14 rounded-2xl border border-dashed border-slate-600 flex items-center justify-center text-slate-500 group-hover:text-white group-hover:border-emerald-500 transition-all bg-[#040812]"><i class="ph ph-plus text-2xl"></i></div>
                                <span class="text-[10px] text-slate-500 font-bold w-full text-center truncate group-hover:text-emerald-400">Nueva</span>
                            </button>
                        </div>

                        <?php foreach($arbol_gastos as $ag): 
                            $p = $ag['padre']; $cCol = $p['color'] ?? 'slate'; $cIco = $p['icono'] ?? 'ph-tag';
                        ?>
                            <div id="grid_hijos_gasto_<?= $p['id'] ?>" class="subgrid-gasto hidden transition-all bg-[#040812]/50 p-4 rounded-3xl border border-slate-800">
                                <div class="flex items-center gap-3 mb-6 border-b border-slate-800 pb-3">
                                    <button type="button" onclick="volverCategorias('gasto')" class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"><i class="ph ph-arrow-left text-lg"></i></button>
                                    <h4 class="text-white font-bold text-sm flex items-center gap-2"><i class="ph <?= $cIco ?> text-<?= $cCol ?>-400"></i> <?= htmlspecialchars($p['nombre']) ?></h4>
                                </div>
                                <div class="grid grid-cols-4 gap-y-6 gap-x-2 container-grid-botones">
                                    
                                    <button type="button" class="btn-cat-gasto flex flex-col items-center gap-2.5 group focus:outline-none" data-id="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                                        <div class="w-14 h-14 rounded-2xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-2xl"></i></div>
                                        <span class="text-[10px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white transition-colors">General</span>
                                    </button>
                                    
                                    <?php foreach($ag['subs'] as $sub): ?>
                                        <button type="button" class="btn-cat-gasto flex flex-col items-center gap-2.5 group focus:outline-none" data-id="<?= $sub['id'] ?>" data-nombre="<?= htmlspecialchars($sub['nombre']) ?>">
                                            <div class="w-14 h-14 rounded-2xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-2xl"></i></div>
                                            <span class="text-[10px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white transition-colors"><?= htmlspecialchars($sub['nombre']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                    
                                    <button type="button" onclick="abrirModalNuevaCat('gasto', <?= $p['id'] ?>, '<?= $cCol ?>', '<?= $cIco ?>')" class="flex flex-col items-center gap-2.5 focus:outline-none group">
                                        <div class="w-14 h-14 rounded-2xl border border-dashed border-<?= $cCol ?>-500/50 flex items-center justify-center text-<?= $cCol ?>-500 hover:text-white hover:bg-<?= $cCol ?>-500/20 transition-all bg-[#040812]"><i class="ph ph-plus text-2xl"></i></div>
                                        <span class="text-[10px] text-slate-500 font-bold w-full text-center truncate group-hover:text-<?= $cCol ?>-400">+ Subcat.</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="seccion_ingresos" class="mb-10 hidden transition-opacity duration-300">
                        <div class="flex items-center justify-between mb-5 pl-2"><h3 class="text-emerald-500 text-[11px] font-bold tracking-[0.15em] uppercase">Fuente de Ingreso</h3></div>
                        
                        <div id="grid_padres_ingreso" class="grid grid-cols-4 gap-y-6 gap-x-2 transition-all">
                            <?php foreach($arbol_ingresos as $ai): 
                                $p = $ai['padre']; $cCol = $p['color'] ?? 'emerald'; $cIco = $p['icono'] ?? 'ph-money';
                            ?>
                                <button type="button" onclick="abrirSubcategorias(<?= $p['id'] ?>, 'ingreso')" class="flex flex-col items-center gap-2.5 group focus:outline-none relative">
                                    <div class="w-14 h-14 rounded-2xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-2xl"></i><div class="absolute bottom-6 -right-1 w-5 h-5 bg-[#040812] rounded-full border border-slate-700 flex items-center justify-center text-slate-400"><i class="ph ph-caret-down text-[10px]"></i></div></div>
                                    <span class="text-[10px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white transition-colors"><?= htmlspecialchars($p['nombre']) ?></span>
                                </button>
                            <?php endforeach; ?>
                            
                            <button type="button" onclick="abrirModalNuevaCat('ingreso', null, null, null)" class="flex flex-col items-center gap-2.5 focus:outline-none group">
                                <div class="w-14 h-14 rounded-2xl border border-dashed border-slate-600 flex items-center justify-center text-slate-500 group-hover:text-white group-hover:border-emerald-500 transition-all bg-[#040812]"><i class="ph ph-plus text-2xl"></i></div>
                                <span class="text-[10px] text-slate-500 font-bold w-full text-center truncate group-hover:text-emerald-400">Nueva</span>
                            </button>
                        </div>

                        <?php foreach($arbol_ingresos as $ai): 
                            $p = $ai['padre']; $cCol = $p['color'] ?? 'emerald'; $cIco = $p['icono'] ?? 'ph-money';
                        ?>
                            <div id="grid_hijos_ingreso_<?= $p['id'] ?>" class="subgrid-ingreso hidden transition-all bg-[#040812]/50 p-4 rounded-3xl border border-slate-800">
                                <div class="flex items-center gap-3 mb-6 border-b border-slate-800 pb-3">
                                    <button type="button" onclick="volverCategorias('ingreso')" class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"><i class="ph ph-arrow-left text-lg"></i></button>
                                    <h4 class="text-white font-bold text-sm flex items-center gap-2"><i class="ph <?= $cIco ?> text-<?= $cCol ?>-400"></i> <?= htmlspecialchars($p['nombre']) ?></h4>
                                </div>
                                <div class="grid grid-cols-4 gap-y-6 gap-x-2 container-grid-botones">
                                    <button type="button" class="btn-cat-ingreso flex flex-col items-center gap-2.5 group focus:outline-none" data-id="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                                        <div class="w-14 h-14 rounded-2xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-2xl"></i></div>
                                        <span class="text-[10px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white">General</span>
                                    </button>
                                    <?php foreach($ai['subs'] as $sub): ?>
                                        <button type="button" class="btn-cat-ingreso flex flex-col items-center gap-2.5 group focus:outline-none" data-id="<?= $sub['id'] ?>" data-nombre="<?= htmlspecialchars($sub['nombre']) ?>">
                                            <div class="w-14 h-14 rounded-2xl bg-<?= $cCol ?>-500/10 border border-<?= $cCol ?>-500/30 flex items-center justify-center text-<?= $cCol ?>-400 transition-all group-hover:bg-<?= $cCol ?>-500/20 shadow-inner"><i class="ph <?= $cIco ?> text-2xl"></i></div>
                                            <span class="text-[10px] text-slate-400 font-bold w-full text-center truncate group-hover:text-white"><?= htmlspecialchars($sub['nombre']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                    <button type="button" onclick="abrirModalNuevaCat('ingreso', <?= $p['id'] ?>, '<?= $cCol ?>', '<?= $cIco ?>')" class="flex flex-col items-center gap-2.5 focus:outline-none group">
                                        <div class="w-14 h-14 rounded-2xl border border-dashed border-<?= $cCol ?>-500/50 flex items-center justify-center text-<?= $cCol ?>-500 hover:text-white hover:bg-<?= $cCol ?>-500/20 transition-all bg-[#040812]"><i class="ph ph-plus text-2xl"></i></div>
                                        <span class="text-[10px] text-slate-500 font-bold w-full text-center truncate group-hover:text-<?= $cCol ?>-400">+ Subcat.</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-10">
                        <input type="text" name="descripcion" id="input_notas" placeholder="Añade una descripción..." required class="w-full bg-transparent border-b border-slate-700 pb-3 text-base text-white focus:outline-none focus:border-blue-500 placeholder-slate-600 transition-colors">
                    </div>

                    <button type="button" id="btn_submit_final" class="w-full bg-gradient-to-r from-[#CE1126] to-[#a30d1e] text-white font-bold py-4 rounded-2xl text-lg transition-all active:scale-[0.98] shadow-lg shadow-red-900/50">Guardar Gasto</button>
                </form>
            </div>
        </div>
    </main>

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
        const inputTipo = document.getElementById('input_tipo'); const secGastos = document.getElementById('seccion_gastos'); const secIngresos = document.getElementById('seccion_ingresos');
        const btnCuentas = document.querySelectorAll('.btn-cuenta'); const hiddenCuenta = document.getElementById('hidden_cuenta'); const hiddenGasto = document.getElementById('hidden_gasto'); const hiddenIngreso = document.getElementById('hidden_ingreso');
        const montoInput = document.getElementById('monto_input'); const displayBalance = document.getElementById('display_balance');
        const btnSubmitFinal = document.getElementById('btn_submit_final'); const luzFondo = document.getElementById('luz-fondo'); const mainContainer = document.getElementById('main_container');
        const form = document.getElementById('formTransaccion'); const inputNotas = document.getElementById('input_notas');

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('ultimo_tipo_registro') === 'ingreso') btnIngreso.click(); 
            else btnGasto.click(); 
        });

        // NAVEGACIÓN CARPETAS
        function abrirSubcategorias(id, tipo) {
            document.getElementById(`grid_padres_${tipo}`).classList.add('hidden');
            document.getElementById(`grid_hijos_${tipo}_${id}`).classList.remove('hidden');
        }
        function volverCategorias(tipo) {
            document.querySelectorAll(`.subgrid-${tipo}`).forEach(el => el.classList.add('hidden'));
            document.getElementById(`grid_padres_${tipo}`).classList.remove('hidden');
        }

        // SELECCIÓN BANCOS
        btnCuentas.forEach(btn => {
            btn.addEventListener('click', () => {
                btnCuentas.forEach(b => { b.classList.remove('card-selected'); b.classList.add('opacity-70'); });
                btn.classList.add('card-selected'); btn.classList.remove('opacity-70');
                hiddenCuenta.value = btn.getAttribute('data-id');
                displayBalance.textContent = `Disponible: RD$ ${btn.getAttribute('data-balance')}`;
                displayBalance.classList.remove('opacity-0');
            });
        });

        // SELECCIÓN CATEGORÍAS
        document.body.addEventListener('click', function(e) {
            const btnG = e.target.closest('.btn-cat-gasto');
            if (btnG) {
                document.querySelectorAll('.btn-cat-gasto').forEach(b => b.firstElementChild.classList.remove('cat-selected'));
                btnG.firstElementChild.classList.add('cat-selected');
                hiddenGasto.value = btnG.getAttribute('data-id');
                const nombreCat = btnG.getAttribute('data-nombre');
                if(inputNotas.value === '' || inputNotas.value === nombreCat) inputNotas.value = nombreCat; // Autorellenar si está vacío
            }

            const btnI = e.target.closest('.btn-cat-ingreso');
            if (btnI) {
                document.querySelectorAll('.btn-cat-ingreso').forEach(b => b.firstElementChild.classList.remove('cat-selected'));
                btnI.firstElementChild.classList.add('cat-selected');
                hiddenIngreso.value = btnI.getAttribute('data-id');
                const nombreCat = btnI.getAttribute('data-nombre');
                if(inputNotas.value === '' || inputNotas.value === nombreCat) inputNotas.value = nombreCat;
            }
        });

        // CAMBIO DE TABS (Gasto/Ingreso)
        btnGasto.addEventListener('click', () => {
            inputTipo.value = 'gasto'; localStorage.setItem('ultimo_tipo_registro', 'gasto');
            btnGasto.className = 'flex-1 bg-white text-black font-bold py-2.5 rounded-xl text-sm transition-all shadow-sm';
            btnIngreso.className = 'flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-sm transition-all hover:text-white bg-transparent';
            secGastos.classList.remove('hidden'); secIngresos.classList.add('hidden');
            volverCategorias('gasto'); // Asegurar que empezamos en la vista de carpetas
            mainContainer.classList.remove('from-emerald-950/30'); mainContainer.classList.add('from-red-950/30');
            luzFondo.className = 'absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-[500px] bg-[#CE1126]/15 rounded-full blur-[100px] pointer-events-none -z-10 transition-colors duration-1000';
            btnSubmitFinal.className = 'w-full bg-gradient-to-r from-[#CE1126] to-[#a30d1e] text-white font-bold py-4 rounded-2xl text-lg transition-all active:scale-[0.98] shadow-lg shadow-red-900/50';
            btnSubmitFinal.textContent = 'Guardar Gasto'; montoInput.classList.replace('text-emerald-400', 'text-white');
        });

        btnIngreso.addEventListener('click', () => {
            inputTipo.value = 'ingreso'; localStorage.setItem('ultimo_tipo_registro', 'ingreso');
            btnIngreso.className = 'flex-1 bg-[#18181b] border border-slate-700 text-white font-bold py-2.5 rounded-xl text-sm transition-all shadow-sm';
            btnGasto.className = 'flex-1 text-slate-500 font-bold py-2.5 rounded-xl text-sm transition-all hover:text-white bg-transparent';
            secIngresos.classList.remove('hidden'); secGastos.classList.add('hidden');
            volverCategorias('ingreso'); 
            mainContainer.classList.remove('from-red-950/30'); mainContainer.classList.add('from-emerald-950/30');
            luzFondo.className = 'absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-[500px] bg-emerald-500/15 rounded-full blur-[100px] pointer-events-none -z-10 transition-colors duration-1000';
            btnSubmitFinal.className = 'w-full bg-gradient-to-r from-emerald-600 to-emerald-700 text-white font-bold py-4 rounded-2xl text-lg transition-all active:scale-[0.98] shadow-lg shadow-emerald-900/50';
            btnSubmitFinal.textContent = 'Guardar Ingreso'; montoInput.classList.replace('text-white', 'text-emerald-400');
        });

        btnSubmitFinal.addEventListener('click', () => {
            if(!montoInput.value || montoInput.value <= 0) return alert("Por favor ingresa una cantidad válida.");
            if(!hiddenCuenta.value) return alert("Por favor selecciona un Banco o Cartera.");
            if(inputTipo.value === 'gasto' && !hiddenGasto.value) return alert("Por favor selecciona una Categoría de gasto.");
            if(inputTipo.value === 'ingreso' && !hiddenIngreso.value) return alert("Por favor selecciona una Fuente de ingreso.");
            form.submit();
        });

        // ==========================================
        // LÓGICA DEL MODAL DE CATEGORÍA ON THE FLY
        // ==========================================
        function seleccionarIconoModal(ico, el) {
            document.getElementById('modal_cat_icono').value = ico;
            document.querySelectorAll('.btn-icono-modal').forEach(b => b.classList.remove('border-emerald-500', 'text-emerald-400'));
            if(el) el.classList.add('border-emerald-500', 'text-emerald-400');
        }

        function seleccionarColorModal(col, el) {
            document.getElementById('modal_cat_color').value = col;
            document.querySelectorAll('.btn-color-modal').forEach(b => b.classList.remove('border-white', 'scale-125', 'border-2'));
            if(el) el.classList.add('border-white', 'scale-125', 'border-2');
        }

        function abrirModalNuevaCat(tipo, idPadre, colorPadre, iconoPadre) {
            document.getElementById('modal_nueva_cat').classList.remove('hidden');
            document.getElementById('modal_cat_nombre').value = '';
            document.getElementById('modal_cat_tipo').value = tipo;
            document.getElementById('modal_cat_padre').value = idPadre || '';

            if(idPadre) {
                document.getElementById('seccion_diseno_cat_modal').classList.add('hidden');
                document.getElementById('modal_cat_icono').value = iconoPadre;
                document.getElementById('modal_cat_color').value = colorPadre;
            } else {
                document.getElementById('seccion_diseno_cat_modal').classList.remove('hidden');
                seleccionarIconoModal('ph-tag', document.querySelector('.btn-icono-modal'));
                seleccionarColorModal('slate', document.querySelector('.btn-color-modal'));
            }
        }

        function cerrarModalNuevaCat() {
            document.getElementById('modal_nueva_cat').classList.add('hidden');
        }

        function aplicarNuevaCat() {
            const nombre = document.getElementById('modal_cat_nombre').value.trim();
            if(!nombre) return alert('Debes ponerle un nombre a la categoría.');

            const tipo = document.getElementById('modal_cat_tipo').value;
            const padreId = document.getElementById('modal_cat_padre').value;
            const icono = document.getElementById('modal_cat_icono').value;
            const color = document.getElementById('modal_cat_color').value;

            document.getElementById('nueva_cat_nombre').value = nombre;
            document.getElementById('nueva_cat_padre').value = padreId;
            document.getElementById('nueva_cat_icono').value = icono;
            document.getElementById('nueva_cat_color').value = color;

            if(tipo === 'gasto') {
                hiddenGasto.value = 'nueva';
                document.querySelectorAll('.btn-cat-gasto').forEach(b => b.firstElementChild.classList.remove('cat-selected'));
            } else {
                hiddenIngreso.value = 'nueva';
                document.querySelectorAll('.btn-cat-ingreso').forEach(b => b.firstElementChild.classList.remove('cat-selected'));
            }
            inputNotas.value = nombre;

            const gridDestino = padreId ? document.getElementById(`grid_hijos_${tipo}_${padreId}`).querySelector('.container-grid-botones') : document.getElementById(`grid_padres_${tipo}`);
            
            const btnHtml = document.createElement('button');
            btnHtml.type = 'button';
            btnHtml.className = `btn-cat-${tipo} flex flex-col items-center gap-2.5 group focus:outline-none`;
            btnHtml.setAttribute('data-nombre', nombre);
            btnHtml.innerHTML = `
                <div class="w-14 h-14 rounded-2xl bg-${color}-500/10 border border-${color}-500/30 flex items-center justify-center text-${color}-400 cat-selected shadow-inner">
                    <i class="ph ${icono} text-2xl"></i>
                </div>
                <span class="text-[10px] text-white font-bold w-full text-center truncate">${nombre} (Nueva)</span>
            `;
            
            gridDestino.insertBefore(btnHtml, gridDestino.lastElementChild);
            cerrarModalNuevaCat();
        }
    </script>
</body>
</html>