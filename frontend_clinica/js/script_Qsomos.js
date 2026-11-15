

document.addEventListener("DOMContentLoaded", () => {

    /* 
       BOTÓN MODO OSCURO (AUTO INYECTADO)
  */
    const themeBtn = document.createElement("button");
    themeBtn.className = "theme-toggle";
    themeBtn.innerHTML = "🌙";
    document.body.appendChild(themeBtn);

    const savedTheme = localStorage.getItem("theme-info");
    if (savedTheme === "dark") {
        document.body.classList.add("theme-dark");
        themeBtn.innerHTML = "☀️";
    }

    themeBtn.addEventListener("click", () => {
        document.body.classList.toggle("theme-dark");
        const isDark = document.body.classList.contains("theme-dark");
        localStorage.setItem("theme-info", isDark ? "dark" : "light");
        themeBtn.innerHTML = isDark ? "☀️" : "🌙";
    });


    /* 
       CANVAS PARTICULAS (NATIVO)
   */
    const canvas = document.createElement("canvas");
    canvas.id = "particles-bg";
    document.body.prepend(canvas);

    const ctx = canvas.getContext("2d");
    let w, h;
    let particles = [];

    function resize() {
        w = canvas.width = window.innerWidth;
        h = canvas.height = window.innerHeight;
    }
    window.addEventListener("resize", resize);
    resize();

    const PARTICLE_COUNT = 70;

    for (let i = 0; i < PARTICLE_COUNT; i++) {
        particles.push({
            x: Math.random() * w,
            y: Math.random() * h,
            r: Math.random() * 2 + 1,
            vx: (Math.random() - 0.5) * 0.6,
            vy: (Math.random() - 0.5) * 0.6
        });
    }

    function drawParticles() {
        ctx.clearRect(0, 0, w, h);

        const color = getComputedStyle(document.body)
            .getPropertyValue('--azul')
            .trim() || '#3b82f6';

        ctx.fillStyle = color;
        ctx.globalAlpha = 0.25;

        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();

            p.x += p.vx;
            p.y += p.vy;

            if (p.x < 0 || p.x > w) p.vx *= -1;
            if (p.y < 0 || p.y > h) p.vy *= -1;
        });

        requestAnimationFrame(drawParticles);
    }
    drawParticles();


    /* 
       REVEAL SCROLL ANIMATION
    */
    const reveals = document.querySelectorAll("section, header, footer");

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("reveal", "show");
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    reveals.forEach(el => observer.observe(el));


    /* 
       MICRO INTERACCIONES (BOTONES & CARDS)
  */
    document.querySelectorAll("a, .mv-card").forEach(el => {
        el.addEventListener("mouseenter", () => {
            el.style.transform = "scale(1.05)";
        });

        el.addEventListener("mouseleave", () => {
            el.style.transform = "";
        });
    });


    /* 
       EFECTO PARALLAX SUAVE EN IMÁGENES
    */
    const parallaxImgs = document.querySelectorAll(
        ".banner-img, .quienes-img, .valores-img"
    );

    window.addEventListener("scroll", () => {
        const scrollY = window.scrollY;
        parallaxImgs.forEach(img => {
            img.style.transform = `translateY(${scrollY * 0.04}px) scale(1.05)`;
        });
    });

});
