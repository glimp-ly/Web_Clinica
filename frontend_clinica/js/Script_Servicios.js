document.addEventListener('DOMContentLoaded', (event) => {
    const container = document.querySelector('.container');

/* Layout unificado (header/footer/dark-mode/user): ver js/layout.js */
});

document.addEventListener('DOMContentLoaded', (event) => {
    const loginLink = document.getElementById('loginLink');
    const loginForm = document.getElementById('loginForm');
    const registerLink = document.getElementById('registerLink');
    const registerForm = document.getElementById('registerForm');
    if (loginLink && loginForm) {
        loginLink.addEventListener('click', (e) => {
            e.preventDefault();
            const formValid = loginForm.reportValidity();
            if (formValid) {
                window.location.href = 'formulario_datos.html';
            } else { }
        });
    }
    if (registerLink && registerForm) {
        function validateRegistration(form) {
            const password = form.elements['password'].value;
            const confirmPassword = form.elements['confirmPassword'].value;
            if (!form.reportValidity()) {
                return false;
            }
            if (password !== confirmPassword) {
                alert("Las contraseñas no coinciden. Por favor, verifíquelas.");
                form.elements['confirmPassword'].focus();
                return false;
            }
            return true;
        }
        registerLink.addEventListener('click', (e) => {
            e.preventDefault();
            if (validateRegistration(registerForm)) {
                window.location.href = 'formulario_datos.html';
            }
        });
    }
});

/* Layout unificado (header/footer/dark-mode/user): ver js/layout.js */

/* Layout unificado (header/footer/dark-mode/user): ver js/layout.js */