<?php
session_start();
require_once '../includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Obtener datos del formulario
$tipo = trim($_POST['tipo'] ?? '');
$contenido = trim($_POST['contenido'] ?? '');

// Validaciones
if (empty($tipo)) {
    http_response_code(400);
    echo json_encode(['error' => 'El tipo de publicación es requerido']);
    exit;
}

if (empty($contenido)) {
    http_response_code(400);
    echo json_encode(['error' => 'El contenido es requerido']);
    exit;
}

// Validar tipo de publicación
$tipos_permitidos = ['POST', 'CONFESIÓN PUBLICA', 'CONFESIÓN ANONIMA'];
if (!in_array($tipo, $tipos_permitidos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de publicación no válido']);
    exit;
}

// Limpiar contenido HTML pero permitir formato básico
$contenido_limpio = strip_tags($contenido, '<strong><b><em><i><u><s><strike>');

try {
    // Insertar el post en la base de datos
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, username, tipo, contenido, fecha) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $username, $tipo, $contenido_limpio]);
    
    $post_id = $pdo->lastInsertId();
    
    // Devolver respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Post publicado exitosamente',
        'post_id' => $post_id,
        'tipo' => $tipo,
        'contenido' => $contenido_limpio,
        'username' => $username,
        'fecha' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar el post: ' . $e->getMessage()]);
}
?> 