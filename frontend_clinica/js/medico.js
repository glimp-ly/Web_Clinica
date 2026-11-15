document.addEventListener('DOMContentLoaded', () => {
    const authGate = document.getElementById('medicoAuthGate');
    const workspace = document.getElementById('medicoWorkspace');
    const forbiddenAlert = document.getElementById('medicoForbiddenAlert');
    let especialidadesList = [];

    document.querySelectorAll('.portal-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.portal-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.portal-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            const target = document.getElementById(btn.getAttribute('data-tab'));
            if (target) target.classList.add('active');
        });
    });

    window.ClinicaAPI.request('api/especialidades').then(r => {
        if (r.ok && r.data.especialidades) especialidadesList = r.data.especialidades;
    });

    document.getElementById('medicoLoginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('medEmail').value.trim();
        const password = document.getElementById('medPass').value;
        const res = await window.ClinicaAPI.request('api/auth/login', {
            method: 'POST',
            body: { email, password }
        });
        if (res.ok) {
            window.ClinicaAPI.syncAuth();
            checkMedicoSession();
        } else {
            window.ClinicaAPI.notify(res.data.message || 'Credenciales inválidas', 'error');
        }
    });

    document.getElementById('btnGoogleMedico').addEventListener('click', () => {
        window.ClinicaAPI.openGoogleAuth('medico', 'medico.html');
    });

    document.getElementById('btnRefreshMedico').addEventListener('click', loadMedicoDashboard);
    document.getElementById('btnLogoutMedico').addEventListener('click', async () => {
        await window.ClinicaAPI.request('api/auth/logout', { method: 'POST' });
        window.location.href = 'index.html';
    });

    async function checkMedicoSession() {
        const chip = document.getElementById('medicoHeaderChip');
        const btnRef = document.getElementById('btnRefreshMedico');
        const btnOut = document.getElementById('btnLogoutMedico');
        const res = await window.ClinicaAPI.request('api/auth/me');
        if (!res.ok || !res.data.user) {
            authGate.style.display = 'block';
            workspace.style.display = 'none';
            if (chip) chip.style.display = 'none';
            if (btnRef) btnRef.style.display = 'none';
            if (btnOut) btnOut.style.display = 'none';
            return;
        }
        const u = res.data.user;
        if (u.rol !== 'medico' && u.rol !== 'admin') {
            authGate.style.display = 'block';
            workspace.style.display = 'none';
            if (chip) chip.style.display = 'none';
            if (btnRef) btnRef.style.display = 'none';
            if (btnOut) btnOut.style.display = 'inline-flex';
            forbiddenAlert.style.display = 'block';
            forbiddenAlert.innerHTML = `Acceso Denegado (403 RF-M01): Su cuenta actual (${u.email}) tiene rol '${u.rol}'. Inicie sesión con una cuenta de Médico. <a href="mis_citas.html" style="color:#033076; text-decoration:underline; margin-left:6px;">Ir a Portal Paciente</a>`;
            return;
        }
        forbiddenAlert.style.display = 'none';
        authGate.style.display = 'none';
        workspace.style.display = 'grid';
        if (chip) {
            chip.style.display = 'flex';
            document.getElementById('medicoHeaderUser').textContent = u.nombre;
        }
        if (btnRef) btnRef.style.display = 'inline-flex';
        if (btnOut) btnOut.style.display = 'inline-flex';
        loadMedicoDashboard();
    }

    async function loadMedicoDashboard() {
        const res = await window.ClinicaAPI.request('api/medicos/mi-resumen');
        if (!res.ok) {
            window.ClinicaAPI.notify(res.data.message || 'Error al cargar resumen médico', 'error');
            return;
        }

        const m = res.data.medico || {};
        const st = res.data.estadisticas || {};

        document.getElementById('medicoHeaderName').textContent = `${m.nombre || 'Médico'} — Portal Clínico`;
        document.getElementById('medicoHeaderSub').textContent =
            `Especialidad: ${m.especialidad_nombre || '-'} · Colegiatura: ${m.cmp || '-'} · Días: ${m.dias_atencion || '-'}`;

        document.getElementById('kpiHoy').textContent = (res.data.citas_hoy || []).length;
        document.getElementById('kpiPendientes').textContent = st.pendientes || 0;
        document.getElementById('kpiConsultas').textContent = st.consultas_historicas || 0;
        document.getElementById('kpiHoras').textContent = (st.horas_acumuladas || 0) + 'h';
        document.getElementById('kpiNoShow').textContent = st.no_shows || 0;

        document.getElementById('dispManana').checked = parseInt(m.turno_manana) === 1;
        document.getElementById('dispTarde').checked = parseInt(m.turno_tarde) === 1;
        document.getElementById('dispDias').value = m.dias_atencion || '';

        renderCitasHoy(res.data.citas_hoy || []);
        renderTodasCitas(res.data.todas_citas || []);
        renderPacientesSelect(res.data.mis_pacientes || []);
        renderRecetasMedico(res.data.recetas || []);
        renderLabMedico(res.data.ordenes_lab || []);
        renderDisponibilidad(res.data.disponibilidad || []);
    }

    function renderCitasHoy(citas) {
        const tbody = document.getElementById('tablaCitasHoy');
        if (citas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7">No hay citas programadas para el día de hoy.</td></tr>';
            return;
        }
        tbody.innerHTML = citas.map(c => `
            <tr>
                <td><b>${c.codigo_cita}</b></td>
                <td><b>${String(c.hora_cita).substring(0, 5)}</b><br><small>${c.turno}</small></td>
                <td><b>${c.nombre_paciente}</b><br><small>DNI: ${c.dni_paciente}</small></td>
                <td>
                    <span class="role-badge role-badge-medico">${c.tipo_sangre || 'O+'}</span>
                    ${parseInt(c.tiene_seguro) === 1 ? `<span class="role-badge role-badge-paciente">${c.aseguradora}</span>` : ''}
                    <br><small style="color:#d93025;">Alergias: ${c.alergias || 'Ninguna'}</small>
                </td>
                <td>${c.motivo_consulta || 'Consulta general'}</td>
                <td><span class="status-pill status-${c.estado}">${c.estado.replace('_', ' ')}</span></td>
                <td>
                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
                        ${c.estado === 'programada' || c.estado === 'reprogramada' ? `
                            <button type="button" class="btn-portal btn-portal-primary btn-estado-cita" data-id="${c.id}" data-estado="en_atencion" style="padding:4px 8px; font-size:0.76rem;">Iniciar Atención</button>
                            <button type="button" class="btn-portal btn-portal-danger btn-estado-cita" data-id="${c.id}" data-estado="no_show" style="padding:4px 8px; font-size:0.76rem;">No-Show</button>
                        ` : ''}
                        ${c.estado === 'en_atencion' ? `
                            <button type="button" class="btn-portal btn-portal-success btn-finalizar-cita" data-id="${c.id}" data-dni="${c.dni_paciente}" style="padding:4px 8px; font-size:0.76rem;">Finalizar</button>
                        ` : ''}
                        <button type="button" class="btn-portal btn-portal-outline btn-abrir-hc" data-dni="${c.dni_paciente}" data-cita="${c.id}" style="padding:4px 8px; font-size:0.76rem;">Historia</button>
                        <button type="button" class="btn-portal btn-portal-outline btn-derivar-cita" data-id="${c.id}" data-paciente="${c.nombre_paciente}" style="padding:4px 8px; font-size:0.76rem;">Derivar</button>
                    </div>
                </td>
            </tr>
        `).join('');

        bindAccionesCitas(tbody);
    }

    function renderTodasCitas(citas) {
        const tbody = document.getElementById('tablaCitasTodas');
        if (citas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">Sin registros.</td></tr>';
            return;
        }
        tbody.innerHTML = citas.map(c => `
            <tr>
                <td><b>${c.codigo_cita}</b></td>
                <td>${c.fecha_cita} ${String(c.hora_cita).substring(0, 5)}</td>
                <td>${c.nombre_paciente} (${c.dni_paciente})</td>
                <td>${c.motivo_consulta || '-'}</td>
                <td><span class="status-pill status-${c.estado}">${c.estado.replace('_', ' ')}</span></td>
                <td>
                    <div style="display:flex; gap:4px; flex-wrap:wrap; align-items:center;">
                        ${c.estado === 'programada' || c.estado === 'reprogramada' ? `
                            <button type="button" class="btn-portal btn-portal-primary btn-estado-cita" data-id="${c.id}" data-estado="en_atencion" style="padding:3px 7px; font-size:0.74rem;">Iniciar</button>
                            <button type="button" class="btn-portal btn-portal-danger btn-estado-cita" data-id="${c.id}" data-estado="no_show" style="padding:3px 7px; font-size:0.74rem;">No-Show</button>
                        ` : ''}
                        ${c.estado === 'en_atencion' ? `
                            <button type="button" class="btn-portal btn-portal-success btn-finalizar-cita" data-id="${c.id}" data-dni="${c.dni_paciente}" style="padding:3px 7px; font-size:0.74rem;">Finalizar</button>
                        ` : ''}
                        <button type="button" class="btn-portal btn-portal-outline btn-abrir-hc" data-dni="${c.dni_paciente}" data-cita="${c.id}" style="padding:3px 7px; font-size:0.74rem;">Historia</button>
                        <button type="button" class="btn-portal btn-portal-outline btn-derivar-cita" data-id="${c.id}" data-paciente="${c.nombre_paciente}" style="padding:3px 7px; font-size:0.74rem;">Derivar</button>
                    </div>
                    ${c.observaciones_atencion ? `<small style="display:block; color:#57606a; margin-top:4px;">${c.observaciones_atencion}</small>` : ''}
                </td>
            </tr>
        `).join('');

        bindAccionesCitas(tbody);
    }

    function bindAccionesCitas(container) {
        container.querySelectorAll('.btn-estado-cita').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const estado = btn.getAttribute('data-estado');
                if (estado === 'no_show' && !confirm('¿Confirmar No-Show (pasados 15 minutos de tolerancia)? Se aplicará el cargo según TyC.')) return;
                const r = await window.ClinicaAPI.request('api/citas/' + id, {
                    method: 'PUT',
                    body: { estado }
                });
                if (r.ok) {
                    window.ClinicaAPI.notify(r.data.message, 'success');
                    loadMedicoDashboard();
                } else {
                    window.ClinicaAPI.notify(r.data.message || 'Error al actualizar cita', 'error');
                }
            });
        });

        container.querySelectorAll('.btn-finalizar-cita').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const obs = prompt('Ingrese resumen/observación de cierre de atención (o deje su evolución ya registrada en la Historia Clínica):', 'Atención concluida satisfactoriamente con registro en historia clínica.');
                if (obs === null) return;
                const r = await window.ClinicaAPI.request('api/citas/' + id, {
                    method: 'PUT',
                    body: { estado: 'finalizada', observaciones_atencion: obs }
                });
                if (r.ok) {
                    window.ClinicaAPI.notify('Cita finalizada correctamente', 'success');
                    loadMedicoDashboard();
                } else {
                    window.ClinicaAPI.notify(r.data.message || 'Error al finalizar cita', 'error');
                }
            });
        });

        container.querySelectorAll('.btn-abrir-hc').forEach(btn => {
            btn.addEventListener('click', () => {
                const dni = btn.getAttribute('data-dni');
                const citaId = btn.getAttribute('data-cita') || '';
                document.querySelector('.portal-tab-btn[data-tab="panelHistoria"]').click();
                document.getElementById('inputBuscarDniHc').value = dni;
                document.getElementById('hcDni').value = dni;
                document.getElementById('hcCitaId').value = citaId;
                cargarHistoriaPaciente(dni);
            });
        });

        container.querySelectorAll('.btn-derivar-cita').forEach(btn => {
            btn.addEventListener('click', () => {
                abrirModalDerivacion(btn.getAttribute('data-id'), btn.getAttribute('data-paciente'));
            });
        });
    }

    function abrirModalDerivacion(citaId, nombrePaciente) {
        const prev = document.getElementById('derivarModal');
        if (prev) prev.remove();

        const espOpts = especialidadesList.map(e => `<option value="${e.id}">${e.nombre}</option>`).join('');
        const nextDate = new Date(Date.now() + 2 * 86400000).toISOString().split('T')[0];

        const overlay = document.createElement('div');
        overlay.id = 'derivarModal';
        overlay.className = 'google-modal-overlay';
        overlay.innerHTML = `
            <div class="google-modal-card">
                <div class="google-modal-header">
                    <div class="google-brand"><i class="fa-solid fa-share-nodes" style="color:#0077b6;"></i> <span>Interconsulta / Derivación (RF-M07)</span></div>
                    <button type="button" class="google-modal-close">&times;</button>
                </div>
                <p class="google-modal-sub">Derivar al paciente <b>${nombrePaciente}</b> a otra especialidad:</p>
                <form id="formDerivar" style="display:flex; flex-direction:column; gap:10px;">
                    <div>
                        <label style="font-size:0.82rem; font-weight:600;">Especialidad Destino*</label>
                        <select id="derEspId" class="portal-select" required>${espOpts}</select>
                    </div>
                    <div class="grid-2col">
                        <div>
                            <label style="font-size:0.82rem; font-weight:600;">Fecha Sugerida*</label>
                            <input type="date" id="derFecha" class="portal-input" value="${nextDate}" required>
                        </div>
                        <div>
                            <label style="font-size:0.82rem; font-weight:600;">Hora*</label>
                            <input type="time" id="derHora" class="portal-input" value="10:30" required>
                        </div>
                    </div>
                    <div>
                        <label style="font-size:0.82rem; font-weight:600;">Motivo Clínico de Interconsulta*</label>
                        <textarea id="derMotivo" class="portal-textarea" rows="2" placeholder="Evaluación especializada complementaria..." required></textarea>
                    </div>
                    <button type="submit" class="btn-portal btn-portal-primary" style="padding:10px;">Confirmar Derivación</button>
                </form>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('.google-modal-close').onclick = () => overlay.remove();

        overlay.querySelector('#formDerivar').onsubmit = async (e) => {
            e.preventDefault();
            const r = await window.ClinicaAPI.request(`api/citas/${citaId}/derivar`, {
                method: 'POST',
                body: {
                    especialidad_id: overlay.querySelector('#derEspId').value,
                    fecha_cita: overlay.querySelector('#derFecha').value,
                    hora_cita: overlay.querySelector('#derHora').value,
                    motivo: overlay.querySelector('#derMotivo').value.trim()
                }
            });
            if (r.ok) {
                overlay.remove();
                window.ClinicaAPI.notify(`Cita derivada generada: ${r.data.codigo_cita}`, 'success');
                loadMedicoDashboard();
            } else {
                window.ClinicaAPI.notify(r.data.message || 'Error al derivar', 'error');
            }
        };
    }

    function renderPacientesSelect(pacientes) {
        const sel = document.getElementById('selectPacienteAsignado');
        sel.innerHTML = '<option value="">Seleccione un paciente asignado...</option>' +
            pacientes.map(p => `<option value="${p.dni}">${p.nombre} ${p.apellidos} (DNI: ${p.dni})</option>`).join('');
        sel.onchange = () => {
            if (sel.value) {
                document.getElementById('inputBuscarDniHc').value = sel.value;
                document.getElementById('hcDni').value = sel.value;
                cargarHistoriaPaciente(sel.value);
            }
        };
    }

    document.getElementById('btnCargarHistoria').addEventListener('click', () => {
        const dni = document.getElementById('inputBuscarDniHc').value.trim();
        if (!dni) {
            window.ClinicaAPI.notify('Ingrese o seleccione el DNI del paciente', 'error');
            return;
        }
        document.getElementById('hcDni').value = dni;
        cargarHistoriaPaciente(dni);
    });

    async function cargarHistoriaPaciente(dni) {
        const resumenDiv = document.getElementById('fichaPacienteResumen');
        const listaDiv = document.getElementById('listaEvolucionesHc');
        const res = await window.ClinicaAPI.request(`api/pacientes/${encodeURIComponent(dni)}/historia`);

        if (!res.ok) {
            resumenDiv.style.display = 'none';
            listaDiv.innerHTML = `<div style="padding:12px; background:#fce8e6; color:#d93025; border:1px solid #f5c2c7; border-radius:6px; font-weight:600;">${res.data.message || 'Acceso denegado (403)'}</div>`;
            window.ClinicaAPI.notify(res.data.message || 'Acceso restringido', 'error');
            return;
        }

        const p = res.data.paciente;
        resumenDiv.style.display = 'block';
        resumenDiv.innerHTML = `
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; font-size:0.86rem;">
                <div><b>Paciente:</b> ${p.nombre} ${p.apellidos} (${p.edad} años)</div>
                <div><b>DNI:</b> ${p.dni} | <b>Tipo de Sangre:</b> <span class="role-badge role-badge-medico">${p.tipo_sangre}</span></div>
                <div><b>Alergias:</b> <span style="color:#d93025; font-weight:700;">${p.alergias || 'Ninguna'}</span></div>
                <div><b>Enfermedades Crónicas:</b> ${p.enfermedades || 'Ninguna'}</div>
                <div><b>Cirugías Previas:</b> ${p.cirugias || 'Ninguna'}</div>
                <div><b>Seguro / EPS:</b> ${parseInt(p.tiene_seguro) === 1 ? `${p.aseguradora} (Póliza: ${p.numero_poliza})` : 'Particular'}</div>
            </div>
        `;

        const entradas = res.data.entradas || [];
        if (entradas.length === 0) {
            listaDiv.innerHTML = '<p style="color:#57606a;">Aún no hay evoluciones registradas para este paciente.</p>';
        } else {
            listaDiv.innerHTML = entradas.map(e => `
                <div style="border:1px solid #d0d7de; border-left:4px solid #029d56; border-radius:6px; padding:10px 12px; font-size:0.84rem;">
                    <div style="display:flex; justify-content:space-between; font-weight:700; color:#033076; margin-bottom:4px;">
                        <span>CIE-10: ${e.diagnostico_cie10 || 'Z00'} — ${e.diagnostico}</span>
                        <span>${e.created_at}</span>
                    </div>
                    <p><b>Médico:</b> ${e.medico_nombre} (${e.especialidad_nombre})</p>
                    <p><b>Signos Vitales:</b> ${e.signos_vitales || '-'}</p>
                    <p><b>Motivo / Anamnesis:</b> ${e.motivo} — ${e.anamnesis}</p>
                    <p><b>Tratamiento:</b> ${e.tratamiento}</p>
                </div>
            `).join('');
        }
    }

    document.getElementById('formNuevaHistoria').addEventListener('submit', async (e) => {
        e.preventDefault();
        const dni = document.getElementById('hcDni').value.trim();
        const r = await window.ClinicaAPI.request(`api/pacientes/${encodeURIComponent(dni)}/historia`, {
            method: 'POST',
            body: {
                cita_id: document.getElementById('hcCitaId').value || null,
                diagnostico_cie10: document.getElementById('hcCie10').value.trim(),
                signos_vitales: document.getElementById('hcSignos').value.trim(),
                motivo: document.getElementById('hcMotivo').value.trim(),
                anamnesis: document.getElementById('hcAnamnesis').value.trim(),
                diagnostico: document.getElementById('hcDiagnostico').value.trim(),
                tratamiento: document.getElementById('hcTratamiento').value.trim()
            }
        });
        if (r.ok) {
            window.ClinicaAPI.notify(r.data.message, 'success');
            cargarHistoriaPaciente(dni);
            loadMedicoDashboard();
        } else {
            window.ClinicaAPI.notify(r.data.message || 'Error al registrar historia', 'error');
        }
    });

    document.getElementById('formEmitirReceta').addEventListener('submit', async (e) => {
        e.preventDefault();
        const dni = document.getElementById('recDni').value.trim();
        const r = await window.ClinicaAPI.request('api/recetas', {
            method: 'POST',
            body: {
                dni_paciente: dni,
                diagnostico: document.getElementById('recDiag').value.trim(),
                indicaciones: document.getElementById('recIndicaciones').value.trim(),
                medicamentos: [{
                    nombre: document.getElementById('recMedNombre').value,
                    dosis: document.getElementById('recDosis').value.trim(),
                    duracion: document.getElementById('recDuracion').value.trim(),
                    cantidad: 1
                }]
            }
        });
        if (r.ok) {
            window.ClinicaAPI.notify(`Receta emitida: ${r.data.codigo_receta}`, 'success');
            loadMedicoDashboard();
        } else {
            window.ClinicaAPI.notify(r.data.message || 'Error al emitir receta', 'error');
        }
    });

    document.getElementById('formEmitirOrdenLab').addEventListener('submit', async (e) => {
        e.preventDefault();
        const dni = document.getElementById('ordLabDni').value.trim();
        const nombreAnalisis = document.getElementById('ordLabAnalisis').value;
        const r = await window.ClinicaAPI.request('api/ordenes-lab', {
            method: 'POST',
            body: {
                dni_paciente: dni,
                analisis: [{ nombre: nombreAnalisis, cantidad: 1 }]
            }
        });
        if (r.ok && r.data.orden) {
            window.ClinicaAPI.notify(`Orden Lab emitida: ${r.data.orden.codigo_orden}`, 'success');
            loadMedicoDashboard();
        } else {
            window.ClinicaAPI.notify(r.data.message || 'Error al emitir orden', 'error');
        }
    });

    function renderRecetasMedico(recetas) {
        const tbody = document.getElementById('tablaRecetasMedico');
        if (recetas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4">Sin recetas emitidas.</td></tr>';
            return;
        }
        tbody.innerHTML = recetas.map(r => `
            <tr>
                <td><b>${r.codigo_receta}</b></td>
                <td>${r.dni_paciente}</td>
                <td>${r.diagnostico}</td>
                <td><span class="status-pill status-${r.estado}">${r.estado}</span></td>
            </tr>
        `).join('');
    }

    function renderLabMedico(ordenes) {
        const tbody = document.getElementById('tablaLabMedico');
        if (ordenes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">Sin órdenes de laboratorio registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = ordenes.map(o => {
            const items = [];
            try {
                const parsed = JSON.parse(o.analisis_json || '[]');
                parsed.forEach(a => items.push(a.nombre));
            } catch (e) {}
            return `
                <tr>
                    <td><b>${o.codigo_orden}</b></td>
                    <td>${o.nombre_paciente}<br><small>DNI: ${o.dni_paciente}</small></td>
                    <td>${items.join(', ')}</td>
                    <td><span class="status-pill status-${o.estado}">${o.estado.replace('_', ' ')}</span></td>
                    <td>${o.resultado_resumen || 'Pendiente de muestra/procesamiento'}</td>
                    <td>
                        <button type="button" class="btn-portal btn-portal-outline btn-audit-lab" data-cod="${o.codigo_orden}" style="padding:4px 8px; font-size:0.76rem;">Detalle Auditado</button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-audit-lab').forEach(btn => {
            btn.addEventListener('click', async () => {
                const cod = btn.getAttribute('data-cod');
                const r = await window.ClinicaAPI.request('api/resultados/' + encodeURIComponent(cod));
                if (r.ok && r.data.resultado) {
                    const ord = r.data.resultado;
                    alert(`RESULTADO DE LABORATORIO (${ord.codigo_orden})\nPaciente: ${ord.nombre_paciente} (DNI: ${ord.dni_paciente})\nResumen: ${ord.resultado_resumen || 'En proceso'}\nObservaciones: ${ord.observaciones_lab || '-'}`);
                } else {
                    window.ClinicaAPI.notify(r.data.message || 'Acceso denegado', 'error');
                }
            });
        });
    }

    document.getElementById('formDisponibilidad').addEventListener('submit', async (e) => {
        e.preventDefault();
        const r = await window.ClinicaAPI.request('api/medicos/mi-disponibilidad', {
            method: 'PUT',
            body: {
                turno_manana: document.getElementById('dispManana').checked,
                turno_tarde: document.getElementById('dispTarde').checked,
                dias_atencion: document.getElementById('dispDias').value.trim(),
                fecha: document.getElementById('dispFecha').value || null,
                estado: document.getElementById('dispEstado').value,
                turno: document.getElementById('dispTurno').value,
                hora_inicio: document.getElementById('dispHoraIni').value,
                hora_fin: document.getElementById('dispHoraFin').value,
                motivo: document.getElementById('dispMotivo').value.trim()
            }
        });
        if (r.ok) {
            window.ClinicaAPI.notify(r.data.message, 'success');
            loadMedicoDashboard();
        } else {
            window.ClinicaAPI.notify(r.data.message || 'Error al actualizar disponibilidad', 'error');
        }
    });

    function renderDisponibilidad(bloques) {
        const tbody = document.getElementById('tablaDisponibilidadMedico');
        if (bloques.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5">No hay bloques específicos registrados.</td></tr>';
            return;
        }
        tbody.innerHTML = bloques.map(b => `
            <tr>
                <td>${b.fecha}</td>
                <td>${b.turno} (${String(b.hora_inicio).substring(0, 5)} - ${String(b.hora_fin).substring(0, 5)})</td>
                <td><span class="status-pill status-${b.estado}">${b.estado}</span></td>
                <td>${b.motivo || '-'}</td>
                <td><button type="button" class="btn-portal btn-portal-danger btn-del-disp" data-id="${b.id}" style="padding:3px 8px; font-size:0.75rem;">Eliminar</button></td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.btn-del-disp').forEach(btn => {
            btn.addEventListener('click', async () => {
                const r = await window.ClinicaAPI.request('api/medicos/mi-disponibilidad/' + btn.getAttribute('data-id'), { method: 'DELETE' });
                if (r.ok) loadMedicoDashboard();
            });
        });
    }

    checkMedicoSession();
});
