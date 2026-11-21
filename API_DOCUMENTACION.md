# Documentación de la API REST — Clínica Centenario del Norte

Documentación completa de los **56 endpoints/operaciones HTTP** del backend PHP 8.3 ([`backend_clinica/`](backend_clinica/)), organizados en los **5 módulos funcionales** del sistema.

---

## 1. Información General

- **URL Base (Docker local)**: `http://localhost:8989/api`
- **Formato de intercambio**: `application/json; charset=utf-8` (las rutas también aceptan formularios `POST` estándar en `Request::body()`).
- **Zona horaria del servidor**: `America/Lima` (`UTC-5`).
- **Preflight CORS**: `OPTIONS /api/*` devuelve `204 No Content`.
- **Autenticación y Sesión**:
  - Basada en **JWT (HS256)** firmado en servidor (`App\Core\JwtAuth`).
  - Se entrega automáticamente en cookie **`HttpOnly`** al iniciar sesión o registrarse, y también se admite mediante encabezado `Authorization: Bearer <token>`.
  - **Roles del sistema**: `paciente`, `medico`, `admin` (y acceso `Público` para catálogos/formularios abiertos).

### Códigos de Estado HTTP Estándar

| Código | Significado en el Sistema |
| :---: | :--- |
| `200` | Operación o consulta exitosa (`ok: true`). |
| `201` | Recurso creado correctamente (usuario, ficha, cita, receta, pedido, orden, campaña, pregunta, contacto). |
| `204` | Respuesta a solicitud `OPTIONS`. |
| `401` | No autenticado, token inválido/expirado o credenciales incorrectas. |
| `403` | Autenticado sin permisos suficientes (control RBAC por rol o acceso a datos clínicos de terceros). |
| `404` | Recurso o endpoint no encontrado. |
| `405` | Método HTTP no soportado para el recurso. |
| `409` | Conflicto de negocio/concurrencia (correo duplicado, solape de cita, bloqueo horario, stock insuficiente, cupo lleno o DNI ya inscrito). |
| `422` | Error de validación de datos o regla de negocio (incluye objeto `errors` por campo; p. ej. regla de 24h o falta de receta médica). |
| `429` | Bloqueo temporal por seguridad tras 5 intentos fallidos de login (15 minutos). |
| `500` | Error interno no controlado del servidor o base de datos. |

---

## 2. Resumen por Módulo

| Módulo | Carpeta Backend | Controladores | Operaciones HTTP |
| :--- | :--- | :--- | :---: |
| **Módulo 1: Acceso, Perfil y Pacientes** | `src/Modules/Modulo1_AccesoPacientes/` | `AuthController`, `PerfilController`, `PacienteController` | **13** |
| **Módulo 2: Citas y Agenda** | `src/Modules/Modulo2_CitasAgenda/` | `EspecialidadController`, `CitaController` | **8** |
| **Módulo 3: Portal Médico, Historia y Recetas** | `src/Modules/Modulo3_PortalMedico/` | `MedicoController`, `HistoriaClinicaController`, `RecetaController` | **13** |
| **Módulo 4: Farmacia y Laboratorio** | `src/Modules/Modulo4_FarmaciaLaboratorio/` | `FarmaciaController`, `LaboratorioController` | **10** |
| **Módulo 5: Comunidad y Administración** | `src/Modules/Modulo5_ComunidadAdmin/` | `CampanaController`, `AtencionPublicaController`, `ReporteController` | **12** |
| **Total** | | **13 controladores** | **56** |

---

## 3. Módulo 1 — Acceso, Perfil y Pacientes (13 operaciones)

**Enrutador**: [`backend_clinica/src/Modules/Modulo1_AccesoPacientes/Routes.php`](backend_clinica/src/Modules/Modulo1_AccesoPacientes/Routes.php)

### Autenticación (`AuthController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `POST` | `/api/auth/register` | Público | Registra un usuario con rol `paciente`, guarda hash `bcrypt`, emite JWT HttpOnly y devuelve `redirect: formulario_datos.html`. **Body**: `email`, `password` (≥6), `confirmPassword`, `nombre` (opcional). | `201`, `409`, `422` |
| `POST` | `/api/auth/login` | Público | Inicia sesión con credenciales. Aplica **rate-limit** (5 intentos fallidos bloquean la cuenta 15 min) y devuelve `redirect` según rol (`mis_citas.html`, `formulario_datos.html`, `medico.html` o `admin.html`). **Body**: `email`, `password`. | `200`, `401`, `422`, `429` |
| `GET` | `/api/auth/google-accounts` | Público | Lista las cuentas demo preconfiguradas para el selector interactivo de Google OAuth. | `200` |
| `POST` | `/api/auth/google` | Público | Autentica o crea/vincula cuenta federada Google OAuth y emite sesión JWT. **Body**: `email`, `nombre`, `google_id` (opcional). | `200`, `422` |
| `POST` | `/api/auth/forgot-password` | Público | Genera token temporal de recuperación (vigencia 1 hora) y encola notificación por email. **Body**: `email`. | `200`, `422` |
| `POST` | `/api/auth/reset-password` | Público | Actualiza contraseña usando token vigente y reinicia bloqueos/intentos fallidos. **Body**: `token`, `password` (≥6). | `200`, `422` |
| `POST` | `/api/auth/logout` | Sesión / Público | Registra cierre de sesión en auditoría y elimina la cookie JWT. | `200` |
| `GET` | `/api/auth/me` | Autenticado | Devuelve los datos del usuario autenticado actual (`user`, más ficha de `paciente` o `medico`). | `200`, `401` |

### Perfil y Derechos ARCO — Ley N° 29733 (`PerfilController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/perfil` | Autenticado | Devuelve el perfil completo del usuario autenticado (`user`, más `ficha` si es paciente o `medico` si es médico). | `200`, `401` |
| `PUT` | `/api/perfil` | Autenticado | Actualiza nombre y/o otorga/revoca el consentimiento de datos personales (`consentimiento_datos`: boolean, auditado). **Body**: `nombre`, `consentimiento_datos`. | `200`, `401` |
| `DELETE` | `/api/perfil` | Autenticado | Ejerce derecho ARCO de cancelación/anonimización: anonimiza datos personales en `pacientes`, desactiva `usuarios`, audita el evento y cierra sesión. | `200`, `401` |

### Fichas de Pacientes (`PacienteController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/pacientes` | `medico`, `admin` | Lista fichas de pacientes. **Query opcional**: `?search=<dni|nombre|apellidos>`. | `200`, `401`, `403` |
| `POST` | `/api/pacientes` | Público / Paciente | Crea o actualiza ficha médica completa (DNI 8 dígitos, edad 0–120, teléfono 9 dígitos, ubigeo, contacto de emergencia, tipo de sangre, alergias/enfermedades/cirugías, seguro EPS y checkboxes obligatorios de TyC y consentimiento Ley 29733). | `201`, `422` |

---

## 4. Módulo 2 — Citas y Agenda (8 operaciones)

**Enrutador**: [`backend_clinica/src/Modules/Modulo2_CitasAgenda/Routes.php`](backend_clinica/src/Modules/Modulo2_CitasAgenda/Routes.php)

### Especialidades (`EspecialidadController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/especialidades` | Público | Lista todas las especialidades médicas activas con clave, descripción y precio de consulta. | `200` |

### Citas Médicas (`CitaController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/citas/ocupadas` | Público | Consulta horarios ya reservados y bloques/ausencias del médico en una fecha para evitar choques en el formulario. **Query obligatoria**: `?medico_id=<id>&fecha=YYYY-MM-DD`. | `200`, `422` |
| `GET` | `/api/citas` | Autenticado / DNI | Lista citas filtradas automáticamente según rol (paciente ve solo las suyas, médico solo las asignadas, admin todas). **Query opcional**: `?dni=XXXXXXXX`. | `200`, `401` |
| `GET` | `/api/mis-citas` | `paciente` / DNI | Alias orientado al portal del paciente para consultar su historial y citas programadas. **Query opcional** (si no hay sesión): `?dni=XXXXXXXX`. | `200`, `401` |
| `POST` | `/api/citas` | Público / Autenticado | Agenda una cita con transacción `FOR UPDATE` (valida bloqueos médicos y evita solape mismo médico/fecha/hora → `409`), crea ficha mínima si el DNI no existe, genera código `CIT-YYYY-XXXXX` y encola notificación WhatsApp/Email. **Body**: `dni`, `numero_cel`, `email`, `especialidad`, `medico_id`, `fecha_inicio`, `hora_cita`, `turno`, `motivo_consulta`, `acepto_terminos`. | `201`, `409`, `422` |
| `PUT` | `/api/citas/{id}` | Autenticado | **Dos modos**: (1) **Reprogramación** (`accion: "reprogramar"` o rol `paciente`): exige >24h de anticipación y valida solape (`409`). **Body**: `fecha_cita`, `hora_cita`, `turno`. (2) **Atención clínica** (`medico`, `admin`): cambia `estado` (`programada`, `en_atencion`, `finalizada`, `no_show`, `cancelada`). Al pasar a `finalizada` exige historia clínica u `observaciones_atencion`; en `no_show` aplica penalidad administrativa de **S/ 20.00**. | `200`, `401`, `403`, `404`, `409`, `422` |
| `DELETE` | `/api/citas/{id}` | Autenticado | Cancela la cita (`estado = "cancelada"`), envía notificación y audita. Si el rol es `paciente`, aplica la **regla de 24 horas** (`422` si faltan menos de 24h). | `200`, `401`, `404`, `422` |
| `POST` | `/api/citas/{id}/derivar` | `medico`, `admin` | Genera una nueva cita de interconsulta (`DER-YYYY-XXXXX`) vinculada a `derivada_de_cita_id` hacia otra especialidad/médico y notifica al paciente. **Body**: `especialidad_id`, `medico_id` (opcional), `fecha_cita`, `hora_cita`, `motivo`. | `201`, `401`, `403`, `404`, `422` |

---

## 5. Módulo 3 — Portal Médico, Historia Clínica y Recetas (13 operaciones)

**Enrutador**: [`backend_clinica/src/Modules/Modulo3_PortalMedico/Routes.php`](backend_clinica/src/Modules/Modulo3_PortalMedico/Routes.php)

### Portal Médico, Staff y Disponibilidad (`MedicoController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/medicos/mi-resumen` | `medico`, `admin` | Devuelve el dashboard consolidado del médico: perfil, `citas_hoy`, `citas_proximas`, `todas_citas`, `disponibilidad`, `mis_pacientes`, `ordenes_lab`, `recetas` y `estadisticas` (atendidas, pendientes, no-shows, horas acumuladas). **Query opcional** (admin): `?medico_id=<id>`. | `200`, `401`, `403` |
| `GET` | `/api/medicos/mi-disponibilidad` | `medico`, `admin` | Lista los bloques de disponibilidad, bloqueos y ausencias del médico. | `200`, `401`, `403` |
| `POST` | `/api/medicos/mi-disponibilidad` | `medico`, `admin` | Registra un bloqueo o ausencia por fecha/turno/rango horario y/o actualiza turnos generales (`turno_manana`, `turno_tarde`, `dias_atencion`). **Body**: `fecha`, `turno`, `hora_inicio`, `hora_fin`, `estado` (`disponible`/`bloqueado`/`ausencia`), `motivo`. | `200`, `401`, `403` |
| `PUT` | `/api/medicos/mi-disponibilidad` | `medico`, `admin` | Equivalente a `POST /api/medicos/mi-disponibilidad` para actualización de turnos y bloqueos. | `200`, `401`, `403` |
| `DELETE` | `/api/medicos/mi-disponibilidad/{id}` | `medico`, `admin` | Elimina un bloque/ausencia específico de la agenda médica. | `200`, `401`, `403` |
| `GET` | `/api/medicos` | Público (extendido `admin`) | Lista el staff médico activo. **Query opcional**: `?search=<texto>&especialidad=<clave|id>&all=1` (`all=1` muestra inactivos solo si es `admin`). | `200` |
| `POST` | `/api/medicos` | `admin` | Crea un nuevo médico en el staff (y su cuenta de acceso si se incluye `email`). **Body**: `nombre`, `cmp`, `dni`, `especialidad_id`, `email` (opcional), `password` (opcional), `descripcion`, `experiencia_anios`, `telefono`, `turno_manana`, `turno_tarde`, `dias_atencion`. | `201`, `401`, `403`, `409`, `422` |
| `PUT` | `/api/medicos/{id}` | `admin` | Actualiza datos, especialidad, horarios o estado `activo` de un médico. | `200`, `401`, `403` |
| `DELETE` | `/api/medicos/{id}` | `admin` | Desactiva lógicamente a un médico del catálogo (`activo = 0`). | `200`, `401`, `403` |

### Historia Clínica (`HistoriaClinicaController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/pacientes/{dni}/historia` | `medico` tratante, `admin`, titular `paciente` | Devuelve ficha del paciente, evoluciones clínicas (`entradas`), `recetas` y `ordenes_lab`. Aplica control estricto: un médico solo accede si tiene cita o registro previo con el paciente; toda lectura queda auditada. | `200`, `401`, `403`, `404` |
| `POST` | `/api/pacientes/{dni}/historia` | `medico` tratante, `admin` | Registra una nueva evolución clínica con código **CIE-10**, actualiza opcionalmente `alergias`/`enfermedades` y audita el evento. **Body**: `motivo`, `anamnesis`, `diagnostico`, `tratamiento` (obligatorios); `diagnostico_cie10`, `examen_fisico`, `signos_vitales`, `evolucion`, `cita_id`. | `201`, `401`, `403`, `404`, `422` |

### Recetas Digitales (`RecetaController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/recetas` | Autenticado | Lista recetas digitales (paciente solo ve las de su DNI; médico/admin pueden filtrar con `?dni=XXXXXXXX`). | `200`, `401` |
| `POST` | `/api/recetas` | `medico`, `admin` | Emite una receta digital vigente por 15 días (`REC-YYYY-XXXXX`) y notifica por correo al paciente. **Body**: `dni_paciente`, `diagnostico`, `medicamentos` (array), `indicaciones`, `cita_id` (opcional). | `201`, `401`, `403`, `404`, `422` |

---

## 6. Módulo 4 — Farmacia y Laboratorio (10 operaciones)

**Enrutador**: [`backend_clinica/src/Modules/Modulo4_FarmaciaLaboratorio/Routes.php`](backend_clinica/src/Modules/Modulo4_FarmaciaLaboratorio/Routes.php)

### Farmacia Virtual (`FarmaciaController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/productos` | Público (extendido `admin`) | Lista productos farmacéuticos con precio, stock e indicador `requiere_receta`. **Query opcional**: `?search=<texto>&all=1` (inactivos solo `admin`). | `200` |
| `POST` | `/api/productos` | `admin` | Agrega un producto al catálogo de farmacia. **Body**: `nombre`, `precio` (>0), `categoria`, `stock`, `requiere_receta`, `imagen`. | `201`, `401`, `403`, `422` |
| `PUT` | `/api/productos/{id}` | `admin` | Actualiza precio, stock, categoría, `requiere_receta` o estado `activo` de un producto (auditado). | `200`, `401`, `403` |
| `GET` | `/api/pedidos` | Autenticado | Lista boletas/pedidos de farmacia con sus `items` (paciente ve solo los propios; médico/admin los últimos 100). | `200`, `401` |
| `POST` | `/api/pedidos` | Público / Autenticado | Procesa checkout transaccional (`FOR UPDATE`): descuenta stock atómicamente (`409` si no alcanza), valida código de receta vigente (`422` si incluye medicamentos con `requiere_receta=1`), calcula cobertura **EPS** del paciente y emite boleta electrónica `BOL-FYYYY-XXXXX`. **Body**: `dni`, `nombre`, `metodo_pago`, `codigo_receta`, `usar_eps`, `items` (`[{id, quantity}]`). | `201`, `404`, `409`, `422` |

### Laboratorio Clínico (`LaboratorioController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/laboratorio/catalogo` | Público | Lista el catálogo de análisis clínicos activos con precio, preparación, tiempo de resultados y valores de referencia. | `200` |
| `GET` | `/api/ordenes-lab` | Autenticado | Lista órdenes de laboratorio según rol (paciente propias, médico asignadas o de sus pacientes, admin todas). | `200`, `401` |
| `POST` | `/api/ordenes-lab` | Público / Autenticado | Registra una orden de laboratorio (`LAB-YYYY-XXXXX`) y boleta (`BOL-LYYYY-XXXXX`) en estado `pendiente_muestra`. **Body**: `dni_paciente`, `nombre_paciente`, `metodo_pago`, `analisis` (`[{nombre, cantidad}]`), `cita_id` (opcional). | `201`, `422` |
| `PUT` | `/api/ordenes-lab/{id}` | `medico`, `admin` | Actualiza estado (`pendiente_muestra`, `en_proceso`, `publicado`) y publica resultados clínicos (`resultado_resumen`, `valores_resultado`, `observaciones_lab`). | `200`, `401`, `403` |
| `GET` | `/api/resultados/{codigo}` | Titular (`paciente` o `?dni=`), `medico` tratante, `admin` | Consulta y descarga segura de resultados por código (`LAB-...`). Bloquea terceros no autorizados con `403` y registra auditoría de lectura o intento denegado. **Query opcional**: `?dni=XXXXXXXX`. | `200`, `403`, `404` |

---

## 7. Módulo 5 — Comunidad, Atención Pública y Administración (12 operaciones)

**Enrutador**: [`backend_clinica/src/Modules/Modulo5_ComunidadAdmin/Routes.php`](backend_clinica/src/Modules/Modulo5_ComunidadAdmin/Routes.php)

### Salud del Servicio

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/health` *(alias `GET /api/`)* | Público | Verifica disponibilidad del servicio API y conectividad activa con MariaDB (`SELECT 1`). | `200`, `500` |

### Campañas de Salud (`CampanaController`)

> Nota: Todas las rutas `/api/campanas*` también aceptan el alias con eñe `/api/campañas*`.

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/campanas` | Público | Lista campañas de salud (`activa`, `pronto`, `finalizada`) con `cupos_totales`, `cupos_ocupados` y `cupos_disponibles`. | `200` |
| `POST` | `/api/campanas` | `admin` | Crea una nueva campaña de salud. **Body**: `titulo`, `descripcion`, `icono`, `fecha_inicio`, `fecha_fin`, `rango_fechas_texto`, `cupos_totales` (>0), `estado`. | `201`, `401`, `403`, `422` |
| `PUT` | `/api/campanas/{id}` | `admin` | Modifica datos, cupos o estado de una campaña existente (auditado). | `200`, `401`, `403` |
| `POST` | `/api/campanas/{id}/inscribir` | Público / Autenticado | Inscribe a un ciudadano descontando cupo de forma atómica (`FOR UPDATE`). Bloquea campañas inactivas, cupos agotados o DNI duplicado (`409`) y envía confirmación por correo. **Body**: `dni` (8 dígitos), `nombre`, `email`. | `201`, `404`, `409`, `422` |

### Preguntas Frecuentes (FAQ) y Afiliaciones (`AtencionPublicaController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/preguntas` | Público | Lista preguntas frecuentes y consultas enviadas por los usuarios. | `200` |
| `POST` | `/api/preguntas` | Público | Registra una nueva pregunta en estado `pendiente`. **Body**: `nombre` (≥3), `email`, `pregunta` (≥10). | `201`, `422` |
| `PUT` | `/api/preguntas/{id}` | `admin` | Publica respuesta oficial y cambia estado a `respondida` (auditado). **Body**: `respuesta`. | `200`, `401`, `403`, `422` |
| `GET` | `/api/contactos` | `admin` | Lista solicitudes de afiliación / contacto recibidas desde la web. | `200`, `401`, `403` |
| `POST` | `/api/contactos` | Público | Registra una solicitud de afiliación en estado `ENVIADO`. **Body**: `dni` (8 dígitos), `celular` (9 dígitos), `correo`, `checkLegal` (true). | `201`, `422` |
| `PUT` | `/api/contactos/{id}` | `admin` | Actualiza estado (`ENVIADO`, `EN_SEGUIMIENTO`, `AFILIADO`, `DESCARTADO`) y `notas_admin`. | `200`, `401`, `403` |

### Reportes y Auditoría (`ReporteController`)

| Método | Ruta | Acceso | Descripción y Parámetros | Códigos |
| :---: | :--- | :--- | :--- | :--- |
| `GET` | `/api/reportes/resumen` *(cualquier `GET /api/reportes*`)* | `admin` | Devuelve el consolidado ejecutivo: `citas_resumen`, `citas_por_especialidad`, `ventas_farmacia`, `ventas_laboratorio`, `ingresos_totales`, `campanas`, últimos 60 registros de `auditoria`, últimas 40 `notificaciones` y listado de `usuarios`. | `200`, `401`, `403` |

---

## 8. Ejemplos Rápidos de Uso (`curl`)

### Iniciar sesión (guardando cookie JWT)
```bash
curl -i -c cookies.txt -X POST http://localhost:8989/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"carlos.ramirez@gmail.com","password":"Paciente123!"}'
```

### Consultar citas del paciente autenticado
```bash
curl -b cookies.txt http://localhost:8989/api/mis-citas
```

### Reservar una cita médica
```bash
curl -b cookies.txt -X POST http://localhost:8989/api/citas \
  -H "Content-Type: application/json" \
  -d '{
    "dni":"71234561",
    "numero_cel":"987654321",
    "email":"carlos.ramirez@gmail.com",
    "especialidad":"cardio",
    "medico_id":1,
    "fecha_inicio":"2026-12-10",
    "hora_cita":"10:30",
    "turno":"manana",
    "motivo_consulta":"Control cardiológico",
    "acepto_terminos":true
  }'
```

### Consultar salud del backend
```bash
curl http://localhost:8989/api/health
```
