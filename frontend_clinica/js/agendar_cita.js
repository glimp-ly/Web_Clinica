$(document).ready(function () {
    const $modal = $('#successModal');
    const $closeBtn = $('.close-modal');
    const $form = $('#citaForm');

    $form.on('submit', function (e) {
        e.preventDefault();

        let isValid = true;

        const dni = $('#dni').val();
        if (dni.length !== 8 || isNaN(dni)) {
            $('#error-dni').show();
            $('#dni').addClass('error-border');
            isValid = false;
        } else {
            $('#error-dni').hide();
            $('#dni').removeClass('error-border');
        }

        const phone = $('#numero_cel').val();
        if (phone.length !== 9 || isNaN(phone)) {
            $('#error-telefono').show();
            $('#numero_cel').addClass('error-border');
            isValid = false;
        } else {
            $('#error-telefono').hide();
            $('#numero_cel').removeClass('error-border');
        }

        const email = $('#email').val();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            $('#error-email').show();
            $('#email').addClass('error-border');
            isValid = false;
        } else {
            $('#error-email').hide();
            $('#email').removeClass('error-border');
        }

        if (!$('#terminos-condiciones').is(':checked')) {
            $('#error-terminos').show();
            isValid = false;
        } else {
            $('#error-terminos').hide();
        }

        if (isValid) {
            $modal.addClass('show');

            $form[0].reset();
        }
    });

    $closeBtn.on('click', function () {
        $modal.removeClass('show');
    });

    $(window).on('click', function (e) {
        if ($(e.target).is($modal)) {
            $modal.removeClass('show');
        }
    });

    $('input').on('input', function () {
        if ($(this).val() !== '') {
            $(this).removeClass('error-border');
            $(this).next('.error').hide();
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
