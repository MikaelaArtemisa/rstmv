<?php
session_start();

// Verifica si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?expired=1");
    exit;
}

$username = htmlspecialchars($_SESSION['username']);
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


  </style>
</head>
<body>

<?php include '../includes/partials/navbar.php'?>


  <div class="dashboard-container">
    <div class="dashboard-bottom-content">
      <div class="dashboard-bottom-img-circle">IMGPF</div>
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

    <!-- Lista de posts publicados -->
    <div id="postsList" class="w-100 mt-4"></div>

    <div class="dashboard-top-section">
      <div class="dashboard-img-sidebar">
        <center>
        <div class="dashboard-img-circle">❤️​</div>
        <span class="dashboard-bottom-username-text">[op1]</span>
        <br><br>
        <div class="dashboard-img-circle">☠️​</div>
        <span class="dashboard-bottom-username-text">[op1]</span>
        <br><br>
        <div class="dashboard-img-circle">💢​</div>
        <span class="dashboard-bottom-username-text">[op1]</span>
        </center>
      </div>

      <div class="dashboard-input-wrapper">
        <textarea class="dashboard-input-field" id="mainInputField" placeholder="Type your thoughts here..."></textarea>
      </div>
    </div>

    <div class="dashboard-accordion accordion" id="accordionExample">
      <div class="dashboard-accordion-item accordion-item">
        <h2 class="dashboard-accordion-header accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
            Ver comentarios
          </button>
        </h2>
        <div id="collapseOne" class="accordion-collapse collapse">
          <div class="dashboard-accordion-body accordion-body">
            <strong>Este es el contenido del acordeón.</strong> Se puede abrir y cerrar haciendo clic en el encabezado.
          </div>
        </div>
      </div>
    </div>

    <!-- Timeline de posts publicados -->
    <div class="row row-cols-1 g-4 mt-4">
      <?php for ($i = 1; $i <= 6; $i++): ?>
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
                  <div class="dashboard-img-circle me-2" style="width: 32px; height: 32px; font-size: 1.1rem; line-height: 32px; background:#330066; color:#c2a4ff;">U</div>
                  <span class="fw-bold" style="color:#c2a4ff;">Usuario</span>
                </div>
                <div class="flex-grow-1 post-content-preview" style="cursor:pointer;" data-post-title="Post timeline #<?php echo $i; ?>" data-post-content="Este es un ejemplo de post publicado en el timeline. Puedes personalizarlo con el contenido real que quieras mostrar.">
                  <strong>Post timeline #<?php echo $i; ?></strong><br>
                  Este es un ejemplo de post publicado en el timeline. Puedes personalizarlo con el contenido real que quieras mostrar.
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

  <script>
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

      // HTML del formulario de publicar (solo el contenido interior, sin fondo ni container)
      const publicarFormHtml = `
        <div class=\"bottom-content\" style=\"display:flex; align-items:center; gap:8px; width:100%; justify-content:center; margin-bottom:8px;\">
          <div class=\"bottom-img-circle\" style=\"width:40px; height:40px; border:2px solid #6a0dad; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:0.8em; font-weight:bold; color:#e0e0e0; background-color:#330066; flex-shrink:0;\">IMGPF</div>
          <span class=\"bottom-username-text\" style=\"font-size:1em; color:#e0e0e0;\">Username</span>
        </div>
        <div class=\"top-section\" style=\"display:flex; align-items:flex-start; width:100%;\">
          <div class=\"input-wrapper\" style=\"flex-grow:1; border:1px solid #6a0dad; border-radius:5px; padding:10px; display:flex; align-items:flex-start; min-height:120px; background-color:#1a1a1a;\">
            <div class=\"input-field\" id=\"mainInputFieldModal\" contenteditable=\"true\" placeholder=\"Inputfield\" style=\"border:none; outline:none; width:100%; font-size:1em; color:#e0e0e0; resize:none; padding:0; margin:0; font-family:sans-serif; box-sizing:border-box; background-color:transparent;\"></div>
          </div>
        </div>
        <div class=\"toolbar\" style=\"width:100%; display:flex; justify-content:flex-start; align-items:center; gap:15px; padding:10px 0; border:1px solid #6a0dad; border-top:none; border-radius:0 0 5px 5px; background-color:#330066; padding-left:10px; margin-top:-10px; z-index:1;\">
          <div class=\"format-icons\">
            <i class=\"bi bi-camera-fill toolbar-icon\" title=\"Cargar foto\"></i>
            <i class=\"bi bi-type-bold toolbar-icon\" data-command=\"bold\" title=\"Negritas\"></i>
            <i class=\"bi bi-type-italic toolbar-icon\" data-command=\"italic\" title=\"Cursiva\"></i>
            <i class=\"bi bi-type-strikethrough toolbar-icon\" data-command=\"strikeThrough\" title=\"Tachar\"></i>
          </div>
          <button class=\"publish-button\" style=\"margin-left:auto; padding:5px 15px; border:1px solid #9933ff; background-color:#9933ff; color:white; border-radius:5px; cursor:pointer; font-size:0.9em; margin-right:10px;\">Publicar</button>
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
        // Lógica para publicar el post (solo frontend)
        if (btn && input) {
          btn.onclick = null;
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            let texto = input.innerHTML || input.innerText || input.value || input.textContent;
            texto = texto.trim();
            if (!texto) {
              input.focus();
              input.style.border = '1px solid #ff5252';
              return;
            }
            // Crear el post visualmente
            const postsList = document.getElementById('postsList');
            const postDiv = document.createElement('div');
            postDiv.className = 'card bg-dark text-light mb-3';
            postDiv.innerHTML = `
              <div class='card-header fw-bold' style='color:#c2a4ff;'>${tipo}</div>
              <div class='card-body'><div class='card-text'>${texto.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}</div></div>
            `;
            postsList.prepend(postDiv);
            // Cerrar el modal
            const modalInstance = bootstrap.Modal.getInstance(modal);
            modalInstance.hide();
          });
        }
      });

      // Modal para ver post completo (igual que en profile.php)
      document.querySelectorAll('.post-content-preview').forEach(function(el) {
        el.addEventListener('click', function() {
          const title = this.getAttribute('data-post-title');
          const content = this.getAttribute('data-post-content');
          if (!document.getElementById('modalVerPost')) {
            const modalHtml = `
              <div class=\"modal fade\" id=\"modalVerPost\" tabindex=\"-1\" aria-labelledby=\"modalVerPostLabel\" aria-hidden=\"true\">
                <div class=\"modal-dialog modal-dialog-centered\">
                  <div class=\"modal-content bg-dark text-light border-secondary\">
                    <div class=\"modal-header border-secondary\">
                      <h5 class=\"modal-title\" id=\"modalVerPostLabel\">Post completo</h5>
                      <button type=\"button\" class=\"btn-close btn-close-white\" data-bs-dismiss=\"modal\" aria-label=\"Cerrar\"></button>
                    </div>
                    <div class=\"modal-body\" id=\"modalVerPostBody\"></div>
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
