<?php
session_start();
require_once '../includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Obtener datos del formulario
$tipo = trim($_POST['tipo'] ?? '');
$contenido = trim($_POST['contenido'] ?? '');

// Validaciones
$hayImagen = isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK;
if (empty($contenido) && !$hayImagen) {
    echo json_encode(['success' => false, 'error' => 'Debes escribir algo o seleccionar una imagen.']);
    exit;
}

if (empty($tipo)) {
    echo json_encode(['success' => false, 'error' => 'El tipo de publicación es requerido']);
    exit;
}

// Validar tipo de publicación
$tipos_permitidos = ['POST', 'CONFESIÓN PUBLICA', 'CONFESIÓN ANONIMA'];
if (!in_array($tipo, $tipos_permitidos)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de publicación no válido']);
    exit;
}

// Limpiar contenido HTML pero permitir formato básico
$contenido_limpio = strip_tags($contenido, '<strong><b><em><i><u><s><strike>');

// Procesar imagen si se subió
$imagen_path = null;
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $imagen = $_FILES['imagen'];
    
    // Validaciones de imagen
    $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $tamano_maximo = 5 * 1024 * 1024; // 5MB
    
    // Verificar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tipo_mime = finfo_file($finfo, $imagen['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($tipo_mime, $tipos_permitidos)) {
        echo json_encode(['success' => false, 'error' => 'Formato de imagen no válido. Solo se permiten JPG, PNG y GIF']);
        exit;
    }
    
    // Verificar tamaño
    if ($imagen['size'] > $tamano_maximo) {
        echo json_encode(['success' => false, 'error' => 'La imagen es demasiado grande. Máximo 5MB']);
        exit;
    }
    
    // Verificar extensión
    $extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($extension, $extensiones_permitidas)) {
        echo json_encode(['success' => false, 'error' => 'Extensión de archivo no válida']);
        exit;
    }
    
    // Crear directorio de uploads si no existe
    $upload_dir = '../uploads/post_images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generar nombre único para la imagen
    $nombre_archivo = 'post_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $ruta_completa = $upload_dir . $nombre_archivo;
    
    // Mover archivo subido
    if (!move_uploaded_file($imagen['tmp_name'], $ruta_completa)) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar la imagen']);
        exit;
    }
    
    $imagen_path = $nombre_archivo;
}

try {
    // Insertar el post en la base de datos
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, username, tipo, contenido, imagen, fecha) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $username, $tipo, $contenido_limpio, $imagen_path]);
    
    $post_id = $pdo->lastInsertId();
    
    // Devolver respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Post publicado exitosamente',
        'post_id' => $post_id,
        'tipo' => $tipo,
        'contenido' => $contenido_limpio,
        'imagen' => $imagen_path,
        'username' => $username,
        'fecha' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    // Si hubo error y se subió una imagen, eliminarla
    if ($imagen_path && file_exists($upload_dir . $imagen_path)) {
        unlink($upload_dir . $imagen_path);
    }
    
    echo json_encode(['success' => false, 'error' => 'Error al guardar el post: ' . $e->getMessage()]);
}
?> 