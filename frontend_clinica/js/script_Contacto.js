document.addEventListener("DOMContentLoaded", function () {
    if (window.jQuery) {
        let indice = 0;
        const slides = $('.slide');
        const totalSlides = slides.length;

        if (totalSlides > 0) {
            setInterval(() => {
                indice++;
                if (indice >= totalSlides) { indice = 0; }
                $('.carrusel-track').css('transform', `translateX(-${indice * 100}%)`);
            }, 5000);
        }

        $('#dni, #celular').on('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        $('#miFormulario').on('submit', async function (e) {
            e.preventDefault();
            let dni = $('#dni').val().trim();
            let cel = $('#celular').val().trim();
            let correo = $('#correo').val().trim();
            let check = $('#checkLegal').is(':checked');

            if (dni.length < 8) {
                window.ClinicaAPI.notify("El DNI debe tener 8 dígitos.", "error");
            } else if (cel.length < 9) {
                window.ClinicaAPI.notify("El Celular debe tener 9 dígitos.", "error");
            } else if (!check) {
                window.ClinicaAPI.notify("Debes aceptar la Política de Privacidad.", "error");
            } else {
                let btn = $('.boton-enviar');
                btn.prop('disabled', true).text('ENVIANDO...');

                const res = await window.ClinicaAPI.request('api/contactos', {
                    method: 'POST',
                    body: { dni, celular: cel, correo, checkLegal: check }
                });

                if (res.ok) {
                    btn.html('<i class="fa-solid fa-check"></i> ENVIADO').css('background', '#029d56');
                    window.ClinicaAPI.notify(res.data.message || "Solicitud registrada con estado ENVIADO", "success");
                    setTimeout(() => {
                        $('#miFormulario')[0].reset();
                        btn.prop('disabled', false).html('SOLICITO INFORMACIÓN').css('background', '#033076');
                    }, 1500);
                } else {
                    btn.prop('disabled', false).html('SOLICITO INFORMACIÓN');
                    window.ClinicaAPI.notify(res.data.message || "Error al registrar solicitud", "error");
                }
            }
        });
    }
});
