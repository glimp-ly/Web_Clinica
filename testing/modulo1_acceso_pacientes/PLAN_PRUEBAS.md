# Plan de Pruebas — Módulo 1: Acceso, Perfil y Pacientes (Integrante 1)

## 1. Alcance y Requerimientos (`REQUERIMIENTOS.md`)
- **RF**: `RF-C01`, `RF-C02`, `RF-C03`, `RF-C04`, `RF-C14`, `RF-C15`, `RF-B02`, `RF-B03`, `RF-B06`
- **RNF**: `RNF-01`, `RNF-02`, `RNF-06`, `RNF-07`, `RNF-10`
- **Backend**: `backend_clinica/src/Modules/Modulo1_AccesoPacientes/` (`Routes.php`, `AuthController`, `PerfilController`, `PacienteController`)
- **Frontend**: `index.html`, `login.html`, `register.html`, `formulario_datos.html`, `Qsomos.html`, `privacidad.html`, `TerCondiciones.html` (`layout.js`, `script-lr.js`, `script_form.js`)
- **Tablas MariaDB**: `usuarios`, `pacientes`, `auditoria_logs`, `notificaciones`

## 2. Matriz de Casos de Prueba (10 Automatizados + RNF Manuales)

| ID | Requerimiento | Tipo | Caso de Prueba | Resultado Esperado |
| :--- | :---: | :--- | :--- | :--- |
| `TC-M1-01` | `RF-C02`, `RNF-06` | Validación / Positivo | Rechazo de contraseña débil (`422`) y registro de paciente (`POST /api/auth/register`) | `422` en `"123456"`; `201` con clave segura, cookie JWT HttpOnly y `redirect: formulario_datos.html` |
| `TC-M1-02` | `RF-C02` | Negativo | Correo duplicado en registro | `409 Conflict`, `ok: false` |
| `TC-M1-03` | `RF-C02`, `RF-B02` | Seguridad | Login con contraseña incorrecta | `401 Unauthorized` |
| `TC-M1-04` | `RF-C02` | Positivo | Login válido + `GET /api/auth/me` + `logout` | `200`, `redirect: mis_citas.html`, sesión activa y cierre limpio |
| `TC-M1-05` | `RF-C03` | Positivo | Cuentas Google OAuth y login federado (`POST /api/auth/google`) | `200`, sesión creada con rol correspondiente |
| `TC-M1-06` | `RF-C03` | Positivo | Recuperación y reset de clave con token `<1h` | `200` en `forgot-password`, `reset-password` y nuevo `login` |
| `TC-M1-07` | `RF-C04`, `RF-C15`, `RF-B03` | Validación / Positivo | Ficha médica inválida (`422`), registro válido (`201`) y `GET /api/perfil` | `422` si faltan DNI/consentimiento; `201` y perfil actualizado |
| `TC-M1-08` | `RNF-06` | Seguridad | Rate-limit de login tras 5 intentos fallidos | Intentos 1–4 `401`; intento 5 devuelve `429` con bloqueo temporal |
| `TC-M1-09` | `RF-B02`, `RF-B06`, `RNF-07` | Seguridad / ARCO | RBAC `GET /api/pacientes` (`403`) + revocar consentimiento (`PUT`) + anonimizar (`DELETE /api/perfil`) | `403` en listado, `200` ARCO y sesión invalidada (`401`) |
| `TC-M1-10` | `RF-C01`, `RF-C14`, `RNF-10` | Smoke / Legal | Acceso HTTP `200` a vistas de inicio, acceso y legales | `200` en todas las vistas asignadas |
| `TC-M1-11` | `RF-D01` (`RF-C03`) | Defecto (`NO CUMPLE`) | Login federado con Facebook (`POST /api/auth/facebook`) | **Esperado**: `200` creando/vinculando cuenta · **Actual**: `404` (endpoint no implementado) |
| `TC-M1-12` | `RF-D02` (`RNF-06`) | Defecto (`NO CUMPLE`) | Revocación de JWT en servidor tras `POST /api/auth/logout` (reuso de cookie `centenario_token`) | **Esperado**: `401 Unauthorized` al reusar el token · **Actual**: `200` (JWT sin blacklist en servidor) |

### Verificaciones RNF Complementarias (Manuales / Herramientas)
- `RNF-01`: Probar viewport `375px`, `768px`, `1366px` (menú hamburguesa ≤850px, sin scroll-x) y Lighthouse Usabilidad ≥90.
- `RNF-02`: Auditoría `axe` WCAG AA (contraste ≥4.5:1, foco visible, `lang="es"`).
- `RF-C15`: Verificar mapa embebido de Google Maps y persistencia del tema oscuro (`localStorage.darkMode`).

## 3. Ejecución
```bash
python3 testing/modulo1_acceso_pacientes/test_modulo1.py
```
