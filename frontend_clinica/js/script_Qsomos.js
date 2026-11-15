

document.addEventListener("DOMContentLoaded", () => {

    /* Dark mode unificado: ver js/layout.js (botón flotante #darkModeToggle) */


    /* Fondo de partículas eliminado: la página usa fondo limpio. */

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
