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

    <div class="dashboard-top-section">
      <div class="dashboard-img-sidebar">
        <center>
        <div class="dashboard-img-circle">❤️​</div>
        <span class="dashboard-bottom-username-text">[100]</span>
        <br><br>
        <div class="dashboard-img-circle">☠️​</div>
        <span class="dashboard-bottom-username-text">[100]</span>
        <br><br>
        <div class="dashboard-img-circle">💢​</div>
        <span class="dashboard-bottom-username-text">[100]</span>
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
    });
  </script>
</body>
</html>
