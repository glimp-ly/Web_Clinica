$(document).ready(function () {
    let doctors = [
        { id: 1, name: "Dr. Juan Pérez", specialty: "Cardiología", description: "Especialista en enfermedades del corazón.", image: "img/doctor1.jpg", experience: 15, clinicYears: 5, consultations: 1200 },
        { id: 2, name: "Dra. María Rodríguez", specialty: "Obstetricia", description: "Cuidado integral del embarazo y parto.", image: "img/doctor2.jpg", experience: 10, clinicYears: 3, consultations: 850 },
        { id: 3, name: "Dr. Carlos Gómez", specialty: "Medicina General", description: "Atención primaria y preventiva.", image: "img/doctor3.jpg", experience: 20, clinicYears: 12, consultations: 5000 },
        { id: 4, name: "Dra. Ana López", specialty: "Pediatría", description: "Salud y bienestar infantil.", image: "img/doctor4.jpg", experience: 8, clinicYears: 2, consultations: 600 },
        { id: 5, name: "Dr. Luis Martínez", specialty: "Ginecología", description: "Salud reproductiva femenina.", image: "img/doctor5.jpg", experience: 12, clinicYears: 6, consultations: 1500 },
        { id: 6, name: "Dra. Sofía Torres", specialty: "Laboratorio", description: "Análisis clínicos y diagnósticos.", image: "img/doctor6.jpg", experience: 5, clinicYears: 1, consultations: 3000 },
        { id: 7, name: "Dr. Pedro Sánchez", specialty: "Análisis", description: "Interpretación de resultados médicos.", image: "img/doctor7.jpg", experience: 18, clinicYears: 10, consultations: 4200 },
        { id: 8, name: "Dra. Elena Díaz", specialty: "Cardiología", description: "Cardiología intervencionista.", image: "img/doctor8.jpg", experience: 14, clinicYears: 4, consultations: 1100 },
        { id: 9, name: "Dr. Miguel Ángel", specialty: "Pediatría", description: "Neonatología y cuidados intensivos.", image: "img/doctor9.jpg", experience: 22, clinicYears: 15, consultations: 3500 }
    ];

    const $doctorsGrid = $('#doctorsGrid');
    const $searchInput = $('#searchInput');
    const $doctorModal = $('#doctorModal');
    const $closeDoctorModal = $('#closeDoctorModal');

    window.ClinicaAPI.request('api/medicos').then(res => {
        if (res.ok && res.data.medicos && res.data.medicos.length > 0) {
            doctors = res.data.medicos.map(m => ({
                id: m.id,
                name: m.nombre,
                specialty: m.especialidad_nombre,
                description: m.descripcion,
                image: m.imagen || 'img/doctor1.jpg',
                experience: m.experiencia_anios,
                clinicYears: m.anios_clinica,
                consultations: m.consultas_realizadas,
                cmp: m.cmp
            }));
            renderDoctors($searchInput.val());
        }
    });

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
                        <p><strong>Especialidad:</strong> ${doctor.specialty} ${doctor.cmp ? `<small>(${doctor.cmp})</small>` : ''}</p>
                        <p>${doctor.description}</p>
                        <button class="btn btn-primary view-profile-btn" style="margin-top: 10px; background-color: #28a745; color: #fff; border-radius: 5px; padding: 6px 12px; border: none; cursor: pointer;">Ver perfil</button>
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
        $('#modalDoctorSpecialty').text(doctor.specialty + (doctor.cmp ? ' · ' + doctor.cmp : ''));
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
});
