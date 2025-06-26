<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?expired=1');
    exit;
}
$user_id = $_SESSION['user_id'];

// Crear directorio de uploads si no existe
$upload_dir = '../uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Obtener datos actuales del usuario
$stmt = $pdo->prepare("SELECT username, aboutme, email, password, profile_picture FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$current_username = $user['username'] ?? '';
$current_desc = $user['aboutme'] ?? '';
$current_email = $user['email'] ?? '';
$current_profile_picture = $user['profile_picture'] ?? '';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_desc = trim($_POST['desc'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $update_desc = false;
    $update_password = false;
    $update_picture = false;
    
    // Actualizar biografía
    if ($new_desc !== $current_desc) {
        $stmt = $pdo->prepare("UPDATE users SET aboutme = ? WHERE id = ?");
        $stmt->execute([$new_desc, $user_id]);
        $current_desc = $new_desc;
        $update_desc = true;
    }
    
    // Procesar imagen de perfil
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_picture'];
        
        // Validaciones de seguridad para la imagen
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = '<div class="alert alert-danger">Error al subir la imagen. Código: ' . $file['error'] . '</div>';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB máximo
            $msg = '<div class="alert alert-danger">La imagen es demasiado grande. Máximo 5MB.</div>';
        } else {
            // Verificar tipo de archivo
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                $msg = '<div class="alert alert-danger">Solo se permiten imágenes JPG, PNG y GIF.</div>';
            } else {
                // Verificar extensión del archivo
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $msg = '<div class="alert alert-danger">Extensión de archivo no permitida.</div>';
                } else {
                    // Generar nombre único para el archivo
                    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Verificar que el archivo sea realmente una imagen
                    $image_info = getimagesize($file['tmp_name']);
                    if ($image_info === false) {
                        $msg = '<div class="alert alert-danger">El archivo no es una imagen válida.</div>';
                    } else {
                        // Intentar subir el archivo
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            // Eliminar imagen anterior si existe
                            if (!empty($current_profile_picture) && file_exists($upload_dir . $current_profile_picture)) {
                                unlink($upload_dir . $current_profile_picture);
                            }
                            
                            // Actualizar en la base de datos
                            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                            $stmt->execute([$new_filename, $user_id]);
                            $current_profile_picture = $new_filename;
                            $update_picture = true;
                        } else {
                            $msg = '<div class="alert alert-danger">Error al guardar la imagen en el servidor.</div>';
                        }
                    }
                }
            }
        }
    }
    
    // Cambiar contraseña si se proporcionaron los campos
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Verificar que todos los campos de contraseña estén completos
        if (empty($current_password)) {
            $msg = '<div class="alert alert-danger">Debe ingresar su contraseña actual.</div>';
        } elseif (empty($new_password)) {
            $msg = '<div class="alert alert-danger">Debe ingresar la nueva contraseña.</div>';
        } elseif (empty($confirm_password)) {
            $msg = '<div class="alert alert-danger">Debe confirmar la nueva contraseña.</div>';
        } else {
            // Verificar contraseña actual
            if (!password_verify($current_password, $user['password'])) {
                $msg = '<div class="alert alert-danger">La contraseña actual es incorrecta.</div>';
            } elseif ($new_password !== $confirm_password) {
                $msg = '<div class="alert alert-danger">Las contraseñas nuevas no coinciden.</div>';
            } elseif (strlen($new_password) < 8) {
                $msg = '<div class="alert alert-danger">La nueva contraseña debe tener al menos 8 caracteres.</div>';
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $msg = '<div class="alert alert-danger">La nueva contraseña debe contener al menos una letra mayúscula.</div>';
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $msg = '<div class="alert alert-danger">La nueva contraseña debe contener al menos una letra minúscula.</div>';
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                $msg = '<div class="alert alert-danger">La nueva contraseña debe contener al menos un número.</div>';
            } elseif ($current_password === $new_password) {
                $msg = '<div class="alert alert-danger">La nueva contraseña debe ser diferente a la actual.</div>';
            } else {
                // Hash de la nueva contraseña y actualización
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $update_password = true;
            }
        }
    }
    
    // Mostrar mensaje de éxito si no hay errores
    if (empty($msg)) {
        $success_msg = '';
        if ($update_desc && $update_password && $update_picture) {
            $success_msg = '¡Perfil, contraseña e imagen actualizados exitosamente!';
        } elseif ($update_desc && $update_password) {
            $success_msg = '¡Perfil y contraseña actualizados exitosamente!';
        } elseif ($update_desc && $update_picture) {
            $success_msg = '¡Perfil e imagen actualizados exitosamente!';
        } elseif ($update_password && $update_picture) {
            $success_msg = '¡Contraseña e imagen actualizadas exitosamente!';
        } elseif ($update_desc) {
            $success_msg = '¡Perfil actualizado exitosamente!';
        } elseif ($update_password) {
            $success_msg = '¡Contraseña actualizada exitosamente!';
        } elseif ($update_picture) {
            $success_msg = '¡Imagen de perfil actualizada exitosamente!';
        }
        
        if (!empty($success_msg)) {
            $msg = '<div class="alert alert-success">' . $success_msg . '</div>';
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
  <style>
    .profile-picture-container {
      position: relative;
      display: inline-block;
      margin-bottom: 20px;
    }
    .profile-picture {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #c2a4ff;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .profile-picture:hover {
      transform: scale(1.05);
      border-color: #9c7cff;
    }
    .upload-overlay {
      position: absolute;
      bottom: 0;
      right: 0;
      background: #c2a4ff;
      border-radius: 50%;
      width: 35px;
      height: 35px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .upload-overlay:hover {
      background: #9c7cff;
      transform: scale(1.1);
    }
    .file-input {
      display: none;
    }
  </style>
</head>
<body style="background-color: #0A0F1E; color: #E0F7FA; min-height: 100vh;">
  <div class="d-flex flex-column justify-content-center align-items-center min-vh-100" style="min-height:100vh;">
    <div class="dashboard-container" style="max-width: 450px; width:100%; background-color: #0d0d0d; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); padding: 32px 24px;">
      <h2 class="mb-4 text-center fw-bold" style="color:#c2a4ff;"><i class="bi bi-pencil-square me-2"></i>Editar Perfil</h2>
      <?php echo $msg; ?>
      
      <form method="POST" enctype="multipart/form-data" autocomplete="off">
        <!-- Foto de perfil -->
        <div class="text-center mb-4">
          <div class="profile-picture-container">
            <img src="<?php echo !empty($current_profile_picture) ? '../uploads/profile_pictures/' . htmlspecialchars($current_profile_picture) : '../assets/images/default-avatar.png'; ?>" 
                 alt="Foto de perfil" 
                 class="profile-picture" 
                 id="profile-preview"
                 onclick="document.getElementById('profile_picture').click();">
            <div class="upload-overlay" onclick="document.getElementById('profile_picture').click();">
              <i class="bi bi-camera text-dark"></i>
            </div>
          </div>
          <input type="file" 
                 class="file-input" 
                 id="profile_picture" 
                 name="profile_picture" 
                 accept="image/jpeg,image/jpg,image/png,image/gif"
                 onchange="previewImage(this);">
          <div class="mt-2">
            <small class="text-white">Haz clic en la imagen para cambiar. Máximo 5MB. Formatos: JPG, PNG, GIF</small>
          </div>
        </div>
        
        <div class="mb-3">
          <label for="username" class="form-label text-white">Usuario</label>
          <input type="text" class="form-control bg-dark text-light border-secondary" id="username" value="<?php echo htmlspecialchars($current_username); ?>" readonly disabled>
          <small class="text-white">El nombre de usuario no se puede editar</small>
        </div>
        <div class="mb-3">
          <label for="desc" class="form-label">Biografía</label>
          <textarea class="form-control bg-dark text-light border-secondary" id="desc" name="desc" rows="3" maxlength="255"><?php echo htmlspecialchars($current_desc); ?></textarea>
        </div>
        
        <hr class="my-4" style="border-color: #333;">
        <h5 class="mb-3" style="color:#c2a4ff;"><i class="bi bi-shield-lock me-2"></i>Cambiar Contraseña</h5>
        
        <div class="mb-3">
          <label for="current_password" class="form-label">Contraseña Actual</label>
          <input type="password" class="form-control bg-dark text-light border-secondary" id="current_password" name="current_password">
        </div>
        <div class="mb-3">
          <label for="new_password" class="form-label">Nueva Contraseña</label>
          <input type="password" class="form-control bg-dark text-light border-secondary" id="new_password" name="new_password">
          <small class="text-muted">Mínimo 8 caracteres, una mayúscula, una minúscula y un número</small>
        </div>
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
          <input type="password" class="form-control bg-dark text-light border-secondary" id="confirm_password" name="confirm_password">
        </div>
        
        <button type="submit" class="btn btn-primary w-100">Guardar Cambios</button>
      </form>
      <a href="profile.php" class="btn btn-link text-secondary mt-3"><i class="bi bi-arrow-left"></i> Volver al perfil</a>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    function previewImage(input) {
      if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validar tamaño (5MB)
        if (file.size > 5 * 1024 * 1024) {
          alert('La imagen es demasiado grande. Máximo 5MB.');
          input.value = '';
          return;
        }
        
        // Validar tipo de archivo
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
          alert('Solo se permiten imágenes JPG, PNG y GIF.');
          input.value = '';
          return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('profile-preview').src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    }
  </script>
</body>
</html> 