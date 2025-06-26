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
    <title>INACX - POST , FOTOS, CONFESIONES, CITAS & MOR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="assets/css/styles.css"> 
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .logo-container {
            text-align: center;
            margin: 20px 0;
        }
        
        .welcome-logo {
            max-width: 250px;
            width: 100%;
            height: auto;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            animation: logoFloat 3s ease-in-out infinite;
        }
        
        .welcome-logo:hover {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
        }
        
        @keyframes logoFloat {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        @media (max-width: 768px) {
            .welcome-logo {
                max-width: 200px;
            }
        }
        
        @media (max-width: 480px) {
            .welcome-logo {
                max-width: 180px;
            }
        }
        
        /* Estilos para validaciones de contraseña */
        .feedback {
            margin-top: 8px;
        }
        
        .progress {
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-bar {
            transition: width 0.3s ease, background-color 0.3s ease;
            border-radius: 10px;
        }
        
        .form-control:focus {
            border-color: #6a0dad;
            box-shadow: 0 0 0 0.2rem rgba(106, 13, 173, 0.25);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        
        /* Animaciones para los iconos de validación */
        .fas {
            margin-right: 5px;
        }
        
        .text-success .fas {
            animation: fadeIn 0.3s ease-in;
        }
        
        .text-danger .fas {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>

</head>
<body>



<?php if (isset($_GET['expired'])): ?>
    <div class="alert alert-warning">Tu sesión ha caducado por inactividad. Por favor, inicia sesión nuevamente.</div>
<?php endif; ?>


    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="auth-panel">
            <div class="form-container">
        
                <div class="logo-container">
                    <img src="assets/images/logo-inacx.png" alt="Logo" class="welcome-logo">
                </div>
                <p class="welcome-text"> POST , FOTOS, CONFESIONES, CITAS & MORE.</p>
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
                        <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>
            <br>
            <div class="accordion" id="registerAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRegister" aria-expanded="false" aria-controls="collapseRegister">
                        📲​REGISTRATE AQUÍ SI NO TIENES CUENTA📍​
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password_register');
            const confirmPasswordInput = document.getElementById('confirmPassword_register');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordMatch = document.getElementById('passwordMatch');
            const registerButton = document.querySelector('button[name="register_submit"]');
            
            // Función para validar fortaleza de contraseña
            function validatePasswordStrength(password) {
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    numbers: /\d/.test(password),
                    special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
                };
                
                let strength = 0;
                let feedback = [];
                
                if (requirements.length) strength++;
                if (requirements.uppercase) strength++;
                if (requirements.lowercase) strength++;
                if (requirements.numbers) strength++;
                if (requirements.special) strength++;
                
                // Generar mensajes de feedback
                if (!requirements.length) feedback.push('Al menos 8 caracteres');
                if (!requirements.uppercase) feedback.push('Una mayúscula');
                if (!requirements.lowercase) feedback.push('Una minúscula');
                if (!requirements.numbers) feedback.push('Un número');
                if (!requirements.special) feedback.push('Un carácter especial');
                
                return { strength, feedback, requirements };
            }
            
            // Función para actualizar indicador de fortaleza
            function updatePasswordStrength() {
                const password = passwordInput.value;
                const validation = validatePasswordStrength(password);
                
                let strengthClass = '';
                let strengthText = '';
                let strengthColor = '';
                
                switch(validation.strength) {
                    case 0:
                    case 1:
                        strengthClass = 'text-danger';
                        strengthText = 'Muy débil';
                        strengthColor = '#dc3545';
                        break;
                    case 2:
                        strengthClass = 'text-warning';
                        strengthText = 'Débil';
                        strengthColor = '#ffc107';
                        break;
                    case 3:
                        strengthClass = 'text-info';
                        strengthText = 'Media';
                        strengthColor = '#17a2b8';
                        break;
                    case 4:
                        strengthClass = 'text-primary';
                        strengthText = 'Fuerte';
                        strengthColor = '#007bff';
                        break;
                    case 5:
                        strengthClass = 'text-success';
                        strengthText = 'Muy fuerte';
                        strengthColor = '#28a745';
                        break;
                }
                
                passwordStrength.innerHTML = `
                    <div class="mt-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="${strengthClass} fw-bold">${strengthText}</span>
                            <div class="progress" style="width: 60%; height: 8px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: ${(validation.strength / 5) * 100}%; background-color: ${strengthColor};" 
                                     aria-valuenow="${validation.strength}" aria-valuemin="0" aria-valuemax="5">
                                </div>
                            </div>
                        </div>
                        ${validation.feedback.length > 0 ? 
                            `<small class="text-muted mt-1 d-block">
                                <i class="fas fa-info-circle"></i> Requiere: ${validation.feedback.join(', ')}
                            </small>` : 
                            '<small class="text-success mt-1 d-block"><i class="fas fa-check-circle"></i> Contraseña cumple todos los requisitos</small>'
                        }
                    </div>
                `;
                
                return validation.strength >= 3; // Mínimo 3 requisitos cumplidos
            }
            
            // Función para validar que las contraseñas coincidan
            function validatePasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword === '') {
                    passwordMatch.innerHTML = '';
                    return false;
                }
                
                if (password === confirmPassword) {
                    passwordMatch.innerHTML = '<small class="text-success mt-1 d-block"><i class="fas fa-check-circle"></i> Las contraseñas coinciden</small>';
                    return true;
                } else {
                    passwordMatch.innerHTML = '<small class="text-danger mt-1 d-block"><i class="fas fa-times-circle"></i> Las contraseñas no coinciden</small>';
                    return false;
                }
            }
            
            // Función para validar el formulario completo
            function validateForm() {
                const passwordValid = updatePasswordStrength();
                const matchValid = validatePasswordMatch();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Habilitar/deshabilitar botón de registro
                if (passwordValid && matchValid && password.length > 0 && confirmPassword.length > 0) {
                    registerButton.disabled = false;
                    registerButton.classList.remove('btn-secondary');
                    registerButton.classList.add('btn-primary');
                } else {
                    registerButton.disabled = true;
                    registerButton.classList.remove('btn-primary');
                    registerButton.classList.add('btn-secondary');
                }
            }
            
            // Event listeners para validación en tiempo real
            passwordInput.addEventListener('input', function() {
                updatePasswordStrength();
                validatePasswordMatch();
                validateForm();
            });
            
            confirmPasswordInput.addEventListener('input', function() {
                validatePasswordMatch();
                validateForm();
            });
            
            // Validación inicial
            validateForm();
            
            // Prevenir envío del formulario si no es válido
            document.querySelector('.register-form').addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const validation = validatePasswordStrength(password);
                
                if (validation.strength < 3) {
                    e.preventDefault();
                    alert('La contraseña debe cumplir al menos 3 de los 5 requisitos de seguridad.');
                    passwordInput.focus();
                    return false;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden.');
                    confirmPasswordInput.focus();
                    return false;
                }
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('La contraseña debe tener al menos 8 caracteres.');
                    passwordInput.focus();
                    return false;
                }
            });
        });
    </script>
</body>
</html>