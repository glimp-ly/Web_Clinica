$(document).ready(function () {
    let products = [
        { id: 1, name: "Paracetamol 500mg", category: "Analgésicos", price: 5.00, stock: 120, requiere_receta: 0, image: "img/paracetamol.jpg" },
        { id: 2, name: "Amoxicilina 500mg", category: "Antibióticos", price: 15.00, stock: 60, requiere_receta: 1, image: "img/amoxicilina.jpeg" },
        { id: 3, name: "Ibuprofeno 400mg", category: "Antiinflamatorios", price: 8.50, stock: 95, requiere_receta: 0, image: "img/ibuprofeno.jpg" },
        { id: 4, name: "Vitamina C 1000mg", category: "Vitaminas", price: 25.00, stock: 80, requiere_receta: 0, image: "img/vitaminaC.jpg" },
        { id: 5, name: "Alcohol 70°", category: "Primeros Auxilios", price: 7.00, stock: 150, requiere_receta: 0, image: "img/alcohol.jpeg" },
        { id: 6, name: "Mascarilla KN95", category: "Protección", price: 3.50, stock: 300, requiere_receta: 0, image: "img/mascarilla.jpg" },
        { id: 7, name: "Jarabe para la Tos", category: "Respiratorio", price: 18.00, stock: 45, requiere_receta: 0, image: "img/jarabe.jpeg" },
        { id: 8, name: "Protector Solar SPF 50", category: "Cuidado de la Piel", price: 45.00, stock: 40, requiere_receta: 0, image: "img/protectorSolar.png" }
    ];

    let cart = {};
    let userDni = '';
    let userName = '';

    const $productsGrid = $('#productsGrid');
    const $searchInput = $('#searchInput');
    const $cartBtn = $('#cartBtn');
    const $cartModal = $('#cartSidebar');
    const $closeCart = $('#closeCart');
    const $cartItems = $('#cartItems');
    const $cartTotal = $('#cartTotal');
    const $cartCount = $('#cartCount');
    const $checkoutBtn = $('#checkoutBtn');

    async function loadProducts() {
        const res = await window.ClinicaAPI.request('api/productos');
        if (res.ok && res.data.productos) {
            products = res.data.productos.map(p => ({
                id: parseInt(p.id),
                name: p.nombre,
                category: p.categoria,
                price: parseFloat(p.precio),
                stock: parseInt(p.stock),
                requiere_receta: parseInt(p.requiere_receta),
                image: p.imagen
            }));
        }
        renderProducts($searchInput.val());
    }

    window.ClinicaAPI.request('api/perfil').then(res => {
        if (res.ok && res.data.data) {
            if (res.data.data.ficha) userDni = res.data.data.ficha.dni || '';
            if (res.data.data.user) userName = res.data.data.user.nombre || '';
        }
    });

    function renderProducts(filterText = '') {
        $productsGrid.empty();
        const btnText = $(window).width() < 769 ? "+" : "Agregar al Carrito";

        const filteredProducts = products.filter(product => {
            const searchText = filterText.toLowerCase();
            return product.name.toLowerCase().includes(searchText) ||
                product.category.toLowerCase().includes(searchText);
        });

        filteredProducts.forEach(product => {
            const recBadge = product.requiere_receta
                ? '<span style="display:inline-block; font-size:0.72rem; background:#fce8e6; color:#d93025; padding:2px 6px; border-radius:4px; font-weight:700; margin-bottom:4px;">Requiere Receta Médica</span>'
                : '';
            const stockText = product.stock > 0
                ? `<small style="color:#57606a; display:block; margin-bottom:6px;">Stock disponible: ${product.stock}</small>`
                : `<small style="color:#d93025; font-weight:700; display:block; margin-bottom:6px;">Sin Stock</small>`;

            const card = $(`
                <div class="product-card">
                    <img src="${product.image}" alt="${product.name}" class="product-image">
                    <div class="product-info">
                        <p class="product-category">${product.category}</p>
                        <h3>${product.name}</h3>
                        ${recBadge}
                        <p class="product-price">S/ ${product.price.toFixed(2)}</p>
                        ${stockText}
                    </div>
                    <button class="add-to-cart-btn" data-id="${product.id}" ${product.stock <= 0 ? 'disabled style="background:#ccc;cursor:not-allowed;"' : ''}>
                        <span class="text-add-cart">${product.stock <= 0 ? 'Agotado' : btnText}</span>
                    </button>
                </div>
            `);

            card.find('.add-to-cart-btn').on('click', function () {
                if (product.stock > 0) addToCart(product);
            });

            $productsGrid.append(card);
        });
    }

    loadProducts();

    $searchInput.on('input', function () {
        renderProducts($(this).val());
    });

    function addToCart(product) {
        const currentQty = cart[product.id] ? cart[product.id].quantity : 0;
        if (currentQty + 1 > product.stock) {
            window.ClinicaAPI.notify('No hay más stock disponible para ' + product.name, 'error');
            return;
        }
        if (cart[product.id]) {
            cart[product.id].quantity += 1;
        } else {
            cart[product.id] = { ...product, quantity: 1 };
        }
        updateCartUI();
    }

    function removeFromCart(id) {
        if (cart[id]) {
            cart[id].quantity -= 1;
            if (cart[id].quantity <= 0) delete cart[id];
        }
        updateCartUI();
    }

    function updateCartUI() {
        $cartItems.empty();
        let total = 0;
        let totalItems = 0;

        Object.values(cart).forEach(item => {
            total += item.price * item.quantity;
            totalItems += item.quantity;

            const listItem = $(`
                <li class="item-cart-product">
                    <img src="${item.image}" alt="${item.name}" class="product-image-cart">
                    <span>${item.name} <span style="color: #28a745;">x${item.quantity}</span> <br> S/ ${item.price.toFixed(2)} ${item.requiere_receta ? '<b style="color:#d93025;font-size:0.75rem;">[Receta]</b>' : ''}</span>
                    <button class="boton-eliminar" data-id="${item.id}">&times;</button>
                </li>
            `);

            listItem.find('.boton-eliminar').on('click', function () {
                removeFromCart($(this).data('id'));
            });

            $cartItems.append(listItem);
        });

        $cartTotal.text(`S/ ${total.toFixed(2)}`);
        $cartCount.text(totalItems);
    }

    function openCart() { $cartModal.addClass('open'); }
    function closeCart() { $cartModal.removeClass('open'); }

    $cartBtn.on('click', function () {
        if (Object.keys(cart).length > 0) openCart();
        else window.ClinicaAPI.notify('Agregue productos al carrito primero', 'error');
    });

    $closeCart.on('click', closeCart);

    $checkoutBtn.on('click', function () {
        if (Object.keys(cart).length === 0) return;
        openCheckoutModal();
    });

    function openCheckoutModal() {
        $('#farmaciaCheckoutModal').remove();
        const needsReceta = Object.values(cart).some(i => i.requiere_receta === 1);
        let total = 0;
        Object.values(cart).forEach(i => { total += i.price * i.quantity; });

        const modal = $(`
            <div id="farmaciaCheckoutModal" class="google-modal-overlay">
                <div class="google-modal-card" style="max-width:500px;">
                    <div class="google-modal-header">
                        <div class="google-brand"><i class="fa-solid fa-file-invoice-dollar" style="color:#029d56;"></i> <span>Pago y Facturación Electrónica</span></div>
                        <button type="button" class="google-modal-close">&times;</button>
                    </div>
                    <form id="checkoutForm" style="margin-top:12px; display:flex; flex-direction:column; gap:10px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:0.82rem; font-weight:600;">DNI Cliente (8 dígitos)*</label>
                                <input type="text" id="payDni" value="${userDni}" maxlength="8" required placeholder="71234561" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px;">
                            </div>
                            <div>
                                <label style="font-size:0.82rem; font-weight:600;">Nombre / Razón Social*</label>
                                <input type="text" id="payNombre" value="${userName}" required placeholder="Nombre completo" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px;">
                            </div>
                        </div>
                        <div>
                            <label style="font-size:0.82rem; font-weight:600;">Método de Pago*</label>
                            <select id="payMetodo" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px;">
                                <option value="tarjeta_visa">Tarjeta Visa / Débito</option>
                                <option value="tarjeta_mastercard">Tarjeta Mastercard</option>
                                <option value="tarjeta_amex">American Express</option>
                                <option value="yape">Yape / Plin QR</option>
                            </select>
                        </div>
                        <div style="background:${needsReceta ? '#fff5f5' : '#f8f9fa'}; padding:10px; border:1px solid ${needsReceta ? '#f5c2c7' : '#e0e0e0'}; border-radius:6px;">
                            <label style="font-size:0.82rem; font-weight:700; color:${needsReceta ? '#d93025' : '#333'};">
                                Código de Receta Digital ${needsReceta ? '(OBLIGATORIO para antibióticos en su carrito)' : '(Opcional)'}
                            </label>
                            <input type="text" id="payReceta" placeholder="Ej: REC-2026-001" ${needsReceta ? 'required' : ''} style="width:100%; margin-top:4px; padding:8px; border:1px solid #ccc; border-radius:6px;">
                            <small style="font-size:0.75rem; color:#57606a;">Recetas de prueba vigentes: <b>REC-2026-001</b>, <b>REC-2026-002</b></small>
                        </div>
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.86rem; cursor:pointer;">
                            <input type="checkbox" id="payUsarEps" checked> Aplicar descuento de cobertura de Seguro EPS (si el DNI tiene póliza activa)
                        </label>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#eef6ff; border-radius:6px; font-weight:700; color:#033076;">
                            <span>Importe Subtotal:</span>
                            <span>S/ ${total.toFixed(2)}</span>
                        </div>
                        <button type="submit" class="btn-google-submit" style="padding:11px; background:#029d56;">Confirmar Pago y Emitir Boleta</button>
                    </form>
                    <div id="boletaResult" style="display:none; margin-top:12px;"></div>
                </div>
            </div>
        `);

        $('body').append(modal);
        modal.find('.google-modal-close').on('click', () => modal.remove());

        modal.find('#checkoutForm').on('submit', async function (e) {
            e.preventDefault();
            const payload = {
                dni: $('#payDni').val().trim(),
                nombre: $('#payNombre').val().trim(),
                metodo_pago: $('#payMetodo').val(),
                codigo_receta: $('#payReceta').val().trim(),
                usar_eps: $('#payUsarEps').is(':checked'),
                items: Object.values(cart)
            };

            const res = await window.ClinicaAPI.request('api/pedidos', {
                method: 'POST',
                body: payload
            });

            if (res.ok && res.data.boleta) {
                const b = res.data.boleta;
                cart = {};
                updateCartUI();
                closeCart();
                loadProducts();

                $('#checkoutForm').hide();
                $('#boletaResult').show().html(`
                    <div style="border:1px solid #029d56; background:#f3faf6; padding:14px; border-radius:6px; font-size:0.88rem; line-height:1.5;">
                        <h4 style="color:#176428; margin-bottom:6px;"><i class="fa-solid fa-circle-check"></i> Boleta Electrónica: ${b.codigo_boleta}</h4>
                        <p><b>Cliente:</b> ${b.nombre_cliente} (DNI: ${b.dni_cliente})</p>
                        <p><b>Fecha:</b> ${b.fecha} | <b>Pago:</b> ${b.metodo_pago.toUpperCase()}</p>
                        ${b.codigo_receta ? `<p><b>Receta Validada:</b> ${b.codigo_receta}</p>` : ''}
                        <hr style="margin:8px 0; border:none; border-top:1px solid #cde7d8;">
                        <p>Subtotal: S/ ${Number(b.subtotal).toFixed(2)}</p>
                        <p style="color:#029d56;">Cobertura EPS: - S/ ${Number(b.descuento_eps).toFixed(2)}</p>
                        <p style="font-size:1rem; font-weight:700; color:#033076;">Total Pagado: S/ ${Number(b.total_pagado).toFixed(2)}</p>
                    </div>
                `);
                window.ClinicaAPI.notify('Pago aprobado: ' + b.codigo_boleta, 'success');
            } else {
                window.ClinicaAPI.notify(res.data.message || 'Error al procesar el pedido', 'error');
            }
        });
    }
});
