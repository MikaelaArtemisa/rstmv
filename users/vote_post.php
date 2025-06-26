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
if (!isset($_POST['post_id']) || !isset($_POST['vote_type'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$post_id = intval($_POST['post_id']);
$vote_type = $_POST['vote_type'];

// Validar tipo de voto
if (!in_array($vote_type, ['like', 'dislike', 'fama'])) {
    echo json_encode(['success' => false, 'error' => 'Tipo de voto inválido']);
    exit;
}

try {
    // Verificar que el post existe
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Post no encontrado']);
        exit;
    }

    // Verificar si el usuario ya votó este tipo en este post
    $stmt = $pdo->prepare("SELECT id FROM post_votes WHERE post_id = ? AND user_id = ? AND vote_type = ?");
    $stmt->execute([$post_id, $user_id, $vote_type]);
    $existing_vote = $stmt->fetch();

    if ($existing_vote) {
        // Si ya votó, eliminar el voto (toggle)
        $stmt = $pdo->prepare("DELETE FROM post_votes WHERE post_id = ? AND user_id = ? AND vote_type = ?");
        $stmt->execute([$post_id, $user_id, $vote_type]);
        $action = 'removed';
    } else {
        // Si no ha votado, agregar el voto
        $stmt = $pdo->prepare("INSERT INTO post_votes (post_id, user_id, vote_type) VALUES (?, ?, ?)");
        $stmt->execute([$post_id, $user_id, $vote_type]);
        $action = 'added';
    }

    // Obtener conteos actualizados
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN vote_type = 'like' THEN 1 ELSE 0 END) as likes,
            SUM(CASE WHEN vote_type = 'dislike' THEN 1 ELSE 0 END) as dislikes,
            SUM(CASE WHEN vote_type = 'fama' THEN 1 ELSE 0 END) as famas
        FROM post_votes 
        WHERE post_id = ?
    ");
    $stmt->execute([$post_id]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'action' => $action,
        'counts' => [
            'likes' => intval($counts['likes'] ?? 0),
            'dislikes' => intval($counts['dislikes'] ?? 0),
            'famas' => intval($counts['famas'] ?? 0)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error en vote_post.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
} 