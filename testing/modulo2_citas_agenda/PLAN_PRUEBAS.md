# Plan de Pruebas — Módulo 2: Citas y Agenda (Integrante 2)

## 1. Alcance y Requerimientos (`REQUERIMIENTOS.md`)
- **RF**: `RF-C05`, `RF-C06`, `RF-C07`, `RF-C08` (especialidades/servicios), `RF-M03`, `RF-M07`, `RF-B01`, `RF-B04`
- **RNF**: `RNF-04`, `RNF-08`
- **Backend**: `backend_clinica/src/Modules/Modulo2_CitasAgenda/` (`Routes.php`, `EspecialidadController`, `CitaController`)
- **Frontend**: `agendar_cita.html`, `mis_citas.html`, `especialidades.html`, `Servicios.html` (`agendar_cita.js`, `mis_citas.js`, `especialidades.js`, `Script_Servicios.js`)
- **Tablas MariaDB**: `especialidades`, `citas`, `medico_disponibilidad`, `notificaciones`

## 2. Matriz de Casos de Prueba (10 Automatizados + RNF Manuales)

| ID | Requerimiento | Tipo | Caso de Prueba | Resultado Esperado |
| :--- | :---: | :--- | :--- | :--- |
| `TC-M2-01` | `RF-C08` | Positivo | Catálogo de especialidades (`GET /api/especialidades`) | `200`, al menos 12 especialidades activas |
| `TC-M2-02` | `RF-C05`, `RF-B03` | Validación / Positivo | Consulta de horas ocupadas (`GET /api/citas/ocupadas`) | `422` en parámetros inválidos; `200` con `horas_ocupadas` y `bloqueos` |
| `TC-M2-03` | `RF-C05`, `RF-C07` | Positivo | Reserva de cita (`POST /api/citas`) + notificación en BD | `201`, código `CIT-YYYY-XXXXX` y registro en `notificaciones` |
| `TC-M2-04` | `RF-C05`, `RF-B04` | Concurrencia | Doble reserva mismo médico/fecha/hora | `409 Conflict` |
| `TC-M2-05` | `RF-C06`, `RF-B01` | Integración | Visibilidad en `GET /api/mis-citas` | `200`, contiene la cita recién creada |
| `TC-M2-06` | `RF-C06` | Positivo | Reprogramación con >24h (`PUT /api/citas/{id}`) | `200`, estado `reprogramada` |
| `TC-M2-07` | `RF-C06` | Regla de Negocio | Cancelación tardía <24h por paciente (`DELETE`) | `422`, rechazo por regla de 24 horas |
| `TC-M2-08` | `RF-M03`, `RF-M07` | Integración | Médico cambia estado `en_atencion` y crea derivación (`POST /derivar`) | `200` y `201` con cita `DER-YYYY-XXXXX` |
| `TC-M2-09` | `RF-M03`, `RF-C06` | Regla de Negocio | Registro `no_show` (penalidad S/20) y cancelación válida >24h | `200` en ambos |
| `TC-M2-10` | `RF-C01`, `RF-C08` | Smoke | Vistas `agendar_cita.html`, `mis_citas.html`, `especialidades.html`, `Servicios.html` | `200` en todas las vistas |

### Verificaciones RNF Complementarias
- `RNF-04`: Medir latencia API `p95 < 500ms` sobre `POST /api/citas` y `GET /api/citas/ocupadas` (ej. k6).
- `RNF-08`: Confirmar ausencia de `name="fecha"` duplicado en `agendar_cita.html` y correcta trazabilidad.

## 3. Ejecución
```bash
python3 testing/modulo2_citas_agenda/test_modulo2.py
```
