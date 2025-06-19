<?php
session_start();

// Simulación de datos actuales (en un caso real, vendrían de la BD)
$current_username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'usuario123';
$current_name = 'Nombre de Ejemplo';
$current_desc = 'Apasionado por la tecnología, desarrollo web y diseño UI/UX. Siempre aprendiendo y compartiendo conocimiento.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Perfil</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body style="background-color: #0A0F1E; color: #E0F7FA; min-height: 100vh;">
  <div class="d-flex flex-column justify-content-center align-items-center min-vh-100" style="min-height:100vh;">
    <div class="dashboard-container" style="max-width: 420px; width:100%; background-color: #0d0d0d; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); padding: 32px 24px;">
      <h2 class="mb-4 text-center fw-bold" style="color:#c2a4ff;"><i class="bi bi-pencil-square me-2"></i>Editar Perfil</h2>
      <form id="editProfileForm" autocomplete="off">
        <div class="mb-3">
          <label for="username" class="form-label">Usuario</label>
          <input type="text" class="form-control bg-dark text-light border-secondary" id="username" name="username" value="<?php echo $current_username; ?>" required minlength="3" maxlength="20">
        </div>
        <div class="mb-3">
          <label for="name" class="form-label">Nombre</label>
          <input type="text" class="form-control bg-dark text-light border-secondary" id="name" name="name" value="<?php echo $current_name; ?>" required maxlength="40">
        </div>
        <div class="mb-3">
          <label for="desc" class="form-label">Descripción</label>
          <textarea class="form-control bg-dark text-light border-secondary" id="desc" name="desc" rows="3" maxlength="180" required><?php echo $current_desc; ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100">Guardar Cambios</button>
        <div id="msgSuccess" class="alert alert-success mt-3 d-none" role="alert">
          ¡Perfil actualizado exitosamente! (Simulado)
        </div>
      </form>
      <a href="profile.php" class="btn btn-link text-secondary mt-3"><i class="bi bi-arrow-left"></i> Volver al perfil</a>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
      e.preventDefault();
      // Validación básica
      const username = document.getElementById('username').value.trim();
      const name = document.getElementById('name').value.trim();
      const desc = document.getElementById('desc').value.trim();
      if(username.length < 3 || name.length < 1 || desc.length < 1) {
        alert('Por favor, completa todos los campos correctamente.');
        return;
      }
      // Simulación de guardado
      document.getElementById('msgSuccess').classList.remove('d-none');
      setTimeout(() => {
        document.getElementById('msgSuccess').classList.add('d-none');
      }, 2000);
    });
  </script>
</body>
</html> 