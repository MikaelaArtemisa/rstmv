<?php
session_start();

require_once 'includes/db.php';

$login_error = '';
$register_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['register_submit'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $login_error = "Por favor, completa todos los campos.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: users/dashboard.php");
                exit;
            } else {
                $login_error = "Email o contraseña inválidos.";
            }
        } catch (PDOException $e) {
            error_log("Error de login: " . $e->getMessage());
            $login_error = "Ocurrió un error al intentar iniciar sesión. Por favor, inténtalo de nuevo más tarde.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $username = filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW);
    $username = preg_replace('/[^a-zA-Z0-9_.]/', '', $username);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if (empty($email) || empty($username) || empty($password) || empty($confirmPassword)) {
        $register_error = "Por favor, completa todos los campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Formato de email inválido.";
    } elseif (strlen($password) < 8) {
        $register_error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($password !== $confirmPassword) {
        $register_error = "Las contraseñas no coinciden.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetchColumn() > 0) {
                $register_error = "El email o nombre de usuario ya existe.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, username, password) VALUES (?, ?, ?)");
                $stmt->execute([$email, $username, $hashed_password]);

                $_SESSION['success_message'] = "¡Registro exitoso! Por favor, inicia sesión.";
                header("Location: index.php");
                exit;
            }
        } catch(PDOException $e) {
            error_log("Error de registro: " . $e->getMessage());
            $register_error = "El registro falló. Por favor, inténtalo de nuevo.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Bienvenido a Nuestra Red Social</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="assets/css/styles.css"> 
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

</head>
<body>



<?php if (isset($_GET['expired'])): ?>
    <div class="alert alert-warning">Tu sesión ha caducado por inactividad. Por favor, inicia sesión nuevamente.</div>
<?php endif; ?>


    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="auth-panel">
            <div class="form-container">
                <h1 class="welcome-header">Welcome</h1>
                <p class="welcome-text">Connect and share with our community.</p>
                <?php if ($login_error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                <form class="auth-form login-form" method="POST" action=""> 
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Log In</button>
                </form>
            </div>
            <br>
            <div class="accordion" id="registerAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRegister" aria-expanded="false" aria-controls="collapseRegister">
                            Don’t have an account? Sign Up
                        </button>
                    </h2>
                    <div id="collapseRegister" class="accordion-collapse collapse" data-bs-parent="#registerAccordion">
                        <div class="accordion-body">
                            <br>
                            <h1 class="welcome-header" style="font-size: 2.2rem;">Regístrate</h1>
                            <p class="welcome-text">Únete a nuestra comunidad.</p>
                            <?php if ($register_error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($register_error); ?></div>
                            <?php endif; ?>
                            <form class="auth-form register-form" method="POST" action="">
                                <div class="mb-3">
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Correo electrónico" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" id="username" name="username" class="form-control" placeholder="Nombre de usuario" required>
                                </div>
                                <div class="mb-3">
                                    <input type="password" id="password_register" name="password" class="form-control" placeholder="Contraseña" required>
                                </div>
                                <div class="mb-3">
                                    <input type="password" id="confirmPassword_register" name="confirmPassword" class="form-control" placeholder="Repite la contraseña" required>
                                </div>
                                <div id="passwordStrength" class="feedback"></div>
                                <div id="passwordMatch" class="feedback"></div>
                                <button type="submit" name="register_submit" class="btn btn-primary w-100">Crear cuenta</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="background-effect"></div>
    <script src="assets/js/javascript.js"></script>
</body>
</html>