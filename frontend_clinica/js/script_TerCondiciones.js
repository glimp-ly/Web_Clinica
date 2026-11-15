document.addEventListener('DOMContentLoaded', function() {

    const botones = document.querySelectorAll('.boton-acordeon');

    botones.forEach(boton => {
        boton.addEventListener('click', function() {        
            const estabaAbierto = this.classList.contains('activo');
            botones.forEach(b => {
                b.classList.remove('activo');
                b.nextElementSibling.style.maxHeight = null;
            });
            if (!estabaAbierto) {
                this.classList.add('activo'); 
                const contenido = this.nextElementSibling;
                contenido.style.maxHeight = contenido.scrollHeight + "px";
            }
        });
    });
});