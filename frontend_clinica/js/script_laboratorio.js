let carrito = {};

document.addEventListener('DOMContentLoaded', () => {
    const carritoModal = document.getElementById('carrito');
    const iconoCarrito = document.querySelector('.icono-lab.carrito');
    const cerrarCarritoBoton = document.querySelector('.cerrar-carrito');
    const contadorCarrito = document.getElementById('contador-carrito');
    const listaCarrito = document.getElementById('lista-carrito');
    const totalAnalisisSpan = document.getElementById('total-analisis');
    const botonesAgregar = document.querySelectorAll('.boton-agregar');
    const botonCheckout = document.querySelector('.boton-checkout');

    const preciosMap = {
        'Análisis de Sangre': 40.00,
        'Perfil Lipídico': 50.00,
        'Biopsia': 300.00,
        'Glucosa': 30.00,
        'Orina Completa': 25.00,
        'Tomografía': 350.00,
        'ADN': 700.00,
        'Hematología': 50.00
    };

    const actualizarCarritoUI = () => {
        listaCarrito.innerHTML = '';
        let totalAnalisis = 0;
        let montoTotal = 0;

        Object.keys(carrito).forEach(key => {
            const item = carrito[key];
            if (item.cantidad > 0) {
                const precio = preciosMap[item.nombre] || 40.00;
                montoTotal += precio * item.cantidad;
                const listItem = document.createElement('li');
                listItem.innerHTML = `
                    <span>${item.nombre} x${item.cantidad} (S/ ${(precio * item.cantidad).toFixed(2)})</span>
                    <button class="boton-eliminar" data-nombre="${item.nombre}">&times;</button>
                `;
                listaCarrito.appendChild(listItem);
                totalAnalisis += item.cantidad;
            }
        });

        contadorCarrito.textContent = totalAnalisis;
        totalAnalisisSpan.textContent = `${totalAnalisis} (S/ ${montoTotal.toFixed(2)})`;

        if (totalAnalisis === 0 && carritoModal.style.display === 'block') {
            carritoModal.style.display = 'none';
        }
    };

    const agregarAlCarrito = (e) => {
        const cuadro = e.target.closest('.cuadro');
        const nombreAnalisis = cuadro.querySelector('.titulo').textContent.trim();
        if (carrito[nombreAnalisis]) {
            carrito[nombreAnalisis].cantidad += 1;
        } else {
            carrito[nombreAnalisis] = {
                nombre: nombreAnalisis,
                precio: preciosMap[nombreAnalisis] || 40.00,
                cantidad: 1
            };
        }
        actualizarCarritoUI();
        window.ClinicaAPI.notify(nombreAnalisis + ' agregado al carrito de laboratorio', 'success');
    };

    const eliminarDelCarrito = (e) => {
        if (e.target.classList.contains('boton-eliminar')) {
            const nombreAEliminar = e.target.dataset.nombre;
            if (carrito[nombreAEliminar]) {
                carrito[nombreAEliminar].cantidad -= 1;
                if (carrito[nombreAEliminar].cantidad <= 0) {
                    delete carrito[nombreAEliminar];
                }
            }
            actualizarCarritoUI();
        }
    };

    botonesAgregar.forEach(boton => boton.addEventListener('click', agregarAlCarrito));
    if (listaCarrito) listaCarrito.addEventListener('click', eliminarDelCarrito);

    if (iconoCarrito) {
        iconoCarrito.addEventListener('click', () => {
            if (Object.keys(carrito).length > 0) {
                carritoModal.style.display = 'block';
            } else {
                window.ClinicaAPI.notify('Agregue al menos un análisis al carrito', 'error');
            }
        });
    }
    if (cerrarCarritoBoton) {
        cerrarCarritoBoton.addEventListener('click', () => { carritoModal.style.display = 'none'; });
    }
    if (carritoModal) {
        window.addEventListener('click', (e) => {
            if (e.target === carritoModal) carritoModal.style.display = 'none';
        });
    }

    if (botonCheckout) {
        botonCheckout.innerHTML = 'Pagar y Generar Orden';
        botonCheckout.addEventListener('click', (e) => {
            e.preventDefault();
            abrirModalCheckoutLab();
        });
    }

    function abrirModalCheckoutLab() {
        const prev = document.getElementById('labPayModal');
        if (prev) prev.remove();

        const userDni = (window.__currentUser && window.__currentUser.paciente) ? window.__currentUser.paciente.dni : '';
        const userName = window.__currentUser ? window.__currentUser.nombre : '';

        const overlay = document.createElement('div');
        overlay.id = 'labPayModal';
        overlay.className = 'google-modal-overlay';
        overlay.innerHTML = `
            <div class="google-modal-card">
                <div class="google-modal-header">
                    <div class="google-brand"><i class="fa-solid fa-flask-vial" style="color:#0077b6;"></i> <span>Orden de Laboratorio y Pago</span></div>
                    <button type="button" class="google-modal-close">&times;</button>
                </div>
                <form id="formOrdenLab" style="margin-top:12px; display:flex; flex-direction:column; gap:10px;">
                    <input type="text" id="labDni" value="${userDni}" maxlength="8" placeholder="DNI del Paciente (8 dígitos)" required style="padding:9px; border:1px solid #ccc; border-radius:6px;">
                    <input type="text" id="labNombre" value="${userName}" placeholder="Nombre completo del Paciente" required style="padding:9px; border:1px solid #ccc; border-radius:6px;">
                    <select id="labMetodo" style="padding:9px; border:1px solid #ccc; border-radius:6px;">
                        <option value="tarjeta_visa">Tarjeta Visa / Débito</option>
                        <option value="tarjeta_mastercard">Tarjeta Mastercard</option>
                        <option value="yape">Yape / Plin</option>
                    </select>
                    <button type="submit" class="btn-google-submit" style="padding:10px; background:#029d56;">Pagar y Obtener Código de Orden</button>
                </form>
                <div id="labOrderSuccess" style="display:none; margin-top:12px;"></div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('.google-modal-close').onclick = () => overlay.remove();

        overlay.querySelector('#formOrdenLab').onsubmit = async (ev) => {
            ev.preventDefault();
            const dni = overlay.querySelector('#labDni').value.trim();
            const nombre = overlay.querySelector('#labNombre').value.trim();
            const metodo = overlay.querySelector('#labMetodo').value;

            const res = await window.ClinicaAPI.request('api/ordenes-lab', {
                method: 'POST',
                body: {
                    dni_paciente: dni,
                    nombre_paciente: nombre,
                    metodo_pago: metodo,
                    analisis: Object.values(carrito)
                }
            });

            if (res.ok && res.data.orden) {
                const o = res.data.orden;
                carrito = {};
                actualizarCarritoUI();
                carritoModal.style.display = 'none';
                overlay.querySelector('#formOrdenLab').style.display = 'none';
                const box = overlay.querySelector('#labOrderSuccess');
                box.style.display = 'block';
                box.innerHTML = `
                    <div style="padding:12px; background:#f3faf6; border:1px solid #029d56; border-radius:6px; font-size:0.9rem; line-height:1.5;">
                        <h4 style="color:#176428;">Orden Generada: ${o.codigo_orden}</h4>
                        <p><b>Boleta Electrónica:</b> ${o.codigo_boleta}</p>
                        <p><b>Paciente:</b> ${o.nombre_paciente} (DNI: ${o.dni_paciente})</p>
                        <p><b>Total Pagado:</b> S/ ${Number(o.total).toFixed(2)}</p>
                        <p style="margin-top:6px; font-size:0.82rem; color:#57606a;">Guarde su código <b>${o.codigo_orden}</b> para descargar sus resultados en línea.</p>
                    </div>
                `;
                window.ClinicaAPI.notify('Orden creada: ' + o.codigo_orden, 'success');
            } else {
                window.ClinicaAPI.notify(res.data.message || 'Error al registrar orden', 'error');
            }
        };
    }

    // Consulta de resultados online con código (RF-C10)
    const btnConsultar = document.getElementById('btnConsultarResultado');
    if (btnConsultar) {
        btnConsultar.addEventListener('click', async () => {
            const codigo = document.getElementById('inputCodigoLab').value.trim().toUpperCase();
            const dni = document.getElementById('inputDniLab').value.trim();
            if (!codigo) {
                window.ClinicaAPI.notify('Ingrese el código de orden (ej. LAB-2026-001)', 'error');
                return;
            }
            const res = await window.ClinicaAPI.request(`api/resultados/${encodeURIComponent(codigo)}?dni=${encodeURIComponent(dni)}`);
            if (!res.ok) {
                window.ClinicaAPI.notify(res.data.message || 'No se pudo acceder al resultado', 'error');
                return;
            }
            mostrarModalResultadoLab(res.data.resultado);
        });
    }

    function mostrarModalResultadoLab(r) {
        const prev = document.getElementById('resultadoLabModal');
        if (prev) prev.remove();

        const filas = (r.valores_resultado || []).map(v => `
            <tr>
                <td style="padding:6px 8px; border-bottom:1px solid #e5e7eb;">${v.parametro}</td>
                <td style="padding:6px 8px; border-bottom:1px solid #e5e7eb; font-weight:700;">${v.valor}</td>
                <td style="padding:6px 8px; border-bottom:1px solid #e5e7eb; color:#57606a;">${v.referencia}</td>
                <td style="padding:6px 8px; border-bottom:1px solid #e5e7eb;">${v.estado}</td>
            </tr>
        `).join('');

        const overlay = document.createElement('div');
        overlay.id = 'resultadoLabModal';
        overlay.className = 'google-modal-overlay';
        overlay.innerHTML = `
            <div class="google-modal-card" style="max-width:620px;">
                <div class="google-modal-header">
                    <div class="google-brand"><i class="fa-solid fa-file-waveform" style="color:#033076;"></i> <span>Informe de Laboratorio: ${r.codigo_orden}</span></div>
                    <button type="button" class="google-modal-close">&times;</button>
                </div>
                <div style="margin-top:12px; font-size:0.88rem; line-height:1.5;">
                    <p><b>Paciente:</b> ${r.nombre_paciente} | <b>DNI:</b> ${r.dni_paciente}</p>
                    <p><b>Estado:</b> <span class="role-badge role-badge-paciente">${r.estado.toUpperCase()}</span> | <b>Publicación:</b> ${r.fecha_publicacion || 'En proceso'}</p>
                    <p style="margin-top:8px;"><b>Resumen Clínico:</b> ${r.resultado_resumen || 'Pendiente de validación bioquímica.'}</p>
                    ${filas ? `
                        <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:0.84rem;">
                            <thead>
                                <tr style="background:#f3f4f6; text-align:left;">
                                    <th style="padding:6px 8px;">Parámetro</th>
                                    <th style="padding:6px 8px;">Resultado</th>
                                    <th style="padding:6px 8px;">Valores Ref.</th>
                                    <th style="padding:6px 8px;">Estado</th>
                                </tr>
                            </thead>
                            <tbody>${filas}</tbody>
                        </table>
                    ` : ''}
                    <p style="margin-top:10px; color:#57606a;"><b>Observaciones:</b> ${r.observaciones_lab || '-'}</p>
                    <div style="margin-top:14px; display:flex; justify-content:flex-end;">
                        <button type="button" onclick="window.print()" class="btn-google-submit"><i class="fa-solid fa-print"></i> Imprimir / Descargar Informe</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('.google-modal-close').onclick = () => overlay.remove();
    }

    // Búsqueda
    const inputBusqueda = document.querySelector('.busqueda input.texto');
    const cuadrosAnalisis = document.querySelectorAll('.cuadro');
    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', () => {
            const textoBusqueda = inputBusqueda.value.toLowerCase().trim();
            cuadrosAnalisis.forEach(cuadro => {
                const titulo = cuadro.querySelector('.titulo').textContent.toLowerCase();
                cuadro.style.display = titulo.includes(textoBusqueda) ? 'flex' : 'none';
            });
        });
    }

    // Carrusel
    const listaAnalisisContainer = document.querySelector('.lista-analisis');
    const botonIzquierda = document.querySelector('.boton-carrusel.izquierda');
    const botonDerecha = document.querySelector('.boton-carrusel.derecha');
    if (listaAnalisisContainer && botonIzquierda && botonDerecha) {
        botonDerecha.addEventListener('click', () => {
            listaAnalisisContainer.scrollBy({ left: listaAnalisisContainer.offsetWidth * 0.75, behavior: 'smooth' });
        });
        botonIzquierda.addEventListener('click', () => {
            listaAnalisisContainer.scrollBy({ left: -listaAnalisisContainer.offsetWidth * 0.75, behavior: 'smooth' });
        });
    }

    // Modal Ver Más
    const modalDescripcion = document.getElementById('modal-descripcion');
    const cerrarModalBtn = modalDescripcion ? modalDescripcion.querySelector('.cerrar-modal') : null;
    const botonesVerMas = document.querySelectorAll('.boton-ver-mas');

    botonesVerMas.forEach(boton => {
        boton.addEventListener('click', () => {
            const cuadro = boton.closest('.cuadro');
            if (cuadro && modalDescripcion) {
                document.getElementById('modal-imagen').src = cuadro.querySelector('img').src;
                document.getElementById('modal-titulo').textContent = cuadro.querySelector('.titulo').textContent;
                document.getElementById('modal-parrafo').textContent = cuadro.querySelector('.descripcion').textContent;
                document.getElementById('modal-parrafo2').innerHTML = cuadro.querySelector('.descripcion2').innerHTML;
                modalDescripcion.style.display = 'flex';
            }
        });
    });
    if (cerrarModalBtn) {
        cerrarModalBtn.addEventListener('click', () => { modalDescripcion.style.display = 'none'; });
    }
    window.addEventListener('click', (event) => {
        if (event.target === modalDescripcion) modalDescripcion.style.display = 'none';
    });
});
