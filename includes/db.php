<?php

// Configuración de la base de datos
// ¡IMPORTANTE!: En un entorno de producción, estas credenciales NO deben estar hardcodeadas aquí.
// Considera usar variables de entorno o un archivo de configuración fuera del directorio público.
define('DB_HOST', 'localhost'); // O la IP de tu servidor de base de datos
define('DB_NAME', 'webecap'); // Reemplaza con el nombre real de tu DB
define('DB_USER', 'root');         // Reemplaza con tu usuario de DB
define('DB_PASS', '');      // Reemplaza con tu contraseña de DB

// DSN (Data Source Name)
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

// Opciones de PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en caso de error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva la emulación de prepared statements (más seguro y rápido)
];

// Intentar la conexión
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // echo "Conexión exitosa a la base de datos."; // Solo para depuración, puedes eliminarlo en producción
} catch (PDOException $e) {
    // Manejo de errores de conexión
    // En un entorno de producción, registra el error y muestra un mensaje genérico al usuario
    // para evitar exponer información sensible de la base de datos.
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

// $pdo ahora es tu objeto de conexión a la base de datos
// Puedes usarlo para ejecutar consultas de forma segura.

?>