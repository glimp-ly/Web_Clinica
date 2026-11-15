// Panel de Administración (RF-B01 - RF-B07)
document.addEventListener('DOMContentLoaded', () => {
    let cachedMedicos = [];
    let cachedCampanas = [];
    let cachedProductos = [];
    let cachedOrdenesLab = [];
    let cachedEspecialidades = [];
    let cachedReportes = null;

    const gateWrapper = document.getElementById('adminGateWrapper');
    const adminApp = document.getElementById('adminApp');
    const loginForm = document.getElementById('adminLoginForm');
    const emailInput = document.getElementById('admEmail');
    const passInput = document.getElementById('admPass');
    const forbiddenAlert = document.getElementById('adminForbiddenAlert');
    const btnGoogle = document.getElementById('btnGoogleAdmin');
    const btnLogout = document.getElementById('btnAdminLogout');
    const headerChip = document.getElementById('adminHeaderChip');
    const nameDisplay = document.getElementById('adminNameDisplay');

    const sidebarBtns = document.querySelectorAll('.portal-sidebar-btn');
    const panels = document.querySelectorAll('.portal-panel');

    async function apiCall(endpoint, method = 'GET', body = null) {
        const res = await window.ClinicaAPI.request(endpoint, { method, body });
        if (!res.ok) {
            const msg = (res.data && res.data.message) ? res.data.message : 'Error en la operación administrativa';
            throw new Error(msg);
        }
        return res.data || res;
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[m]);
    }

    function formatFecha(dtStr) {
        if (!dtStr) return '-';
        try {
            return new Date(String(dtStr).replace(' ', 'T')).toLocaleString('es-PE', {
                year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
            });
        } catch (e) {
            return dtStr;
        }
    }

    sidebarBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            sidebarBtns.forEach(b => b.classList.remove('active'));
            panels.forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            const target = document.getElementById(targetId);
            if (target) target.classList.add('active');
        });
    });

    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.getAttribute('data-close'));
            if (modal) modal.style.display = 'none';
        });
    });

    document.querySelectorAll('.btn-demo-adm').forEach(btn => {
        btn.addEventListener('click', () => {
            emailInput.value = btn.getAttribute('data-email');
            passInput.value = 'Admin123!';
        });
    });

    checkAdminSession();

    async function checkAdminSession() {
        try {
            const user = await window.ClinicaAPI.getMe();
            if (user && user.rol === 'admin') {
                showAdminWorkspace(user);
            } else if (user) {
                showAuthGate();
                const link = user.rol === 'medico' ? 'medico.html' : 'mis_citas.html';
                forbiddenAlert.innerHTML = `Acceso denegado: Su sesión actual tiene rol <strong>${escapeHtml(user.rol)}</strong>. <a href="${link}" style="color:#033076; text-decoration:underline; margin-left:6px;">Ir a mi portal</a>`;
                forbiddenAlert.style.display = 'block';
            } else {
                showAuthGate();
            }
        } catch (e) {
            showAuthGate();
        }
    }

    function showAuthGate() {
        gateWrapper.style.display = 'block';
        adminApp.style.display = 'none';
        headerChip.style.display = 'none';
        btnLogout.style.display = 'none';
    }

    function showAdminWorkspace(user) {
        gateWrapper.style.display = 'none';
        adminApp.style.display = 'grid';
        headerChip.style.display = 'flex';
        btnLogout.style.display = 'inline-flex';
        nameDisplay.textContent = user.nombre;
        loadAllAdminData();
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            forbiddenAlert.style.display = 'none';
            try {
                const res = await window.ClinicaAPI.login(emailInput.value.trim(), passInput.value);
                if (res.user && res.user.rol === 'admin') {
                    window.ClinicaAPI.toast('Sesión administrativa iniciada', 'success');
                    showAdminWorkspace(res.user);
                } else {
                    await window.ClinicaAPI.logout();
                    forbiddenAlert.textContent = 'Acceso denegado: La cuenta no pertenece al rol Administrador.';
                    forbiddenAlert.style.display = 'block';
                }
            } catch (err) {
                forbiddenAlert.textContent = err.message || 'Credenciales incorrectas.';
                forbiddenAlert.style.display = 'block';
            }
        });
    }

    if (btnGoogle) {
        btnGoogle.addEventListener('click', () => {
            window.ClinicaAPI.openGoogleModal('admin', (user) => {
                if (user && user.rol === 'admin') {
                    showAdminWorkspace(user);
                } else {
                    window.ClinicaAPI.toast('La cuenta Google seleccionada no es de Administrador', 'error');
                }
            });
        });
    }

    if (btnLogout) {
        btnLogout.addEventListener('click', async () => {
            await window.ClinicaAPI.logout();
            window.ClinicaAPI.toast('Sesión finalizada', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 300);
        });
    }

    async function loadAllAdminData() {
        await loadEspecialidades();
        await Promise.all([
            loadReportes(),
            loadMedicos(),
            loadCampanas(),
            loadFarmacia(),
            loadLaboratorio(),
            loadFaqYContactos()
        ]);
    }

    async function loadEspecialidades() {
        try {
            const data = await apiCall('especialidades');
            cachedEspecialidades = data.especialidades || [];
            const sel = document.getElementById('medEspecialidad');
            if (sel && cachedEspecialidades.length) {
                sel.innerHTML = cachedEspecialidades.map(e =>
                    `<option value="${e.id}">${escapeHtml(e.nombre)}</option>`
                ).join('');
            }
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    }

    async function loadReportes() {
        try {
            const data = await apiCall('reportes/resumen');
            cachedReportes = data;

            const cr = data.citas_resumen || {};
            document.getElementById('statTotalCitas').textContent = cr.total || 0;
            document.getElementById('statCitasDesglose').textContent =
                `Prog: ${cr.programadas || 0} | Aten: ${cr.en_atencion || 0} | Canc: ${cr.canceladas || 0}`;
            document.getElementById('statFinalizadas').textContent = cr.finalizadas || 0;
            document.getElementById('statNoShows').textContent = `No-Show: ${cr.no_shows || 0}`;

            const vf = data.ventas_farmacia || {};
            document.getElementById('statVentasFarmacia').textContent = `S/ ${parseFloat(vf.ingresos_pagados || 0).toFixed(2)}`;
            document.getElementById('statDescuentoEps').textContent = `Subsidio EPS: S/ ${parseFloat(vf.cubierto_eps || 0).toFixed(2)}`;

            const vl = data.ventas_laboratorio || {};
            document.getElementById('statIngresosTotales').textContent = `S/ ${parseFloat(data.ingresos_totales || 0).toFixed(2)}`;
            document.getElementById('statVentasLab').textContent = `Lab: S/ ${parseFloat(vl.ingresos_lab || 0).toFixed(2)} (${vl.ordenes || 0} órdenes)`;

            renderEspecialidadesResumen(data.citas_por_especialidad || []);
            renderUsuariosSistema(data.usuarios || []);
            renderAuditoriaLogs(data.auditoria || []);
            renderNotificaciones(data.notificaciones || []);
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    }

    function renderEspecialidadesResumen(list) {
        const tbody = document.getElementById('tablaEspecialidadesResumen');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="portal-empty-msg">Sin datos registrados.</td></tr>';
            return;
        }
        const maxVal = Math.max(...list.map(i => parseInt(i.total_citas, 10) || 0), 1);
        tbody.innerHTML = list.map(i => {
            const c = parseInt(i.total_citas, 10) || 0;
            const pct = Math.round((c / maxVal) * 100);
            return `
                <tr>
                    <td><strong>${escapeHtml(i.especialidad)}</strong></td>
                    <td><span class="status-pill status-programada">${c}</span></td>
                    <td style="width:45%;">
                        <div style="background:#e1e4e8; border-radius:4px; height:8px; overflow:hidden;">
                            <div style="background:#0077b6; height:100%; width:${pct}%;"></div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderUsuariosSistema(usuarios) {
        const tbody = document.getElementById('tablaUsuariosSistema');
        if (!usuarios.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="portal-empty-msg">No hay usuarios registrados.</td></tr>';
            return;
        }
        tbody.innerHTML = usuarios.map(u => {
            const rolePill = u.rol === 'admin' ? 'status-cancelada' : (u.rol === 'medico' ? 'status-en_atencion' : 'status-programada');
            const authType = u.google_id ? '<i class="fa-brands fa-google" style="color:#0077b6;"></i> Google' : 'Credenciales';
            const statePill = parseInt(u.activo, 10) === 1
                ? '<span class="status-pill status-finalizada">Activo</span>'
                : '<span class="status-pill status-cancelada">Inactivo</span>';
            return `
                <tr>
                    <td>#${u.id}</td>
                    <td><strong>${escapeHtml(u.nombre)}</strong></td>
                    <td>${escapeHtml(u.email)}</td>
                    <td><span class="status-pill ${rolePill}">${escapeHtml(u.rol).toUpperCase()}</span></td>
                    <td>${authType}</td>
                    <td>${statePill}</td>
                    <td><small>${formatFecha(u.created_at)}</small></td>
                </tr>
            `;
        }).join('');
    }

    const btnCsv = document.getElementById('btnExportarCsv');
    if (btnCsv) {
        btnCsv.addEventListener('click', () => {
            if (!cachedReportes) return;
            const cr = cachedReportes.citas_resumen || {};
            const vf = cachedReportes.ventas_farmacia || {};
            const vl = cachedReportes.ventas_laboratorio || {};
            let csv = 'REPORTE ADMINISTRATIVO - CLINICA CENTENARIO DEL NORTE\n';
            csv += `Fecha,${new Date().toLocaleString('es-PE')}\n`;
            csv += `Citas Totales,${cr.total || 0}\n`;
            csv += `Citas Finalizadas,${cr.finalizadas || 0}\n`;
            csv += `No-Shows,${cr.no_shows || 0}\n`;
            csv += `Ingresos Farmacia,${vf.ingresos_pagados || 0}\n`;
            csv += `Ingresos Laboratorio,${vl.ingresos_lab || 0}\n`;
            csv += `Ingresos Totales,${cachedReportes.ingresos_totales || 0}\n\nEspecialidad,Total Citas\n`;
            (cachedReportes.citas_por_especialidad || []).forEach(e => {
                csv += `"${e.especialidad}",${e.total_citas}\n`;
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte_clinica_${new Date().toISOString().slice(0, 10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
            window.ClinicaAPI.toast('Reporte CSV generado', 'success');
        });
    }

    // ---- 2. MÉDICOS ----
    async function loadMedicos() {
        try {
            const data = await apiCall('medicos?all=1');
            cachedMedicos = data.medicos || [];
            renderMedicos(cachedMedicos);
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    }

    function renderMedicos(list) {
        const tbody = document.getElementById('tablaMedicosAdmin');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="portal-empty-msg">No se encontraron médicos.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(m => {
            const isActive = parseInt(m.activo, 10) === 1;
            const turnos = `${m.turno_manana ? 'Mañana ' : ''}${m.turno_tarde ? 'Tarde' : ''}`.trim() || 'Sin turno';
            return `
                <tr>
                    <td><strong>${escapeHtml(m.cmp)}</strong><br><small>DNI: ${escapeHtml(m.dni)}</small></td>
                    <td><strong>${escapeHtml(m.nombre)}</strong><br><small style="color:#57606a;">${escapeHtml(m.email || 'Sin cuenta web')}</small></td>
                    <td>${escapeHtml(m.especialidad_nombre)}<br><small>${m.experiencia_anios || 0} años exp.</small></td>
                    <td><small>${escapeHtml(turnos)}<br>${escapeHtml(m.dias_atencion || '')}</small></td>
                    <td>${isActive ? '<span class="status-pill status-finalizada">Activo</span>' : '<span class="status-pill status-cancelada">Inactivo</span>'}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-portal btn-portal-outline btn-edit-med" data-id="${m.id}" style="padding:4px 8px; font-size:0.78rem;">Editar</button>
                            <button type="button" class="btn-portal ${isActive ? 'btn-portal-danger' : 'btn-portal-success'} btn-toggle-med" data-id="${m.id}" data-active="${m.activo}" style="padding:4px 8px; font-size:0.78rem;">
                                ${isActive ? 'Desactivar' : 'Activar'}
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-edit-med').forEach(btn => {
            btn.addEventListener('click', () => openMedicoModal(parseInt(btn.getAttribute('data-id'), 10)));
        });

        tbody.querySelectorAll('.btn-toggle-med').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const nextActive = parseInt(btn.getAttribute('data-active'), 10) === 1 ? 0 : 1;
                try {
                    await apiCall(`medicos/${id}`, 'PUT', { activo: nextActive });
                    window.ClinicaAPI.toast('Estado del médico actualizado', 'success');
                    loadMedicos();
                } catch (err) {
                    window.ClinicaAPI.toast(err.message, 'error');
                }
            });
        });
    }

    document.getElementById('filterMedicos')?.addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        renderMedicos(cachedMedicos.filter(m =>
            `${m.nombre} ${m.cmp} ${m.dni} ${m.especialidad_nombre}`.toLowerCase().includes(q)
        ));
    });

    function openMedicoModal(medId = null) {
        const form = document.getElementById('formMedico');
        form.reset();
        const grupoDni = document.getElementById('grupoMedDni');
        const grupoEmail = document.getElementById('grupoMedEmail');
        const grupoPass = document.getElementById('grupoMedPass');

        if (medId) {
            const m = cachedMedicos.find(x => parseInt(x.id, 10) === medId);
            if (!m) return;
            document.getElementById('modalMedicoTitulo').innerHTML = '<i class="fa-solid fa-pen"></i> Editar Médico y Horario';
            document.getElementById('medId').value = m.id;
            document.getElementById('medNombre').value = m.nombre;
            document.getElementById('medCmp').value = m.cmp;
            document.getElementById('medDni').value = m.dni;
            document.getElementById('medDni').required = false;
            document.getElementById('medEspecialidad').value = m.especialidad_id;
            document.getElementById('medExp').value = m.experiencia_anios || 5;
            document.getElementById('medTelefono').value = m.telefono || '999888777';
            document.getElementById('medDias').value = m.dias_atencion || 'Lunes,Martes,Miércoles,Jueves,Viernes,Sábado';
            document.getElementById('medTurnoM').checked = parseInt(m.turno_manana, 10) === 1;
            document.getElementById('medTurnoT').checked = parseInt(m.turno_tarde, 10) === 1;
            document.getElementById('medDesc').value = m.descripcion || '';
            grupoDni.style.display = 'none';
            grupoEmail.style.display = 'none';
            grupoPass.style.display = 'none';
        } else {
            document.getElementById('modalMedicoTitulo').innerHTML = '<i class="fa-solid fa-user-plus"></i> Registrar Médico';
            document.getElementById('medId').value = '';
            document.getElementById('medDni').required = true;
            document.getElementById('medPassword').value = 'Medico123!';
            grupoDni.style.display = 'block';
            grupoEmail.style.display = 'block';
            grupoPass.style.display = 'block';
        }
        document.getElementById('modalMedico').style.display = 'flex';
    }

    document.getElementById('btnNuevoMedicoModal')?.addEventListener('click', () => openMedicoModal(null));

    document.getElementById('formMedico')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('medId').value;
        const payload = {
            nombre: document.getElementById('medNombre').value.trim(),
            cmp: document.getElementById('medCmp').value.trim(),
            especialidad_id: parseInt(document.getElementById('medEspecialidad').value, 10),
            experiencia_anios: parseInt(document.getElementById('medExp').value, 10) || 1,
            telefono: document.getElementById('medTelefono').value.trim(),
            dias_atencion: document.getElementById('medDias').value.trim(),
            turno_manana: document.getElementById('medTurnoM').checked ? 1 : 0,
            turno_tarde: document.getElementById('medTurnoT').checked ? 1 : 0,
            descripcion: document.getElementById('medDesc').value.trim()
        };
        try {
            if (id) {
                await apiCall(`medicos/${id}`, 'PUT', payload);
                window.ClinicaAPI.toast('Médico actualizado correctamente', 'success');
            } else {
                payload.dni = document.getElementById('medDni').value.trim();
                payload.email = document.getElementById('medEmail').value.trim();
                payload.password = document.getElementById('medPassword').value;
                await apiCall('medicos', 'POST', payload);
                window.ClinicaAPI.toast('Médico registrado con éxito', 'success');
            }
            document.getElementById('modalMedico').style.display = 'none';
            loadMedicos();
            loadReportes();
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    });

    // ---- 3. CAMPAÑAS ----
    async function loadCampanas() {
        try {
            const data = await apiCall('campanas');
            cachedCampanas = data.campanas || [];
            renderCampanas(cachedCampanas);
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    }

    function renderCampanas(list) {
        const tbody = document.getElementById('tablaCampanasAdmin');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="portal-empty-msg">No hay campañas registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(c => {
            const ocup = parseInt(c.cupos_ocupados, 10) || 0;
            const tot = parseInt(c.cupos_totales, 10) || 1;
            const disp = Math.max(0, tot - ocup);
            const pct = Math.min(100, Math.round((ocup / tot) * 100));
            const statusClass = c.estado === 'activa' ? 'status-finalizada' : (c.estado === 'pronto' ? 'status-programada' : 'status-cancelada');
            const nextStatus = c.estado === 'activa' ? 'finalizada' : 'activa';
            return `
                <tr>
                    <td><strong>${escapeHtml(c.titulo)}</strong><br><small style="color:#57606a;">${escapeHtml(c.descripcion || '').slice(0, 75)}</small></td>
                    <td><small>${escapeHtml(c.rango_fechas_texto || '-')}</small></td>
                    <td>
                        <strong>${ocup}/${tot}</strong> (${disp} disp.)
                        <div style="background:#e1e4e8; height:6px; border-radius:3px; margin-top:4px; overflow:hidden;">
                            <div style="background:#029d56; height:100%; width:${pct}%;"></div>
                        </div>
                    </td>
                    <td><span class="status-pill ${statusClass}">${escapeHtml(c.estado).toUpperCase()}</span></td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-portal btn-portal-outline btn-edit-camp" data-id="${c.id}" style="padding:4px 8px; font-size:0.78rem;">Editar</button>
                            <button type="button" class="btn-portal btn-portal-outline btn-toggle-camp" data-id="${c.id}" data-next="${nextStatus}" style="padding:4px 8px; font-size:0.78rem;">
                                ${c.estado === 'activa' ? 'Finalizar' : 'Activar'}
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-edit-camp').forEach(btn => {
            btn.addEventListener('click', () => openCampanaModal(parseInt(btn.getAttribute('data-id'), 10)));
        });

        tbody.querySelectorAll('.btn-toggle-camp').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    await apiCall(`campanas/${btn.getAttribute('data-id')}`, 'PUT', { estado: btn.getAttribute('data-next') });
                    window.ClinicaAPI.toast('Estado de campaña actualizado', 'success');
                    loadCampanas();
                } catch (err) {
                    window.ClinicaAPI.toast(err.message, 'error');
                }
            });
        });
    }

    function openCampanaModal(campId = null) {
        const form = document.getElementById('formCampana');
        form.reset();
        const today = new Date().toISOString().slice(0, 10);
        if (campId) {
            const c = cachedCampanas.find(x => parseInt(x.id, 10) === campId);
            if (!c) return;
            document.getElementById('modalCampanaTitulo').innerHTML = '<i class="fa-solid fa-pen"></i> Editar Campaña';
            document.getElementById('campId').value = c.id;
            document.getElementById('campTitulo').value = c.titulo;
            document.getElementById('campDescripcion').value = c.descripcion || '';
            document.getElementById('campFechaInicio').value = (c.fecha_inicio || today).slice(0, 10);
            document.getElementById('campFechaFin').value = (c.fecha_fin || today).slice(0, 10);
            document.getElementById('campCupo').value = c.cupos_totales || 100;
            document.getElementById('campEstado').value = c.estado || 'activa';
            document.getElementById('campRangoTexto').value = c.rango_fechas_texto || '';
        } else {
            document.getElementById('modalCampanaTitulo').innerHTML = '<i class="fa-solid fa-bullhorn"></i> Nueva Campaña';
            document.getElementById('campId').value = '';
            document.getElementById('campFechaInicio').value = today;
            document.getElementById('campFechaFin').value = today;
            document.getElementById('campCupo').value = 150;
            document.getElementById('campEstado').value = 'activa';
        }
        document.getElementById('modalCampana').style.display = 'flex';
    }

    document.getElementById('btnNuevaCampanaModal')?.addEventListener('click', () => openCampanaModal(null));

    document.getElementById('formCampana')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('campId').value;
        const fi = document.getElementById('campFechaInicio').value;
        const ff = document.getElementById('campFechaFin').value;
        const payload = {
            titulo: document.getElementById('campTitulo').value.trim(),
            descripcion: document.getElementById('campDescripcion').value.trim(),
            fecha_inicio: fi ? `${fi} 08:00:00` : null,
            fecha_fin: ff ? `${ff} 18:00:00` : null,
            cupos_totales: parseInt(document.getElementById('campCupo').value, 10) || 100,
            estado: document.getElementById('campEstado').value,
            rango_fechas_texto: document.getElementById('campRangoTexto').value.trim()
        };
        try {
            if (id) {
                await apiCall(`campanas/${id}`, 'PUT', payload);
                window.ClinicaAPI.toast('Campaña actualizada', 'success');
            } else {
                await apiCall('campanas', 'POST', payload);
                window.ClinicaAPI.toast('Campaña creada y publicada', 'success');
            }
            document.getElementById('modalCampana').style.display = 'none';
            loadCampanas();
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    });

    // ---- 4. FARMACIA ----
    async function loadFarmacia() {
        try {
            const data = await apiCall('productos?all=1');
            cachedProductos = data.productos || [];
            renderProductos(cachedProductos);
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    }

    function renderProductos(list) {
        const tbody = document.getElementById('tablaFarmaciaAdmin');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="portal-empty-msg">No hay productos registrados.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(p => {
            const isAct = parseInt(p.activo, 10) === 1;
            const req = parseInt(p.requiere_receta, 10) === 1;
            return `
                <tr>
                    <td><strong>${escapeHtml(p.nombre)}</strong><br><small>ID #${p.id}</small></td>
                    <td>${escapeHtml(p.categoria)}</td>
                    <td><input type="number" step="0.01" min="0.50" class="portal-input inp-prod-precio" data-id="${p.id}" value="${parseFloat(p.precio).toFixed(2)}" style="width:92px;"></td>
                    <td><input type="number" min="0" class="portal-input inp-prod-stock" data-id="${p.id}" value="${parseInt(p.stock, 10)}" style="width:82px;"></td>
                    <td>${req ? '<span class="status-pill status-reprogramada">Sí</span>' : '<span class="status-pill status-finalizada">Libre</span>'}</td>
                    <td>${isAct ? '<span class="status-pill status-finalizada">Activo</span>' : '<span class="status-pill status-cancelada">Oculto</span>'}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-portal btn-portal-primary btn-save-prod" data-id="${p.id}" style="padding:4px 8px; font-size:0.78rem;">Guardar</button>
                            <button type="button" class="btn-portal ${isAct ? 'btn-portal-danger' : 'btn-portal-outline'} btn-toggle-prod" data-id="${p.id}" data-active="${p.activo}" style="padding:4px 8px; font-size:0.78rem;">
                                ${isAct ? 'Ocultar' : 'Activar'}
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-save-prod').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const precio = parseFloat(tbody.querySelector(`.inp-prod-precio[data-id="${id}"]`).value);
                const stock = parseInt(tbody.querySelector(`.inp-prod-stock[data-id="${id}"]`).value, 10);
                try {
                    await apiCall(`productos/${id}`, 'PUT', { precio, stock });
                    window.ClinicaAPI.toast('Precio y stock actualizados', 'success');
                    loadFarmacia();
                } catch (err) {
                    window.ClinicaAPI.toast(err.message, 'error');
                }
            });
        });

        tbody.querySelectorAll('.btn-toggle-prod').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const next = parseInt(btn.getAttribute('data-active'), 10) === 1 ? 0 : 1;
                try {
                    await apiCall(`productos/${id}`, 'PUT', { activo: next });
                    window.ClinicaAPI.toast('Visibilidad del producto actualizada', 'success');
                    loadFarmacia();
                } catch (err) {
                    window.ClinicaAPI.toast(err.message, 'error');
                }
            });
        });
    }

    document.getElementById('filterProductos')?.addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        renderProductos(cachedProductos.filter(p => `${p.nombre} ${p.categoria}`.toLowerCase().includes(q)));
    });

    document.getElementById('btnNuevoProductoModal')?.addEventListener('click', () => {
        document.getElementById('formNuevoProducto').reset();
        document.getElementById('modalNuevoProducto').style.display = 'flex';
    });

    document.getElementById('formNuevoProducto')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await apiCall('productos', 'POST', {
                nombre: document.getElementById('prodNombre').value.trim(),
                categoria: document.getElementById('prodCategoria').value,
                precio: parseFloat(document.getElementById('prodPrecio').value),
                stock: parseInt(document.getElementById('prodStock').value, 10),
                imagen: document.getElementById('prodImagen').value.trim() || 'img/farmacia.png',
                requiere_receta: document.getElementById('prodReceta').checked ? 1 : 0
            });
            window.ClinicaAPI.toast('Producto agregado al inventario', 'success');
            document.getElementById('modalNuevoProducto').style.display = 'none';
            loadFarmacia();
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    });

    // ---- 5. LABORATORIO ----
    async function loadLaboratorio() {
        try {
            const data = await apiCall('ordenes-lab');
            cachedOrdenesLab = data.ordenes || [];
            renderOrdenesLab();
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    }

    function renderOrdenesLab() {
        const filtro = document.getElementById('filterLabEstado')?.value || 'todos';
        const list = filtro === 'todos' ? cachedOrdenesLab : cachedOrdenesLab.filter(o => o.estado === filtro);
        const tbody = document.getElementById('tablaLabAdmin');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="portal-empty-msg">No hay órdenes de laboratorio en este estado.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(o => {
            const analisisTxt = Array.isArray(o.analisis)
                ? o.analisis.map(a => a.nombre || a.codigo || a).join(', ')
                : '-';
            const pill = o.estado === 'publicado' ? 'status-finalizada' : (o.estado === 'en_proceso' ? 'status-en_atencion' : 'status-programada');
            return `
                <tr>
                    <td><strong>${escapeHtml(o.codigo_orden)}</strong><br><small>${formatFecha(o.created_at)}</small></td>
                    <td>${escapeHtml(o.nombre_paciente || '-')}<br><small>DNI: ${escapeHtml(o.dni_paciente)}</small></td>
                    <td><small>${escapeHtml(analisisTxt)}</small></td>
                    <td><strong>S/ ${parseFloat(o.total || 0).toFixed(2)}</strong></td>
                    <td><span class="status-pill ${pill}">${escapeHtml(o.estado)}</span></td>
                    <td><small>${escapeHtml((o.resultado_resumen || 'Pendiente').slice(0, 70))}</small></td>
                    <td>
                        <button type="button" class="btn-portal btn-portal-primary btn-edit-lab" data-id="${o.id}" style="padding:4px 8px; font-size:0.78rem;">
                            Emitir Resultado
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-edit-lab').forEach(btn => {
            btn.addEventListener('click', () => {
                const ord = cachedOrdenesLab.find(x => parseInt(x.id, 10) === parseInt(btn.getAttribute('data-id'), 10));
                if (!ord) return;
                document.getElementById('labOrdenId').value = ord.id;
                document.getElementById('labCodigoTxt').textContent = `${ord.codigo_orden} — ${ord.nombre_paciente} (DNI ${ord.dni_paciente})`;
                document.getElementById('labEstado').value = ord.estado === 'pendiente_muestra' ? 'publicado' : ord.estado;
                document.getElementById('labResultadoResumen').value = ord.resultado_resumen || '';
                document.getElementById('labObservaciones').value = ord.observaciones_lab || 'Validado por Laboratorio Clínico Centenario del Norte.';
                document.getElementById('modalPublicarLab').style.display = 'flex';
            });
        });
    }

    document.getElementById('filterLabEstado')?.addEventListener('change', renderOrdenesLab);

    document.getElementById('formPublicarLab')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('labOrdenId').value;
        const resumen = document.getElementById('labResultadoResumen').value.trim();
        try {
            await apiCall(`ordenes-lab/${id}`, 'PUT', {
                estado: document.getElementById('labEstado').value,
                resultado_resumen: resumen,
                observaciones_lab: document.getElementById('labObservaciones').value.trim()
            });
            window.ClinicaAPI.toast('Resultado de laboratorio actualizado', 'success');
            document.getElementById('modalPublicarLab').style.display = 'none';
            loadLaboratorio();
            loadReportes();
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    });

    // ---- 6. FAQ Y CONTACTOS ----
    async function loadFaqYContactos() {
        try {
            const [faqData, contData] = await Promise.all([
                apiCall('preguntas'),
                apiCall('contactos')
            ]);
            renderFaqAdmin(faqData.preguntas || []);
            renderContactosAdmin(contData.contactos || []);
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    }

    function renderFaqAdmin(list) {
        const tbody = document.getElementById('tablaFaqAdmin');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="portal-empty-msg">No hay preguntas registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(p => {
            const pill = p.estado === 'respondida' ? 'status-finalizada' : 'status-reprogramada';
            return `
                <tr>
                    <td><strong>${escapeHtml(p.nombre)}</strong><br><small>${escapeHtml(p.email)}</small></td>
                    <td>${escapeHtml(p.pregunta)}</td>
                    <td><small>${escapeHtml(p.respuesta || 'Sin respuesta aún')}</small></td>
                    <td><span class="status-pill ${pill}">${escapeHtml(p.estado)}</span></td>
                    <td>
                        <button type="button" class="btn-portal btn-portal-outline btn-reply-faq"
                            data-id="${p.id}"
                            data-preg="${escapeHtml(p.pregunta)}"
                            data-resp="${escapeHtml(p.respuesta || '')}"
                            style="padding:4px 8px; font-size:0.78rem;">Responder</button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-reply-faq').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('faqId').value = btn.getAttribute('data-id');
                document.getElementById('faqPreguntaTexto').textContent = btn.getAttribute('data-preg');
                document.getElementById('faqRespuesta').value = btn.getAttribute('data-resp');
                document.getElementById('modalResponderFaq').style.display = 'flex';
            });
        });
    }

    document.getElementById('formResponderFaq')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('faqId').value;
        try {
            await apiCall(`preguntas/${id}`, 'PUT', {
                respuesta: document.getElementById('faqRespuesta').value.trim()
            });
            window.ClinicaAPI.toast('Respuesta FAQ publicada', 'success');
            document.getElementById('modalResponderFaq').style.display = 'none';
            loadFaqYContactos();
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    });

    function renderContactosAdmin(list) {
        const tbody = document.getElementById('tablaContactosAdmin');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="portal-empty-msg">No hay solicitudes registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(c => {
            const pill = c.estado === 'AFILIADO' ? 'status-finalizada' : (c.estado === 'DESCARTADO' ? 'status-cancelada' : 'status-programada');
            return `
                <tr>
                    <td><strong>${escapeHtml(c.dni)}</strong></td>
                    <td>${escapeHtml(c.celular)}<br><small>${escapeHtml(c.correo)}</small></td>
                    <td><small>${escapeHtml(c.notas_admin || '-')}</small></td>
                    <td><span class="status-pill ${pill}">${escapeHtml(c.estado)}</span></td>
                    <td><small>${formatFecha(c.created_at)}</small></td>
                    <td>
                        <button type="button" class="btn-portal btn-portal-outline btn-edit-contacto"
                            data-id="${c.id}"
                            data-dni="${escapeHtml(c.dni)}"
                            data-cel="${escapeHtml(c.celular)}"
                            data-estado="${escapeHtml(c.estado)}"
                            data-notas="${escapeHtml(c.notas_admin || '')}"
                            style="padding:4px 8px; font-size:0.78rem;">Gestionar</button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-edit-contacto').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('contactoId').value = btn.getAttribute('data-id');
                document.getElementById('contactoInfoTxt').textContent = `DNI ${btn.getAttribute('data-dni')} · Celular ${btn.getAttribute('data-cel')}`;
                document.getElementById('contactoEstado').value = btn.getAttribute('data-estado');
                document.getElementById('contactoNotas').value = btn.getAttribute('data-notas');
                document.getElementById('modalGestionContacto').style.display = 'flex';
            });
        });
    }

    document.getElementById('formGestionContacto')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('contactoId').value;
        try {
            await apiCall(`contactos/${id}`, 'PUT', {
                estado: document.getElementById('contactoEstado').value,
                notas_admin: document.getElementById('contactoNotas').value.trim()
            });
            window.ClinicaAPI.toast('Solicitud de afiliación actualizada', 'success');
            document.getElementById('modalGestionContacto').style.display = 'none';
            loadFaqYContactos();
        } catch (err) {
            window.ClinicaAPI.toast(err.message, 'error');
        }
    });

    // ---- 7. AUDITORÍA Y NOTIFICACIONES ----
    document.getElementById('btnRefrescarAuditoria')?.addEventListener('click', loadReportes);

    function renderAuditoriaLogs(logs) {
        const tbody = document.getElementById('tablaAuditoriaAdmin');
        if (!logs.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="portal-empty-msg">Sin registros de auditoría.</td></tr>';
            return;
        }
        tbody.innerHTML = logs.map(l => `
            <tr>
                <td><small>${formatFecha(l.created_at)}</small></td>
                <td><strong>${escapeHtml(l.usuario_email || 'Invitado')}</strong><br><small>${escapeHtml(l.rol || 'anonimo')}</small></td>
                <td><span class="status-pill status-programada">${escapeHtml(l.accion)}</span></td>
                <td>${escapeHtml(l.recurso || '-')}${l.recurso_id ? ` #${escapeHtml(l.recurso_id)}` : ''}</td>
                <td><small>${escapeHtml(l.detalle || '')}</small></td>
                <td><small>${escapeHtml(l.ip_address || '-')}</small></td>
            </tr>
        `).join('');
    }

    function renderNotificaciones(notifs) {
        const tbody = document.getElementById('tablaNotificacionesAdmin');
        if (!notifs.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="portal-empty-msg">No hay notificaciones registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = notifs.map(n => `
            <tr>
                <td><small>${formatFecha(n.created_at)}</small></td>
                <td><span class="status-pill status-en_atencion">${escapeHtml(n.canal || 'email').toUpperCase()}</span></td>
                <td>${escapeHtml(n.destinatario)}</td>
                <td><strong>${escapeHtml(n.asunto)}</strong></td>
                <td><span class="status-pill status-finalizada">${escapeHtml(n.estado || 'enviada')}</span></td>
            </tr>
        `).join('');
    }
});
