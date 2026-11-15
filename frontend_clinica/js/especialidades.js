document.addEventListener("DOMContentLoaded", async () => {
    let especialidadesData = [
        { key: "cardio", nombre: "Cardiología", icon: "fa-heart", descripcion: "Prevención y tratamiento de enfermedades cardiovasculares.", items: ["Chequeos completos", "Electrocardiograma", "Control presión arterial"] },
        { key: "pedia", nombre: "Pediatría", icon: "fa-baby", descripcion: "Atención integral para niños y adolescentes.", items: ["Vacunación", "Control crecimiento", "Orientación a padres"] },
        { key: "odontologia", nombre: "Odontología", icon: "fa-tooth", descripcion: "Salud y estética dental.", items: ["Limpieza dental", "Ortodoncia", "Tratamientos restaurativos"] },
        { key: "psicologia", nombre: "Psicología", icon: "fa-brain", descripcion: "Bienestar emocional y salud mental.", items: ["Terapia individual", "Manejo estrés", "Orientación emocional"] },
        { key: "general", nombre: "Medicina General", icon: "fa-user-doctor", descripcion: "Atención primaria integral.", items: ["Consulta general", "Control preventivo", "Derivación a especialistas"] },
        { key: "dermatologia", nombre: "Dermatología", icon: "fa-hand-sparkles", descripcion: "Cuidado de piel, cabello y uñas.", items: ["Tratamiento acné", "Dermatología estética", "Prevención enfermedades cutáneas"] },
        { key: "oftalmologia", nombre: "Oftalmología", icon: "fa-eye", descripcion: "Salud visual y diagnóstico oftalmológico.", items: ["Exámenes visuales", "Control visión", "Detección temprana"] },
        { key: "neumologia", nombre: "Neumología", icon: "fa-lungs", descripcion: "Tratamiento de enfermedades respiratorias.", items: ["Evaluación pulmonar", "Tratamiento asma y bronquitis", "Seguimiento clínico"] }
    ];

    const grid = document.querySelector(".especialidades .grid");
    const modalOverlay = document.getElementById("modalOverlay");
    const modalClose = document.getElementById("modalClose");
    const modalIcon = document.getElementById("modalIcon");
    const modalTitle = document.getElementById("modalTitle");
    const modalDescription = document.getElementById("modalDescription");
    const modalList = document.getElementById("modalList");

    const res = await window.ClinicaAPI.request("api/especialidades");
    if (res.ok && res.data.especialidades && res.data.especialidades.length > 0) {
        especialidadesData = res.data.especialidades.map(e => ({
            key: e.clave,
            nombre: e.nombre,
            icon: e.icono || "fa-stethoscope",
            descripcion: e.descripcion,
            items: e.items || []
        }));
    }

    function openModal(esp) {
        if (!modalOverlay) return;
        modalIcon.innerHTML = `<i class="fa-solid ${esp.icon}"></i>`;
        modalTitle.textContent = esp.nombre;
        modalDescription.textContent = esp.descripcion;
        modalList.innerHTML = (esp.items || []).map(i => `<li><i class="fa-solid fa-check" style="color:#029d56; margin-right:6px;"></i>${i}</li>`).join("");
        modalOverlay.classList.remove("hidden");
    }

    function closeModal() {
        if (modalOverlay) modalOverlay.classList.add("hidden");
    }

    if (grid) {
        grid.innerHTML = "";
        especialidadesData.forEach(esp => {
            const card = document.createElement("div");
            card.className = "card";
            card.innerHTML = `
                <div class="icon"><i class="fa-solid ${esp.icon}"></i></div>
                <h3>${esp.nombre}</h3>
                <p>${esp.descripcion}</p>
                <button type="button" class="btn-card">Ver Detalles</button>
            `;
            card.addEventListener("click", () => openModal(esp));
            grid.appendChild(card);
        });
    }

    if (modalOverlay) {
        const btnPrimary = modalOverlay.querySelector(".modal-footer .btn-primary");
        const btnSecondary = modalOverlay.querySelector(".modal-footer .btn-secondary");
        if (btnPrimary) btnPrimary.onclick = () => { window.location.href = "agendar_cita.html"; };
        if (btnSecondary) btnSecondary.onclick = () => { window.location.href = "Contacto.html"; };
    }

    if (modalClose) modalClose.addEventListener("click", closeModal);
    if (modalOverlay) {
        modalOverlay.addEventListener("click", (e) => {
            if (e.target === modalOverlay) closeModal();
        });
    }
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") closeModal();
    });
});
