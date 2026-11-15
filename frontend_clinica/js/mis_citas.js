document.addEventListener('DOMContentLoaded', () => {
    const authSection = document.getElementById('patientAuthSection');
    const dashSection = document.getElementById('patientDashboardSection');

    document.querySelectorAll('.portal-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.portal-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.portal-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            const target = document.getElementById(btn.getAttribute('data-tab'));
            if (target) target.classList.add('active');
        });
    });

    const loginForm = document.getElementById('patientLoginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('patEmail').value.trim();
            const password = document.getElementById('patPass').value;
            const res = await window.ClinicaAPI.request('api/auth/login', {
                method: 'POST',
                body: { email, password }
            });
            if (res.ok) {
                window.ClinicaAPI.notify('Sesión iniciada correctamente', 'success');
                window.ClinicaAPI.syncAuth();
                initPatientPortal();
            } else {
                window.ClinicaAPI.notify(res.data.message || 'Credenciales inválidas', 'error');
            }
        });
    }

    const btnGoogle = document.getElementById('btnGooglePatient');
    if (btnGoogle) {
        btnGoogle.addEventListener('click', () => {
            window.ClinicaAPI.openGoogleAuth('paciente', 'mis_citas.html');
        });
    }

    async function initPatientPortal() {
        const me = await window.ClinicaAPI.request('api/auth/me');
        if (!me.ok || !me.data.user) {
            authSection.style.display = 'block';
            dashSection.style.display = 'none';
            return;
        }
        const u = me.data.user;
        if (u.rol === 'medico') {
            window.location.href = 'medico.html';
            return;
        }
        if (u.rol === 'admin') {
            window.location.href = 'admin.html';
            return;
        }

        authSection.style.display = 'none';
        dashSection.style.display = 'block';
        const dni = u.paciente ? u.paciente.dni : '';
        const seguroInfo = (u.paciente && parseInt(u.paciente.tiene_seguro) === 1)
            ? ` · Seguro: ${u.paciente.aseguradora} (${u.paciente.cobertura_eps_pct}% cobertura)`
            : ' · Particular';
        document.getElementById('patientWelcomeSub').textContent =
            `Paciente: ${u.nombre} ${dni ? '(DNI: ' + dni + ')' : ''}${seguroInfo}`;

        loadMisCitas();
        loadMisRecetas();
        loadMisLab();
        loadMisPedidos();
    }

    async function loadMisCitas() {
        const tbody = document.getElementById('misCitasBody');
        const res = await window.ClinicaAPI.request('api/mis-citas');
        if (!res.ok || !res.data.citas || res.data.citas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7">No tiene citas registradas actualmente.</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.citas.map(c => {
            const puedeModificar = c.estado === 'programada' || c.estado === 'reprogramada';
            return `
                <tr>
                    <td><b>${c.codigo_cita}</b></td>
                    <td>${c.especialidad_nombre}</td>
                    <td>${c.medico_nombre}</td>
                    <td>${c.fecha_cita} <b>${String(c.hora_cita).substring(0, 5)}</b></td>
                    <td>${c.turno}</td>
                    <td><span class="status-pill status-${c.estado}">${c.estado.replace('_', ' ')}</span></td>
                    <td>
                        ${puedeModificar ? `
                            <button type="button" class="btn-portal btn-portal-outline btn-reprog" data-id="${c.id}" data-fecha="${c.fecha_cita}" data-hora="${String(c.hora_cita).substring(0, 5)}" data-turno="${c.turno}" style="padding:4px 8px; font-size:0.78rem;">Reprogramar</button>
                            <button type="button" class="btn-portal btn-portal-danger btn-cancel" data-id="${c.id}" style="padding:4px 8px; font-size:0.78rem;">Cancelar</button>
                        ` : `<small style="color:#57606a;">${c.observaciones_atencion || '-'}</small>`}
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-cancel').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('¿Desea cancelar esta cita? (Recuerde que aplica la regla de 24 horas de anticipación)')) return;
                const r = await window.ClinicaAPI.request('api/citas/' + btn.getAttribute('data-id'), { method: 'DELETE' });
                if (r.ok) {
                    window.ClinicaAPI.notify(r.data.message, 'success');
                    loadMisCitas();
                } else {
                    window.ClinicaAPI.notify(r.data.message || 'No se pudo cancelar la cita', 'error');
                }
            });
        });

        tbody.querySelectorAll('.btn-reprog').forEach(btn => {
            btn.addEventListener('click', () => {
                openReprogramarModal(
                    btn.getAttribute('data-id'),
                    btn.getAttribute('data-fecha'),
                    btn.getAttribute('data-hora'),
                    btn.getAttribute('data-turno')
                );
            });
        });
    }

    function openReprogramarModal(id, fechaActual, horaActual, turnoActual) {
        const prev = document.getElementById('reprogModal');
        if (prev) prev.remove();

        const overlay = document.createElement('div');
        overlay.id = 'reprogModal';
        overlay.className = 'google-modal-overlay';
        overlay.innerHTML = `
            <div class="google-modal-card">
                <div class="google-modal-header">
                    <div class="google-brand"><i class="fa-regular fa-calendar" style="color:#0077b6;"></i> <span>Reprogramar Cita</span></div>
                    <button type="button" class="google-modal-close">&times;</button>
                </div>
                <form id="reprogForm" style="margin-top:12px; display:flex; flex-direction:column; gap:10px;">
                    <div>
                        <label style="font-size:0.82rem; font-weight:600;">Nueva Fecha</label>
                        <input type="date" id="repFecha" class="portal-input" value="${fechaActual}" required>
                    </div>
                    <div>
                        <label style="font-size:0.82rem; font-weight:600;">Nueva Hora</label>
                        <input type="time" id="repHora" class="portal-input" value="${horaActual}" required>
                    </div>
                    <div>
                        <label style="font-size:0.82rem; font-weight:600;">Turno</label>
                        <select id="repTurno" class="portal-select">
                            <option value="manana" ${turnoActual === 'manana' ? 'selected' : ''}>Mañana</option>
                            <option value="tarde" ${turnoActual === 'tarde' ? 'selected' : ''}>Tarde</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-portal btn-portal-primary" style="padding:10px;">Guardar Reprogramación</button>
                </form>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('.google-modal-close').onclick = () => overlay.remove();

        overlay.querySelector('#reprogForm').onsubmit = async (e) => {
            e.preventDefault();
            const r = await window.ClinicaAPI.request('api/citas/' + id, {
                method: 'PUT',
                body: {
                    accion: 'reprogramar',
                    fecha_cita: overlay.querySelector('#repFecha').value,
                    hora_cita: overlay.querySelector('#repHora').value,
                    turno: overlay.querySelector('#repTurno').value
                }
            });
            if (r.ok) {
                overlay.remove();
                window.ClinicaAPI.notify(r.data.message, 'success');
                loadMisCitas();
            } else {
                window.ClinicaAPI.notify(r.data.message || 'No se pudo reprogramar', 'error');
            }
        };
    }

    async function loadMisRecetas() {
        const tbody = document.getElementById('misRecetasBody');
        const res = await window.ClinicaAPI.request('api/recetas');
        if (!res.ok || !res.data.recetas || res.data.recetas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No tiene recetas registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.recetas.map(r => {
            const meds = (r.medicamentos || []).map(m => `${m.nombre} (${m.dosis || ''})`).join(', ');
            return `
                <tr>
                    <td><b>${r.codigo_receta}</b></td>
                    <td>${r.medico_nombre}<br><small>${r.especialidad_nombre}</small></td>
                    <td>${r.diagnostico}</td>
                    <td>${meds}<br><small style="color:#57606a;">${r.indicaciones}</small></td>
                    <td>${r.fecha_emision} al ${r.fecha_vencimiento}</td>
                    <td><span class="status-pill status-${r.estado}">${r.estado}</span></td>
                </tr>
            `;
        }).join('');
    }

    async function loadMisLab() {
        const tbody = document.getElementById('misLabBody');
        const res = await window.ClinicaAPI.request('api/ordenes-lab');
        if (!res.ok || !res.data.ordenes || res.data.ordenes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No tiene órdenes de laboratorio registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.ordenes.map(o => {
            const items = (o.analisis || []).map(a => a.nombre).join(', ');
            return `
                <tr>
                    <td><b>${o.codigo_orden}</b></td>
                    <td>${items}</td>
                    <td>S/ ${Number(o.total).toFixed(2)}</td>
                    <td><span class="status-pill status-${o.estado}">${o.estado.replace('_', ' ')}</span></td>
                    <td>${o.resultado_resumen || 'En procesamiento'}</td>
                    <td>
                        <button type="button" class="btn-portal btn-portal-primary btn-ver-lab" data-cod="${o.codigo_orden}" style="padding:4px 8px; font-size:0.78rem;">Ver / Descargar</button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-ver-lab').forEach(btn => {
            btn.addEventListener('click', async () => {
                const cod = btn.getAttribute('data-cod');
                const r = await window.ClinicaAPI.request('api/resultados/' + encodeURIComponent(cod));
                if (r.ok && r.data.resultado) {
                    const ord = r.data.resultado;
                    alert(`INFORME DE LABORATORIO: ${ord.codigo_orden}\nPaciente: ${ord.nombre_paciente} (DNI: ${ord.dni_paciente})\nEstado: ${ord.estado}\n\nResultado: ${ord.resultado_resumen || 'En proceso'}\nObservaciones: ${ord.observaciones_lab || '-'}`);
                } else {
                    window.ClinicaAPI.notify(r.data.message || 'No disponible', 'error');
                }
            });
        });
    }

    async function loadMisPedidos() {
        const tbody = document.getElementById('misPedidosBody');
        const res = await window.ClinicaAPI.request('api/pedidos');
        if (!res.ok || !res.data.pedidos || res.data.pedidos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7">No registra compras en farmacia.</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.pedidos.map(p => {
            const prods = (p.items || []).map(i => `${i.nombre_producto} x${i.cantidad}`).join(', ');
            return `
                <tr>
                    <td><b>${p.codigo_boleta}</b></td>
                    <td>${p.created_at}</td>
                    <td>${prods}</td>
                    <td>S/ ${Number(p.subtotal).toFixed(2)}</td>
                    <td style="color:#029d56;">- S/ ${Number(p.descuento_eps).toFixed(2)}</td>
                    <td><b>S/ ${Number(p.total_pagado).toFixed(2)}</b></td>
                    <td>${p.metodo_pago.toUpperCase()}</td>
                </tr>
            `;
        }).join('');
    }

    initPatientPortal();
});
