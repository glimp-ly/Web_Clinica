$(document).ready(function () {
    const products = [
        {
            id: 1,
            name: "Paracetamol 500mg",
            category: "Analgésicos",
            price: 5.00,
            image: "img/paracetamol.jpg"
        },
        {
            id: 2,
            name: "Amoxicilina 500mg",
            category: "Antibióticos",
            price: 15.00,
            image: "img/amoxicilina.jpeg"
        },
        {
            id: 3,
            name: "Ibuprofeno 400mg",
            category: "Antiinflamatorios",
            price: 8.50,
            image: "img/ibuprofeno.jpg"
        },
        {
            id: 4,
            name: "Vitamina C 1000mg",
            category: "Vitaminas",
            price: 25.00,
            image: "img/vitaminaC.jpg"
        },
        {
            id: 5,
            name: "Alcohol 70°",
            category: "Primeros Auxilios",
            price: 7.00,
            image: "img/alcohol.jpeg"
        },
        {
            id: 6,
            name: "Mascarilla KN95",
            category: "Protección",
            price: 3.50,
            image: "img/mascarilla.jpg"
        },
        {
            id: 7,
            name: "Jarabe para la Tos",
            category: "Respiratorio",
            price: 18.00,
            image: "img/jarabe.jpeg"
        },
        {
            id: 8,
            name: "Protector Solar SPF 50",
            category: "Cuidado de la Piel",
            price: 45.00,
            image: "img/protectorSolar.png"
        }
    ];

    let cart = {};

    const $productsGrid = $('#productsGrid');
    const $searchInput = $('#searchInput');
    const $cartBtn = $('#cartBtn');
    const $cartModal = $('#cartSidebar');
    const $closeCart = $('#closeCart');
    const $cartItems = $('#cartItems');
    const $cartTotal = $('#cartTotal');
    const $cartCount = $('#cartCount');

    function renderProducts(filterText = '') {
        $productsGrid.empty();

        const btnText = $(window).width() < 769 ? "+" : "Agregar al Carrito";

        const filteredProducts = products.filter(product => {
            const searchText = filterText.toLowerCase();
            return product.name.toLowerCase().includes(searchText) ||
                product.category.toLowerCase().includes(searchText);
        });

        filteredProducts.forEach(product => {
            const card = $(`
                <div class="product-card">
                    <img src="${product.image}" alt="${product.name}" class="product-image">
                    <div class="product-info">
                        <p class="product-category">${product.category}</p>
                        <h3>${product.name}</h3>
                        <p class="product-price">S/ ${product.price.toFixed(2)}</p>
                    </div>
                    <button class="add-to-cart-btn" data-id="${product.id}"><span class="text-add-cart">${btnText}</span></button>
                </div>
            `);

            card.find('.add-to-cart-btn').on('click', function () {
                addToCart(product);
            });

            $productsGrid.append(card);
        });
    }

    renderProducts();

    $searchInput.on('input', function () {
        renderProducts($(this).val());
    });

    function addToCart(product) {
        if (cart[product.id]) {
            cart[product.id].quantity += 1;
        } else {
            cart[product.id] = {
                ...product,
                quantity: 1
            };
        }
        updateCartUI();
    }

    function removeFromCart(id) {
        if (cart[id]) {
            cart[id].quantity -= 1;
            if (cart[id].quantity <= 0) {
                delete cart[id];
            }
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
                    <span>${item.name} <span style="color: green;">x${item.quantity}</span> <br> S/ ${item.price.toFixed(2)}</span>
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

        if (totalItems > 0) {
            $cartCount.addClass('animar-aumento');
            setTimeout(() => {
                $cartCount.removeClass('animar-aumento');
            }, 300);
        }
    }

    function openCart() {
        $cartModal.addClass('open');
    }

    function closeCart() {
        $cartModal.removeClass('open');
    }

    $cartBtn.on('click', function () {
        if (Object.keys(cart).length > 0) {
            openCart();
        }
    });

    $closeCart.on('click', closeCart);

    $(window).on('click', function (e) {
        if ($(e.target).is($cartModal)) {
            closeCart();
        }
    });

    const $darkModeToggle = $('#darkModeToggle');
    const $body = $('body');
    const $icon = $darkModeToggle.find('i');

    if (localStorage.getItem('darkMode') === 'enabled') {
        $body.addClass('dark-mode');
        $icon.removeClass('fa-moon').addClass('fa-sun');
    }

    $darkModeToggle.on('click', function () {
        $body.toggleClass('dark-mode');

        if ($body.hasClass('dark-mode')) {
            localStorage.setItem('darkMode', 'enabled');
            $icon.removeClass('fa-moon').addClass('fa-sun');
        } else {
            localStorage.setItem('darkMode', 'disabled');
            $icon.removeClass('fa-sun').addClass('fa-moon');
        }
    });

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

$(window).on('resize', function () {
    const isMobile = $(this).width() < 769;
    $('.text-add-cart').text(isMobile ? "+" : "Agregar al carrito");
}).resize();