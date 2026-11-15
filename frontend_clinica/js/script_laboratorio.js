/*SCRIPT DEL LOGIN Y REGISTER*/
document.addEventListener('DOMContentLoaded', (event) => {
    //Referencias
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


/*SCRIPT PARA VALIDAR LOS CAMPOS DE LOGIN Y REGISTER AL CAMBIAR DE BUTTON A "a" */

document.addEventListener('DOMContentLoaded', (event) => {
    //Referencias
    const loginLink = document.getElementById('loginLink');
    const loginForm = document.getElementById('loginForm');
    const registerLink = document.getElementById('registerLink');
    const registerForm = document.getElementById('registerForm');
    if (loginLink && loginForm) {
        loginLink.addEventListener('click', (e) => {
            e.preventDefault(); 
            const formValid = loginForm.reportValidity();
            if (formValid) {
                window.location.href = 'formulario_datos.html'; 
            } else {}
        });
    }
    if (registerLink && registerForm) {
        function validateRegistration(form) {
            const password = form.elements['password'].value;
            const confirmPassword = form.elements['confirmPassword'].value;
            if (!form.reportValidity()) {
                return false;
            }
            if (password !== confirmPassword) {
                alert("Las contraseñas no coinciden. Por favor, verifíquelas.");
                form.elements['confirmPassword'].focus();
                return false;
            }
            return true; 
        }
        registerLink.addEventListener('click', (e) => {
            e.preventDefault(); 
            if (validateRegistration(registerForm)) {
                window.location.href = 'formulario_datos.html'; 
            }
        });
    }
});

let carrito = {};

/*FUNCIONALIDAD DEL CARRITO DE COMPRAS*/
document.addEventListener('DOMContentLoaded', () => {
    //Referencias
    const carritoModal = document.getElementById('carrito');
    const iconoCarrito = document.querySelector('.icono-lab.carrito');
    const cerrarCarritoBoton = document.querySelector('.cerrar-carrito');
    const contadorCarrito = document.getElementById('contador-carrito');
    const listaCarrito = document.getElementById('lista-carrito');
    const totalAnalisisSpan = document.getElementById('total-analisis');
    const listaAnalisisContainer = document.querySelector('.lista-analisis');
    const botonesAgregar = document.querySelectorAll('.boton-agregar');

    // Función para actualizar la interfaz del carrito
    const actualizarCarritoUI = () => {
        listaCarrito.innerHTML = '';
        let totalAnalisis = 0;
        // 1. Recorrer el OBJETO del carrito y crear los elementos de la lista
        Object.keys(carrito).forEach(key => {
            const item = carrito[key];
            if (item.cantidad > 0) {
                const listItem = document.createElement('li');
                // Formato: "Nombre Análisis xN"
                listItem.innerHTML = `
                <span>${item.nombre} x${item.cantidad}</span>
                <button class="boton-eliminar" data-nombre="${item.nombre}">&times;</button>
                `;
                listaCarrito.appendChild(listItem);
                totalAnalisis += item.cantidad;
            }
        });
        // 2. Actualizar contadores
        contadorCarrito.textContent = totalAnalisis;
        totalAnalisisSpan.textContent = totalAnalisis;
        // 3. Aplicar animación al contador (si hay elementos)
        if (totalAnalisis > 0) {
            contadorCarrito.classList.add('animar-aumento');
            setTimeout(() => {
                contadorCarrito.classList.remove('animar-aumento');
            }, 300);
        }
        // 4. Si el carrito está vacío, ocultar el modal
        if (totalAnalisis === 0 && carritoModal.style.display === 'block') {
            carritoModal.style.display = 'none';
        }
    };

    // Funcion para agregar un analisis al carrito
    const agregarAlCarrito = (e) => {
        const cuadro = e.target.closest('.cuadro');
        const nombreAnalisis = cuadro.querySelector('.titulo').textContent;
        // Usar el nombre como clave para verificar si ya existe
        if (carrito[nombreAnalisis]) {
            carrito[nombreAnalisis].cantidad += 1;
        } else {
            carrito[nombreAnalisis] = {
                nombre: nombreAnalisis,
                cantidad: 1
            };
        }
        actualizarCarritoUI();
    };

    // Funcion para eliminar un analisis del carrito
    const eliminarDelCarrito = (e) => {
        if (e.target.classList.contains('boton-eliminar')) {
            const nombreAEliminar = e.target.dataset.nombre;
            if (carrito[nombreAEliminar]) {
                carrito[nombreAEliminar].cantidad -= 1;
                // Si la cantidad llega a 0, eliminar la clave del objeto
                if (carrito[nombreAEliminar].cantidad <= 0) {
                    delete carrito[nombreAEliminar];
                }
            }
            actualizarCarritoUI();
        }
    };

    // Asignar listeners
    botonesAgregar.forEach(boton => {
        boton.addEventListener('click', agregarAlCarrito);
    });
    listaCarrito.addEventListener('click', eliminarDelCarrito);

    if (iconoCarrito) {
        iconoCarrito.addEventListener('click', () => {
            // Abrir solo si hay elementos
            if (Object.keys(carrito).length > 0) { 
                carritoModal.style.display = 'block';
            }
        });
    }
    if (cerrarCarritoBoton) {
        cerrarCarritoBoton.addEventListener('click', () => {
            carritoModal.style.display = 'none';
        });
    }
    if (carritoModal) {
         window.addEventListener('click', (e) => {
             if (e.target === carritoModal) {
                 carritoModal.style.display = 'none';
             }
         });
    }
});

/*FUNCIONALIDAD DE BUSQUEDA*/
document.addEventListener('DOMContentLoaded', () => {
    // Referencias
    const inputBusqueda = document.querySelector('.busqueda input');
    const cuadrosAnalisis = document.querySelectorAll('.cuadro');

    const filtrarAnalisis = () => {
        const textoBusqueda = inputBusqueda.value.toLowerCase().trim();
        cuadrosAnalisis.forEach(cuadro => {
            const titulo = cuadro.querySelector('.titulo').textContent.toLowerCase();
            if (titulo.includes(textoBusqueda)) {
                // Mostrar la tarjeta si coincide
                cuadro.style.display = 'flex'; // Usamos 'flex' porque es el display original en tu CSS
            } else {
                // Ocultar la tarjeta si no coincide
                cuadro.style.display = 'none';
            }
        });
    };
    //'input' para filtrar en tiempo real
    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', filtrarAnalisis);
    }
});

/*FUNCIONALIDAD DEL CARRUSEL*/
document.addEventListener('DOMContentLoaded', () => {
    const listaAnalisisContainer = document.querySelector('.lista-analisis');
    const botonIzquierda = document.querySelector('.boton-carrusel.izquierda');
    const botonDerecha = document.querySelector('.boton-carrusel.derecha');
    
    if (listaAnalisisContainer && botonIzquierda && botonDerecha) {
        botonDerecha.addEventListener('click', () => {
            const scrollAmount = listaAnalisisContainer.offsetWidth * 0.75;
            listaAnalisisContainer.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
        });
        botonIzquierda.addEventListener('click', () => {
            const scrollAmount = listaAnalisisContainer.offsetWidth * 0.75;
            listaAnalisisContainer.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
        });
    } 
});

/*------- FUNCIONALIDAD DEL VER MAS -------*/
document.addEventListener('DOMContentLoaded', () => {
    const modalDescripcion = document.getElementById('modal-descripcion');
    const cerrarModalBtn = modalDescripcion.querySelector('.cerrar-modal');
    const botonesVerMas = document.querySelectorAll('.boton-ver-mas');
    function abrirModalDescripcion(cuadro, desc1, desc2) {
        // 1. Obtener la informaciOn de la tarjeta (article.cuadro)
        const imagenSrc = cuadro.querySelector('img').src;
        const titulo = cuadro.querySelector('.titulo').textContent;
        // 2. Llenar el contenido del modal
        document.getElementById('modal-imagen').src = imagenSrc;
        document.getElementById('modal-titulo').textContent = titulo;
        document.getElementById('modal-parrafo').textContent = desc1;
        document.getElementById('modal-parrafo2').innerHTML = desc2;
        //3. Si no hay info
        if (desc1.trim() === '...') {
            document.getElementById('modal-parrafo').textContent = 'Descripción completa no disponible en este momento. Por favor, consulte con nuestro personal.';
        }
        // 3. Mostrar el modal
        modalDescripcion.style.display = 'flex';
    }
    botonesVerMas.forEach(boton => {
        boton.addEventListener('click', () => {
            const cuadro = boton.closest('.cuadro');
            if (cuadro) {
                const parrafo = cuadro.querySelector('.descripcion').textContent;
                const parrafo2 = cuadro.querySelector('.descripcion2').innerHTML;
                abrirModalDescripcion(cuadro, parrafo, parrafo2);
            }
        });
    });
    // 5. Cerrar el modal al hacer click en 'x'
    cerrarModalBtn.addEventListener('click', () => {
        modalDescripcion.style.display = 'none';
    });
    // 6. Cerrar el modal si el usuario hace click fuera del contenido (en el overlay)
    window.addEventListener('click', (event) => {
        if (event.target === modalDescripcion) {
            modalDescripcion.style.display = 'none';
        }
    });
});

/*Footer*/
document.addEventListener('DOMContentLoaded',(event) => {
    if(window.innerWidth<= 767){
        const footerHeaders = document.querySelectorAll('.footer-links h4');
        footerHeaders.forEach(header =>{
            header.addEventListener('click', () =>{
                const parent = header.closest('.footer-links');
                const content = parent.querySelector('.footer-content');
                if(parent.classList.contains('active')){
                    content.style.maxHeight = 0;
                    parent.classList.remove('active');
                }else{
                    document.querySelectorAll('.footer-links.active').forEach(item =>{
                        item.classList.remove('active');
                        item.querySelector('.footer-content').style.maxHeight=0;
                    });
                    parent.classList.add('active');
                    content.style.maxHeight = content.scrollHeight + "px";
                }
            });
        });
    }
});
/*Modo oscuro*/
document.addEventListener('DOMContentLoaded', () => {
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const body = document.body;
    const icon = darkModeToggle.querySelector('i');

    // Función para actualizar el icono
    const updateIcon = (isDark) => {
        if (isDark) {
            icon.classList.replace('fa-moon', 'fa-sun');
        } else {
            icon.classList.replace('fa-sun', 'fa-moon');
        }
    };

    // Cargar preferencia guardada
    const currentTheme = localStorage.getItem('theme');
    if (currentTheme === 'dark') {
        body.classList.add('dark-mode');
        updateIcon(true);
    }

    darkModeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        
        updateIcon(isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
});