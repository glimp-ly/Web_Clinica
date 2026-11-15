
document.addEventListener("DOMContentLoaded", () => {

    /* Dark mode unificado: ver js/layout.js (botón flotante #darkModeToggle) */


    /* Fondo de partículas eliminado: la página usa fondo limpio. */


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
