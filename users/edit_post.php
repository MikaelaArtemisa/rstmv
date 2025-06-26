<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validar datos
if (!isset($_POST['post_id']) || !isset($_POST['contenido'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$post_id = intval($_POST['post_id']);
$contenido = trim($_POST['contenido']);

// Verificar que el post pertenece al usuario
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$post_id, $user_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Procesar imagen si se subió una nueva
$imagen_filename = $post['imagen'];
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['imagen'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Formato de imagen no válido']);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'La imagen es demasiado grande (máx 5MB)']);
        exit;
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'post_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $upload_dir = '../uploads/post_images/';
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
        echo json_encode(['success' => false, 'error' => 'Error al subir la imagen']);
        exit;
    }
    // Opcional: eliminar imagen anterior si existe y es diferente
    if ($imagen_filename && file_exists($upload_dir . $imagen_filename)) {
        @unlink($upload_dir . $imagen_filename);
    }
    $imagen_filename = $new_filename;
}

// Actualizar post
$stmt = $pdo->prepare('UPDATE posts SET contenido = ?, imagen = ? WHERE id = ? AND user_id = ?');
$ok = $stmt->execute([$contenido, $imagen_filename, $post_id, $user_id]);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar el post']);
} 