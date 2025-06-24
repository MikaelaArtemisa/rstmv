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
$stmt = $pdo->prepare("SELECT p.*, u.profile_picture FROM posts p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.fecha DESC LIMIT 20");
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
      top: 10px;
      left: 10px;
      background: #330066;
      color: #c2a4ff;
      border-radius: 8px;
      padding: 0.2em 0.7em;
      font-size: 0.85em;
      font-weight: bold;
    }
    
    .post-date {
      position: absolute;
      top: 10px;
      right: 10px;
      color: #aaa;
      font-size: 0.8em;
    }
    
    .post-content {
      margin-top: 2.5em;
      margin-bottom: 1em;
      white-space: pre-wrap;
      word-break: break-word;
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

   

    <!-- Timeline de posts publicados -->
    <div class="row row-cols-1 g-4 mt-4">
      <?php if (empty($posts)): ?>
        <div class="col">
          <div class="card bg-dark text-light h-100 shadow-sm border-0">
            <div class="card-body text-center">
              <p class="text-muted">No hay publicaciones aún. ¡Sé el primero en publicar!</p>
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
                  <div class="flex-grow-1 post-content-preview" style="cursor:pointer;" data-post-title="<?php echo htmlspecialchars($post['tipo']); ?>" data-post-content="<?php echo htmlspecialchars(processContentForDisplay($post['contenido'])); ?>">
                    <div class="post-type-badge"><?php echo htmlspecialchars($post['tipo']); ?></div>
                    <div class="post-date"><?php echo date('d/m/Y', strtotime($post['fecha'])); ?></div>
                    <div class="post-content">
                      <?php echo nl2br(processContentForDisplay($post['contenido'])); ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
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

      // Modal para ver post completo
      document.querySelectorAll('.post-content-preview').forEach(function(el) {
        el.addEventListener('click', function() {
          const title = this.getAttribute('data-post-title');
          const content = this.getAttribute('data-post-content');
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
                  </div>
                </div>
              </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
          }
          document.getElementById('modalVerPostLabel').textContent = title;
          document.getElementById('modalVerPostBody').innerHTML = `<p>${content}</p>`;
          const modal = new bootstrap.Modal(document.getElementById('modalVerPost'));
          modal.show();
        });
      });
    });
  </script>
</body>
</html>
