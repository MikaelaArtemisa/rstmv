<?php
session_start();

// Error messages
$login_error = $register_error = '';

// Login processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['register_submit'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $login_error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: dashboard.php");
            exit;
        } else {
            $login_error = "Invalid email or password.";
        }
    }
}

// Registration processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validation
    if (empty($email) || empty($username) || empty($password) || empty($confirmPassword)) {
        $register_error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $register_error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirmPassword) {
        $register_error = "Passwords do not match.";
    } else {
        // Check if email or username exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetchColumn() > 0) {
            $register_error = "Email or username already exists.";
        } else {
            // Register user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, username, password) VALUES (?, ?, ?)");
            
            try {
                $stmt->execute([$email, $username, $hashed_password]);
                $_SESSION['success_message'] = "Registration successful! Please log in.";
                header("Location: index.php");
                exit;
            } catch(PDOException $e) {
                $register_error = "Registration failed. Please try again.";
            }
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
</head>
<body>
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
                                    <input type="password" id="password" name="password" class="form-control" placeholder="Contraseña" required>
                                </div>
                                <div class="mb-3">
                                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" placeholder="Repite la contraseña" required>
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