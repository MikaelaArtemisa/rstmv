<?php
session_start();
require_once '../includes/db.php';

// Verifica si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?expired=1");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

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
    // Si hay error al crear la tabla, continuar sin votos
    error_log("Error creando tabla post_votes: " . $e->getMessage());
}

// Agregar campo famas a la tabla users si no existe
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS famas INT DEFAULT 0");
} catch (PDOException $e) {
    // Si hay error, continuar sin el campo famas
    error_log("Error agregando campo famas: " . $e->getMessage());
}

// Obtener información del usuario incluyendo la foto de perfil
$stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $user_info['profile_picture'] ?? '';

// Obtener todos los posts ordenados por fecha descendente con conteos de votos
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.profile_picture, u.username,
               COALESCE(SUM(CASE WHEN pv.vote_type = 'like' THEN 1 ELSE 0 END), 0) as likes_count,
               COALESCE(SUM(CASE WHEN pv.vote_type = 'dislike' THEN 1 ELSE 0 END), 0) as dislikes_count,
               COALESCE(SUM(CASE WHEN pv.vote_type = 'fama' THEN 1 ELSE 0 END), 0) as famas_count
        FROM posts p 
        LEFT JOIN users u ON p.user_id = u.id 
        LEFT JOIN post_votes pv ON p.id = pv.post_id
        GROUP BY p.id, p.user_id, p.tipo, p.contenido, p.imagen, p.fecha, u.profile_picture, u.username
        ORDER BY p.fecha DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si hay error, usar consulta simple sin votos
    $stmt = $pdo->prepare("SELECT p.*, u.profile_picture, u.username FROM posts p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.fecha DESC LIMIT 20");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar conteos de votos como 0 por defecto
    foreach ($posts as &$post) {
        $post['likes_count'] = 0;
        $post['dislikes_count'] = 0;
        $post['famas_count'] = 0;
    }
}

// Obtener votos del usuario actual para marcar botones como votados
$user_votes = [];
try {
    $stmt = $pdo->prepare("SELECT post_id, vote_type FROM post_votes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si hay error, continuar sin votos del usuario
    error_log("Error obteniendo votos del usuario: " . $e->getMessage());
}

// Crear array de votos del usuario para JavaScript
$user_votes_js = [];
foreach ($user_votes as $vote) {
    $user_votes_js[] = [
        'post_id' => $vote['post_id'],
        'vote_type' => $vote['vote_type']
    ];
}

// Función para obtener la URL de la imagen de perfil
function getProfilePictureUrl($profile_picture, $username) {
    if (!empty($profile_picture)) {
        return '../uploads/profile_pictures/' . htmlspecialchars($profile_picture);
    } else {
        // Avatar por defecto usando las iniciales del username
        return 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=330066&color=c2a4ff&size=40';
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
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>RSIDEA Layout - Dark High Contrast</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css"> 

  <style>
    .profile-picture {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #6a0dad;
      background: #330066;
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    .profile-picture:hover {
      transform: scale(1.1);
    }
    .profile-picture-small {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #6a0dad;
      background: #330066;
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    .profile-picture-small:hover {
      transform: scale(1.1);
    }
    .username-link {
      color: #c2a4ff;
      text-decoration: none;
      cursor: pointer;
      transition: color 0.2s ease;
    }
    .username-link:hover {
      color: #9c7cff;
      text-decoration: underline;
    }
    .modal-image {
      max-width: 100%;
      max-height: 80vh;
      object-fit: contain;
    }
    
    /* Estilos del formulario de publicación */
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
    
    .post-type-badge {
      position: absolute;
      top: -15px;
      left: 10px;
      background: #330066;
      color: #c2a4ff;
      border-radius: 8px;
      padding: 0.2em 0.7em;
      font-size: 0.85em;
      font-weight: bold;
      margin-bottom: 30px;
    }
    
    .post-date {
      position: absolute;
      top: 10px;
      right: 10px;
      color: #aaa;
      font-size: 0.8em;
    }
    
    .post-content {
      margin-top: 1em;
      margin-bottom: 1em;
      white-space: pre-wrap;
      word-break: break-word;
      text-align: left;
      width: 100%;
      padding-left: 10px;
      padding-right: 10px;
    }
    
    .post-image-container {
      margin-top: 15px;
    }
    
    .post-image {
      transition: transform 0.2s ease;
    }
    
    .post-image:hover {
      transform: scale(1.02);
    }
    
    /* Estilos para preview en tiempo real */
    .preview-section {
      margin-top: 15px;
      padding: 15px;
      background-color: #1a1a1a;
      border: 1px solid #6a0dad;
      border-radius: 5px;
      display: none;
    }
    
    .preview-section.show {
      display: block;
    }
    
    .preview-title {
      color: #c2a4ff;
      font-size: 0.9em;
      margin-bottom: 10px;
      font-weight: bold;
    }
    
    .preview-content {
      color: #e0e0e0;
      white-space: pre-wrap;
      word-break: break-word;
      font-size: 0.95em;
      line-height: 1.4;
    }
    
    .preview-content strong, .preview-content b {
      font-weight: bold;
      color: #fff;
    }
    
    .preview-content em, .preview-content i {
      font-style: italic;
      color: #c2a4ff;
    }
    
    .preview-content u {
      text-decoration: underline;
      color: #9933ff;
    }
    
    .preview-content s, .preview-content strike {
      text-decoration: line-through;
      color: #888;
    }
    
    .dashboard-img-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #330066;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2em;
      transition: all 0.3s ease;
      border: 2px solid #6a0dad;
    }
    
    .dashboard-img-circle.vote-btn {
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .dashboard-img-circle.vote-btn:hover {
      transform: scale(1.1);
      filter: brightness(1.2);
    }
    
    .dashboard-img-circle.vote-btn.voted {
      opacity: 0.7;
      pointer-events: none;
      cursor: not-allowed;
      filter: brightness(1.3);
      transform: scale(1.1);
      border-color: #28a745;
    }
    
    .dashboard-img-circle.vote-btn:disabled {
      opacity: 0.5;
      pointer-events: none;
      cursor: not-allowed;
    }
  </style>
</head>
<body>

<?php include '../includes/partials/navbar.php'?>


  <div class="dashboard-container">
    <div class="dashboard-bottom-content">
      <img src="<?php echo getProfilePictureUrl($profile_picture, $username); ?>" alt="Foto de perfil" class="profile-picture" onclick="showImageModal(this.src, '<?php echo htmlspecialchars($username); ?>')">
      <span class="dashboard-bottom-username-text"><?php echo $username; ?></span>
    </div>

  

    <!-- Modal Bootstrap para publicar -->
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

   

    <!-- Timeline de posts publicados -->
    <div class="row row-cols-1 g-4 mt-4">
      <?php if (empty($posts)): ?>
        <div class="col">
          <div class="card bg-dark text-light h-100 shadow-sm border-0">
            <div class="card-body text-center">
              <p class="text-white">No hay publicaciones aún. ¡Sé el primero en publicar!</p>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($posts as $post): ?>
          <div class="col">
            <div class="card bg-dark text-light h-100 shadow-sm border-0">
              <div class="card-body d-flex flex-row align-items-stretch gap-3">
                <div class="dashboard-img-sidebar d-flex flex-column justify-content-center align-items-center gap-2 flex-shrink-0" style="min-width:48px;">
                  <div class="dashboard-img-circle vote-btn" data-post-id="<?php echo $post['id']; ?>" data-vote-type="like" style="cursor: pointer;">❤️​</div>
                  <span class="dashboard-bottom-username-text vote-count" data-post-id="<?php echo $post['id']; ?>" data-vote-type="like">[<?php echo $post['likes_count']; ?>]</span>
                  <div class="dashboard-img-circle vote-btn" data-post-id="<?php echo $post['id']; ?>" data-vote-type="dislike" style="cursor: pointer;">☠️​</div>
                  <span class="dashboard-bottom-username-text vote-count" data-post-id="<?php echo $post['id']; ?>" data-vote-type="dislike">[<?php echo $post['dislikes_count']; ?>]</span>
                  <div class="dashboard-img-circle vote-btn" data-post-id="<?php echo $post['id']; ?>" data-vote-type="fama" style="cursor: pointer;">💎​​</div>
                  <span class="dashboard-bottom-username-text vote-count" data-post-id="<?php echo $post['id']; ?>" data-vote-type="fama">[<?php echo $post['famas_count']; ?>]</span>
                </div>
                <div class="flex-grow-1 d-flex flex-column">
                  <div class="d-flex align-items-center mb-2">
                    <?php if ($post['tipo'] === 'CONFESIÓN ANONIMA'): ?>
                      <img src="https://ui-avatars.com/api/?name=Anónimo&background=330066&color=c2a4ff&size=40" alt="Anónimo" class="profile-picture-small me-2">
                      <span class="fw-bold username-link">Anónimo</span>
                    <?php else: ?>
                      <img src="<?php echo getProfilePictureUrl($post['profile_picture'], $post['username']); ?>" alt="Foto de perfil" class="profile-picture-small me-2" onclick="showImageModal(this.src, '<?php echo htmlspecialchars($post['username']); ?>')">
                      <span class="fw-bold username-link" onclick="window.location.href='profile.php?username=<?php echo urlencode($post['username']); ?>'">
                        <?php echo htmlspecialchars($post['username']); ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="flex-grow-1 post-content-preview" style="cursor:pointer;" 
                       data-post-id="<?php echo $post['id']; ?>"
                       data-post-user-id="<?php echo $post['user_id']; ?>"
                       data-post-title="<?php echo htmlspecialchars($post['tipo']); ?>" 
                       data-post-content="<?php echo htmlspecialchars(processContentForDisplay($post['contenido'])); ?>">
                    <div class="post-type-badge"><?php echo htmlspecialchars($post['tipo']); ?></div>
                    <div class="post-date"><?php echo date('d/m/Y', strtotime($post['fecha'])); ?></div>
                    <div class="post-content" style="text-align: left;">
                      <?php echo nl2br(processContentForDisplay($post['contenido'])); ?>
                    </div>
                    <?php if (!empty($post['imagen'])): ?>
                      <div class="post-image-container mt-3">
                        <img src="../uploads/post_images/<?php echo htmlspecialchars($post['imagen']); ?>" 
                             alt="Imagen del post" 
                             class="post-image" 
                             style="max-width: 100%; max-height: 300px; border-radius: 8px; cursor: pointer;"
                             onclick="showPostImageModal(this.src, '<?php echo htmlspecialchars($post['username']); ?>')">
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal para ver post completo -->
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

  <!-- Modal de éxito para eliminación -->
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

    document.addEventListener('DOMContentLoaded', function () {
      // Escuchar cambios de famas desde otras páginas
      window.addEventListener('storage', function(e) {
        if (e.key === 'fama_update') {
          const famaUpdate = JSON.parse(e.newValue);
          if (famaUpdate && famaUpdate.type === 'fama_update') {
            console.log('Famas actualizado desde otra página:', famaUpdate);
            // Aquí podrías actualizar algún contador global si es necesario
          }
        }
      });
      
      // Escuchar eventos personalizados
      window.addEventListener('famaUpdated', function(e) {
        console.log('Evento famaUpdated recibido:', e.detail);
        // Aquí podrías actualizar algún contador global si es necesario
      });

      // Marcar botones ya votados por el usuario
      const userVotes = <?php echo json_encode($user_votes_js); ?>;
      userVotes.forEach(vote => {
        const button = document.querySelector(`[data-post-id="${vote.post_id}"][data-vote-type="${vote.vote_type}"]`);
        if (button) {
          button.classList.add('voted');
          button.style.opacity = '0.7';
          button.style.pointerEvents = 'none';
          button.style.cursor = 'not-allowed';
          button.style.filter = 'brightness(1.3)';
          button.style.transform = 'scale(1.1)';
          button.style.borderColor = '#28a745';
        }
      });

      const mainInputField = document.getElementById('mainInputField');
      const toolbarIcons = document.querySelectorAll('.dashboard-toolbar-icon[data-command]');
      const publishButton = document.getElementById('publishButton');

      toolbarIcons.forEach(icon => {
        icon.addEventListener('click', function (event) {
          event.preventDefault();
          const command = this.dataset.command;
          const start = mainInputField.selectionStart;
          const end = mainInputField.selectionEnd;
          const selectedText = mainInputField.value.substring(start, end);
          let replacement = selectedText;

          switch (command) {
            case 'bold':
              replacement = `**${selectedText}**`;
              break;
            case 'italic':
              replacement = `*${selectedText}*`;
              break;
            case 'strikeThrough':
              replacement = `~${selectedText}~`;
              break;
          }

          mainInputField.setRangeText(replacement, start, end, 'end');
          mainInputField.focus();
        });
      });

      const cameraIcon = document.querySelector('.bi-camera-fill');
      if (cameraIcon) {
        cameraIcon.addEventListener('click', function () {
          alert('Aquí se abriría un diálogo para cargar una foto.');
        });
      }

      if (publishButton) {
        publishButton.addEventListener('click', function () {
          const content = mainInputField.value.trim();
          // Permitir publicar si hay texto o imagen seleccionada
          if (content || selectedImageFile) {
            alert('Contenido a publicar: "' + content + '"');
            mainInputField.value = '';
          } else {
            alert('Debes escribir algo o seleccionar una imagen.');
          }
        });
      }

      // HTML del formulario de publicar con el formato de publicarpost.html
      const publicarFormHtml = `
        <div class="publish-form">
          <div class="bottom-content">
            <img src="<?php echo getProfilePictureUrl($profile_picture, $username); ?>" alt="Foto de perfil" class="profile-picture" onclick="showImageModal(this.src, '<?php echo htmlspecialchars($username); ?>')">
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
            if (!texto && !selectedImageFile) {
              mostrarError('Debes escribir algo o seleccionar una imagen.');
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

      // Modal para ver post completo
      document.querySelectorAll('.post-content-preview').forEach(function(el) {
        el.addEventListener('click', function() {
          const postId = this.getAttribute('data-post-id');
          const postUserId = this.getAttribute('data-post-user-id');
          const currentUserId = '<?php echo $user_id; ?>';
          const title = this.getAttribute('data-post-title');
          const content = this.getAttribute('data-post-content');
          const postImage = this.querySelector('.post-image');
          
          if (!document.getElementById('modalVerPost')) {
            const modalHtml = `
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
              </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
          }
          
          let modalContent = `<p style="text-align: left;">${content}</p>`;
          if (postImage) {
            modalContent += `<div class="text-center mt-3">
              <img src="${postImage.src}" alt="Imagen del post" style="max-width: 100%; max-height: 400px; border-radius: 8px; cursor: pointer;" onclick="showPostImageModal('${postImage.src}', '${title}')">
            </div>`;
          }
          
          document.getElementById('modalVerPostLabel').textContent = title;
          document.getElementById('modalVerPostBody').innerHTML = modalContent;
          
          // Mostrar botón de eliminar solo si el post pertenece al usuario actual
          const footer = document.getElementById('modalVerPostFooter');
          if (postUserId === currentUserId) {
            footer.innerHTML = `
              <button type="button" class="btn btn-danger" onclick="confirmarEliminarPost(${postId})">
                <i class="bi bi-trash me-2"></i>Eliminar post
              </button>
            `;
          } else {
            footer.innerHTML = '';
          }
          
          const modal = new bootstrap.Modal(document.getElementById('modalVerPost'));
          modal.show();
        });
      });

      // Función para confirmar eliminación de post
      window.confirmarEliminarPost = function(postId) {
        // Cerrar modal de post
        const modalPost = bootstrap.Modal.getInstance(document.getElementById('modalVerPost'));
        modalPost.hide();
        
        // Mostrar modal de confirmación
        const modalConfirmar = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
        modalConfirmar.show();
        
        // Configurar botón de confirmar
        document.getElementById('btnConfirmarEliminar').onclick = function() {
          eliminarPost(postId);
        };
      };

      // Función para eliminar post
      function eliminarPost(postId) {
        // Cerrar modal de confirmación
        const modalConfirmar = bootstrap.Modal.getInstance(document.getElementById('modalConfirmarEliminar'));
        modalConfirmar.hide();
        
        // Enviar petición de eliminación
        fetch('delete_post.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'post_id=' + postId
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Mostrar modal de éxito
            const modalExito = new bootstrap.Modal(document.getElementById('modalEliminacionExitosa'));
            modalExito.show();
          } else {
            // Mostrar modal de error
            document.getElementById('modalErrorEliminacionBody').innerHTML = `<p>${data.error || 'Error desconocido'}</p>`;
            const modalError = new bootstrap.Modal(document.getElementById('modalErrorEliminacion'));
            modalError.show();
          }
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('modalErrorEliminacionBody').innerHTML = '<p>Error al eliminar el post</p>';
          const modalError = new bootstrap.Modal(document.getElementById('modalErrorEliminacion'));
          modalError.show();
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
          
          fetch('dashboard.php', {
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
              // Actualizar conteos en tiempo real
              const countElements = document.querySelectorAll(`.vote-count[data-post-id="${postId}"]`);
              countElements.forEach(span => {
                const type = span.getAttribute('data-vote-type');
                if (data.counts[type] !== undefined) {
                  span.textContent = `[${data.counts[type]}]`;
                }
              });
              
              // Si es un voto de fama, actualizar también el contador de famas en profile.php si está abierto
              if (voteType === 'fama') {
                // Buscar y actualizar el contador de famas en el perfil si está visible
                const famaStats = document.querySelectorAll('.insta-profile-stat span');
                famaStats.forEach(stat => {
                  if (stat.textContent.includes('💎')) {
                    const currentFamas = parseInt(stat.textContent.match(/\d+/)[0]) || 0;
                    const newFamas = currentFamas + 1;
                    stat.innerHTML = stat.textContent.replace(/\d+/, newFamas);
                    console.log(`Actualizando famas en dashboard: ${currentFamas} -> ${newFamas}`);
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
      });
    });
  </script>
</body>
</html>
