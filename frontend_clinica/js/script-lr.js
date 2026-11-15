document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.container');
    const registerBtn = document.querySelector('.register_btn');
    const loginBtn = document.querySelector('.login_btn');

    if (registerBtn && loginBtn && container) {
        registerBtn.addEventListener('click', () => {
            container.classList.add('active');
        });
        loginBtn.addEventListener('click', () => {
            container.classList.remove('active');
        });
    }

    const loginLink = document.getElementById('loginLink');
    const loginForm = document.getElementById('loginForm');
    const registerLink = document.getElementById('registerLink');
    const registerForm = document.getElementById('registerForm');

    async function handleLogin(e) {
        if (e) e.preventDefault();
        if (!loginForm.reportValidity()) return;

        const email = loginForm.elements['emailInput'].value.trim();
        const password = loginForm.elements['passwordInput'].value;

        loginLink.textContent = 'Verificando...';
        const res = await window.ClinicaAPI.request('api/auth/login', {
            method: 'POST',
            body: { email, password }
        });
        loginLink.textContent = 'Iniciar Sesión';

        if (res.ok) {
            window.ClinicaAPI.notify('Bienvenido(a), ' + res.data.user.nombre, 'success');
            setTimeout(() => {
                window.location.href = res.data.redirect || 'index.html';
            }, 350);
        } else {
            window.ClinicaAPI.notify(res.data.message || 'Credenciales incorrectas', 'error');
        }
    }

    async function handleRegister(e) {
        if (e) e.preventDefault();
        if (!registerForm.reportValidity()) return;

        const email = registerForm.elements['emailInput'].value.trim();
        const password = registerForm.elements['password'].value;
        const confirmPassword = registerForm.elements['confirmPassword'].value;

        if (password !== confirmPassword) {
            window.ClinicaAPI.notify('Las contraseñas no coinciden. Por favor, verifíquelas.', 'error');
            registerForm.elements['confirmPassword'].focus();
            return;
        }

        registerLink.textContent = 'Registrando...';
        const res = await window.ClinicaAPI.request('api/auth/register', {
            method: 'POST',
            body: { email, password, confirmPassword }
        });
        registerLink.textContent = 'Registrarse';

        if (res.ok) {
            window.ClinicaAPI.notify('Cuenta registrada exitosamente. Complete su ficha médica.', 'success');
            setTimeout(() => {
                window.location.href = res.data.redirect || 'formulario_datos.html';
            }, 400);
        } else {
            window.ClinicaAPI.notify(res.data.message || 'No se pudo completar el registro', 'error');
        }
    }

    if (loginLink && loginForm) {
        loginLink.addEventListener('click', handleLogin);
        loginForm.addEventListener('submit', handleLogin);
    }

    if (registerLink && registerForm) {
        registerLink.addEventListener('click', handleRegister);
        registerForm.addEventListener('submit', handleRegister);
    }

    document.querySelectorAll('.iconos_redes a').forEach(anchor => {
        anchor.addEventListener('click', (e) => {
            e.preventDefault();
            window.ClinicaAPI.openGoogleAuth();
        });
    });

    const forgotLink = document.querySelector('.link_olvido a');
    if (forgotLink) {
        forgotLink.addEventListener('click', (e) => {
            e.preventDefault();
            openPasswordRecoveryModal();
        });
    }

    function openPasswordRecoveryModal() {
        const existing = document.getElementById('recoveryModal');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'recoveryModal';
        overlay.className = 'google-modal-overlay';
        overlay.innerHTML = `
            <div class="google-modal-card">
                <div class="google-modal-header">
                    <div class="google-brand"><i class="fa-solid fa-key" style="color:#0077b6;"></i> <span>Recuperar Contraseña</span></div>
                    <button type="button" class="google-modal-close">&times;</button>
                </div>
                <p class="google-modal-sub">Ingrese su correo registrado para recibir un token temporal (expira en 1 hora):</p>
                <form id="forgotStep1" class="google-custom-form" style="border-top:none; padding-top:0;">
                    <input type="email" id="recEmail" placeholder="correo@ejemplo.com" required>
                    <button type="submit" class="btn-google-submit">Enviar Token</button>
                </form>
                <form id="forgotStep2" style="display:none; margin-top:12px; border-top:1px solid #e5e7eb; padding-top:12px;">
                    <p id="demoTokenHint" style="font-size:0.82rem; color:#176428; margin-bottom:8px; font-weight:600;"></p>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <input type="text" id="recToken" placeholder="Código de recuperación" required style="padding:8px 10px; border:1px solid #d0d7de; border-radius:6px;">
                        <input type="password" id="recNewPass" placeholder="Nueva contraseña (mín. 6 caracteres)" minlength="6" required style="padding:8px 10px; border:1px solid #d0d7de; border-radius:6px;">
                        <button type="submit" class="btn-google-submit" style="width:100%; padding:10px;">Restablecer Contraseña</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(overlay);

        overlay.querySelector('.google-modal-close').addEventListener('click', () => overlay.remove());
        overlay.addEventListener('click', (ev) => { if (ev.target === overlay) overlay.remove(); });

        overlay.querySelector('#forgotStep1').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const email = overlay.querySelector('#recEmail').value.trim();
            const r = await window.ClinicaAPI.request('api/auth/forgot-password', {
                method: 'POST',
                body: { email }
            });
            if (r.ok) {
                window.ClinicaAPI.notify(r.data.message, 'success');
                const step2 = overlay.querySelector('#forgotStep2');
                step2.style.display = 'block';
                if (r.data.demo_token) {
                    overlay.querySelector('#demoTokenHint').textContent = 'Token generado (Notificación enviada): ' + r.data.demo_token;
                    overlay.querySelector('#recToken').value = r.data.demo_token;
                }
            } else {
                window.ClinicaAPI.notify(r.data.message || 'Error al solicitar token', 'error');
            }
        });

        overlay.querySelector('#forgotStep2').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const token = overlay.querySelector('#recToken').value.trim();
            const password = overlay.querySelector('#recNewPass').value;
            const r = await window.ClinicaAPI.request('api/auth/reset-password', {
                method: 'POST',
                body: { token, password }
            });
            if (r.ok) {
                window.ClinicaAPI.notify(r.data.message, 'success');
                overlay.remove();
            } else {
                window.ClinicaAPI.notify(r.data.message || 'Token inválido', 'error');
            }
        });
    }
});
