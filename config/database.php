<?php
$host = '127.0.0.1';
$db   = 'maneja_tu_dinero';
$user = 'root';
$pass = ''; // Recuerda: si usas MAMP, suele ser 'root'. Si usas Herd, déjalo vacío.
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Imprimimos un mensaje de éxito en pantalla


} catch (\PDOException $e) {
    // Si falla, mostramos el error exacto

}
