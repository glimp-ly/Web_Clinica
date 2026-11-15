# Sistema Web — Clínica Centenario del Norte

Plataforma web integral de gestión y atención hospitalaria para la **Clínica Centenario del Norte**, compuesta por una arquitectura desacoplada por roles (**Público / Landing**, **Paciente**, **Médico** y **Administrador**), frontend web unificado (incluyendo modo oscuro) y API REST completa en PHP 8.3 conectada a MariaDB.

---

## Estructura del Repositorio

```text
Web_Clinica/
├── Dockerfile                # Imagen PHP 8.3 + Apache en puerto 8989 (frontend_clinica + Alias /api)
├── docker-compose.yml        # Orquestación del servicio web (puerto 8989 y conexión a MariaDB)
├── docker/
│   └── 000-default.conf      # VirtualHost Apache: DocumentRoot en frontend_clinica y Alias /api en backend_clinica/public
├── USUARIOS_PRUEBA.md        # Cuentas precargadas de prueba (credenciales y Google OAuth) y datos demo
├── database/
│   └── schema.sql            # Esquema completo MariaDB (19 tablas en test_clinica + seed data)
├── backend_clinica/          # Backend PHP 8.3 desacoplado (arquitectura modular por capas)
│   ├── config/
│   │   └── database.php      # Conexión PDO MariaDB (unix socket / host)
│   ├── public/
│   │   ├── .htaccess         # Reescritura de rutas /api/* hacia public/index.php
│   │   └── index.php         # Front controller / autoloader PSR-4 (zona horaria America/Lima)
│   └── src/
│       ├── Router.php        # Enrutador REST hacia controladores de dominio
│       ├── Core/             # Request, Response, JwtAuth (HS256 + cookies HttpOnly), AuditService
│       └── Controllers/      # Auth, Perfil, Paciente, Especialidad, Medico, Cita, Receta, Farmacia, Laboratorio, Campana, AtencionPublica y ReporteController
└── frontend_clinica/         # Frontend web 100% estático (HTML, CSS, JS e imágenes)
    ├── .htaccess             # Reescritura amigable /medico y /admin
    ├── css/
    │   ├── styles.css        # Estilos globales, header/footer y tema claro/oscuro unificados
    │   ├── portales.css      # Shell y componentes de los portales Paciente, Médico y Admin
    │   └── ...               # Hojas de estilo por vista
    ├── js/
    │   ├── layout.js         # Gestión de sesión, menú adaptativo por rol, modal Google OAuth y tema oscuro
    │   ├── admin.js          # Lógica del Panel de Administración (reportes, CRUD y auditoría)
    │   ├── medico.js         # Lógica del Portal Médico (agenda, historia clínica, recetas, lab y disponibilidad)
    │   ├── mis_citas.js      # Portal del Paciente (citas 24h, recetas, resultados lab y boletas)
    │   ├── agendar_cita.js   # Reserva de citas con consulta de horarios ocupados/bloqueados
    │   └── ...               # Scripts por módulo (farmacia, laboratorio, campañas, staff, FAQ, contacto)
    ├── img/                  # Recursos gráficos del sitio y catálogo
    ├── index.html            # Landing pública e ingreso al sistema
    ├── login.html / register.html
    ├── mis_citas.html        # Portal del Paciente
    ├── agendar_cita.html     # Reserva de citas médicas
    ├── formulario_datos.html # Ficha médica del paciente (consentimiento Ley N° 29733 y EPS)
    ├── farmacia.html         # Farmacia virtual con cobertura EPS y validación de recetas
    ├── Laboratorio.html      # Catálogo de análisis clínicos y consulta de resultados por código
    ├── campañas.html         # Campañas preventivas con control atómico de cupos
    ├── medico.html           # Portal Médico Especialista (workspace con barra lateral)
    ├── admin.html            # Panel de Administración General (workspace con barra lateral)
    └── especialidades.html / staff_medico.html / Servicios.html / Blog.html / faq.html / Contacto.html / Qsomos.html / privacidad.html / TerCondiciones.html
```

---

## Desacoplamiento y Funcionalidades por Rol

1. **Landing Pública (`index.html`)**:
   - Punto de entrada informativo y de acceso al sistema (`Iniciar Sesión` / `Registrarse`), sin exponer paneles ni rutas internas a visitantes sin sesión.
   - Al autenticarse (por credenciales o Google OAuth), cada usuario es redirigido automáticamente a su pantalla correspondiente:
     - **Paciente** → `mis_citas.html` (o `formulario_datos.html` si aún no registra ficha médica).
     - **Médico** → `medico.html`.
     - **Administrador** → `admin.html`.
2. **Portal del Paciente (`mis_citas.html` y subvistas)**:
   - Barra de submenús enfocada en sus servicios (`Mis Citas y Lab`, `Agendar Cita`, `Farmacia`, `Laboratorio`, `Campañas`, `Mi Ficha Médica`).
   - Agendamiento en tiempo real con validación de choques (`409`) y visualización de horarios ocupados (`/api/citas/ocupadas`), reprogramación/cancelación respetando la regla de 24 horas, recetas digitales, resultados de laboratorio y boletas de farmacia con descuento EPS.
3. **Portal Médico (`medico.html`)**:
   - Workspace con barra lateral de 5 módulos (`Agenda y Atención`, `Historia Clínica`, `Recetas y Órdenes Lab`, `Resultados Lab`, `Mi Disponibilidad`).
   - Visualización inmediata de las citas reservadas por pacientes (tanto del día como próximas), transición de estados (`en_atencion`, `finalizada`, `no_show`), derivación interconsulta, registro de evoluciones CIE-10 y bloqueo de turnos/ausencias.
4. **Panel de Administración (`admin.html`)**:
   - Workspace con barra lateral de 7 módulos (`Dashboard y Reportes`, `Médicos y Horarios`, `Campañas de Salud`, `Inventario Farmacia`, `Órdenes Laboratorio`, `FAQ y Afiliaciones`, `Auditoría Ley 29733`), exportación CSV y bitácora de auditoría/notificaciones.

---

## Base de Datos MariaDB

El archivo [`database/schema.sql`](database/schema.sql) crea la base de datos **`test_clinica`** con **19 tablas**:
`usuarios`, `pacientes`, `especialidades`, `medicos`, `medico_disponibilidad`, `citas`, `historias_clinicas`, `recetas`, `productos_farmacia`, `pedidos_farmacia`, `pedido_items`, `catalogo_laboratorio`, `ordenes_laboratorio`, `campanas`, `campana_inscripciones`, `preguntas_faq`, `contactos_afiliacion`, `notificaciones` y `auditoria_logs`.

Incluye datos de prueba precargados (2 administradores, 5 médicos con acceso web + 4 especialistas adicionales de staff, 5 pacientes con fichas y EPS, 12 especialidades, productos farmacéuticos, catálogo de laboratorio, citas, recetas y campañas).

---

## Endpoints Principales de la API (`/api`)

- **Autenticación y Perfil**: `/auth/register`, `/auth/login`, `/auth/google-accounts`, `/auth/google`, `/auth/logout`, `/auth/me`, `/auth/forgot-password`, `/auth/reset-password`, `/perfil` (incluye derechos ARCO).
- **Clínico y Agenda**: `/especialidades`, `/medicos`, `/medicos/mi-resumen`, `/medicos/mi-disponibilidad`, `/citas/ocupadas`, `/citas`, `/mis-citas`, `/citas/{id}/derivar`, `/pacientes`, `/pacientes/{dni}/historia`, `/recetas`.
- **Farmacia y Laboratorio**: `/productos`, `/pedidos`, `/laboratorio/catalogo`, `/ordenes-lab`, `/resultados/{codigo}`.
- **Comunidad y Administración**: `/campanas`, `/campanas/{id}/inscribir`, `/preguntas`, `/contactos`, `/reportes/resumen`.

---

## Despliegue con Docker (Puerto `8989`)

1. Cargar el esquema y los datos de prueba en MariaDB (`database/schema.sql`).
2. Levantar el contenedor desde la raíz del proyecto:

```bash
docker compose up -d --build
```

3. Acceder en el navegador a **`http://localhost:8989/index.html`**.

Para probar inmediatamente los tres roles (credenciales directas o cuentas Google de prueba), consulta [`USUARIOS_PRUEBA.md`](USUARIOS_PRUEBA.md).
