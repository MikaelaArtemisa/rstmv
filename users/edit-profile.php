<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?expired=1');
    exit;
}
$user_id = $_SESSION['user_id'];

// Obtener datos actuales del usuario
$stmt = $pdo->prepare("SELECT username, aboutme, email FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$current_username = $user['username'] ?? '';
$current_desc = $user['aboutme'] ?? '';
$current_email = $user['email'] ?? '';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $new_desc = trim($_POST['desc'] ?? '');
    if ($new_username === '') {
        $msg = '<div class="alert alert-danger">El nombre de usuario no puede estar vacío.</div>';
    } else {
        // Verificar si el username está en uso por otro usuario
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $stmt->execute([$new_username, $user_id]);
        if ($stmt->fetch()) {
            $msg = '<div class="alert alert-danger">El nombre de usuario ya está en uso.</div>';
        } else {
            // Actualizar username y aboutme
            $stmt = $pdo->prepare("UPDATE users SET username = ?, aboutme = ? WHERE id = ?");
            $stmt->execute([$new_username, $new_desc, $user_id]);
            $_SESSION['username'] = $new_username;
            $current_username = $new_username;
            $current_desc = $new_desc;
            $msg = '<div class="alert alert-success">¡Perfil actualizado exitosamente!</div>';
        }
    }
}
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
      <?php echo $msg; ?>
      <form method="POST" autocomplete="off">
        <div class="mb-3">
          <label for="username" class="form-label">Usuario</label>
          <input type="text" class="form-control bg-dark text-light border-secondary" id="username" name="username" value="<?php echo htmlspecialchars($current_username); ?>" required minlength="3" maxlength="50">
        </div>
        <div class="mb-3">
          <label for="desc" class="form-label">Biografía</label>
          <textarea class="form-control bg-dark text-light border-secondary" id="desc" name="desc" rows="3" maxlength="255" required><?php echo htmlspecialchars($current_desc); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100">Guardar Cambios</button>
      </form>
      <a href="profile.php" class="btn btn-link text-secondary mt-3"><i class="bi bi-arrow-left"></i> Volver al perfil</a>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html> 