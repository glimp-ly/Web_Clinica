document.addEventListener('DOMContentLoaded', () => {
    
    // ==========================================
    // 0. CONFIGURACIÓN DE PARTÍCULAS (FONDO)
    // ==========================================
    /* Esto activa el efecto de red en el header */
    if (document.getElementById('particles-js')) {
        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 60, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#002f6c" }, // Color de los puntos (Azul)
                "shape": { "type": "circle" },
                "opacity": { "value": 0.2, "random": false },
                "size": { "value": 3, "random": true },
                "line_linked": {
                    "enable": true,
                    "distance": 150,
                    "color": "#28a745", // Color de las líneas (Verde)
                    "opacity": 0.2,
                    "width": 1
                },
                "move": { "enable": true, "speed": 2, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": { "enable": true, "mode": "grab" },
                    "onclick": { "enable": true, "mode": "push" },
                    "resize": true
                },
                "modes": {
                    "grab": { "distance": 140, "line_linked": { "opacity": 1 } }
                }
            },
            "retina_detect": true
        });
    }

    // ==========================================
    // 1. LÓGICA DEL HEADER (NUEVO)
    // ==========================================
    
    // --- A. Menú Hamburguesa (Móvil) ---
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation(); // Evita que se cierre inmediatamente al hacer clic
            navLinks.classList.toggle('active');
        });

        // Cerrar menú al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!navLinks.contains(e.target) && !menuToggle.contains(e.target)) {
                navLinks.classList.remove('active');
            }
        });
    }

    // --- B. Popup de Usuario ---
    const userIcon = document.getElementById('userIcon');
    const userPopup = document.getElementById('userPopup');

    if (userIcon && userPopup) {
        userIcon.addEventListener('click', (e) => {
            e.stopPropagation();
            userPopup.classList.toggle('active');
        });

        // Cerrar popup al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!userPopup.contains(e.target) && !userIcon.contains(e.target)) {
                userPopup.classList.remove('active');
            }
        });
    }

    // --- C. Modo Oscuro (Dark Mode) ---
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const themeIcon = themeToggle ? themeToggle.querySelector('i') : null;

    // Verificar preferencia guardada
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        if(themeIcon) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        }
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            // Cambiar icono y guardar preferencia
            if (body.classList.contains('dark-mode')) {
                if(themeIcon) {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun'); // Cambia a sol
                }
                localStorage.setItem('theme', 'dark');
            } else {
                if(themeIcon) {
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon'); // Cambia a luna
                }
                localStorage.setItem('theme', 'light');
            }
        });
    }

    // ==========================================
    // 2. CONFIGURACIÓN DE LAS CAMPAÑAS (DATOS)
    // ==========================================
    const now = new Date();
    
    const campaigns = {
        1: { end: new Date(now.getTime() + (2 * 24 * 60 * 60 * 1000)), total: 1000 }, // 2 días
        2: { end: new Date(now.getTime() + (5 * 24 * 60 * 60 * 1000)), total: 800 },  // 5 días
        3: { end: new Date(now.getTime() + (12 * 60 * 60 * 1000)), total: 600 },       // 12 horas
        4: { end: null, total: 700 }, // Próximamente
        5: { end: null, total: 500 }, // Próximamente
        6: { end: null, total: 900 }  // Próximamente
    };

    // ==========================================
    // 3. LOGICA DE CUENTA REGRESIVA (TIMERS)
    // ==========================================
    function updateTimers() {
        const cards = document.querySelectorAll('.card');

        cards.forEach(card => {
            const id = card.getAttribute('data-id');
            const timerElement = card.querySelector('.timer');
            const campaignData = campaigns[id];

            if (campaignData && campaignData.end) {
                const nowTime = new Date().getTime();
                const distance = campaignData.end - nowTime;

                if (distance < 0) {
                    timerElement.innerHTML = "Campaña Finalizada";
                    timerElement.style.color = "#718096";
                    const btn = card.querySelector('.enroll-btn');
                    if(btn) {
                        btn.disabled = true;
                        btn.textContent = "Cerrado";
                        btn.style.background = "#ccc";
                    }
                } else {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    timerElement.innerHTML = `<i class="far fa-clock"></i> Cierra en: ${days}d ${hours}h ${minutes}m ${seconds}s`;
                }
            } else {
                if(timerElement) timerElement.innerHTML = "";
            }
        });
    }

    setInterval(updateTimers, 1000);
    updateTimers();

    // ==========================================
    // 4. LÓGICA DEL MODAL Y FORMULARIO
    // ==========================================
    const modal = document.getElementById('modal');
    const enrollButtons = document.querySelectorAll('.enroll-btn');
    const closeModalBtn = document.querySelector('.close-modal');
    const enrollForm = document.getElementById('enrollForm');
    const toast = document.getElementById('toast');
    
    let currentCardId = null;

    // ABRIR MODAL
    enrollButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!btn.disabled) {
                const card = btn.closest('.card');
                currentCardId = card.getAttribute('data-id');
                
                enrollForm.reset();
                const errorDiv = document.querySelector('.modal-error');
                if(errorDiv) errorDiv.textContent = '';
                
                modal.classList.remove('hidden');
            }
        });
    });

    // CERRAR MODAL
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
    }

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });

    // ==========================================
    // 5. PROCESAR INSCRIPCIÓN
    // ==========================================
    if (enrollForm) {
        enrollForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const dni = document.getElementById('dni').value;
            const name = document.getElementById('name').value;
            const errorMsg = document.querySelector('.modal-error');

            if (dni.length < 8) {
                errorMsg.textContent = "El DNI debe tener al menos 8 dígitos.";
                return;
            }

            const submitBtn = enrollForm.querySelector('button');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = "Procesando...";
            submitBtn.disabled = true;

            setTimeout(() => {
                // Actualizar contador visualmente
                if (currentCardId) {
                    const card = document.querySelector(`.card[data-id="${currentCardId}"]`);
                    const enrolledSpan = card.querySelector('.enrolled');
                    
                    if(enrolledSpan) {
                        let currentCount = parseInt(enrolledSpan.textContent.replace(/,/g, '')) || 0;
                        currentCount++;
                        enrolledSpan.textContent = currentCount;
                    }
                }

                modal.classList.add('hidden');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;

                showToast(`¡Inscripción exitosa para ${name}!`);
            }, 1500);
        });
    }

    function showToast(message) {
        if(toast) {
            toast.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }
    }
});