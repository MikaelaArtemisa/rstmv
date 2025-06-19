<?php
session_start();
require_once '../includes/db.php'; // Ajusta el path si es necesario

// Simulación de login para pruebas (elimina esto en producción)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'usuario_demo';
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Procesar publicación
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo'], $_POST['contenido'])) {
    $tipo = trim($_POST['tipo']);
    $contenido = trim($_POST['contenido']);
    if ($tipo && $contenido) {
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, username, tipo, contenido) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $username, $tipo, $contenido]);
        $msg = "¡Post publicado!";
    } else {
        $msg = "Completa todos los campos.";
    }
}

// Obtener SOLO los posts del usuario logueado
$stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY fecha DESC");
$stmt->execute([$user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
</head>
<body style="background-color: #0A0F1E; color: #E0F7FA;">
<div class="container py-4">
  <h2 class="mb-4 text-center" style="color:#c2a4ff;">Mi Perfil</h2>
  <div class="card bg-dark text-light mb-4 p-4 shadow-sm" style="max-width:600px; margin:auto;">
    <h4 class="mb-3">Publicar algo nuevo</h4>
    <?php if ($msg): ?>
      <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label for="tipo" class="form-label">Tipo de publicación</label>
        <select name="tipo" id="tipo" class="form-select bg-dark text-light border-secondary" required>
          <option value="">Selecciona...</option>
          <option value="POST">POST</option>
          <option value="CONFESIÓN PUBLICA">CONFESIÓN PUBLICA</option>
          <option value="CONFESIÓN ANONIMA">CONFESIÓN ANONIMA</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="contenido" class="form-label">Contenido</label>
        <textarea name="contenido" id="contenido" class="form-control bg-dark text-light border-secondary" rows="3" required maxlength="500"></textarea>
      </div>
      <button type="submit" class="btn btn-primary w-100">Publicar</button>
    </form>
  </div>

  <h3 class="mt-5 mb-3 text-center" style="color:#c2a4ff;">Mis publicaciones</h3>
  <div class="row row-cols-1 g-4">
    <?php foreach ($posts as $post): ?>
      <div class="col">
        <div class="card bg-dark text-light h-100 shadow-sm border-0">
          <div class="card-body d-flex flex-row align-items-stretch gap-3">
            <div class="dashboard-img-sidebar d-flex flex-column justify-content-center align-items-center gap-2 flex-shrink-0" style="min-width:48px;">
              <div class="dashboard-img-circle"><?php echo htmlspecialchars($post['tipo'][0]); ?></div>
              <span class="dashboard-bottom-username-text" style="font-size:0.8em;"><?php echo htmlspecialchars($post['tipo']); ?></span>
            </div>
            <div class="flex-grow-1 d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <div class="dashboard-img-circle me-2" style="width: 32px; height: 32px; font-size: 1.1rem; line-height: 32px; background:#330066; color:#c2a4ff;">
                  <?php echo strtoupper($post['username'][0]); ?>
                </div>
                <span class="fw-bold" style="color:#c2a4ff;"><?php echo htmlspecialchars($post['username']); ?></span>
                <span class="ms-auto small text-secondary"><?php echo date('d/m/Y H:i', strtotime($post['fecha'])); ?></span>
              </div>
              <div class="flex-grow-1">
                <?php echo nl2br(htmlspecialchars($post['contenido'])); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($posts)): ?>
      <div class="col"><div class="alert alert-secondary text-center">No has publicado nada aún.</div></div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
