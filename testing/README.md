# Plan de Distribución, Trazabilidad ERS v2 y Testing — 5 Módulos Equivalentes

El sistema **Clínica Centenario del Norte** está dividido en **5 módulos funcionales equivalentes e independientes** (`backend_clinica/src/Modules/` + `testing/modulo*/`), repartiendo de forma equilibrada **los 42 requerimientos base + 10 requerimientos de defecto (`RF-D01`–`RF-D10`) de [`REQUERIMIENTOS.md`](../REQUERIMIENTOS.md)** y **60 casos de prueba automatizados (12 por integrante: 10 `CUMPLE` + 2 `NO CUMPLE` reproducibles para el reporte de errores del curso)**.

---

## 1. Reparto Equitativo por Integrante

| Integrante | Módulo | Backend (`src/Modules/`) | Testing (`testing/`) | Requerimientos Asignados (`REQUERIMIENTOS.md`) | Casos Auto (`PASS` / `DEFECTOS`) |
| :---: | :--- | :--- | :--- | :--- | :---: |
| **1** | **Acceso, Perfil y Pacientes** | `Modulo1_AccesoPacientes/` | `modulo1_acceso_pacientes/` | `RF-C01..04`, `RF-C14`, `RF-C15`, `RF-B02/03/06`, `RNF-01/02/06/07/10`, **`RF-D01`**, **`RF-D02`** | 12 (10 / 2) |
| **2** | **Citas y Agenda** | `Modulo2_CitasAgenda/` | `modulo2_citas_agenda/` | `RF-C05..08`, `RF-M03`, `RF-M07`, `RF-B01/04`, `RNF-04/08`, **`RF-D03`**, **`RF-D04`** | 12 (10 / 2) |
| **3** | **Portal Médico, Historia y Recetas** | `Modulo3_PortalMedico/` | `modulo3_portal_medico/` | `RF-C08`, `RF-M01/02/04/05/08`, `RF-B02/07`, `RNF-06/07/08`, **`RF-D05`**, **`RF-D06`** | 12 (10 / 2) |
| **4** | **Farmacia y Laboratorio** | `Modulo4_FarmaciaLaboratorio/` | `modulo4_farmacia_laboratorio/` | `RF-C09/10`, `RF-M05/06`, `RF-B04/05/07`, `RNF-03/04/10`, **`RF-D07`**, **`RF-D08`** | 12 (10 / 2) |
| **5** | **Comunidad y Administración** | `Modulo5_ComunidadAdmin/` | `modulo5_comunidad_admin/` | `RF-C11..13`, `RF-B01/04/06/07`, `RNF-05/09/11/12`, **`RF-D09`**, **`RF-D10`** | 12 (10 / 2) |

---

## 2. Matriz Completa de Cobertura (42 / 42 Requerimientos de `REQUERIMIENTOS.md`)

### Portal Cliente (`RF-C01` – `RF-C15`)
| ID | Módulo Responsable | Casos de Prueba / Verificación |
| :---: | :---: | :--- |
| `RF-C01` | M1 (y vistas en M2–M5) | `TC-M1-10`, `TC-M2-10`, `TC-M3-10`, `TC-M4-10`, `TC-M5-10` + prueba responsive ≤850px |
| `RF-C02` | M1 | `TC-M1-01`, `TC-M1-02`, `TC-M1-03`, `TC-M1-04` |
| `RF-C03` | M1 | `TC-M1-05`, `TC-M1-06` |
| `RF-C04` | M1 | `TC-M1-07` |
| `RF-C05` | M2 | `TC-M2-02`, `TC-M2-03`, `TC-M2-04` |
| `RF-C06` | M2 | `TC-M2-05`, `TC-M2-06`, `TC-M2-07`, `TC-M2-09` |
| `RF-C07` | M2 | `TC-M2-03` (notificación registrada en tabla `notificaciones`) |
| `RF-C08` | M2 + M3 | `TC-M2-01`, `TC-M2-10` (especialidades/servicios) · `TC-M3-01`, `TC-M3-10` (staff/buscador) |
| `RF-C09` | M4 | `TC-M4-01`, `TC-M4-02`, `TC-M4-03`, `TC-M4-04`, `TC-M4-05` |
| `RF-C10` | M4 | `TC-M4-06`, `TC-M4-07`, `TC-M4-08`, `TC-M4-09`, `TC-M4-10` |
| `RF-C11` | M5 | `TC-M5-02`, `TC-M5-03`, `TC-M5-10` |
| `RF-C12` | M5 | `TC-M5-04`, `TC-M5-07`, `TC-M5-10` |
| `RF-C13` | M5 | `TC-M5-05`, `TC-M5-07`, `TC-M5-10` |
| `RF-C14` | M1 | `TC-M1-10` (`Qsomos.html`, `privacidad.html`, `TerCondiciones.html`) |
| `RF-C15` | M1 | `TC-M1-07`, `TC-M1-10` (`/api/perfil`, Maps y tema oscuro unificado en `layout.js`) |

### Portal Médico (`RF-M01` – `RF-M08`)
| ID | Módulo Responsable | Casos de Prueba / Verificación |
| :---: | :---: | :--- |
| `RF-M01` | M3 | `TC-M3-02`, `TC-M3-03`, `TC-M3-10` |
| `RF-M02` | M3 | `TC-M3-04`, `TC-M3-05` |
| `RF-M03` | M2 | `TC-M2-08`, `TC-M2-09` (`en_atencion`, `no_show` S/20, `finalizada`) |
| `RF-M04` | M3 | `TC-M3-06`, `TC-M3-07` |
| `RF-M05` | M3 + M4 | `TC-M3-08` (emisión receta) · `TC-M4-05`, `TC-M4-07` (validación farmacia + orden lab) |
| `RF-M06` | M4 | `TC-M4-08`, `TC-M4-09` (médico tratante lee resultados con auditoría) |
| `RF-M07` | M2 | `TC-M2-08` (`POST /api/citas/{id}/derivar`) |
| `RF-M08` | M3 | `TC-M3-02` (`/api/medicos/mi-resumen`) |

### Backend Transversal (`RF-B01` – `RF-B07`)
| ID | Módulo Responsable | Casos de Prueba / Verificación |
| :---: | :---: | :--- |
| `RF-B01` | Todos (M1–M5) | Persistencia verificada tras `POST`→`GET` en los 5 módulos y `TC-M5-01` |
| `RF-B02` | Todos (núcleo M1) | `TC-M1-03`, `TC-M1-09`, `TC-M3-03`, `TC-M3-06`, `TC-M3-09`, `TC-M4-09`, `TC-M5-06` |
| `RF-B03` | Todos (núcleo M1) | `TC-M1-07`, `TC-M2-02`, `TC-M2-07`, `TC-M3-07`, `TC-M4-02`, `TC-M5-04` |
| `RF-B04` | M2 + M4 + M5 | `TC-M2-04` (solape cita 409) · `TC-M4-03` (stock 409) · `TC-M5-03` (cupo/DNI 409) |
| `RF-B05` | M4 | `TC-M4-04`, `TC-M4-05`, `TC-M4-07` (pagos, boletas y cobertura EPS) |
| `RF-B06` | M1 + M5 | `TC-M1-09` (consentimiento/ARCO) · `TC-M5-08` (logs `auditoria_logs`) |
| `RF-B07` | M3 + M4 + M5 | `TC-M3-09` (CRUD médicos) · `TC-M4-10` (CRUD productos) · `TC-M5-07..09` (FAQ/contactos/campañas/reportes) |

### Requerimientos No Funcionales (`RNF-01` – `RNF-12`)
| ID | Módulo Responsable | Enfoque de Verificación |
| :---: | :---: | :--- |
| `RNF-01` | M1 | Pruebas responsive 375/768/1366px y Lighthouse usabilidad ≥90 |
| `RNF-02` | M1 | Auditoría accesibilidad WCAG AA (`axe`, contraste, `lang="es"`, teclado) |
| `RNF-03` | M4 | Rendimiento frontend catálogos/búsqueda (<200ms, Lighthouse ≥85) |
| `RNF-04` | M2 + M4 | Latencia API p95 <500ms en `/api/citas` y `/api/pedidos` (k6 / carga) |
| `RNF-05` | M5 | Disponibilidad y healthcheck (`TC-M5-01`) + degradación sin CDN |
| `RNF-06` | M1 + M3 | OWASP: JWT HttpOnly, bcrypt, RBAC y rate-limit 5 intentos (`TC-M1-08`) |
| `RNF-07` | M1 + M3 | Privacidad historia clínica (`TC-M3-06`) y anonimización ARCO (`TC-M1-09`) |
| `RNF-08` | M2 + M3 | Mantenibilidad: clave tema única `darkMode`, sin `renderDoctors` ni `name="fecha"` duplicados |
| `RNF-09` | M5 | Observabilidad: registros en `auditoria_logs` y `notificaciones` (`TC-M5-08`) |
| `RNF-10` | M1 + M4 | Cumplimiento legal Ley 29733 (`TC-M1-10`) y boletas electrónicas (`TC-M4-04`) |
| `RNF-11` | M5 | Compatibilidad cross-browser (Chrome/Edge/Firefox/Safari) |
| `RNF-12` | M5 | Respaldo y restauración MariaDB (`mysqldump` / RPO ≤24h, RTO ≤4h) |

---

## 3. Ejecución de Pruebas

```bash
# Levantar servicio en Docker (puerto 8989)
docker compose up -d --build

# Ejecutar todos los módulos (50 casos automatizados)
python3 testing/run_all.py

# O ejecutar cada módulo individualmente
python3 testing/modulo1_acceso_pacientes/test_modulo1.py
python3 testing/modulo2_citas_agenda/test_modulo2.py
python3 testing/modulo3_portal_medico/test_modulo3.py
python3 testing/modulo4_farmacia_laboratorio/test_modulo4.py
python3 testing/modulo5_comunidad_admin/test_modulo5.py
```
