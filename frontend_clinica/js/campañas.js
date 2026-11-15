document.addEventListener('DOMContentLoaded', () => {
    const now = new Date();
    const campaigns = {
        1: { end: new Date(now.getTime() + (3 * 24 * 60 * 60 * 1000)), total: 1000, occupied: 14 },
        2: { end: new Date(now.getTime() + (6 * 24 * 60 * 60 * 1000)), total: 800, occupied: 9 },
        3: { end: new Date(now.getTime() + (20 * 60 * 60 * 1000)), total: 600, occupied: 22 },
        4: { end: null, total: 700, occupied: 0 },
        5: { end: null, total: 500, occupied: 0 },
        6: { end: null, total: 900, occupied: 0 }
    };

    async function syncCampaignsFromBackend() {
        const res = await window.ClinicaAPI.request('api/campanas');
        if (res.ok && res.data.campanas) {
            res.data.campanas.forEach(c => {
                campaigns[c.id] = {
                    end: (c.estado === 'activa' && c.fecha_fin) ? new Date(c.fecha_fin.replace(' ', 'T')) : null,
                    total: parseInt(c.cupos_totales),
                    occupied: parseInt(c.cupos_ocupados),
                    estado: c.estado
                };
                const card = document.querySelector(`.card[data-id="${c.id}"]`);
                if (card) {
                    const titleEl = card.querySelector('h3');
                    if (titleEl) titleEl.textContent = c.titulo;
                    const dateEl = card.querySelector('.date');
                    if (dateEl) dateEl.textContent = c.rango_fechas_texto;
                    const footerSmall = card.querySelector('.card-footer-info small');
                    if (footerSmall) {
                        footerSmall.innerHTML = `<span class="enrolled">${c.cupos_ocupados}</span> / ${c.cupos_totales} cupos`;
                    }
                    const btn = card.querySelector('.enroll-btn');
                    if (btn) {
                        if (parseInt(c.cupos_ocupados) >= parseInt(c.cupos_totales)) {
                            btn.disabled = true;
                            btn.textContent = 'Cupo Lleno';
                        } else if (c.estado !== 'activa') {
                            btn.disabled = true;
                            btn.textContent = 'Próximamente';
                        } else {
                            btn.disabled = false;
                            btn.textContent = 'Inscribirme';
                        }
                    }
                }
            });
            updateTimers();
        }
    }

    function updateTimers() {
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            const id = card.getAttribute('data-id');
            const timerElement = card.querySelector('.timer');
            const campaignData = campaigns[id];

            if (campaignData && campaignData.end) {
                const nowTime = new Date().getTime();
                const distance = campaignData.end - nowTime;

                if (distance < 0) {
                    timerElement.innerHTML = "Campaña Finalizada";
                    timerElement.style.color = "#718096";
                    const btn = card.querySelector('.enroll-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = "Cerrado";
                        btn.style.background = "#ccc";
                    }
                } else {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    timerElement.innerHTML = `<i class="far fa-clock"></i> Cierra en: ${days}d ${hours}h ${minutes}m ${seconds}s`;
                }
            } else {
                if (timerElement) timerElement.innerHTML = "";
            }
        });
    }

    setInterval(updateTimers, 1000);
    updateTimers();
    syncCampaignsFromBackend();

    const modal = document.getElementById('modal');
    const enrollButtons = document.querySelectorAll('.enroll-btn');
    const closeModalBtn = document.querySelector('.close-modal');
    const enrollForm = document.getElementById('enrollForm');
    const toast = document.getElementById('toast');
    let currentCardId = null;

    enrollButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            if (!btn.disabled) {
                const card = btn.closest('.card');
                currentCardId = card.getAttribute('data-id');
                enrollForm.reset();
                if (window.__currentUser) {
                    document.getElementById('name').value = window.__currentUser.nombre || '';
                    document.getElementById('email').value = window.__currentUser.email || '';
                    if (window.__currentUser.paciente && window.__currentUser.paciente.dni) {
                        document.getElementById('dni').value = window.__currentUser.paciente.dni;
                    }
                }
                const errorDiv = document.querySelector('.modal-error');
                if (errorDiv) errorDiv.textContent = '';
                modal.classList.remove('hidden');
            }
        });
    });

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
    }

    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    if (enrollForm) {
        enrollForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const dni = document.getElementById('dni').value.trim();
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const errorMsg = document.querySelector('.modal-error');

            if (!/^\d{8}$/.test(dni)) {
                errorMsg.textContent = "El DNI debe tener exactamente 8 dígitos numéricos.";
                return;
            }

            const submitBtn = enrollForm.querySelector('button');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = "Procesando...";
            submitBtn.disabled = true;

            const res = await window.ClinicaAPI.request(`api/campanas/${currentCardId}/inscribir`, {
                method: 'POST',
                body: { dni, nombre: name, email }
            });

            submitBtn.textContent = originalText;
            submitBtn.disabled = false;

            if (res.ok) {
                modal.classList.add('hidden');
                syncCampaignsFromBackend();
                showToast(res.data.message || `¡Inscripción exitosa para ${name}!`);
            } else {
                errorMsg.textContent = res.data.message || 'No se pudo completar la inscripción.';
            }
        });
    }

    function showToast(message) {
        if (toast) {
            toast.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        }
    }
});
