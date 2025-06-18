document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordStrengthDiv = document.getElementById('passwordStrength');
    const passwordMatchDiv = document.getElementById('passwordMatch');
    const registerButton = document.querySelector('button[name="register_submit"]');

    function checkPasswordStrength() {
        const password = passwordInput.value;
        let strength = 0;
        let message = '';
        let color = '';

        if (password.length >= 8) {
            strength += 1;
        }
        if (password.match(/[a-z]/)) {
            strength += 1;
        }
        if (password.match(/[A-Z]/)) {
            strength += 1;
        }
        if (password.match(/\d/)) {
            strength += 1;
        }
        if (password.match(/[^a-zA-Z0-9]/)) {
            strength += 1;
        }

        switch (strength) {
            case 0:
            case 1:
                message = 'Muy débil';
                color = 'red';
                break;
            case 2:
                message = 'Débil';
                color = 'orange';
                break;
            case 3:
                message = 'Moderada';
                color = 'yellowgreen';
                break;
            case 4:
                message = 'Fuerte';
                color = 'green';
                break;
            case 5:
                message = 'Muy fuerte';
                color = 'darkgreen';
                break;
        }

        passwordStrengthDiv.textContent = 'Fortaleza: ' + message;
        passwordStrengthDiv.style.color = color;
    }

    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (confirmPassword === '') {
            passwordMatchDiv.textContent = '';
            return;
        }

        if (password === confirmPassword) {
            passwordMatchDiv.textContent = 'Las contraseñas coinciden.';
            passwordMatchDiv.style.color = 'green';
        } else {
            passwordMatchDiv.textContent = 'Las contraseñas no coinciden.';
            passwordMatchDiv.style.color = 'red';
        }
    }

    function validateForm() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        // Básicamente, si la validación JS no es buena, el botón está deshabilitado.
        // La validación del backend es la que realmente asegura la seguridad.
        if (password.length < 8 || password !== confirmPassword) {
            registerButton.disabled = true;
        } else {
            registerButton.disabled = false;
        }
    }

    // Inicializar el estado del botón al cargar la página
    validateForm();

    // Añadir listeners a los campos de contraseña
    passwordInput.addEventListener('input', function() {
        checkPasswordStrength();
        checkPasswordMatch();
        validateForm();
    });

    confirmPasswordInput.addEventListener('input', function() {
        checkPasswordMatch();
        validateForm();
    });
});