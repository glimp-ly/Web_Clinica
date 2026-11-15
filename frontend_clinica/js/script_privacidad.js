
document.addEventListener("DOMContentLoaded", () => {

    /* 
       BOTÓN TEMA (AUTO SEGURO)
    */
    let themeBtn = document.querySelector(".theme-toggle");

    if (!themeBtn) {
        themeBtn = document.createElement("button");
        themeBtn.className = "theme-toggle";
        themeBtn.innerHTML = "🌙";
        document.body.appendChild(themeBtn);
    }

    const savedTheme = localStorage.getItem("theme");
    if (savedTheme === "dark") {
        document.body.classList.add("theme-dark");
        themeBtn.innerHTML = "☀️";
    }

    themeBtn.addEventListener("click", () => {
        document.body.classList.toggle("theme-dark");
        const isDark = document.body.classList.contains("theme-dark");
        themeBtn.innerHTML = isDark ? "☀️" : "🌙";
        localStorage.setItem("theme", isDark ? "dark" : "light");
    });


    /* 
       PARTÍCULAS PROFESIONALES
    */
    const canvas = document.getElementById("particles-bg");
    const ctx = canvas.getContext("2d");

    let w, h;
    function resizeCanvas() {
        w = canvas.width = window.innerWidth;
        h = canvas.height = window.innerHeight;
    }
    resizeCanvas();
    window.addEventListener("resize", resizeCanvas);

    const particles = Array.from({ length: 90 }, () => ({
        x: Math.random() * w,
        y: Math.random() * h,
        r: Math.random() * 2 + 0.6,
        vx: (Math.random() - 0.5) * 0.7,
        vy: (Math.random() - 0.5) * 0.7
    }));

    function drawParticles() {
        ctx.clearRect(0, 0, w, h);

        const dark = document.body.classList.contains("theme-dark");
        ctx.fillStyle = dark
            ? "rgba(255,255,255,0.55)"
            : "rgba(15,63,126,0.45)";
        ctx.strokeStyle = dark
            ? "rgba(255,255,255,0.18)"
            : "rgba(15,63,126,0.18)";

        particles.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;

            if (p.x < 0 || p.x > w) p.vx *= -1;
            if (p.y < 0 || p.y > h) p.vy *= -1;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
        });

        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const dx = particles[i].x - particles[j].x;
                const dy = particles[i].y - particles[j].y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < 130) {
                    ctx.globalAlpha = 1 - dist / 130;
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.stroke();
                }
            }
        }

        ctx.globalAlpha = 1;
        requestAnimationFrame(drawParticles);
    }

    drawParticles();


    /* 
       ACORDEÓN PRO
  */
    const accordionItems = document.querySelectorAll(".accordion-item");

    accordionItems.forEach(item => {
        const btn = item.querySelector(".accordion-btn");
        if (!btn) return;

        btn.addEventListener("click", () => {
            const isOpen = item.classList.contains("active");

            accordionItems.forEach(i => i.classList.remove("active"));
            if (!isOpen) item.classList.add("active");
        });
    });


    /*
       REVEAL ANIMATION
  */
    const reveals = document.querySelectorAll(".reveal");

    const observer = new IntersectionObserver(
        entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("show");
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15 }
    );

    reveals.forEach(el => observer.observe(el));


    /* 
       MICRO INTERACCIONES
   */
    document.querySelectorAll("button, .cta, .cta-footer-btn")
        .forEach(el => {
            el.addEventListener("mouseenter", () => {
                el.style.transform = "scale(1.05)";
            });

            el.addEventListener("mouseleave", () => {
                el.style.transform = "scale(1)";
            });
        });

});
