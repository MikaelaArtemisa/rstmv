<?php
// Configuración de la base de datos
$host = 'sql301.infinityfree.com';
$db   = 'if0_39274452_inacx'; // Cambia esto por el nombre real de tu base de datos
$user = 'if0_39274452'; // Cambia si tu usuario es distinto
$pass = 'inacx123'; // Cambia si tu contraseña no está vacía
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Modo de errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve resultados como arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa sentencias preparadas reales
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"     // Asegura codificación UTF-8
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
