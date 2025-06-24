<?php
session_start();
require_once '../includes/db.php'; // Ajusta el path si es necesario

// Determinar a quién mostrar
$username_param = isset($_GET['username']) ? trim($_GET['username']) : null;

if ($username_param) {
    // Buscar usuario por username
    $stmt = $pdo->prepare("SELECT id, email, username, aboutme, profile_picture FROM users WHERE username = ? LIMIT 1");
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
    $stmt = $pdo->prepare("SELECT id, email, username, aboutme, profile_picture FROM users WHERE id = ? LIMIT 1");
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
      padding: 0;
      margin: 0;
      font-family: sans-serif;
      box-sizing: border-box;
      background-color: transparent;
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
  </style>
</head>
<body>
<?php include '../includes/partials/navbar.php'; ?>
<div class="container-full py-4">
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

  <!-- Galería de publicaciones tipo Instagram -->
  <div class="insta-gallery mb-5 compact-gallery">
    <?php if (empty($posts)): ?>
      <div class="insta-post-card" style="aspect-ratio: auto; min-height: 200px;">
        <div class="insta-post-content">
          <p class="text-muted">No hay publicaciones aún.</p>
          <?php if ($is_own_profile): ?>
            <a href="dashboard.php" class="btn btn-primary btn-sm">Ir al dashboard para publicar</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <?php foreach (array_slice($posts, 0, 9) as $post): ?>
        <div class="insta-post-card" onclick="showPostModal('<?php echo htmlspecialchars($post['tipo']); ?>', '<?php echo htmlspecialchars(processContentForDisplay($post['contenido'])); ?>', '<?php echo date('d/m/Y H:i', strtotime($post['fecha'])); ?>')">
          <div class="insta-post-type"><?php echo htmlspecialchars($post['tipo']); ?></div>
          <div class="insta-post-date"><?php echo date('d/m/Y', strtotime($post['fecha'])); ?></div>
          <div class="insta-post-content"><?php echo nl2br(processContentForDisplay(substr($post['contenido'], 0, 100))); ?><?php echo strlen($post['contenido']) > 100 ? '...' : ''; ?></div>
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

  <!-- Modal para ver post completo (igual que en dashboard.php) -->
  <div class="modal fade" id="modalVerPost" tabindex="-1" aria-labelledby="modalVerPostLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="modalVerPostLabel">Post completo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" id="modalVerPostBody"></div>
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

    // Función para mostrar post completo en modal (igual que en dashboard.php)
    function showPostModal(tipo, contenido, fecha) {
      const modal = new bootstrap.Modal(document.getElementById('modalVerPost'));
      document.getElementById('modalVerPostLabel').textContent = tipo;
      document.getElementById('modalVerPostBody').innerHTML = `<p>${contenido}</p>`;
      modal.show();
    }

    document.addEventListener('DOMContentLoaded', function () {
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
          <div class="toolbar">
            <div class="format-icons">
              <i class="bi bi-camera-fill toolbar-icon" title="Cargar foto"></i>
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
              alert('El campo de entrada está vacío.');
              return;
            }
            
            // Deshabilitar botón mientras se procesa
            btn.disabled = true;
            btn.textContent = 'Publicando...';
            
            // Enviar datos al servidor
            const formData = new FormData();
            formData.append('tipo', tipo);
            formData.append('contenido', texto);
            
            fetch('publish_post.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                alert('¡Post publicado exitosamente!');
                // Cerrar modal y recargar página para mostrar el nuevo post
                const modalInstance = bootstrap.Modal.getInstance(modal);
                modalInstance.hide();
                location.reload();
              } else {
                alert('Error: ' + (data.error || 'Error desconocido'));
                btn.disabled = false;
                btn.textContent = 'Publicar ' + tipo;
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Error al publicar el post');
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
