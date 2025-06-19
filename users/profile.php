<?php
session_start();
require_once '../includes/db.php'; // Ajusta el path si es necesario

// Determinar a quién mostrar
$username_param = isset($_GET['username']) ? trim($_GET['username']) : null;

if ($username_param) {
    // Buscar usuario por username
    $stmt = $pdo->prepare("SELECT id, email, username, aboutme FROM users WHERE username = ? LIMIT 1");
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
    $stmt = $pdo->prepare("SELECT id, email, username, aboutme FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_own_profile = true;
}

// Obtener SOLO los posts del usuario mostrado
$stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY fecha DESC");
$stmt->execute([$user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular contadores por tipo de post
$fama_count = 0;
$conf_publica_count = 0;
$conf_anonima_count = 0;
foreach ($posts as $post) {
    if ($post['tipo'] === 'POST') $fama_count++;
    if ($post['tipo'] === 'CONFESIÓN PUBLICA') $conf_publica_count++;
    if ($post['tipo'] === 'CONFESIÓN ANONIMA') $conf_anonima_count++;
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
  </style>
</head>
<body>
<?php include '../includes/partials/navbar.php'; ?>
<div class="container-full py-4">
  <!-- Encabezado tipo Instagram -->
  <div class="insta-profile-header mb-4">
    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>&background=330066&color=c2a4ff&size=110" alt="Avatar" class="insta-avatar">
    <div class="insta-profile-info">
      <div class="insta-profile-username"><?php echo htmlspecialchars($username); ?>
      <?php if ($is_own_profile && !isset($_GET['username'])): ?>

      <?php endif; ?>
      <?php if ($is_own_profile): ?>
        <a href="edit-profile.php" class="btn btn-outline-light btn-sm">Editar perfil</a>
      <?php endif; ?>
    </div>
      <div class="insta-profile-stats">
        <div class="insta-profile-stat">
          <span> 📝 <?php echo count($posts); ?> 📝</span>
          <small>Publicaciones</small>
        </div>
        <div class="insta-profile-stat">
          <span> 💎​​ <?php echo  $fama_count; ?> 💎​</span>
          <small>Fama</small>
        </div>
        <div class="insta-profile-stat">
          <span> ​🫨 <?php echo $conf_publica_count; ?> 🫨​</span>
          <small>Conf. Públicas</small>
        </div>
        <div class="insta-profile-stat">
          <span>​🙈 <?php echo $conf_anonima_count; ?> 🙈​</span>
          <small>Conf. Anónima</small>
        </div>
      </div>
      <!-- Información del perfil -->
      <div class="insta-profile-info-extra mt-2 mb-2 p-2" style="background:#181c2f; border-radius:10px;">
        <div><strong>Biografía:</strong> <?php echo htmlspecialchars($user_info['aboutme'] ?? ''); ?></div>
      </div>

    </div>
  </div>

  <!-- Grid de posts ficticios tipo timeline dashboard (en pequeño) -->
  <div class="explora-section mb-4">
    <h3 class="mb-2 text-center" style="color:#c2a4ff; font-size:1.1rem;">Explora publicaciones</h3>
    <div class="insta-gallery mini-fake-posts compact-gallery">
      <?php for ($i = 1; $i <= 6; $i++): ?>
        <div class="insta-post-card mini-post-card">
          <div class="insta-post-type">FICTICIO</div>
          <div class="insta-post-date"><?php echo date('d/m/Y', strtotime("-{$i} days")); ?></div>
          <div class="insta-post-content" style="font-size:0.95em;">
            <div class="d-flex align-items-center mb-2 justify-content-center gap-2">
              <div class="dashboard-img-circle" style="width:24px; height:24px; font-size:0.9rem; line-height:24px; background:#330066; color:#c2a4ff; display:inline-flex; align-items:center; justify-content:center;">U</div>
              <span class="fw-bold" style="color:#c2a4ff; font-size:0.95em;">UsuarioDemo<?php echo $i; ?></span>
            </div>
            <div style="font-size:0.92em;">Este es un post ficticio de ejemplo para el timeline.</div>
            <div class="d-flex justify-content-center gap-2 mt-2" style="font-size:0.9em;">
              <span title="Fama"><i class="bi bi-heart-fill" style="color:#e25555;"></i> 100</span>
              <span title="Conf. Pública"><i class="bi bi-emoji-smile-fill" style="color:#ffc107;"></i> 50</span>
              <span title="Conf. Anónima"><i class="bi bi-emoji-neutral-fill" style="color:#6c757d;"></i> 25</span>
            </div>
          </div>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Galería de publicaciones tipo Instagram (sin título ni centrado especial) -->
  <div class="insta-gallery mb-5 compact-gallery">
    <?php foreach (array_slice($posts, 0, 9) as $post): ?>
      <div class="insta-post-card">
        <div class="insta-post-type"><?php echo htmlspecialchars($post['tipo']); ?></div>
        <div class="insta-post-date"><?php echo date('d/m/Y', strtotime($post['fecha'])); ?></div>
        <div class="insta-post-content"><?php echo nl2br(htmlspecialchars($post['contenido'])); ?></div>
      </div>
    <?php endforeach; ?>
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
          <div class='text-center'>
            <p>Formulario de publicación aquí (solo ejemplo visual).</p>
            <input type='text' class='form-control mb-2' placeholder='¿Qué quieres publicar?'>
            <button class='btn btn-primary'>Publicar</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
