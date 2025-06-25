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

// Obtener información del usuario incluyendo la foto de perfil
$stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $user_info['profile_picture'] ?? '';

// Obtener todos los posts ordenados por fecha descendente
$stmt = $pdo->prepare("SELECT p.*, u.profile_picture, u.username FROM posts p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.fecha DESC LIMIT 20");
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
      text-align: center;
      margin-left: 15%;
      margin-right: auto;
      width: 70%;
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
                  <div class="dashboard-img-circle">❤️​</div>
                  <span class="dashboard-bottom-username-text">[100]</span>
                  <div class="dashboard-img-circle">☠️​</div>
                  <span class="dashboard-bottom-username-text">[100]</span>
                  <div class="dashboard-img-circle">💢​</div>
                  <span class="dashboard-bottom-username-text">[100]</span>
                </div>
                <div class="flex-grow-1 d-flex flex-column">
                  <div class="d-flex align-items-center mb-2">
                    <img src="<?php echo getProfilePictureUrl($post['profile_picture'], $post['username']); ?>" alt="Foto de perfil" class="profile-picture-small me-2" onclick="showImageModal(this.src, '<?php echo htmlspecialchars($post['username']); ?>')">
                    <span class="fw-bold username-link" onclick="window.location.href='profile.php?username=<?php echo urlencode($post['username']); ?>'"><?php echo htmlspecialchars($post['username']); ?></span>
                  </div>
                  <div class="flex-grow-1 post-content-preview" style="cursor:pointer;" 
                       data-post-id="<?php echo $post['id']; ?>"
                       data-post-user-id="<?php echo $post['user_id']; ?>"
                       data-post-title="<?php echo htmlspecialchars($post['tipo']); ?>" 
                       data-post-content="<?php echo htmlspecialchars(processContentForDisplay($post['contenido'])); ?>">
                    <div class="post-type-badge"><?php echo htmlspecialchars($post['tipo']); ?></div>
                    <div class="post-date"><?php echo date('d/m/Y', strtotime($post['fecha'])); ?></div>
                    <div class="post-content">
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

      // Validaciones de imagen
      const tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
      const tamanoMaximo = 5 * 1024 * 1024; // 5MB

      if (!tiposPermitidos.includes(file.type)) {
        mostrarError('Formato de imagen no válido. Solo se permiten JPG, PNG y GIF');
        return;
      }

      if (file.size > tamanoMaximo) {
        mostrarError('La imagen es demasiado grande. Máximo 5MB');
        return;
      }

      // Mostrar vista previa
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('imagePreview').src = e.target.result;
        document.getElementById('imagePreviewSection').style.display = 'block';
        selectedImageFile = file;
      };
      reader.readAsDataURL(file);
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
          if (content) {
            alert('Contenido a publicar: "' + content + '"');
            mainInputField.value = '';
          } else {
            alert('El campo de entrada está vacío.');
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
              <input type="file" id="imageInput" accept="image/*" style="display:none;" onchange="handleImageSelect(event)">
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
          
          let modalContent = `<p>${content}</p>`;
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
    });
  </script>
</body>
</html>
