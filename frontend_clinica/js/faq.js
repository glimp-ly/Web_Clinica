document.addEventListener('DOMContentLoaded', () => {

    /* =========================================
       1. HEADER Y NAVEGACIÓN (Necesario para el menú)
    ========================================= */
    // Menú Hamburguesa
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            navLinks.classList.toggle('active');
        });
        document.addEventListener('click', (e) => {
            if (!navLinks.contains(e.target) && !menuToggle.contains(e.target)) {
                navLinks.classList.remove('active');
            }
        });
    }

    // Popup de Usuario
    const userIcon = document.getElementById('userIcon');
    const userPopup = document.getElementById('userPopup');
    if (userIcon && userPopup) {
        userIcon.addEventListener('click', (e) => {
            e.stopPropagation();
            userPopup.classList.toggle('active');
        });
        document.addEventListener('click', (e) => {
            if (!userPopup.contains(e.target) && !userIcon.contains(e.target)) {
                userPopup.classList.remove('active');
            }
        });
    }

    /* =========================================
       2. MODO OSCURO / CLARO (Adaptado)
    ========================================= */
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const themeIcon = themeToggle ? themeToggle.querySelector('i') : null;

    // Cargar tema guardado
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        if(themeIcon) { themeIcon.classList.replace('fa-moon', 'fa-sun'); }
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            
            // Cambiar icono FontAwesome
            if (isDark) {
                if(themeIcon) { themeIcon.classList.replace('fa-moon', 'fa-sun'); }
                localStorage.setItem('theme', 'dark');
            } else {
                if(themeIcon) { themeIcon.classList.replace('fa-sun', 'fa-moon'); }
                localStorage.setItem('theme', 'light');
            }
        });
    }

    /* =========================================
       3. FAQ ACORDEÓN (Tu lógica)
    ========================================= */
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        if (question) {
            question.addEventListener('click', () => {
                faqItems.forEach(other => {
                    if (other !== item) other.classList.remove('active');
                });
                item.classList.toggle('active');
            });
        }
    });

    /* =========================================
       4. MODAL (Adaptado a .hidden)
    ========================================= */
    const modal = document.getElementById('questionModal');
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');
    const form = document.getElementById('questionForm');
    const toast = document.getElementById('toast');

    // Funciones de utilidad
    function openModal() {
        if(modal) modal.classList.remove('hidden');
    }

    function closeModal() {
        if(modal) {
            modal.classList.add('hidden');
            form.reset(); // Limpiar formulario al cerrar
        }
    }

    // Event Listeners
    if (openModalBtn) openModalBtn.addEventListener('click', openModal);
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeModal();
        });
    }

    /* =========================================
       5. VALIDACIÓN FORM (Tu lógica + Toast)
    ========================================= */
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const questionInput = document.getElementById('question');

    function showToast(message) {
        if(toast) {
            toast.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        } else {
            alert(message);
        }
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    if (form) {
        form.addEventListener('submit', e => {
            e.preventDefault();
            
            let valid = true;
            let errorMessage = "";

            if (nameInput.value.trim().length < 3) {
                errorMessage = 'Ingrese un nombre válido (mínimo 3 letras)';
                valid = false;
            } else if (!validateEmail(emailInput.value)) {
                errorMessage = 'Correo electrónico inválido';
                valid = false;
            } else if (questionInput.value.trim().length < 10) {
                errorMessage = 'La pregunta debe tener al menos 10 caracteres';
                valid = false;
            }

            if (!valid) {
                alert("⚠️ " + errorMessage); // Usamos alert para error rápido
            } else {
                showToast('✅ Tu pregunta fue enviada correctamente');
                closeModal();
            }
        });
    }

    /* =========================================
       6. PARTÍCULAS (Tu código canvas adaptado)
    ========================================= */
    // Adaptación: Aseguramos que el elemento sea un CANVAS
    let canvasContainer = document.getElementById('particles-js');
    let canvas;

    if (canvasContainer) {
        // Si es un div, lo reemplazamos por un canvas para que tu código funcione
        if (canvasContainer.tagName !== 'CANVAS') {
            canvas = document.createElement('canvas');
            canvas.id = 'particles-js';
            // Estilos necesarios para fondo
            canvas.style.position = 'fixed';
            canvas.style.top = '0';
            canvas.style.left = '0';
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.zIndex = '-1';
            canvas.style.pointerEvents = 'none';
            canvasContainer.parentNode.replaceChild(canvas, canvasContainer);
        } else {
            canvas = canvasContainer;
        }

        const ctx = canvas.getContext('2d');
        let particles = [];
        const count = 80;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resize);
        resize();

        class Particle {
            constructor() {
                this.reset();
            }
            reset() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 1;
                this.speed = Math.random() * 0.6 + 0.2;
            }
            update() {
                this.y -= this.speed;
                if (this.y < 0) this.reset();
            }
            draw() {
                // Adaptado para detectar 'dark-mode' en lugar de 'dark'
                ctx.fillStyle = body.classList.contains('dark-mode')
                    ? 'rgba(255,255,255,0.6)' 
                    : 'rgba(40,90,223,0.25)'; // Tu color azul con transparencia
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function initParticles() {
            particles = [];
            for (let i = 0; i < count; i++) {
                particles.push(new Particle());
            }
        }

        function animateParticles() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => {
                p.update();
                p.draw();
            });
            requestAnimationFrame(animateParticles);
        }

        initParticles();
        animateParticles();
    }
});