document.addEventListener("DOMContentLoaded", function () {

    const $userIcon = $('#userIcon');
    const $userPopup = $('#userPopup');

    $userIcon.on('click', function (e) {
        e.stopPropagation();
        $userPopup.toggleClass('active');
    });

    $(window).on('click', function (e) {
        if (!$userIcon.is(e.target) && $userIcon.has(e.target).length === 0 &&
            !$userPopup.is(e.target) && $userPopup.has(e.target).length === 0) {
            $userPopup.removeClass('active');
        }
    });

    const canvas = document.getElementById('canvas-red');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let parts = [];

        function ajustar() {
            canvas.width = canvas.parentElement.offsetWidth;
            canvas.height = canvas.parentElement.offsetHeight;
            crear();
        }

        class Part {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 1;
                this.vx = (Math.random() - 0.5) * 0.5;
                this.vy = (Math.random() - 0.5) * 0.5;
            }
            update() {
                this.x += this.vx; this.y += this.vy;
                if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
                if (this.y < 0 || this.y > canvas.height) this.vy *= -1;
            }
            draw() {
                ctx.fillStyle = 'rgba(255,255,255,0.4)';
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function crear() {
            parts = [];
            let n = (canvas.width * canvas.height) / 15000;
            for (let i = 0; i < n; i++) parts.push(new Part());
        }

        function animar() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            parts.forEach((p, i) => {
                p.update(); p.draw();
                for (let j = i; j < parts.length; j++) {
                    let dx = p.x - parts[j].x;
                    let dy = p.y - parts[j].y;
                    let d = Math.sqrt(dx * dx + dy * dy);
                    if (d < 100) {
                        ctx.beginPath();
                        ctx.strokeStyle = `rgba(255,255,255,${0.1 - d / 1000})`;
                        ctx.moveTo(p.x, p.y);
                        ctx.lineTo(parts[j].x, parts[j].y);
                        ctx.stroke();
                    }
                }
            });
            requestAnimationFrame(animar);
        }
        window.addEventListener('resize', ajustar);
        ajustar(); animar();
    }

    // --- 2. CARRUSEL DE IMÁGENES ---
    if (window.jQuery) {

        let indice = 0;
        const slides = $('.slide');
        const totalSlides = slides.length;

        if (totalSlides > 0) {
            setInterval(() => {
                indice++;
                if (indice >= totalSlides) { indice = 0; }
                $('.carrusel-track').css('transform', `translateX(-${indice * 100}%)`);
            }, 5000); // <- Se cambia cada 5 segundos
        }

        // --- 3. VALIDACIÓN DEL FORMULARIO ---
        // Validación numérica
        $('#dni, #celular').on('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        $('#miFormulario').on('submit', function (e) {
            e.preventDefault();
            let dni = $('#dni').val();
            let cel = $('#celular').val();
            let check = $('#checkLegal').is(':checked');

            // Validaciones simples
            if (dni.length < 8) {
                alert("El DNI debe tener 8 dígitos.");
            } else if (cel.length < 9) {
                alert("El Celular debe tener 9 dígitos.");
            } else if (!check) {
                alert("Debes aceptar recibir información (check).");
            } else {
                let btn = $('.boton-enviar');
                let textoOriginal = btn.html();

                btn.html('<i class="fa-solid fa-check"></i> ENVIADO').css('background', '#00ca63');

                setTimeout(() => {
                    alert("¡Gracias! Hemos recibido tus datos.");
                    $('#miFormulario')[0].reset();
                    btn.html('SOLICITAR AHORA').css('background', '#033076');
                }, 1500);
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', (event) => {
    const menuToogle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('.main-nav');
    const navLinks = document.querySelector('.nav-links');

    if (menuToogle && nav) {
        menuToogle.addEventListener('click', () => {
            nav.classList.toggle('open');
        });
    }

    if (navLinks) {
        navLinks.addEventListener('click', (e) => {
            if (window.innerWidth <= 850) {
                const link = e.target.closest('a');
                const listItem = e.target.closest('li');

                if (link && listItem.querySelector('.submenu')) {
                    e.preventDefault();

                    const submenu = listItem.querySelector('.submenu');

                    document.querySelectorAll('.nav-links > li').forEach(item => {
                        if (item !== listItem && item.classList.contains('submenu-open')) {
                            item.classList.remove('submenu-open');
                            item.querySelector('.submenu').style.maxHeight = 0;
                        }
                    });

                    listItem.classList.toggle('submenu-open');

                    if (listItem.classList.contains('submenu-open')) {
                        submenu.style.maxHeight = submenu.scrollHeight + "px";
                    } else {
                        submenu.style.maxHeight = 0;
                    }
                }
            }
        });
    }
});


document.addEventListener('DOMContentLoaded', (event) => {
    if (window.innerWidth <= 767) {
        const footerHeaders = document.querySelectorAll('.footer-links h4');

        footerHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const parent = header.closest('.footer-links');
                const content = parent.querySelector('.footer-content');

                if (parent.classList.contains('active')) {
                    content.style.maxHeight = 0;
                    parent.classList.remove('active');
                } else {
                    document.querySelectorAll('.footer-links.active').forEach(item => {
                        item.classList.remove('active');
                        item.querySelector('.footer-content').style.maxHeight = 0;
                    });

                    parent.classList.add('active');
                    content.style.maxHeight = content.scrollHeight + "px";
                }
            });
        });
    }
});