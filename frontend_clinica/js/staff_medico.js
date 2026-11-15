$(document).ready(function () {
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

    const doctors = [
        { name: "Dr. Juan Pérez", specialty: "Cardiologia", description: "Especialista en enfermedades del corazón.", image: "img/doctor1.jpg", experience: 15, clinicYears: 5, consultations: 1200 },
        { name: "Dra. Maria Rodriguez", specialty: "Obstetricia", description: "Cuidado integral del embarazo y parto.", image: "img/doctor2.jpg", experience: 10, clinicYears: 3, consultations: 850 },
        { name: "Dr. Carlos Gomez", specialty: "Medicina General", description: "Atención primaria y preventiva.", image: "img/doctor3.jpg", experience: 20, clinicYears: 12, consultations: 5000 },
        { name: "Dra. Ana Lopez", specialty: "Pediatria", description: "Salud y bienestar infantil.", image: "img/doctor4.jpg", experience: 8, clinicYears: 2, consultations: 600 },
        { name: "Dr. Luis Martinez", specialty: "Ginecología", description: "Salud reproductiva femenina.", image: "img/doctor5.jpg", experience: 12, clinicYears: 6, consultations: 1500 },
        { name: "Dra. Sofia Torres", specialty: "Laboratorio", description: "Análisis clínicos y diagnósticos.", image: "img/doctor6.jpg", experience: 5, clinicYears: 1, consultations: 3000 },
        { name: "Dr. Pedro Sanchez", specialty: "Análisis", description: "Interpretación de resultados médicos.", image: "img/doctor7.jpg", experience: 18, clinicYears: 10, consultations: 4200 },
        { name: "Dra. Elena Diaz", specialty: "Cardiologia", description: "Cardiología intervencionista.", image: "img/doctor8.jpg", experience: 14, clinicYears: 4, consultations: 1100 },
        { name: "Dr. Miguel Angel", specialty: "Pediatria", description: "Neonatología y cuidados intensivos.", image: "img/doctor9.jpg", experience: 22, clinicYears: 15, consultations: 3500 }
    ];

    const $doctorsGrid = $('#doctorsGrid');
    const $searchInput = $('#searchInput');
    const $doctorModal = $('#doctorModal');
    const $closeDoctorModal = $('#closeDoctorModal');

    function renderDoctors(filterText = '') {
        $doctorsGrid.empty();

        const filteredDoctors = doctors.filter(doctor =>
            doctor.specialty.toLowerCase().includes(filterText.toLowerCase()) ||
            doctor.name.toLowerCase().includes(filterText.toLowerCase())
        );

        if (filteredDoctors.length === 0) {
            $doctorsGrid.append('<p>No se encontraron médicos con esa especialidad o nombre.</p>');
            return;
        }

        filteredDoctors.forEach((doctor, index) => {
            const card = `
                <div class="doctor-card">
                    <div class="doctor-image">
                        <img src="${doctor.image}" alt="${doctor.name}" style="object-fit: cover; border-radius: 5px;">
                    </div>
                    <div class="doctor-info">
                        <h3>${doctor.name}</h3>
                        <p><strong>Especialidad:</strong> ${doctor.specialty}</p>
                        <p>${doctor.description}</p>
                        <button class="btn btn-primary view-profile-btn" data-index="${index}" style="margin-top: 10px; background-color: #28a745; color: #fff; border-radius: 5px; padding: 5px 10px; border: none; cursor: pointer;">Ver perfil</button>
                    </div>
                </div>
            `;
            $doctorsGrid.append(card);
        });

        $('.view-profile-btn').on('click', function () {
            const index = $(this).data('index');
        });
    }

    function renderDoctors(filterText = '') {
        $doctorsGrid.empty();

        const filteredDoctors = doctors.filter(doctor =>
            doctor.specialty.toLowerCase().includes(filterText.toLowerCase()) ||
            doctor.name.toLowerCase().includes(filterText.toLowerCase())
        );

        if (filteredDoctors.length === 0) {
            $doctorsGrid.append('<p>No se encontraron médicos con esa especialidad o nombre.</p>');
            return;
        }

        filteredDoctors.forEach(doctor => {
            const card = $(`
                <div class="doctor-card">
                    <div class="doctor-image">
                        <img src="${doctor.image}" alt="${doctor.name}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 5px;">
                    </div>
                    <div class="doctor-info">
                        <h3>${doctor.name}</h3>
                        <p><strong>Especialidad:</strong> ${doctor.specialty}</p>
                        <p>${doctor.description}</p>
                        <button class="btn btn-primary view-profile-btn" style="margin-top: 10px; background-color: #28a745; color: #fff; border-radius: 5px; padding: 5px 10px; border: none; cursor: pointer;">Ver perfil</button>
                    </div>
                </div>
            `);

            card.find('.view-profile-btn').on('click', function () {
                openDoctorModal(doctor);
            });

            $doctorsGrid.append(card);
        });
    }

    function openDoctorModal(doctor) {
        $('#modalDoctorImage').attr('src', doctor.image);
        $('#modalDoctorName').text(doctor.name);
        $('#modalDoctorSpecialty').text(doctor.specialty);
        $('#modalDoctorDescription').text(doctor.description);
        $('#modalExperience').text(doctor.experience + '+');
        $('#modalClinicYears').text(doctor.clinicYears);
        $('#modalConsultations').text(doctor.consultations);

        $doctorModal.addClass('show');
    }

    $closeDoctorModal.on('click', function () {
        $doctorModal.removeClass('show');
    });

    $(window).on('click', function (e) {
        if ($(e.target).is($doctorModal)) {
            $doctorModal.removeClass('show');
        }
    });

    renderDoctors();

    $searchInput.on('input', function () {
        renderDoctors($(this).val());
    });

    // Dark Mode Logic
    const $darkModeToggle = $('#darkModeToggle');
    const $body = $('body');
    const $icon = $darkModeToggle.find('i');

    // Check for saved preference
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
