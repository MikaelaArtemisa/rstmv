<?php
session_start();
require_once '../includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit;
}

// Verificar si se recibió el ID del post
if (!isset($_POST['post_id']) || empty($_POST['post_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID de post no proporcionado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id']);

try {
    // Verificar que el post existe y pertenece al usuario
    $stmt = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$post_id, $user_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post no encontrado o no tienes permisos para eliminarlo']);
        exit;
    }
    
    // Eliminar el post
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$post_id, $user_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Post eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar el post']);
    }
    
} catch (PDOException $e) {
    error_log("Error al eliminar post: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}
?> 