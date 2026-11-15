# Cuentas de Acceso y Usuarios de Prueba
## Sistema Web de Gestión y Atención — Clínica Centenario del Norte

Este documento detalla los usuarios de prueba precargados en la base de datos MariaDB (`test_clinica`) para probar los 3 roles del sistema (**Paciente**, **Médico** y **Administrador**) a través de los dos métodos de autenticación implementados:
1. **Credenciales Directas** (Correo Electrónico + Contraseña).
2. **Federación Google OAuth** (Selector interactivo con cuentas preconfiguradas o correo Google personalizado).

---

## 🌐 URLs de Acceso del Sistema (Puerto 8989)

| Pantalla | URL Directa | Roles / Alcance |
| :--- | :--- | :--- |
| **Landing Pública / Inicio** | `http://localhost:8989/index.html` | Entrada pública y acceso al sistema (`Iniciar Sesión` / `Registrarse`) |
| **Login Unificado** | `http://localhost:8989/login.html` | Redirige automáticamente según rol (`Paciente` → `mis_citas.html`, `Médico` → `medico.html`, `Admin` → `admin.html`) |
| **Portal del Paciente** | `http://localhost:8989/mis_citas.html` | Pacientes autenticados (submenús: Citas, Agendar, Farmacia, Lab, Campañas, Mi Ficha) |
| **Portal Médico Especialista** | `http://localhost:8989/medico.html` | Exclusivo Médicos (`medico`) y Administradores |
| **Panel de Administración** | `http://localhost:8989/admin.html` | Exclusivo Administradores (`admin`) |

---

## 1. 🛡️ Administradores de Prueba (2 Cuentas)

| # | Nombre Completo | Correo Institucional | Contraseña | Google OAuth ID | Cargo / Función |
| :-: | :--- | :--- | :--- | :--- | :--- |
| **1** | Roberto Mendoza | `admin1@centenario.pe` | `Admin123!` | `google_admin_1` | Dirección Administrativa |
| **2** | Lucía Vargas | `admin2@centenario.pe` | `Admin123!` | `google_admin_2` | Jefatura de Sistemas |

---

## 2. 🩺 Médicos de Prueba (5 Cuentas con Acceso Web)

| # | Nombre Completo | Correo Institucional | Contraseña | CMP | DNI | Especialidad | Google OAuth ID |
| :-: | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | Dr. Juan Pérez | `juan.perez@centenario.pe` | `Medico123!` | `CMP-45101` | `40112233` | Cardiología | `google_medico_1` |
| **2** | Dra. María Rodríguez | `maria.rodriguez@centenario.pe` | `Medico123!` | `CMP-48202` | `40223344` | Obstetricia | `google_medico_2` |
| **3** | Dr. Carlos Gómez | `carlos.gomez@centenario.pe` | `Medico123!` | `CMP-39303` | `40334455` | Medicina General | `google_medico_3` |
| **4** | Dra. Ana López | `ana.lopez@centenario.pe` | `Medico123!` | `CMP-51404` | `40445566` | Pediatría | `google_medico_4` |
| **5** | Dr. Luis Martínez | `luis.martinez@centenario.pe` | `Medico123!` | `CMP-46505` | `40556677` | Ginecología | `google_medico_5` |

*(Además existen 4 especialistas adicionales en catálogo público de staff sin cuenta de login web: Dra. Sofía Torres, Dr. Pedro Sánchez, Dra. Elena Díaz y Dr. Miguel Ángel).*

---

## 3. 👤 Pacientes de Prueba (5 Cuentas)

| # | Nombre Completo | Correo Electrónico | Contraseña | DNI | Seguro / EPS | Google OAuth ID |
| :-: | :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | Carlos Alberto Ramírez Soto | `carlos.ramirez@gmail.com` | `Paciente123!` | `71234561` | Rímac EPS (80% cobertura) | `google_paciente_1` |
| **2** | Elena Patricia Castro Vera | `elena.castro@gmail.com` | `Paciente123!` | `72345672` | Pacífico EPS (70% cobertura) | `google_paciente_2` |
| **3** | Jorge Luis Chávez Díaz | `jorge.chavez@gmail.com` | `Paciente123!` | `73456783` | Particular (Sin EPS) | `google_paciente_3` |
| **4** | Valeria Fernanda Ríos Mendoza | `valeria.rios@gmail.com` | `Paciente123!` | `74567894` | Mapfre EPS (75% cobertura) | `google_paciente_4` |
| **5** | Diego Alejandro Paredes Silva | `diego.paredes@gmail.com` | `Paciente123!` | `75678905` | La Positiva EPS (60% cobertura) | `google_paciente_5` |

---

## 4. 🔑 Datos de Prueba Precargados en MariaDB

### A. Órdenes y Resultados de Laboratorio
- **`LAB-2026-001`**: Perfil Lipídico + Glucosa (Paciente: Carlos Alberto Ramírez Soto, DNI `71234561`). Estado: `publicado`.
- **`LAB-2026-002`**: Hematología + Orina Completa (Paciente: Elena Patricia Castro Vera, DNI `72345672`). Estado: `publicado`.
- **`LAB-2026-003`**: Glucosa + Perfil Lipídico (Paciente: Jorge Luis Chávez Díaz, DNI `73456783`). Estado: `en_proceso`.

### B. Recetas Médicas Electrónicas
- **`REC-2026-001`**: Emitida por Dr. Juan Pérez para Carlos Ramírez (DNI `71234561`). Incluye *Amoxicilina 500mg* y *Paracetamol 500mg*. Estado: `vigente`.
- **`REC-2026-002`**: Emitida por Dra. María Rodríguez para Elena Castro (DNI `72345672`). Incluye *Amoxicilina 500mg* y *Vitamina C 1000mg*. Estado: `vigente`.
- **`REC-2026-003`**: Emitida por Dr. Carlos Gómez para Jorge Chávez (DNI `73456783`). Incluye *Ibuprofeno 400mg* y *Jarabe para la Tos*. Estado: `vigente`.

### C. Citas Médicas Iniciales
- **`CIT-2026-0001`**: Carlos Alberto Ramírez Soto con Dr. Juan Pérez (Cardiología, hoy 09:30, `programada`).
- **`CIT-2026-0002`**: Elena Patricia Castro Vera con Dra. María Rodríguez (Obstetricia, hoy 10:30, `programada`).
- **`CIT-2026-0003`**: Jorge Luis Chávez Díaz con Dr. Carlos Gómez (Medicina General, hoy 11:15, `en_atencion`).
- **`CIT-2026-0004`**: Valeria Fernanda Ríos Mendoza con Dr. Luis Martínez (Ginecología, hoy 12:00, `programada`).
- **`CIT-2026-0005`**: Diego Alejandro Paredes Silva con Dra. Ana López (Pediatría, hoy 15:30, `programada`).
- **`CIT-2026-0006`**: Carlos Alberto Ramírez Soto con Dr. Carlos Gómez (Medicina General, +4 días 10:00, `programada`).
- **`CIT-2026-0007`**: Elena Patricia Castro Vera con Dr. Juan Pérez (Cardiología, +3 días 16:00, `programada` - interconsulta).
- **`CIT-2026-0008`**: Carlos Alberto Ramírez Soto con Dr. Juan Pérez (Cardiología, -5 días 09:00, `finalizada`).
- **`CIT-2026-0009`**: Jorge Luis Chávez Díaz con Dr. Juan Pérez (Cardiología, -2 días 15:00, `no_show`).

---

## 5. 🐳 Despliegue en Docker (Puerto 8989)

```bash
cd /home/glimp/Web_Clinica
docker compose up -d
```

Verificación rápida:
```bash
docker ps --filter "name=clinica_centenario_web"
curl -I http://localhost:8989/index.html
```
