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
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RSIDEA Layout - Perfil</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="../assets/css/styles.css" />

  <style>
    body, html {
      height: 100%;
      margin: 0;
      background-color: #121212;
      color: #ddd;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .dashboard-container {
      min-height: 60vh;
      display: flex;
      flex-direction: column;
      padding: 24px 32px;
      box-sizing: border-box;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 32px;
      border-bottom: 1px solid #333;
      padding-bottom: 20px;
    }
    .user-info .dashboard-img-circle {
      width: 80px;
      height: 80px;
      font-size: 2.4rem;
      line-height: 80px;
      text-align: center;
      background-color: #3a3a3a;
      border-radius: 50%;
      user-select: none;
      flex-shrink: 0;
      color: #c2a4ff;
    }
    .user-details {
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex-grow: 1;
    }
    .user-details h2 {
      margin: 0;
      font-weight: 700;
      font-size: 1.8rem;
      color: #c2a4ff;
    }
    .user-details p.description {
      font-size: 1rem;
      color: #aaa;
      margin: 0;
      max-width: 600px;
    }
    .user-stats {
      display: flex;
      gap: 32px;
      font-size: 1rem;
      color: #9e9e9e;
      user-select: none;
      margin-top: 8px;
    }
    .user-stats span {
      white-space: nowrap;
    }

    /* Grid de posts con 4 columnas */
    .posts-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 24px;
      padding-right: 8px;
    }

    /* Cada post ocupa 2 columnas, es decir, el doble de ancho */
    .post-block {
      background-color: #1f1f1f;
      border-radius: 14px;
      box-shadow: 0 4px 12px rgba(106, 13, 173, 0.5);
      padding: 16px 20px;
      display: flex;
      align-items: center;
      color: #ddd;
      user-select: none;
      min-height: 100px;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
      cursor: default;
      grid-column: span 2;
    }
    .post-block:hover {
      background-color: #2a2a2a;
      box-shadow: 0 6px 18px rgba(106, 13, 173, 0.9);
    }

    .post-block .dashboard-img-sidebar {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-right: 18px;
      min-width: 64px;
      user-select: none;
    }
    .post-block .dashboard-img-circle {
      font-size: 1.2rem;
      width: 26px;
      height: 26px;
      margin-bottom: 6px;
      line-height: 26px;
      background-color: #333;
      border-radius: 50%;
      color: #c2a4ff;
    }
    .post-block .dashboard-bottom-username-text {
      font-size: 0.7rem;
      margin-bottom: 10px;
      line-height: 1rem;
      color: #bbb;
    }

    .post-content {
      font-size: 1rem;
      line-height: 1.4;
      white-space: normal;
      flex: 1;
      overflow-wrap: break-word;
    }
  </style>
</head>
<body>

<?php include '../includes/partials/navbar.php' ?>

<div class="dashboard-container">

  <!-- Info usuario -->
  <div class="user-info">
    <div class="dashboard-img-circle">IMGPF</div>
    <div class="user-details">
      <h2><?php echo $username; ?></h2>
      <p class="description">Apasionado por la tecnología, desarrollo web y diseño UI/UX. Siempre aprendiendo y compartiendo conocimiento.</p>
      <div class="user-stats">
        <span><strong>Seguidores:</strong> 256</span>
        <span><strong>Siguiendo:</strong> 180</span>
        <span><strong>Publicaciones:</strong> 34</span>
      </div>
    </div>
  </div>

  <!-- Grid de posts -->
  <div class="posts-grid">
    <?php for ($i = 1; $i <= 3; $i++): ?>
      <div class="post-block">
        <div class="dashboard-img-sidebar">
          <div class="dashboard-img-circle">❤️</div>
          <span class="dashboard-bottom-username-text">100</span>
          <div class="dashboard-img-circle">☠️</div>
          <span class="dashboard-bottom-username-text">100</span>
          <div class="dashboard-img-circle">💢</div>
          <span class="dashboard-bottom-username-text">100</span>
        </div>
        <div class="post-content">
          <strong>Post #<?php echo $i; ?></strong><br>
          Este es un ejemplo de texto en la tarjeta del post. Puedes personalizarlo con el contenido real que quieras mostrar.
        </div>
      </div>
    <?php endfor; ?>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<div class="modal fade" id="modalPublicacionAvanzada" tabindex="-1" aria-labelledby="modalPublicacionAvanzadaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
    <div class="modal-content bg-dark text-light border-secondary">

      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="modalPublicacionAvanzadaLabel">Publicación Avanzada</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">


   <div class="container">

        <div class="bottom-content">
            <div class="bottom-img-circle">IMGPF</div>
            <span class="bottom-username-text">Username</span>
        </div>

        <div class="top-section">


            <div class="input-wrapper">
                <div class="input-field" id="mainInputField" contenteditable="true" placeholder="Inputfield"></div>
            </div>
        </div>

        <div class="toolbar">
            <div class="format-icons">
                <i class="bi bi-camera-fill toolbar-icon" title="Cargar foto"></i>
                <i class="bi bi-type-bold toolbar-icon" data-command="bold" title="Negritas"></i>
                <i class="bi bi-type-italic toolbar-icon" data-command="italic" title="Cursiva"></i>
                <i class="bi bi-type-strikethrough toolbar-icon" data-command="strikeThrough" title="Tachar"></i>
            </div>
            
            
            <button class="publish-button">Publicar</button>
        </div>

        </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mainInputField = document.getElementById('mainInputField');
            const toolbarIcons = document.querySelectorAll('.toolbar-icon[data-command]');

            toolbarIcons.forEach(icon => {
                icon.addEventListener('mousedown', function(event) {
                    event.preventDefault(); 
                    
                    const command = this.dataset.command;
                    
                    mainInputField.focus(); 
                    
                    document.execCommand(command, false, null);
                });
            });

            const cameraIcon = document.querySelector('.bi-camera-fill');
            if (cameraIcon) {
                cameraIcon.addEventListener('click', function() {
                    alert('Aquí se abriría un diálogo para cargar una foto.');
                });
            }

            // NUEVO: Funcionalidad para el botón Publicar
            const publishButton = document.querySelector('.publish-button');
            if (publishButton) {
                publishButton.addEventListener('click', function() {
                    const content = mainInputField.innerHTML; // Obtener el HTML con formato
                    alert('Contenido a publicar: ' + content);
                    // Aquí podrías enviar 'content' a tu servidor
                });
            }
        });
    </script>

      </div>

    </div>
  </div>
</div>


</body>
</html>
