<?php
session_start();
require_once '../includes/db.php'; // Ajusta el path si es necesario

// Crear tabla post_votes si no existe
try {
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS post_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        vote_type ENUM('like', 'dislike', 'fama') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (post_id, user_id, vote_type),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($createTableSQL);
    
    // Crear índices si no existen
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_post_votes_post_id ON post_votes(post_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_post_votes_user_id ON post_votes(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_post_votes_type ON post_votes(vote_type)");
} catch (PDOException $e) {
    error_log("Error creando tabla post_votes: " . $e->getMessage());
}

// Agregar campo famas a la tabla users si no existe
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS famas INT DEFAULT 0");
} catch (PDOException $e) {
    error_log("Error agregando campo famas: " . $e->getMessage());
}

// Crear tabla user_famas si no existe
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_famas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voter_id INT NOT NULL,
            target_user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_fama (voter_id, target_user_id),
            FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    error_log("Error creando tabla user_famas: " . $e->getMessage());
}

// Endpoint para obtener famas actualizado
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_famas') {
    header('Content-Type: application/json');
    
    $username_param = $_GET['username'] ?? '';
    
    if (empty($username_param)) {
        echo json_encode(['success' => false, 'error' => 'Username requerido']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT famas FROM users WHERE username = ?");
        $stmt->execute([$username_param]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'famas' => intval($user['famas'] ?? 0)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    }
    exit;
}

// Determinar a quién mostrar
$username_param = isset($_GET['username']) ? trim($_GET['username']) : null;

if ($username_param) {
    // Buscar usuario por username
    $stmt = $pdo->prepare("SELECT id, email, username, aboutme, profile_picture, famas FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username_param]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_info) {
        // Usuario no encontrado
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Perfil no encontrado</title></head><body style="background:#0A0F1E;color:#fff;"><div style="text-align:center;margin-top:10vh;"><h2>Usuario no encontrado</h2><a href="dashboard.php" style="color:#c2a4ff;">Volver al inicio</a></div></body></html>';
        exit;
    }
    $user_id = $user_info['id'];
    $username = $user_info['username'];
    $is_own_profile = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id);
} else {
    // Perfil propio (requiere login)
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php?expired=1');
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $stmt = $pdo->prepare("SELECT id, email, username, aboutme, profile_picture, famas FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_own_profile = true;
}

// Obtener SOLO los posts del usuario mostrado con conteos de votos
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               COALESCE(SUM(CASE WHEN pv.vote_type = 'like' THEN 1 ELSE 0 END), 0) as likes_count,
               COALESCE(SUM(CASE WHEN pv.vote_type = 'dislike' THEN 1 ELSE 0 END), 0) as dislikes_count,
               COALESCE(SUM(CASE WHEN pv.vote_type = 'fama' THEN 1 ELSE 0 END), 0) as famas_count
        FROM posts p 
        LEFT JOIN post_votes pv ON p.id = pv.post_id
        WHERE p.user_id = ?
        GROUP BY p.id, p.user_id, p.tipo, p.contenido, p.imagen, p.fecha
        ORDER BY p.fecha DESC
    ");
    $stmt->execute([$user_id]);
    $all_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si hay error, usar consulta simple sin votos
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY fecha DESC");
    $stmt->execute([$user_id]);
    $all_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar conteos de votos como 0 por defecto
    foreach ($all_posts as &$post) {
        $post['likes_count'] = 0;
        $post['dislikes_count'] = 0;
        $post['famas_count'] = 0;
    }
}

// Calcular contadores por tipo de post (sobre todos los posts)
$fama_count = 0;
$conf_publica_count = 0;
$conf_anonima_count = 0;
foreach ($all_posts as $post) {
    if ($post['tipo'] === 'POST') $fama_count++;
    if ($post['tipo'] === 'CONFESIÓN PUBLICA') $conf_publica_count++;
    if ($post['tipo'] === 'CONFESIÓN ANONIMA') $conf_anonima_count++;
}

// Para mostrar en la galería:
if ($is_own_profile) {
    $posts = $all_posts; // El usuario ve todos sus posts
} else {
    // Los demás no ven confesiones anónimas
    $posts = array_filter($all_posts, function($post) {
        return $post['tipo'] !== 'CONFESIÓN ANONIMA';
    });
    $posts = array_values($posts);
}

// Función para obtener la URL de la imagen de perfil
function getProfilePictureUrl($profile_picture, $username) {
    if (!empty($profile_picture)) {
        return '../uploads/profile_pictures/' . htmlspecialchars($profile_picture);
    } else {
        // Avatar por defecto usando las iniciales del username
        return 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=330066&color=c2a4ff&size=110';
    }
}

// Función para procesar contenido HTML y convertirlo a formato visual
function processContent($content) {
    // Convertir etiquetas HTML a formato visual
    $content = str_replace(['<strong>', '<b>'], '**', $content);
    $content = str_replace(['</strong>', '</b>'], '**', $content);
    $content = str_replace(['<em>', '<i>'], '*', $content);
    $content = str_replace(['</em>', '</i>'], '*', $content);
    $content = str_replace(['<u>'], '__', $content);
    $content = str_replace(['</u>'], '__', $content);
    $content = str_replace(['<s>', '<strike>'], '~~', $content);
    $content = str_replace(['</s>', '</strike>'], '~~', $content);
    
    // Limpiar cualquier otra etiqueta HTML
    $content = strip_tags($content);
    
    return $content;
}

// Función para procesar contenido y mostrar formato real
function processContentForDisplay($content) {
    // Convertir etiquetas HTML a formato visual real
    $content = str_replace(['<strong>', '<b>'], '**', $content);
    $content = str_replace(['</strong>', '</b>'], '**', $content);
    $content = str_replace(['<em>', '<i>'], '*', $content);
    $content = str_replace(['</em>', '</i>'], '*', $content);
    $content = str_replace(['<u>'], '__', $content);
    $content = str_replace(['</u>'], '__', $content);
    $content = str_replace(['<s>', '<strike>'], '~~', $content);
    $content = str_replace(['</s>', '</strike>'], '~~', $content);
    
    // Aplicar formato real
    $content = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content);
    $content = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $content);
    $content = preg_replace('/__(.*?)__/s', '<u>$1</u>', $content);
    $content = preg_replace('/~~(.*?)~~/s', '<s>$1</s>', $content);
    
    return $content;
}

// Procesar votos si se recibe una petición POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vote') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id'] ?? 0);
    $vote_type = $_POST['vote_type'] ?? '';
    
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
            // Si ya votó, no permitir votar de nuevo (botón bloqueado)
            echo json_encode(['success' => false, 'error' => 'Ya has votado este tipo']);
            exit;
        } else {
            // Si no ha votado, agregar el voto
            $stmt = $pdo->prepare("INSERT INTO post_votes (post_id, user_id, vote_type) VALUES (?, ?, ?)");
            $stmt->execute([$post_id, $user_id, $vote_type]);
            
            // Si es un voto de fama, actualizar el contador de famas del usuario del post
            if ($vote_type === 'fama') {
                // Obtener el user_id del post
                $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
                $stmt->execute([$post_id]);
                $post_user = $stmt->fetch();
                
                if ($post_user && $post_user['user_id'] != $user_id) { // No votarse a sí mismo
                    // Actualizar famas del usuario del post
                    $stmt = $pdo->prepare("UPDATE users SET famas = famas + 1 WHERE id = ?");
                    $stmt->execute([$post_user['user_id']]);
                }
            }
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
            'counts' => [
                'likes' => intval($counts['likes'] ?? 0),
                'dislikes' => intval($counts['dislikes'] ?? 0),
                'famas' => intval($counts['famas'] ?? 0)
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Error en votación: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    }
    exit;
}

// Procesar voto de famas directo al usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'give_fama_user') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        exit;
    }
    
    $voter_id = $_SESSION['user_id'];
    $target_user_id = intval($_POST['target_user_id'] ?? 0);
    
    if ($voter_id == $target_user_id) {
        echo json_encode(['success' => false, 'error' => 'No puedes darte famas a ti mismo']);
        exit;
    }
    
    try {
        // Verificar que el usuario objetivo existe
        $stmt = $pdo->prepare("SELECT id, username, famas FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $target_user = $stmt->fetch();
        
        if (!$target_user) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            exit;
        }
        
        // Verificar si ya le dio famas a este usuario
        $stmt = $pdo->prepare("SELECT id FROM user_famas WHERE voter_id = ? AND target_user_id = ?");
        $stmt->execute([$voter_id, $target_user_id]);
        $existing_fama = $stmt->fetch();
        
        if ($existing_fama) {
            echo json_encode(['success' => false, 'error' => 'Ya le has dado famas a este usuario']);
            exit;
        }
        
        // Insertar el voto de fama
        $stmt = $pdo->prepare("INSERT INTO user_famas (voter_id, target_user_id) VALUES (?, ?)");
        $stmt->execute([$voter_id, $target_user_id]);
        
        // Actualizar famas del usuario objetivo
        $stmt = $pdo->prepare("UPDATE users SET famas = famas + 1 WHERE id = ?");
        $stmt->execute([$target_user_id]);
        
        // Obtener famas actualizado
        $stmt = $pdo->prepare("SELECT famas FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $updated_user = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'new_famas' => intval($updated_user['famas']),
            'message' => 'Famas otorgado exitosamente'
        ]);
        
    } catch (PDOException $e) {
        error_log("Error dando famas al usuario: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mi Perfil - Publicaciones</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <style>
    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
      background-color: #0A0F1E;
      color: #E0F7FA;
    }
    body {
      min-height: 100vh;
      width: 100vw;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding-bottom: 80px; /* Espacio para el navbar inferior */
    }
    .container-full {
      width: 100vw;
      max-width: 100vw;
      padding-left: 0;
      padding-right: 0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 80vh;
      margin-top: 80px; /* Margen superior para evitar que se superponga con elementos del navegador */
    }
    .insta-avatar {
      width: 110px;
      height: 110px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #c2a4ff;
      background: #222;
      display: block;
      margin: 0 auto;
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    .insta-avatar:hover {
      transform: scale(1.05);
    }
    .insta-profile-header {
      display: flex;
      align-items: center;
      gap: 2rem;
      justify-content: center;
      margin-bottom: 2rem;
      flex-wrap: wrap;
    }
    .insta-profile-info {
      flex: 1;
      min-width: 220px;
    }
    .insta-profile-username {
      font-size: 2rem;
      font-weight: bold;
      color: #c2a4ff;
    }
    .insta-profile-stats {
      display: flex;
      gap: 2rem;
      margin: 1rem 0;
    }
    .insta-profile-stat {
      text-align: center;
    }
    .insta-profile-stat span {
      display: block;
      font-weight: bold;
      font-size: 1.2rem;
      color: #E0F7FA;
    }
    .insta-profile-stat small {
      color: #aaa;
    }
    .insta-gallery {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.2rem;
      width: 100vw;
      max-width: 100vw;
      margin-left: 0;
      margin-right: 0;
    }
    @media (min-width: 600px) {
      .insta-gallery {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (min-width: 900px) {
      .insta-gallery {
        grid-template-columns: repeat(3, 1fr);
      }
    }
    .insta-post-card {
      background: #181c2f;
      border-radius: 12px;
      overflow: hidden;
      aspect-ratio: 1 / 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      box-shadow: 0 2px 8px #0002;
      border: 1px solid #2a2a3a;
      padding: 1rem;
      position: relative;
      min-width: 0;
      min-height: 0;
      height: 100%;
      width: 100%;
      max-width: 100vw;
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    .insta-post-card:hover {
      transform: scale(1.02);
    }
    .insta-post-type {
      position: absolute;
      top: 10px;
      left: 10px;
      background: #330066;
      color: #c2a4ff;
      border-radius: 8px;
      padding: 0.2em 0.7em;
      font-size: 0.85em;
      font-weight: bold;
      margin-bottom: 1px;
    }
    .insta-post-date {
      position: absolute;
      top: 10px;
      right: 10px;
      color: #aaa;
      font-size: 0.8em;
    }
    .insta-post-content {
      color: #E0F7FA;
      font-size: 1.1em;
      text-align: center;
      margin-top: 2.5em;
      margin-bottom: 1em;
      word-break: break-word;
      width: 100%;
      white-space: pre-wrap;
      padding-left: 10px;
      padding-right: 10px;
    }
    .insta-post-image {
      transition: transform 0.2s ease;
    }
    .insta-post-image:hover {
      transform: scale(1.02);
    }
    @media (max-width: 600px) {
      .insta-profile-header {
        flex-direction: column;
        gap: 1rem;
      }
      .insta-profile-info {
        text-align: center;
      }
    }
    .mini-fake-posts {
      grid-template-columns: repeat(3, 1fr) !important;
      gap: 0.7rem !important;
    }
    .mini-post-card {
      aspect-ratio: 1/1;
      min-height: 80px;
      max-width: 120px;
      padding: 0.5rem !important;
      font-size: 0.92em;
    }
    @media (max-width: 600px) {
      .mini-fake-posts {
        grid-template-columns: repeat(2, 1fr) !important;
      }
      .mini-post-card {
        max-width: 90px;
        min-height: 60px;
      }
    }
    .centered-section {
      min-height: 50vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }
    .explora-section {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 30vh;
    }
    .explora-section h3 {
      width: 100%;
      text-align: center;
    }
    .compact-gallery {
      max-width: 700px;
      margin-left: auto;
      margin-right: auto;
    }
    @media (max-width: 800px) {
      .compact-gallery {
        max-width: 400px;
      }
    }
    .modal-image {
      max-width: 100%;
      max-height: 80vh;
      object-fit: contain;
    }
    
    /* Estilos del formulario de publicación (igual que dashboard.php) */
    .publish-form {
      background-color: #0d0d0d;
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
      color: #e0e0e0;
      font-family: sans-serif;
    }
    
    .publish-form .bottom-content {
      display: flex;
      align-items: center;
      gap: 8px;
      width: 100%;
      justify-content: center;
      margin-bottom: 10px;
    }
    
    .publish-form .input-wrapper {
      flex-grow: 1;
      border: 1px solid #6a0dad;
      border-radius: 5px;
      padding: 10px;
      display: flex;
      align-items: flex-start;
      min-height: 120px;
      margin-bottom: -1px;
      background-color: #1a1a1a;
    }
    
    .publish-form .input-field {
      border: none;
      outline: none;
      width: 100%;
      font-size: 1em;
      color: #e0e0e0;
      resize: none;
      padding: 10px;
      margin: 0;
      font-family: sans-serif;
      box-sizing: border-box;
      background-color: transparent;
      min-height: 120px;
      cursor: text;
    }
    
    .publish-form .input-field::placeholder {
      color: #888;
    }
    
    .publish-form .toolbar {
      width: 100%;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      gap: 15px;
      padding: 10px 0;
      border: 1px solid #6a0dad;
      border-top: none;
      border-radius: 0 0 5px 5px;
      background-color: #330066;
      padding-left: 10px;
      margin-top: -10px;
      z-index: 1;
    }
    
    .publish-form .toolbar-icon {
      font-size: 1.2em;
      color: #e0e0e0;
      cursor: pointer;
      transition: color 0.2s ease;
    }
    
    .publish-form .toolbar-icon:hover {
      color: #9933ff;
    }
    
    .publish-form .publish-button {
      margin-left: auto;
      padding: 5px 15px;
      border: 1px solid #9933ff;
      background-color: #9933ff;
      color: white;
      border-radius: 5px;
      cursor: pointer;
      font-size: 0.9em;
      margin-right: 10px;
      transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    
    .publish-form .publish-button:hover {
      background-color: #6a0dad;
      border-color: #6a0dad;
    }
    
    .publish-form .publish-button:disabled {
      background-color: #666;
      border-color: #666;
      cursor: not-allowed;
    }

    /* Estilos responsivos para móviles */
    @media (max-width: 768px) {
      .container-full {
        margin-top: 450px !important;
        padding-top: 20px !important;
        padding-bottom: 120px !important;
        min-height: auto;
      }
      .insta-profile-header {
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding: 0 15px;
      }
      .insta-profile-info {
        text-align: center;
        width: 100%;
      }
      .insta-avatar {
        width: 90px;
        height: 90px;
      }
      .insta-profile-username {
        font-size: 1.5rem;
      }
      .insta-profile-stats {
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
      }
      .insta-gallery {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 0 15px;
        margin-bottom: 100px; /* Espacio extra para el navbar */
      }
      .insta-post-card {
        aspect-ratio: auto;
        min-height: 200px;
        max-height: 300px;
      }
      .insta-profile-info-extra {
        margin: 0 10px;
      }
    }
    @media (max-width: 480px) {
      .container-full {
        margin-top: 500px !important;
        padding-top: 15px !important;
        padding-bottom: 140px !important;
      }
      .insta-avatar {
        width: 80px;
        height: 80px;
      }
      .insta-profile-username {
        font-size: 1.3rem;
      }
      .insta-profile-stats {
        gap: 0.8rem;
        flex-wrap: wrap;
      }
      .insta-profile-stat {
        min-width: 80px;
      }
      .insta-profile-stat span {
        font-size: 1rem;
      }
      .insta-profile-stat small {
        font-size: 0.8rem;
      }
      .insta-post-content {
        font-size: 1em;
        margin-top: 2em;
      }
      .vote-buttons {
        bottom: 5px;
        left: 5px;
        gap: 8px;
      }
      .vote-btn {
        font-size: 1em !important;
      }
      .vote-count {
        font-size: 0.8em !important;
      }
      .insta-profile-header {
        padding: 0 10px;
      }
      .insta-gallery {
        padding: 0 10px;
        margin-bottom: 120px;
      }
    }
    
    /* Estilos para el botón Dar Famas */
    .give-fama-btn {
      transition: all 0.3s ease;
      border-radius: 20px;
      padding: 8px 16px;
      font-size: 0.9em;
      box-shadow: 0 2px 8px rgba(23, 162, 184, 0.3);
      background: #17a2b8 !important;
      border-color: #17a2b8 !important;
      color: white !important;
    }
    
    .give-fama-btn:hover:not(:disabled):not(.voted) {
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(23, 162, 184, 0.5);
      background: #138496 !important;
      border-color: #138496 !important;
    }
    
    .give-fama-btn:active:not(:disabled):not(.voted) {
      transform: scale(0.95);
    }
    
    .give-fama-btn.voted {
      pointer-events: none;
      cursor: not-allowed;
      background: #28a745 !important;
      border-color: #28a745 !important;
      color: white !important;
    }
    
    .give-fama-btn:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }
    
    /* Estilos para el texto promocional y URL */
    .profile-promo-text {
      color: #c2a4ff;
      font-size: 0.9em;
      margin-bottom: 5px;
      font-weight: 500;
    }
    
    .profile-promo-text i {
      color: #ff6b6b;
      margin-right: 5px;
    }
    
    .profile-url-text {
      color: #aaa;
      font-size: 0.8em;
      margin-bottom: 0;
    }
    
    .profile-url-text i {
      color: #17a2b8;
      margin-right: 5px;
    }
    
    .profile-url-link {
      color: #17a2b8;
      text-decoration: none;
      transition: color 0.2s ease;
    }
    
    .profile-url-link:hover {
      color: #138496;
      text-decoration: underline;
    }
  </style>
</head>
<body>
<?php include '../includes/partials/navbar.php'; ?>
<div class="container-full py-4" style="margin-top: 60px;">
  <!-- Encabezado tipo Instagram -->
  <div class="insta-profile-header mb-4">
    <img src="<?php echo getProfilePictureUrl($user_info['profile_picture'], $username); ?>" alt="Avatar" class="insta-avatar" onclick="showImageModal(this.src, '<?php echo htmlspecialchars($username); ?>')">
    <div class="insta-profile-info">
      <div class="insta-profile-username"><?php echo htmlspecialchars($username); ?>
      <?php if ($is_own_profile && !isset($_GET['username'])): ?>

      <?php endif; ?>
      <?php if ($is_own_profile): ?>
        <a href="edit-profile.php" class="btn btn-outline-light btn-sm">Editar perfil</a>
      <?php endif; ?>
      </div>
      <!-- Mostrar URL del perfil y botón para copiar -->
      <div class="mt-2 mb-2 d-flex align-items-center gap-2" style="flex-wrap:wrap;">
        <?php
          $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
          // Siempre incluir el username en la URL, incluso para el propio perfil
          $profile_url = $base_url . '/profile.php?username=' . urlencode($username);
        ?>
        <input type="text" id="profileUrlInput" class="form-control form-control-sm" value="<?php echo htmlspecialchars($profile_url); ?>" readonly style="max-width:320px; background:#222; color:#c2a4ff; border:1px solid #6a0dad;">
        <button class="btn btn-secondary btn-sm" type="button" onclick="copiarUrlPerfil()"><i class="bi bi-share"></i> Compartir perfil</button>
        
        <!-- Botón Dar Famas -->
        <?php if (!$is_own_profile): ?>
          <button class="btn btn-outline-info btn-sm give-fama-btn" 
                  data-user-id="<?php echo $user_id; ?>" 
                  data-username="<?php echo htmlspecialchars($username); ?>"
                  style="background: #17a2b8; border-color: #17a2b8; color: white; font-weight: bold; display: inline-block !important;">
            <i class="bi bi-gem"></i> Dar Famas
          </button>
        <?php endif; ?>
      </div>
      
      <!-- Texto promocional y URL del perfil -->
      <div class="mt-2 mb-2">
        <p class="profile-promo-text">
          <i class="bi bi-heart-fill"></i> 
          Sígueme en InacX y dame famas
        </p>
        <p class="profile-url-text">
          <i class="bi bi-link-45deg"></i> 
          <a href="<?php echo htmlspecialchars($profile_url); ?>" target="_blank" class="profile-url-link">
            <?php echo htmlspecialchars($profile_url); ?>
          </a>
        </p>
      </div>
      <script>
        function copiarUrlPerfil() {
          // Copiar el texto promocional + la URL del perfil
          var textoPromocional = "Sígueme en InacX y dame famas";
          var urlPerfil = document.getElementById('profileUrlInput').value;
          var textoCompleto = textoPromocional + " " + urlPerfil;
          
          // Crear un elemento temporal para copiar el texto
          var tempInput = document.createElement('textarea');
          tempInput.value = textoCompleto;
          document.body.appendChild(tempInput);
          tempInput.select();
          tempInput.setSelectionRange(0, 99999); // Para móviles
          document.execCommand('copy');
          document.body.removeChild(tempInput);
          
          // Mostrar feedback
          var btn = event.target.closest('button');
          var original = btn.innerHTML;
          btn.innerHTML = '<i class="bi bi-check-circle"></i> ¡Copiado!';
          setTimeout(function(){ btn.innerHTML = original; }, 1200);
        }
      </script>
      <div class="insta-profile-stats">
        <div class="insta-profile-stat">
          <span> 📝 <?php echo count($posts); ?> 📝</span>
          <small>Publicaciones</small>
        </div>
        <div class="insta-profile-stat">
          <span> 💎​​ <?php echo $user_info['famas'] ?? 0; ?> 💎​</span>
          <small>Fama</small>
        </div>
        <div class="insta-profile-stat">
          <span>​🙈 <?php echo $conf_anonima_count; ?> 🙈​</span>
          <small>Conf. Anónima</small>
        </div>
      </div>
      
      <!-- Información del perfil -->
      <div class="insta-profile-info-extra mt-2 mb-2 p-2" style="background:#181c2f; border-radius:10px;">
        <div><strong>⭐​:</strong> <?php echo htmlspecialchars($user_info['aboutme'] ?? ''); ?></div>
      </div>

    </div>
  </div>

  <!-- Galería de publicaciones tipo Instagram -->
  <div class="insta-gallery mb-5 compact-gallery">
    <?php if (empty($posts)): ?>
      <div class="insta-post-card" style="aspect-ratio: auto; min-height: 200px;">
        <div class="insta-post-content" style="color: #fff;">
          <p class="text-white">No hay publicaciones aún.</p>
          <?php if ($is_own_profile): ?>
            <div style="display: flex; justify-content: center;">
              <a href="dashboard.php" class="btn btn-primary btn-sm">Ir al dashboard para publicar</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <?php foreach (array_slice($posts, 0, 9) as $post): ?>
        <div class="insta-post-card" onclick="showPostModal('<?php echo htmlspecialchars($post['tipo']); ?>', '<?php echo htmlspecialchars(processContentForDisplay($post['contenido'])); ?>', '<?php echo date('d/m/Y H:i', strtotime($post['fecha'])); ?>', '<?php echo $post['id']; ?>')">
          <div class="insta-post-type"><?php echo htmlspecialchars($post['tipo']); ?></div>
          <div class="insta-post-date"><?php echo date('d/m/Y', strtotime($post['fecha'])); ?></div>
          
          <!-- Botones de votación -->
          <div class="vote-buttons" style="position: absolute; bottom: 10px; left: 10px; display: flex; gap: 10px; z-index: 10;">
            <div class="vote-btn" data-post-id="<?php echo $post['id']; ?>" data-vote-type="like" style="cursor: pointer; font-size: 1.2em;" onclick="event.stopPropagation();">❤️</div>
            <span class="vote-count" data-post-id="<?php echo $post['id']; ?>" data-vote-type="like" style="color: #c2a4ff; font-size: 0.9em;"><?php echo $post['likes_count']; ?></span>
            <div class="vote-btn" data-post-id="<?php echo $post['id']; ?>" data-vote-type="dislike" style="cursor: pointer; font-size: 1.2em;" onclick="event.stopPropagation();">☠️</div>
            <span class="vote-count" data-post-id="<?php echo $post['id']; ?>" data-vote-type="dislike" style="color: #c2a4ff; font-size: 0.9em;"><?php echo $post['dislikes_count']; ?></span>
            <div class="vote-btn" data-post-id="<?php echo $post['id']; ?>" data-vote-type="fama" style="cursor: pointer; font-size: 1.2em;" onclick="event.stopPropagation();">💎</div>
            <span class="vote-count" data-post-id="<?php echo $post['id']; ?>" data-vote-type="fama" style="color: #c2a4ff; font-size: 0.9em;"><?php echo $post['famas_count']; ?></span>
          </div>
          
          <?php if (!empty($post['imagen'])): ?>
            <div class="insta-post-image" style="width: 100%; height: 60%; margin-bottom: 10px;">
              <img src="../uploads/post_images/<?php echo htmlspecialchars($post['imagen']); ?>" 
                   alt="Imagen del post" 
                   style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
            </div>
          <?php endif; ?>
          <div class="insta-post-content" style="text-align: left; <?php echo !empty($post['imagen']) ? 'margin-top: 0;' : 'margin-top: 2.5em;'; ?>">
            <?php echo nl2br(processContentForDisplay(substr($post['contenido'], 0, 100))); ?><?php echo strlen($post['contenido']) > 100 ? '...' : ''; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

  <!-- Modal Bootstrap para publicar (igual que en dashboard.php) -->
  <div class="modal fade" id="modalPublicar" tabindex="-1" aria-labelledby="modalPublicarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalPublicarLabel">Publicar</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" id="modalPublicarBody">
          <!-- Aquí se insertará el formulario directamente -->
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para mostrar imagen completa -->
  <div class="modal fade" id="modalImagen" tabindex="-1" aria-labelledby="modalImagenLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalImagenLabel">Foto de perfil</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalImagenSrc" src="" alt="Imagen completa" class="modal-image">
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para mostrar imagen de post -->
  <div class="modal fade" id="modalImagenPost" tabindex="-1" aria-labelledby="modalImagenPostLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalImagenPostLabel">Imagen del post</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalImagenPostSrc" src="" alt="Imagen del post" class="modal-image">
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para ver post completo (igual que en dashboard.php) -->
  <div class="modal fade" id="modalVerPost" tabindex="-1" aria-labelledby="modalVerPostLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalVerPostLabel">Post completo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" id="modalVerPostBody"></div>
        <div class="modal-footer border-secondary" id="modalVerPostFooter">
          <!-- El botón de eliminar se agregará dinámicamente aquí -->
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de confirmación para eliminar post -->
  <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1" aria-labelledby="modalConfirmarEliminarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalConfirmarEliminarLabel">Confirmar eliminación</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p>¿Estás seguro de que quieres eliminar este post?</p>
          <p class="text-muted small">Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">Eliminar post</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de éxito para confirmar eliminación -->
  <div class="modal fade" id="modalEliminacionExitosa" tabindex="-1" aria-labelledby="modalEliminacionExitosaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalEliminacionExitosaLabel">
            <i class="bi bi-check-circle-fill text-success me-2"></i>Post eliminado
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p>El post ha sido eliminado exitosamente.</p>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-primary" onclick="location.reload()">Aceptar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de error para eliminación -->
  <div class="modal fade" id="modalErrorEliminacion" tabindex="-1" aria-labelledby="modalErrorEliminacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalErrorEliminacionLabel">
            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Error
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" id="modalErrorEliminacionBody">
          <p>Ha ocurrido un error al eliminar el post.</p>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de éxito para publicación -->
  <div class="modal fade" id="modalPublicacionExitosa" tabindex="-1" aria-labelledby="modalPublicacionExitosaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalPublicacionExitosaLabel">
            <i class="bi bi-check-circle-fill text-success me-2"></i>Post publicado
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p>Tu post ha sido publicado exitosamente.</p>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-primary" onclick="window.location.href='dashboard.php'">Aceptar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de error para publicación -->
  <div class="modal fade" id="modalErrorPublicacion" tabindex="-1" aria-labelledby="modalErrorPublicacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalErrorPublicacionLabel">
            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Error
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" id="modalErrorPublicacionBody">
          <p>Ha ocurrido un error al publicar el post.</p>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    // Función para mostrar imagen en modal
    function showImageModal(imageSrc, username) {
      const modal = new bootstrap.Modal(document.getElementById('modalImagen'));
      document.getElementById('modalImagenSrc').src = imageSrc;
      document.getElementById('modalImagenLabel').textContent = 'Foto de perfil de ' + username;
      modal.show();
    }

    // Función para mostrar imagen de post en modal
    function showPostImageModal(imageSrc, username) {
      const modal = new bootstrap.Modal(document.getElementById('modalImagenPost'));
      document.getElementById('modalImagenPostSrc').src = imageSrc;
      document.getElementById('modalImagenPostLabel').textContent = 'Imagen del post de ' + username;
      modal.show();
    }

    // Función para mostrar post completo en modal (igual que en dashboard.php)
    function showPostModal(tipo, contenido, fecha, postId = null) {
      const modal = new bootstrap.Modal(document.getElementById('modalVerPost'));
      document.getElementById('modalVerPostLabel').textContent = tipo;
      
      // Buscar la imagen del post en el elemento clickeado
      const clickedElement = event.currentTarget;
      const postImage = clickedElement.querySelector('.insta-post-image img');
      
      let modalContent = `<p style="text-align: left;">${contenido}</p>`;
      if (postImage) {
        modalContent += `<div class="text-center mt-3">
          <img src="${postImage.src}" alt="Imagen del post" style="max-width: 100%; max-height: 400px; border-radius: 8px; cursor: pointer;" onclick="showPostImageModal('${postImage.src}', '${tipo}')">
        </div>`;
      }
      
      // Mostrar 'Anónimo' si es confesión anónima
      if (tipo === 'CONFESIÓN ANONIMA') {
        modalContent = `<div style='font-weight:bold;'>Anónimo</div>` + modalContent;
      }
      
      document.getElementById('modalVerPostBody').innerHTML = modalContent;
      
      // Agregar botón de eliminar solo si es el perfil propio y se proporciona postId
      const footer = document.getElementById('modalVerPostFooter');
      if (<?php echo $is_own_profile ? 'true' : 'false'; ?> && postId) {
        footer.innerHTML = `
          <button type="button" class="btn btn-danger btn-sm" onclick="confirmarEliminarPost(${postId})">
            <i class="bi bi-trash"></i> Eliminar post
          </button>
        `;
      } else {
        footer.innerHTML = '';
      }
      
      modal.show();
    }

    // Variables globales para manejar la imagen seleccionada
    let selectedImageFile = null;

    // Función para manejar la selección de imagen
    function handleImageSelect(event) {
      const file = event.target.files[0];
      if (!file) return;

      // Validaciones de imagen - incluir formatos de celulares
      const tiposPermitidos = [
        'image/jpeg', 
        'image/jpg', 
        'image/png', 
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/bmp',
        'image/tiff',
        'image/tif'
      ];
      const tamanoMaximo = 5 * 1024 * 1024; // 5MB

      if (!tiposPermitidos.includes(file.type)) {
        mostrarError('Formato de imagen no válido. Formatos permitidos: JPG, PNG, GIF, WebP, HEIC, BMP, TIFF');
        return;
      }

      if (file.size > tamanoMaximo) {
        mostrarError('La imagen es demasiado grande. Máximo 5MB');
        return;
      }

      // Comprimir imagen antes de mostrar vista previa
      comprimirImagen(file, function(compressedFile) {
        // Mostrar vista previa con la imagen comprimida
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('imagePreview').src = e.target.result;
          document.getElementById('imagePreviewSection').style.display = 'block';
          selectedImageFile = compressedFile;
        };
        reader.readAsDataURL(compressedFile);
      });
    }

    // Función para comprimir imagen
    function comprimirImagen(file, callback) {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      const img = new Image();
      
      img.onload = function() {
        // Calcular nuevas dimensiones manteniendo proporción
        let { width, height } = img;
        const maxWidth = 1920;
        const maxHeight = 1080;
        
        if (width > maxWidth) {
          height = (height * maxWidth) / width;
          width = maxWidth;
        }
        if (height > maxHeight) {
          width = (width * maxHeight) / height;
          height = maxHeight;
        }
        
        // Configurar canvas
        canvas.width = width;
        canvas.height = height;
        
        // Dibujar imagen redimensionada
        ctx.drawImage(img, 0, 0, width, height);
        
        // Convertir a blob con compresión
        canvas.toBlob(function(blob) {
          // Crear nuevo archivo con el blob comprimido
          const compressedFile = new File([blob], file.name, {
            type: file.type,
            lastModified: Date.now()
          });
          
          // Verificar si la compresión fue efectiva
          if (compressedFile.size >= file.size) {
            // Si no se comprimió, usar el archivo original
            callback(file);
          } else {
            console.log(`Imagen comprimida: ${file.size} -> ${compressedFile.size} bytes (${Math.round((1 - compressedFile.size/file.size) * 100)}% reducción)`);
            callback(compressedFile);
          }
        }, file.type, 0.8); // Calidad 0.8 (80%)
      };
      
      img.src = URL.createObjectURL(file);
    }

    // Función para remover imagen seleccionada
    function removeSelectedImage() {
      document.getElementById('imagePreviewSection').style.display = 'none';
      document.getElementById('imageInput').value = '';
      selectedImageFile = null;
    }

    // Función para mostrar error
    function mostrarError(mensaje) {
      document.getElementById('modalErrorPublicacionBody').innerHTML = `<p>${mensaje}</p>`;
      const modalError = new bootstrap.Modal(document.getElementById('modalErrorPublicacion'));
      modalError.show();
    }

    // Función para mostrar éxito
    function mostrarExito() {
      const modalExito = new bootstrap.Modal(document.getElementById('modalPublicacionExitosa'));
      modalExito.show();
    }

    // Función para confirmar eliminación de post
    function confirmarEliminarPost(postId) {
      // Cerrar el modal del post
      const modalPost = bootstrap.Modal.getInstance(document.getElementById('modalVerPost'));
      modalPost.hide();
      
      // Mostrar modal de confirmación
      const modalConfirmar = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
      modalConfirmar.show();
      
      // Configurar el botón de confirmar
      document.getElementById('btnConfirmarEliminar').onclick = function() {
        eliminarPost(postId);
      };
    }

    // Función para eliminar el post
    function eliminarPost(postId) {
      const btnConfirmar = document.getElementById('btnConfirmarEliminar');
      btnConfirmar.disabled = true;
      btnConfirmar.textContent = 'Eliminando...';
      
      // Enviar solicitud de eliminación
      const formData = new FormData();
      formData.append('post_id', postId);
      
      fetch('delete_post.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Cerrar modal de confirmación
          const modalConfirmar = bootstrap.Modal.getInstance(document.getElementById('modalConfirmarEliminar'));
          modalConfirmar.hide();
          // Mostrar modal de éxito
          const modalExitosa = new bootstrap.Modal(document.getElementById('modalEliminacionExitosa'));
          modalExitosa.show();
        } else {
          // Mostrar modal de error
          document.getElementById('modalErrorEliminacionBody').innerHTML = `<p>Error: ${data.error || 'Error al eliminar el post'}</p>`;
          const modalError = new bootstrap.Modal(document.getElementById('modalErrorEliminacion'));
          modalError.show();
          btnConfirmar.disabled = false;
          btnConfirmar.textContent = 'Eliminar post';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        // Mostrar modal de error
        document.getElementById('modalErrorEliminacionBody').innerHTML = '<p>Error al eliminar el post. Por favor, inténtalo de nuevo.</p>';
        const modalError = new bootstrap.Modal(document.getElementById('modalErrorEliminacion'));
        modalError.show();
        btnConfirmar.disabled = false;
        btnConfirmar.textContent = 'Eliminar post';
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      // Escuchar cambios de famas desde otras páginas
      window.addEventListener('storage', function(e) {
        if (e.key === 'fama_update') {
          const famaUpdate = JSON.parse(e.newValue);
          if (famaUpdate && famaUpdate.type === 'fama_update') {
            // Actualizar contador de famas en el perfil
            updateFamasCount();
          }
        }
      });
      
      // Escuchar eventos personalizados
      window.addEventListener('famaUpdated', function(e) {
        updateFamasCount();
      });
      
      // Función para actualizar el contador de famas
      function updateFamasCount() {
        // Hacer una petición AJAX para obtener el famas actualizado
        fetch('profile.php?action=get_famas&username=<?php echo urlencode($username); ?>')
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Actualizar el contador de famas en el perfil
              const famaStats = document.querySelectorAll('.insta-profile-stat span');
              famaStats.forEach(stat => {
                if (stat.textContent.includes('💎')) {
                  stat.innerHTML = ` 💎​​ ${data.famas} 💎​`;
                  console.log(`Famas actualizado en profile.php: ${data.famas}`);
                }
              });
            }
          })
          .catch(error => {
            console.error('Error actualizando famas:', error);
          });
      }

      // Función para manejar votos
      document.addEventListener('click', function(e) {
        if (e.target.classList.contains('vote-btn')) {
          const postId = e.target.getAttribute('data-post-id');
          const voteType = e.target.getAttribute('data-vote-type');
          
          console.log('Votando:', { postId, voteType }); // Debug
          
          // Deshabilitar el botón inmediatamente
          e.target.style.opacity = '0.5';
          e.target.style.pointerEvents = 'none';
          e.target.style.cursor = 'not-allowed';
          
          // Enviar voto al servidor
          const formData = new FormData();
          formData.append('action', 'vote');
          formData.append('post_id', postId);
          formData.append('vote_type', voteType);
          
          fetch('profile.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            console.log('Response status:', response.status); // Debug
            return response.json();
          })
          .then(data => {
            console.log('Response data:', data); // Debug
            if (data.success) {
              // Actualizar conteos en tiempo real - corregir selector
              const countElements = document.querySelectorAll(`.vote-count[data-post-id="${postId}"][data-vote-type="${voteType}"]`);
              console.log('Encontrados elementos de conteo:', countElements.length); // Debug
              
              countElements.forEach(span => {
                const type = span.getAttribute('data-vote-type');
                if (data.counts[type] !== undefined) {
                  const oldValue = span.textContent;
                  span.textContent = data.counts[type];
                  console.log(`Actualizando ${type}: ${oldValue} -> ${data.counts[type]}`); // Debug
                }
              });
              
              // Si es un voto de fama, actualizar también el contador de famas en el perfil
              if (voteType === 'fama') {
                // Actualizar el contador de famas en el perfil actual
                const famaStats = document.querySelectorAll('.insta-profile-stat span');
                famaStats.forEach(stat => {
                  if (stat.textContent.includes('💎')) {
                    const currentFamas = parseInt(stat.textContent.match(/\d+/)[0]) || 0;
                    const newFamas = currentFamas + 1;
                    stat.innerHTML = ` 💎​​ ${newFamas} 💎​`;
                    console.log(`Actualizando famas en profile.php: ${currentFamas} -> ${newFamas}`);
                  }
                });
                
                // Notificar a otras páginas sobre el cambio de famas
                const famaUpdate = {
                  type: 'fama_update',
                  post_id: postId,
                  timestamp: Date.now()
                };
                localStorage.setItem('fama_update', JSON.stringify(famaUpdate));
                
                // Disparar evento personalizado para otras pestañas
                window.dispatchEvent(new CustomEvent('famaUpdated', {
                  detail: famaUpdate
                }));
              }
              
              // Cambiar estilo del botón para indicar que está votado y deshabilitarlo permanentemente
              e.target.style.filter = 'brightness(1.3)';
              e.target.style.transform = 'scale(1.1)';
              e.target.style.opacity = '0.7';
              e.target.style.pointerEvents = 'none';
              e.target.style.cursor = 'not-allowed';
              e.target.classList.add('voted');
              
              // Mostrar mensaje de éxito
              console.log(`Voto ${voteType} registrado exitosamente`);
              
              // Opcional: mostrar notificación temporal
              const notification = document.createElement('div');
              notification.textContent = `¡Voto ${voteType} registrado!`;
              notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                z-index: 9999;
                font-weight: bold;
              `;
              document.body.appendChild(notification);
              
              // Remover notificación después de 2 segundos
              setTimeout(() => {
                if (notification.parentNode) {
                  notification.parentNode.removeChild(notification);
                }
              }, 2000);
              
            } else {
              console.error('Error al votar:', data.error);
              // Mostrar mensaje al usuario
              alert('Error: ' + data.error);
              // Rehabilitar el botón si hay error
              e.target.style.opacity = '1';
              e.target.style.pointerEvents = 'auto';
              e.target.style.cursor = 'pointer';
              e.target.style.filter = 'brightness(1)';
              e.target.style.transform = 'scale(1)';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión. Verifica la consola para más detalles.');
            // Rehabilitar el botón si hay error
            e.target.style.opacity = '1';
            e.target.style.pointerEvents = 'auto';
            e.target.style.cursor = 'pointer';
            e.target.style.filter = 'brightness(1)';
            e.target.style.transform = 'scale(1)';
          });
        }
        
        // Manejar botón "Dar Famas" directo al usuario
        if (e.target.classList.contains('give-fama-btn') || e.target.closest('.give-fama-btn')) {
          const button = e.target.classList.contains('give-fama-btn') ? e.target : e.target.closest('.give-fama-btn');
          const targetUserId = button.getAttribute('data-user-id');
          const username = button.getAttribute('data-username');
          
          console.log('Dando famas a usuario:', { targetUserId, username }); // Debug
          
          // Deshabilitar el botón inmediatamente
          button.disabled = true;
          button.style.opacity = '0.5';
          button.style.pointerEvents = 'none';
          button.style.cursor = 'not-allowed';
          button.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';
          
          // Enviar voto de fama al servidor
          const formData = new FormData();
          formData.append('action', 'give_fama_user');
          formData.append('target_user_id', targetUserId);
          
          fetch('profile.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            console.log('Response status:', response.status); // Debug
            return response.json();
          })
          .then(data => {
            console.log('Response data:', data); // Debug
            if (data.success) {
              // Actualizar contador de famas en tiempo real
              const famaStats = document.querySelectorAll('.insta-profile-stat span');
              famaStats.forEach(stat => {
                if (stat.textContent.includes('💎')) {
                  stat.innerHTML = ` 💎​​ ${data.new_famas} 💎​`;
                  console.log(`Actualizando famas del usuario: ${data.new_famas}`);
                }
              });
              
              // Cambiar estilo del botón para indicar que está votado
              button.style.background = '#28a745';
              button.style.borderColor = '#28a745';
              button.style.color = 'white';
              button.style.filter = 'brightness(1.1)';
              button.style.transform = 'scale(1.05)';
              button.innerHTML = '<i class="bi bi-check-circle"></i> ¡Famas Dado!';
              button.classList.add('voted');
              
              // Mostrar notificación de éxito
              const notification = document.createElement('div');
              notification.textContent = `¡Famas otorgado a ${username}!`;
              notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                z-index: 9999;
                font-weight: bold;
              `;
              document.body.appendChild(notification);
              
              // Remover notificación después de 3 segundos
              setTimeout(() => {
                if (notification.parentNode) {
                  notification.parentNode.removeChild(notification);
                }
              }, 3000);
              
              // Notificar a otras páginas sobre el cambio de famas
              const famaUpdate = {
                type: 'fama_update',
                user_id: targetUserId,
                timestamp: Date.now()
              };
              localStorage.setItem('fama_update', JSON.stringify(famaUpdate));
              
              // Disparar evento personalizado para otras pestañas
              window.dispatchEvent(new CustomEvent('famaUpdated', {
                detail: famaUpdate
              }));
              
            } else {
              console.error('Error al dar famas:', data.error);
              // Mostrar mensaje al usuario
              alert('Error: ' + data.error);
              // Rehabilitar el botón si hay error
              button.disabled = false;
              button.style.opacity = '1';
              button.style.pointerEvents = 'auto';
              button.style.cursor = 'pointer';
              button.style.background = '#17a2b8';
              button.style.borderColor = '#17a2b8';
              button.style.color = 'white';
              button.style.filter = 'brightness(1)';
              button.style.transform = 'scale(1)';
              button.innerHTML = '<i class="bi bi-gem"></i> Dar Famas';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión. Verifica la consola para más detalles.');
            // Rehabilitar el botón si hay error
            button.disabled = false;
            button.style.opacity = '1';
            button.style.pointerEvents = 'auto';
            button.style.cursor = 'pointer';
            button.style.background = '#17a2b8';
            button.style.borderColor = '#17a2b8';
            button.style.color = 'white';
            button.style.filter = 'brightness(1)';
            button.style.transform = 'scale(1)';
            button.innerHTML = '<i class="bi bi-gem"></i> Dar Famas';
          });
        }
      });

      // HTML del formulario de publicar con el formato de publicarpost.html (igual que dashboard.php)
      const publicarFormHtml = `
        <div class="publish-form">
          <div class="bottom-content">
            <img src="<?php echo getProfilePictureUrl($user_info['profile_picture'], $username); ?>" alt="Foto de perfil" class="profile-picture" onclick="showImageModal(this.src, '<?php echo htmlspecialchars($username); ?>')" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid #6a0dad; background:#330066; cursor:pointer; transition:transform 0.2s ease;">
            <span class="bottom-username-text" style="font-size:1em; color:#e0e0e0;"><?php echo $username; ?></span>
          </div>
          <div class="top-section">
            <div class="input-wrapper">
              <div class="input-field" id="mainInputFieldModal" contenteditable="true" placeholder="Escribe tu publicación aquí..."></div>
            </div>
          </div>
          <div class="image-preview-section" id="imagePreviewSection" style="display:none; margin-top:10px;">
            <div class="selected-image-container" style="position:relative; display:inline-block;">
              <img id="imagePreview" src="" alt="Vista previa" style="max-width:200px; max-height:200px; border-radius:8px;">
              <button type="button" class="btn btn-danger btn-sm" style="position:absolute; top:5px; right:5px;" onclick="removeSelectedImage()">
                <i class="bi bi-x"></i>
              </button>
            </div>
          </div>
          <div class="toolbar">
            <div class="format-icons">
              <input type="file" id="imageInput" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/heic,image/heif,image/bmp,image/tiff,image/tif" style="display:none;" onchange="handleImageSelect(event)">
              <i class="bi bi-camera-fill toolbar-icon" title="Cargar foto" onclick="document.getElementById('imageInput').click()"></i>
              <i class="bi bi-type-bold toolbar-icon" data-command="bold" title="Negritas"></i>
              <i class="bi bi-type-italic toolbar-icon" data-command="italic" title="Cursiva"></i>
              <i class="bi bi-type-strikethrough toolbar-icon" data-command="strikeThrough" title="Tachar"></i>
            </div>
            <button class="publish-button" id="publishButtonModal">Publicar</button>
          </div>
        </div>
      `;

      const modal = document.getElementById('modalPublicar');
      const modalLabel = document.getElementById('modalPublicarLabel');
      const modalBody = document.getElementById('modalPublicarBody');

      modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const tipo = button.getAttribute('data-tipo');
        
        // Inserta el formulario directamente
        modalBody.innerHTML = publicarFormHtml;
        
        // Personaliza el formulario
        let titulo = modalBody.querySelector('.bottom-username-text');
        if (titulo) titulo.textContent = tipo;
        
        let input = modalBody.querySelector('.input-field');
        if (input) input.setAttribute('placeholder', 'Escribe tu ' + tipo.toLowerCase() + ' aquí...');
        
        let btn = modalBody.querySelector('.publish-button');
        if (btn) btn.textContent = 'Publicar ' + tipo;
        
        // Cambia el título del modal
        modalLabel.textContent = tipo;

        // Lógica de formato para el input (negrita, cursiva, tachado)
        const toolbarIcons = modalBody.querySelectorAll('.toolbar-icon[data-command]');
        toolbarIcons.forEach(icon => {
          icon.addEventListener('mousedown', function(event) {
            event.preventDefault();
            const command = this.dataset.command;
            input.focus();
            document.execCommand(command, false, null);
          });
        });
        
        // Lógica para publicar el post
        if (btn && input) {
          btn.onclick = null;
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            let texto = input.innerHTML || input.innerText || input.value || input.textContent;
            texto = texto.trim();
            
            if (!texto) {
              mostrarError('El campo de entrada está vacío.');
              return;
            }
            
            // Deshabilitar botón mientras se procesa
            btn.disabled = true;
            btn.textContent = 'Publicando...';
            
            // Crear FormData para enviar texto e imagen
            const formData = new FormData();
            formData.append('tipo', tipo);
            formData.append('contenido', texto);
            
            // Agregar imagen si se seleccionó una
            if (selectedImageFile) {
              formData.append('imagen', selectedImageFile);
            }
            
            // Enviar datos al servidor
            fetch('publish_post.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                // Cerrar modal de publicación
                const modalInstance = bootstrap.Modal.getInstance(modal);
                modalInstance.hide();
                // Mostrar modal de éxito
                mostrarExito();
              } else {
                mostrarError(data.error || 'Error desconocido');
                btn.disabled = false;
                btn.textContent = 'Publicar ' + tipo;
              }
            })
            .catch(error => {
              console.error('Error:', error);
              mostrarError('Error al publicar el post');
              btn.disabled = false;
              btn.textContent = 'Publicar ' + tipo;
            });
          });
        }
      });
    });
  </script>
</body>
</html>
