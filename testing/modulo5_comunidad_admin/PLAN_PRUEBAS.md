# Plan de Pruebas — Módulo 5: Comunidad, Atención Pública y Administración (Integrante 5)

## 1. Alcance y Requerimientos (`REQUERIMIENTOS.md`)
- **RF**: `RF-C11`, `RF-C12`, `RF-C13`, `RF-B01`, `RF-B04` (cupos campañas), `RF-B06` (auditoría), `RF-B07` (campañas/reportes)
- **RNF**: `RNF-05`, `RNF-09`, `RNF-11`, `RNF-12`
- **Backend**: `backend_clinica/src/Modules/Modulo5_ComunidadAdmin/` (`Routes.php`, `CampanaController`, `AtencionPublicaController`, `ReporteController`)
- **Frontend**: `campañas.html`, `Blog.html`, `faq.html`, `Contacto.html`, `admin.html` (`campañas.js`, `script_Blog.js`, `faq.js`, `script_Contacto.js`, `admin.js`)
- **Tablas MariaDB**: `campanas`, `campana_inscripciones`, `preguntas_faq`, `contactos_afiliacion`, `auditoria_logs`, `notificaciones`

## 2. Matriz de Casos de Prueba (10 Automatizados + RNF Manuales)

| ID | Requerimiento | Tipo | Caso de Prueba | Resultado Esperado |
| :--- | :---: | :--- | :--- | :--- |
| `TC-M5-01` | `RNF-05`, `RF-B01` | Smoke | Healthcheck API y BD (`GET /api/health`) | `200`, `status: healthy` |
| `TC-M5-02` | `RF-C11` | Positivo | Listado de campañas con cupos disponibles (`GET /api/campanas`) | `200`, `cupos_disponibles` calculado |
| `TC-M5-03` | `RF-C11`, `RF-B04` | Concurrencia / Negativo | Inscripción a campaña (`201`) y segundo intento mismo DNI (`409`) | Descuento atómico y bloqueo de duplicado |
| `TC-M5-04` | `RF-C12`, `RF-B03` | Validación / Positivo | Pregunta FAQ inválida (`422`) y válida (`201`) | `422` en campos cortos/correo inválido; `201` creada |
| `TC-M5-05` | `RF-C13` | Positivo | Registro público de afiliación (`POST /api/contactos`) | `201`, estado `ENVIADO` |
| `TC-M5-06` | `RF-B02`, `RF-B07` | Seguridad / RBAC | Paciente intenta leer `GET /api/reportes/resumen` | `403 Forbidden` |
| `TC-M5-07` | `RF-C12`, `RF-C13`, `RF-B07` | Admin | Admin responde FAQ (`PUT /api/preguntas/{id}`) y actualiza contacto (`PUT /api/contactos/{id}`) | `200` en ambas operaciones |
| `TC-M5-08` | `RF-B06`, `RF-B07`, `RNF-09` | Observabilidad | Dashboard admin `GET /api/reportes/resumen` | `200`, métricas de citas/ventas y trazas en `auditoria` |
| `TC-M5-09` | `RF-B07` | Validación / CRUD Admin | Rechazo de fechas invertidas (`422`) y creación/actualización de campaña (`POST/PUT /api/campanas`) | `422` si `fecha_fin < fecha_inicio`; `201` y `200` en datos válidos |
| `TC-M5-10` | `RF-C11`, `RF-C12`, `RF-C13` | Smoke | Acceso HTTP `200` a `campañas.html`, `Blog.html`, `faq.html`, `Contacto.html`, `admin.html`, `/admin` | `200` en todas las vistas |
| `TC-M5-11` | `RF-D09` (`RF-B07`) | Defecto (`NO CUMPLE`) | Coherencia de aforo: reducir `cupos_totales` por debajo de `cupos_ocupados` (`PUT /api/campanas/{id}`) | **Esperado**: `409`/`422` · **Actual**: `200` (deja campaña en sobre-aforo) |
| `TC-M5-12` | `RF-D10` (`RF-C12`) | Defecto (`NO CUMPLE`) | Respuesta admin a pregunta FAQ inexistente (`PUT /api/preguntas/999999`) | **Esperado**: `404` · **Actual**: `200` (responde éxito sin verificar existencia) |

### Verificaciones RNF Complementarias
- `RNF-05`: Desactivar temporalmente CDN externo y validar que el flujo crítico no se rompe.
- `RNF-11`: Validar matriz de navegadores (Chrome, Edge, Firefox, Safari) en móvil y desktop.
- `RNF-12`: Ejecutar respaldo `mysqldump test_clinica > backup.sql` y prueba de restauración verificando RPO ≤24h y RTO ≤4h.

## 3. Ejecución
```bash
python3 testing/modulo5_comunidad_admin/test_modulo5.py
```
