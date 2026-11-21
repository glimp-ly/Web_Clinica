# ERS v2 — Web Clínica Centenario del Norte (con Backend + Portal Cliente y Portal Médico)

> Uso: testing funcional / no funcional — cada requerimiento puede resultar CUMPLE / NO CUMPLE.

## 1. Estructura actual (as-is) y brecha hacia to-be

### 1.1 Inventario actual
Vistas: `index.html`, `login.html`, `register.html`, `formulario_datos.html`, `agendar_cita.html`, `Servicios.html`, `especialidades.html`, `staff_medico.html`, `farmacia.html`, `Laboratorio.html`, `Blog.html`, `campañas.html`, `faq.html`, `Contacto.html`, `Qsomos.html`, `privacidad.html`, `TerCondiciones.html`. Lógica en `js/` (jQuery, validaciones solo cliente, catálogos hardcodeados: 8 productos en `js/farmacia.js:2-59`, 9 médicos en `js/staff_medico.js:17-27`, 8 especialidades en `js/especialidades.js:2-11`, 6 campañas en `js/campañas.js:119-126`). Destinos `agendar_cita.php` (`agendar_cita.html:101`) y `procesar_registro.php` (`formulario_datos.html:16`)


## 2. Actores y alcance
- **Cliente/Paciente:** se registra, completa ficha, agenda/reprograma/cancela citas, compra farmacia/laboratorio, se inscribe a campañas, consulta resultados y blog/FAQ.
- **Médico:** gestiona agenda y disponibilidad, atiende citas, registra historia clínica, emite recetas/órdenes de laboratorio, visualiza ficha del paciente.
- **Admin/Recepción:** gestiona médicos, horarios, cupos, stock, campañas, reportes.
- **Sistemas externos:** pasarela pagos, WhatsApp/email, aseguradoras (EPS), Google Maps (ya embebido en `index.html:140-141`).

## 3. RF Portal Cliente (evolución del frontend actual + backend)

| ID | Requerimiento | Fuente as-is | Criterio de aceptación testing (frontend + backend) |
|---|---|---|---|
| RF-C01 | Navegación global 6 menús + responsive hamburguesa ≤850px + footer 4 columnas | `index.html:23-64,183-227`, `js/agendar_cita.js:110-150` | 200 en todos los enlaces; 375px abre `.main-nav`; footer con legales y redes externas |
| RF-C02 | Registro con email+password+confirmación y login con sesión | `login.html:13-77`, `js/script-lr.js:71-114` | Password≠confirm → bloquea; válido → `POST /api/auth/register` 201 + `POST /api/auth/login` 200 con JWT/HttpOnly; contraseña hasheada en BD, nunca en claro |
| RF-C03 | Recuperación de contraseña y login social | Link muerto "Olvidó su contraseña?", iconos `href="#"` en `login.html:25-34` | Flujo token por email expira <1h; OAuth Google/Facebook crea/vincula cuenta |
| RF-C04 | Ficha ampliada del paciente (datos, ubigeo, emergencia, salud, seguro, consentimiento) | `formulario_datos.html:16-226`, `js/script_form.js:2-117` | Validación cliente+servidor: DNI `^\d{8}$`, tel `^\d{9}$` distintos, tipo sangre, ubigeo en cascada; `POST /api/pacientes` 201; sin checkbox TyC/consentimiento → 422 |
| RF-C05 | Agendar cita (especialidad 7, rango fechas, turno, DNI/cel/email) con confirmación | `agendar_cita.html:101-169`, `js/agendar_cita.js:6-54` | Válido → `POST /api/citas` 201, aparece en Mis Citas y en agenda del médico; solape mismo médico/hora → 409; muestra modal éxito + notificación real |
| RF-C06 | Mis Citas: listar, reprogramar y cancelar (regla 24h, tolerancia 15 min) | Link muerto "Mis Citas"; regla en `TerCondiciones.html:72-85`, `faq.html:179-188` | `GET /api/mis-citas` lista; `PUT` reprograma si >24h; `DELETE` cancela si >24h sino 422; no-show genera penalidad según TyC |
| RF-C07 | Notificación real de cita (WhatsApp/email) | Modal promete aviso en `agendar_cita.html:185-186` | Tras 201 de cita se encola notificación; mock del proveedor recibe payload <60s |
| RF-C08 | Catálogos informativos: servicios, especialidades con modal, staff con buscador y perfil | `Servicios.html:92-124`, `js/especialidades.js:98-193`, `js/staff_medico.js:69-131` | `GET /api/especialidades`, `/api/medicos?search=`; buscador filtra en <200ms; modal con stats; sin resultados mensaje esperado |
| RF-C09 | Farmacia: vitrina, búsqueda, carrito, checkout con pago y boleta | `js/farmacia.js:2-179` (checkout no cobra) | Stock descontado en `POST /api/pedidos` 201; pago con tarjeta/Yape → 200 + boleta electrónica; sin stock → 409; Amoxicilina exige receta → 422 sin adjunto |
| RF-C10 | Laboratorio: catálogo con precio/preparación/resultados, carrito, pago y resultados online | `Laboratorio.html:106-239`, `js/script_laboratorio.js:91-288` | Compra genera orden; resultados publicados → paciente los descarga con código en <24h publicadas; `GET /api/resultados/{codigo}` 200 solo dueño |
| RF-C11 | Campañas: ver activas/próximas con countdown y cupos, inscribirse | `campañas.html:97-247`, `js/campañas.js:119-260` | `GET /api/campañas`; cupo lleno → botón bloqueado 409; inscripción `POST /api/campañas/{id}/inscribir` valida DNI≥8 y descuenta cupo atómicamente |
| RF-C12 | Blog expandible + FAQ acordeón + envío de pregunta | `Blog.html:83-195`, `faq.html:108-224` | `POST /api/preguntas` valida nombre≥3, email, pregunta≥10; admin la ve para responder |
| RF-C13 | Afiliación/contacto con validación y seguimiento | `Contacto.html:130-159`, `js/script_Contacto.js:100-126` | Válido → `POST /api/contactos` 201 + estado ENVIADO; DNI<8/cel<9/sin check → bloquea |
| RF-C14 | Institucional + legal (Qsomos, privacidad Ley 29733, TyC 7 secciones) | `Qsomos.html:103-163`, `privacidad.html:127-226`, `TerCondiciones.html:42-188` | Accesibles ≤2 clics desde footer; contenido versionado con fecha vigencia |
| RF-C15 | Perfil, ubicación/horarios, modo oscuro persistente | `index.html:132-159`, popups usuario, `js/agendar_cita.js:73-107` | `GET /api/perfil`; Maps carga; horarios coinciden con Backend; tema persiste con clave única |

## 4. RF Portal Médico (subsistema nuevo, necesario aunque no existe en HTML)

| ID | Requerimiento | Criterio de aceptación testing |
|---|---|---|
| RF-M01 | Login médico con rol y dashboard (mis citas del día, próximos, pendientes de historia) | Rol `medico` → 200 + redirección a `/medico`; paciente no accede → 403 |
| RF-M02 | Gestión de agenda y disponibilidad (bloques, turnos mañana/tarde, ausencias) | `PUT /api/medicos/mi-disponibilidad` 200; bloque ocupado no se oferta al cliente |
| RF-M03 | Atender cita: iniciar/pasar a en-atención/finalizar/no-show | Transiciones válidas; finalizar exige historia o motivo; no-show aplica regla TyC |
| RF-M04 | Historia clínica por paciente (alergias, enfermedades, cirugías, tipo sangre, seguro) | `GET /api/pacientes/{dni}/historia` solo médico asignado/admin → 200, otros → 403; cada escritura auditable |
| RF-M05 | Emitir receta digital y orden de laboratorio vinculadas a la cita | `POST /api/recetas`, `/api/ordenes-lab` 201; paciente las ve; farmacia valida receta |
| RF-M06 | Ver resultados de laboratorio de sus pacientes | Solo dueño del caso + médico tratante; trazabilidad de accesos |
| RF-M07 | Interconsulta / derivación a otra especialidad | Crea cita derivada con motivo; especialidad destino la recibe |
| RF-M08 | Mis estadísticas (consultas, horas, no-show) | `GET /api/medicos/mi-resumen` cuadra con BD |

## 5. RF Backend / Transversal (para que Cliente+Médico funcionen)

| ID | Requerimiento | Criterio |
|---|---|---|
| RF-B01 | API REST + BD persistente de todo (usuarios, fichas, citas, pedidos, campañas, historias) | Recargar no pierde datos; `GET` tras `POST` devuelve lo creado |
| RF-B02 | Roles y autorización (cliente/médico/admin) en cada endpoint | Matriz de permisos testeada: 401 sin token, 403 rol indebido |
| RF-B03 | Validación servidor espejo del cliente + manejo de errores estándar | Payload inválido → 422 con campo+mensaje; bypass por DevTools no persiste |
| RF-B04 | Concurrencia: cupos, citas y stock atómicos | 2 inscripciones último cupo → solo 1 con 201, otra 409; doble booking mismo slot → 409 |
| RF-B05 | Pagos, facturación electrónica y seguros EPS | Pago aprobado → pedido `pagado` + comprobante; gasto no cubierto → deuda del paciente (según `TerCondiciones.html:139-148`) |
| RF-B06 | Auditoría y consentimiento (quién vio/editó datos sensibles y cuándo) | Log con usuario+timestamp+acción; revocar consentimiento bloquea uso secundario |
| RF-B07 | Panel admin: CRUD médicos/horarios/campañas/stock, reportes citas/ventas | `GET /api/reportes/*` cuadra con BD; cambio de cupo se refleja en countdown |

## 6. RNF (con backend — métricas para CUMPLE/NO CUMPLE)

| ID | Tipo | RNF | Métrica testeable |
|---|---|---|---|
| RNF-01 | Producto/usabilidad | Responsive sin scroll-x, feedback <100ms, éxito ≤1.5s, ≤3 clics a cita/farmacia/lab | 375/768/1366px ok; Lighthouse usabilidad ≥90 |
| RNF-02 | Producto/accesibilidad | WCAG AA: contraste ≥4.5:1, foco visible, `lang="es"`, alt no vacío, teclado y lector | Auditoría axe 0 violaciones críticas |
| RNF-03 | Producto/rendimiento front | LCP <2.5s, página <1.5MB en 4G, búsqueda <200ms | Lighthouse rendimiento ≥85 |
| RNF-04 | Producto/rendimiento back | API p95 <500ms, countdown/carrusel sin jank | k6: p95 citas/pedidos <500ms con 100 VU |
| RNF-05 | Producto/fiabilidad | Disponibilidad ≥99.5%, degradación elegante sin CDN/imagen | Caída particles/jQuery no rompe flujo crítico |
| RNF-06 | Producto/seguridad | OWASP: auth JWT, hash bcrypt, validación servidor, HTTPS+CSP, sin datos en `localStorage`, rate-limit login | ZAP 0 altas; login 5 intentos → bloqueo temporal |
| RNF-07 | Producto/privacidad | Cifrado tránsito/reposo historia clínica, minimización, ARCO | Solo campos necesarios; `DELETE /api/perfil` anonimiza |
| RNF-08 | Organizacional/mantenibilidad | Un CSS/JS por módulo, clave tema única, 0 duplicados críticos, trazabilidad ID↔código↔prueba | Hoy NO CUMPLE: `darkMode` vs `theme`, `renderDoctors` duplicada en `js/staff_medico.js:34-103`, `name="fecha"` duplicado en `agendar_cita.html:141-142` |
| RNF-09 | Organizacional/observabilidad | Logs, métricas y trazas de API y notificaciones | Cada 201/409/422 deja log correlacionado |
| RNF-10 | Externo/legal | Ley 29733, TyC versionados, comprobantes electrónicos | Privacidad+TyC ≤2 clics; textos con responsable/finalidad/ARCO/24h/15min |
| RNF-11 | Externo/compatibilidad | Chrome/Edge/Firefox/Safari últimas 2, estático servible en subcarpeta | Matriz browser × viewport pasa |
| RNF-12 | Externo/recuperación | Backup RPO ≤24h RTO ≤4h para citas/pedidos/historias | Restore de prueba cumple RPO/RTO |

## 7. Trazabilidad para plan de pruebas

Cliente: `index→RF-C01/C15`, `login/register→RF-C02/C03`, `formulario→RF-C04`, `agendar→RF-C05/C06/C07`, `Servicios/especialidades/staff/Blog/faq/Qsomos/privacidad/TyC→RF-C08/C12/C14`, `farmacia→RF-C09`, `Laboratorio→RF-C10`, `campañas→RF-C11`, `Contacto→RF-C13`. Médico: sin HTML actual → RF-M01–M08 casos nuevos (hoy NO CUMPLE por ausencia, tras backend deben CUMPLIR). Backend: RF-B01–B07 + RNF-04–RNF-09–RNF-12.

## 8. Brechas as-is que el testing debe explotar
Sin API/BD/roles; auth ficticia; pagos/resultados/notificaciones prometidos sin implementar; cupos/stock en memoria; validación solo cliente; inconsistencias de código del §6 RNF-08.

---

## 9. Requerimientos con Estado `NO CUMPLE` por Módulo (Hallazgos para Reporte de Errores)

> Los siguientes 10 requerimientos/criterios de aceptación (2 por cada uno de los 5 módulos) están cubiertos por las suites automatizadas en `testing/modulo*/` y actualmente fallan (`NO CUMPLE`) frente al backend real, permitiendo documentar defectos reproducibles en el informe de testing del curso.

| ID Defecto | Módulo | RF / RNF Padre | Requerimiento / Criterio Esperado | Comportamiento Actual (`NO CUMPLE`) | Severidad | Caso de Prueba |
| :---: | :---: | :---: | :--- | :--- | :---: | :---: |
| `RF-D01` | **M1** | `RF-C03` | Autenticación federada con Facebook (`POST /api/auth/facebook`) debe crear/vincular cuenta y devolver `200` | El endpoint `/api/auth/facebook` no está implementado y devuelve `404` | Media | `TC-M1-11` |
| `RF-D02` | **M1** | `RF-C02`, `RNF-06` | Política de contraseñas seguras: rechazar (`422`) claves débiles menores a 8 caracteres o sin mayúsculas/números (ej. `"123456"`) | `POST /api/auth/register` solo exige 6 caracteres y acepta `"123456"` devolviendo `201` | Alta | `TC-M1-12` |
| `RF-D03` | **M2** | `RF-C05`, `RF-B03` | Impedir agendar citas médicas en fechas anteriores al día actual (`422`) | `POST /api/citas` acepta fechas pasadas (ej. `2020-01-15`) y crea la cita con `201` | Alta | `TC-M2-11` |
| `RF-D04` | **M2** | `RF-M07`, `RF-B04` | La derivación a interconsulta (`POST /api/citas/{id}/derivar`) debe validar solape de agenda del médico destino (`409`) | Inserta la cita derivada sin validar disponibilidad, generando doble reserva (`201`) | Alta | `TC-M2-12` |
| `RF-D05` | **M3** | `RF-M02`, `RF-B03` | Los bloques de disponibilidad médica deben validar que `hora_inicio < hora_fin` (`422`) | `POST /api/medicos/mi-disponibilidad` acepta rangos invertidos (`18:00` a `08:00`) con `200` | Media | `TC-M3-11` |
| `RF-D06` | **M3** | `RF-M05`, `RF-B03` | Al emitir receta vinculada a una cita (`cita_id`), validar que la cita exista en BD (`404`/`422`) | `POST /api/recetas` acepta `cita_id: 999999` inexistente y crea la receta con `201` | Media | `TC-M3-12` |
| `RF-D07` | **M4** | `RF-C09`, `RF-M05` | Una receta médica digital dispensada en farmacia debe marcarse como `dispensada` e impedir su reutilización (`422`/`409`) | `POST /api/pedidos` mantiene la receta en estado `vigente` y permite reutilizar `REC-2026-001` múltiples veces (`201`) | Alta | `TC-M4-11` |
| `RF-D08` | **M4** | `RF-C10`, `RF-B03` | Las órdenes de laboratorio deben rechazar cantidades `<= 0` y totales negativos (`422`) | `POST /api/ordenes-lab` acepta `cantidad: -2` y genera una orden con total negativo (`201`) | Alta | `TC-M4-12` |
| `RF-D09` | **M5** | `RF-B07`, `RF-B03` | Al crear una campaña de salud, validar que `fecha_fin >= fecha_inicio` (`422`) | `POST /api/campanas` acepta `fecha_fin` anterior a `fecha_inicio` devolviendo `201` | Media | `TC-M5-11` |
| `RF-D10` | **M5** | `RF-C12`, `RF-B03` | Responder una pregunta FAQ con un ID inexistente (`PUT /api/preguntas/{id}`) debe devolver `404` | Devuelve `200 OK` ("Respuesta publicada") incluso cuando el ID `999999` no existe | Baja | `TC-M5-12` |
