document.addEventListener('DOMContentLoaded', () => {
    function bindAccordion() {
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            if (question && !question.__bound) {
                question.__bound = true;
                question.addEventListener('click', () => {
                    faqItems.forEach(other => {
                        if (other !== item) other.classList.remove('active');
                    });
                    item.classList.toggle('active');
                });
            }
        });
    }

    bindAccordion();

    window.ClinicaAPI.request('api/preguntas').then(res => {
        if (res.ok && res.data.preguntas) {
            const list = document.querySelector('.faq-list');
            res.data.preguntas.filter(p => p.estado === 'respondida' && p.respuesta).forEach(p => {
                const div = document.createElement('div');
                div.className = 'faq-item';
                div.innerHTML = `
                    <div class="faq-question">
                        <div class="q-title">
                            <i class="fas fa-circle-question question-icon"></i>
                            <h3>${p.pregunta}</h3>
                        </div>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="faq-answer">
                        <p>${p.respuesta}</p>
                    </div>
                `;
                list.appendChild(div);
            });
            bindAccordion();
        }
    });

    const modal = document.getElementById('questionModal');
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');
    const form = document.getElementById('questionForm');
    const toast = document.getElementById('toast');

    function openModal() { if (modal) modal.classList.remove('hidden'); }
    function closeModal() {
        if (modal) {
            modal.classList.add('hidden');
            form.reset();
        }
    }

    if (openModalBtn) openModalBtn.addEventListener('click', openModal);
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeModal();
        });
    }

    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const questionInput = document.getElementById('question');

    function showToast(message) {
        if (toast) {
            toast.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        } else {
            window.ClinicaAPI.notify(message, 'success');
        }
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    if (form) {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const nombre = nameInput.value.trim();
            const email = emailInput.value.trim();
            const pregunta = questionInput.value.trim();

            if (nombre.length < 3) {
                window.ClinicaAPI.notify('Ingrese un nombre válido (mínimo 3 letras)', 'error');
                return;
            }
            if (!validateEmail(email)) {
                window.ClinicaAPI.notify('Correo electrónico inválido', 'error');
                return;
            }
            if (pregunta.length < 10) {
                window.ClinicaAPI.notify('La pregunta debe tener al menos 10 caracteres', 'error');
                return;
            }

            const res = await window.ClinicaAPI.request('api/preguntas', {
                method: 'POST',
                body: { nombre, email, pregunta }
            });

            if (res.ok) {
                showToast('Tu pregunta fue enviada y registrada correctamente');
                closeModal();
            } else {
                window.ClinicaAPI.notify(res.data.message || 'Error al enviar pregunta', 'error');
            }
        });
    }
});
