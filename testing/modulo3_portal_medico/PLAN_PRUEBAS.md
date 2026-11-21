# Plan de Pruebas — Módulo 3: Portal Médico, Historia Clínica y Recetas (Integrante 3)

## 1. Alcance y Requerimientos (`REQUERIMIENTOS.md`)
- **RF**: `RF-C08` (staff médico), `RF-M01`, `RF-M02`, `RF-M04`, `RF-M05` (recetas), `RF-M08`, `RF-B02`, `RF-B07` (CRUD médicos)
- **RNF**: `RNF-06`, `RNF-07`, `RNF-08`
- **Backend**: `backend_clinica/src/Modules/Modulo3_PortalMedico/` (`Routes.php`, `MedicoController`, `HistoriaClinicaController`, `RecetaController`)
- **Frontend**: `medico.html`, `staff_medico.html` (`medico.js`, `staff_medico.js`)
- **Tablas MariaDB**: `medicos`, `medico_disponibilidad`, `historias_clinicas`, `recetas`

## 2. Matriz de Casos de Prueba (10 Automatizados + RNF Manuales)

| ID | Requerimiento | Tipo | Caso de Prueba | Resultado Esperado |
| :--- | :---: | :--- | :--- | :--- |
| `TC-M3-01` | `RF-C08` | Positivo | Búsqueda pública en staff (`GET /api/medicos?search=Juan`) | `200`, filtra médicos activos |
| `TC-M3-02` | `RF-M01`, `RF-M08` | Positivo | Dashboard y KPIs del médico (`GET /api/medicos/mi-resumen`) | `200`, citas hoy/próximas/historial y estadísticas |
| `TC-M3-03` | `RF-M01`, `RF-B02` | Seguridad | Paciente intenta acceder a `/api/medicos/mi-resumen` | `403 Forbidden` |
| `TC-M3-04` | `RF-M02` | Validación / Positivo | Rechazo rango horario invertido (`422`) y registro de bloqueo en `/api/medicos/mi-disponibilidad` | `422` en `18:00-08:00`; `200` con bloqueo válido |
| `TC-M3-05` | `RF-M02` | Integración | Bloqueo impide agendar al paciente (`409`) y `DELETE` lo libera | `409` al intentar reservar en bloque; `200` al eliminar |
| `TC-M3-06` | `RF-M04`, `RNF-07` | Seguridad / RBAC | Historia clínica: médico tratante (`200`) vs paciente ajeno (`403`) | Privacidad estricta y acceso auditado |
| `TC-M3-07` | `RF-M04`, `RF-B03` | Validación / Positivo | Evolución CIE-10 inválida (`422`) y válida (`201`) | `422` sin campos obligatorios; `201` registrada |
| `TC-M3-08` | `RF-M05` | Validación / Positivo | Validación `cita_id` inexistente (`404`), emisión y consulta de receta digital (`POST/GET /api/recetas`) | `404` en cita inexistente; `201` con código `REC-YYYY-XXXXX` |
| `TC-M3-09` | `RF-B07`, `RF-B02` | Seguridad / CRUD | Médico bloqueado (`403`) vs Admin CRUD `/api/medicos` (`POST/PUT/DELETE`) | `403` para médico; `201`/`200` para admin |
| `TC-M3-10` | `RF-M01`, `RF-C08` | Smoke | Acceso HTTP `200` a `/medico`, `/medico.html`, `/staff_medico.html` | `200` en todas las rutas |
| `TC-M3-11` | `RF-D05` (`RF-M02`) | Defecto (`NO CUMPLE`) | Aislamiento anti-IDOR en `DELETE /api/medicos/mi-disponibilidad/{id}` entre médicos | **Esperado**: `403 Forbidden` · **Actual**: `200` (otro médico elimina bloqueo ajeno) |
| `TC-M3-12` | `RF-D06` (`RF-M05`) | Defecto (`NO CUMPLE`) | Verificación de alergias clínicas al emitir receta (`Penicilina` a paciente alérgico a `Penicilina, Sulfas`) | **Esperado**: `409`/`422` (alerta de alergia) · **Actual**: `201` (emite receta sin validar alergias) |

### Verificaciones RNF Complementarias
- `RNF-08`: Verificar en `js/staff_medico.js` una sola definición de `renderDoctors` y búsqueda `<200ms`.

## 3. Ejecución
```bash
python3 testing/modulo3_portal_medico/test_modulo3.py
```
