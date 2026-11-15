/* Layout unificado (header/footer/dark-mode/user): ver js/layout.js */

/* Layout unificado (header/footer/dark-mode/user): ver js/layout.js */

/*Boton Ver Mas*/
document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.boton-ver-mas');
    buttons.forEach(button => {
        button.addEventListener('click', () => {
            // Encuentra el contenedor principal de la tarjeta
            const card = button.closest('.mini-tarjeta');
            // Encuentra el div que contiene el texto completo (clase 'sidebar-full-text')
            const fullText = card.querySelector('.sidebar-full-text');

            // Verifica si el texto completo esta oculto (tiene la clase 'hidden')
            if (fullText.classList.contains('hidden')) {
                // Si esta oculto, lo muestra
                fullText.classList.remove('hidden');
                // Cambia el texto del botón
                button.textContent = 'Ver Menos';
                // Opcional: Añade una clase al articulo para cambiar estilos (como el color del botón)
                card.classList.add('expanded');
            } else {
                // Si esto visible, lo oculta
                fullText.classList.add('hidden');
                // Cambia el texto del boton
                button.textContent = 'Ver Más';
                // Opcional: Elimina la clase de expansion
                card.classList.remove('expanded');
            }
        });
    });
});
/* Layout unificado (header/footer/dark-mode/user): ver js/layout.js */
/* Layout unificado (header/footer/dark-mode/user): ver js/layout.js */