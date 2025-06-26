<nav class="navbar fixed-bottom bg-dark border-top border-secondary shadow social-navbar">
  <div class="container-fluid d-flex justify-content-around align-items-center text-center">

    <a href="../users/dashboard.php" class="nav-link text-white d-flex flex-column align-items-center">
      <i class="bi bi-house-door-fill fs-4"></i>
      <small>Inicio</small>
    </a>

    <!-- Botón de publicar con opciones -->
    <div class="dropup">
      <a class="nav-link text-white d-flex flex-column align-items-center dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-plus-circle-fill fs-4"></i>
        <small>Publicar</small>
      </a>
      <ul class="dropdown-menu text-start">
        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalPublicar" data-tipo="POST">POST</a></li>
        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalPublicar" data-tipo="CONFESIÓN ANONIMA">Confesión Anónima</a></li>
      </ul>
    </div>

    <!-- Dropup en Perfil -->
    <div class="dropup">
      <a class="nav-link text-white d-flex flex-column align-items-center dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-person-circle fs-4"></i>
        <small>Perfil</small>
      </a>
      <ul class="dropdown-menu dropdown-menu-end text-start">
        <li><a class="dropdown-item" href="../users/profile.php"> YO (Mi perfil) </a></li>
        <li><a class="dropdown-item" href="../users/edit-profile.php">Editar perfil</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="../users/logout.php">Cerrar sesión</a></li>
      </ul>
    </div>

  </div>
</nav>
