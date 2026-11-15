$(document).ready(function () {
    const $modal = $('#successModal');
    const $closeBtn = $('.close-modal');
    const $form = $('#citaForm');
    const $espSelect = $('#especialidad');
    const $medSelect = $('#medico_id');
    const $hint = $('#availabilityHint');

    function getLocalDateYmd() {
        const now = new Date();
        const offsetMs = now.getTimezoneOffset() * 60000;
        return new Date(now.getTime() - offsetMs).toISOString().split('T')[0];
    }

    const today = getLocalDateYmd();
    $('#fecha-inicio').val(today).attr('min', today);
    $('#fecha-fin').val(today);
    $('#fecha-inicio').on('change', function () {
        $('#fecha-fin').val($(this).val());
        refreshAvailability();
    });

    let allDoctors = [];

    function populateDoctors(espClave) {
        $medSelect.empty();
        $medSelect.append('<option value="">Asignación automática por especialidad</option>');
        const list = espClave
            ? allDoctors.filter(d => d.especialidad_clave === espClave || String(d.especialidad_id) === String(espClave))
            : allDoctors;
        list.forEach(d => {
            $medSelect.append(`<option value="${d.id}">${d.nombre} (${d.especialidad_nombre})</option>`);
        });
        if (list.length === 1) {
            $medSelect.val(String(list[0].id));
        }
        refreshAvailability();
    }

    async function refreshAvailability() {
        if (!$hint.length) return;
        let medicoId = $medSelect.val();
        if (!medicoId) {
            const espClave = $espSelect.val();
            const matched = allDoctors.filter(d => d.especialidad_clave === espClave || String(d.especialidad_id) === String(espClave));
            if (matched.length > 0) medicoId = String(matched[0].id);
        }
        const fecha = $('#fecha-inicio').val();
        if (!medicoId || !fecha) {
            $hint.html('');
            return;
        }
        const r = await window.ClinicaAPI.request(`api/citas/ocupadas?medico_id=${medicoId}&fecha=${fecha}`);
        if (!r.ok) {
            $hint.html('');
            return;
        }
        const ocupadas = r.data.horas_ocupadas || [];
        const bloqueos = r.data.bloqueos || [];
        const parts = [];
        if (ocupadas.length) {
            parts.push(`<span style="color:#d93025;">Horas ya reservadas el ${fecha}: <strong>${ocupadas.join(', ')}</strong></span>`);
        } else {
            parts.push(`<span style="color:#176428;">Agenda disponible para el ${fecha}.</span>`);
        }
        if (bloqueos.length) {
            parts.push(`<span style="color:#9a6700;">Bloqueos: ${bloqueos.map(b => `${b.turno} (${b.motivo || b.estado})`).join('; ')}</span>`);
        }
        $hint.html(parts.join(' &middot; '));
    }

    Promise.all([
        window.ClinicaAPI.request('api/especialidades'),
        window.ClinicaAPI.request('api/medicos')
    ]).then(([espRes, medRes]) => {
        if (medRes.ok && medRes.data.medicos) {
            allDoctors = medRes.data.medicos;
        }
        if (espRes.ok && espRes.data.especialidades) {
            $espSelect.empty().append('<option value="">Selecciona la especialidad</option>');
            espRes.data.especialidades.forEach(e => {
                $espSelect.append(`<option value="${e.clave}">${e.nombre} (S/ ${parseFloat(e.precio_consulta || 80).toFixed(2)})</option>`);
            });
            const params = new URLSearchParams(window.location.search);
            const preEsp = params.get('especialidad');
            if (preEsp) {
                $espSelect.val(preEsp);
            }
        }
        populateDoctors($espSelect.val());
    });

    window.ClinicaAPI.request('api/perfil').then(res => {
        if (res.ok && res.data.data) {
            const u = res.data.data.user;
            const f = res.data.data.ficha;
            if (f) {
                $('#dni').val(f.dni || '');
                $('#numero_cel').val(f.telefono || '');
            }
            if (u && u.email) {
                $('#email').val(u.email);
            }
        }
    });

    $espSelect.on('change', function () {
        populateDoctors($(this).val());
    });

    $medSelect.on('change', refreshAvailability);

    $('input[name="turno"]').on('change', function () {
        if ($(this).val() === 'tarde') {
            $('#hora_cita').val('15:00');
        } else {
            $('#hora_cita').val('09:00');
        }
    });

    $form.on('submit', async function (e) {
        e.preventDefault();

        let isValid = true;
        const dni = $('#dni').val().trim();
        if (!/^\d{8}$/.test(dni)) {
            $('#error-dni').show();
            $('#dni').addClass('error-border');
            isValid = false;
        } else {
            $('#error-dni').hide();
            $('#dni').removeClass('error-border');
        }

        const phone = $('#numero_cel').val().trim();
        if (!/^\d{9}$/.test(phone)) {
            $('#error-telefono').show();
            $('#numero_cel').addClass('error-border');
            isValid = false;
        } else {
            $('#error-telefono').hide();
            $('#numero_cel').removeClass('error-border');
        }

        const email = $('#email').val().trim();
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

        if (!isValid) return;

        const payload = {
            dni: dni,
            numero_cel: phone,
            email: email,
            especialidad: $espSelect.val(),
            medico_id: $medSelect.val() || null,
            fecha_inicio: $('#fecha-inicio').val(),
            fecha_fin: $('#fecha-fin').val() || $('#fecha-inicio').val(),
            hora_cita: $('#hora_cita').val(),
            turno: $('input[name="turno"]:checked').val() || 'manana',
            motivo_consulta: ($('#motivo_consulta').val() || '').trim() || undefined,
            'terminos-condiciones': true
        };

        const res = await window.ClinicaAPI.request('api/citas', {
            method: 'POST',
            body: payload
        });

        if (res.ok && res.data.cita) {
            const c = res.data.cita;
            $modal.find('.modal-header h2').text('¡Cita Programada! (' + c.codigo_cita + ')');
            $modal.find('.modal-body p').html(
                `<strong>Paciente DNI:</strong> ${c.dni_paciente}<br>` +
                `<strong>Especialidad:</strong> ${c.especialidad} — ${c.medico}<br>` +
                `<strong>Fecha y Hora:</strong> ${c.fecha_cita} a las ${c.hora_cita} (${c.turno})<br>` +
                `<strong>Notificación:</strong> Enviada a WhatsApp y correo electrónico.<br><br>` +
                `<a href="mis_citas.html" style="display:inline-block; padding:8px 16px; background:#029d56; color:#fff; border-radius:6px; text-decoration:none; font-weight:600;">Ver en Mis Citas</a>`
            );
            $modal.addClass('show');
        } else {
            window.ClinicaAPI.notify(res.data.message || 'No se pudo agendar la cita', 'error');
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
});
