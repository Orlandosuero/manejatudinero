<?php
// 1. Requerir la conexión a la base de datos
require_once 'config/database.php';

// 2. Verificar que los datos vienen por el método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Capturar y limpiar los datos del formulario
    $origen_id   = (int) $_POST['cuenta_origen_id'];
    $destino_id  = (int) $_POST['cuenta_destino_id'];
    $monto       = (float) $_POST['monto'];
    $descripcion = htmlspecialchars(trim($_POST['descripcion']));
    $fecha       = date('Y-m-d'); // Fecha actual por defecto

    try {
        // 3. Iniciar Transacción SQL (Garantiza que todo se ejecute junto o nada se ejecute)
        $pdo->beginTransaction();

        // A. Registrar en el libro diario (Tabla transacciones)
        $sqlTransaccion = "INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, monto, fecha, descripcion) 
                           VALUES (:origen, :destino, :monto, :fecha, :descripcion)";
        $stmt = $pdo->prepare($sqlTransaccion);
        $stmt->execute([
            ':origen' => $origen_id,
            ':destino' => $destino_id,
            ':monto' => $monto,
            ':fecha' => $fecha,
            ':descripcion' => $descripcion
        ]);

        // B. Actualizar el balance de la cuenta Origen (Ej: Disminuir Efectivo/Banco)
        $sqlRestar = "UPDATE cuentas SET balance = balance - :monto WHERE id = :id";
        $stmtRestar = $pdo->prepare($sqlRestar);
        $stmtRestar->execute([':monto' => $monto, ':id' => $origen_id]);

        // C. Actualizar el balance de la cuenta Destino (Ej: Aumentar Gasto/Inventario)
        $sqlSumar = "UPDATE cuentas SET balance = balance + :monto WHERE id = :id";
        $stmtSumar = $pdo->prepare($sqlSumar);
        $stmtSumar->execute([':monto' => $monto, ':id' => $destino_id]);

        // 4. Confirmar los cambios si no hubo errores
        $pdo->commit();

        // 5. Redirigir al Dashboard con mensaje de éxito
        header("Location: index.php?status=success");
        exit;

    } catch (Exception $e) {
        // Si hay error, revertir todo (RollBack) para mantener los libros cuadrados
        $pdo->rollBack();
        die("Error crítico de sistema: " . $e->getMessage());
    }
} else {
    // Si intentan entrar directo al archivo sin enviar formulario, devolver al inicio
    header("Location: index.php");
    exit;
}
?>