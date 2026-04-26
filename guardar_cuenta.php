<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = htmlspecialchars(trim($_POST['nombre']));
    $clasificacion_id = (int) $_POST['clasificacion_id'];
    // El balance inicial suele ser 0, a menos que estés registrando una cuenta que ya tiene dinero
    $balance_inicial = !empty($_POST['balance_inicial']) ? (float) $_POST['balance_inicial'] : 0.00;

    try {
        $sql = "INSERT INTO cuentas (clasificacion_id, nombre, balance) VALUES (:clasificacion, :nombre, :balance)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':clasificacion' => $clasificacion_id,
            ':nombre' => $nombre,
            ':balance' => $balance_inicial
        ]);

        header("Location: cuentas.php?status=success");
        exit;
    } catch (Exception $e) {
        die("Error al guardar la cuenta: " . $e->getMessage());
    }
} else {
    header("Location: cuentas.php");
    exit;
}
?>