#!/usr/bin/env python3
"""Suite automatizada del Módulo 1: Acceso, Perfil, ARCO y Fichas de Pacientes."""

from __future__ import annotations

import os
import sys
import uuid

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from common.api_client import ApiClient, ModuleTestRunner, run_db_cleanup


def main() -> int:
    runner = ModuleTestRunner("Módulo 1 — Acceso, Perfil y Pacientes")
    print(f"\n=== {runner.module_title} ===")

    tag = uuid.uuid4().hex[:6]
    test_email = f"qa.m1.{tag}@centenario.test"
    test_dni = "89" + str(int(tag, 16) % 1000000).zfill(6)
    test_user_id: int | None = None

    try:
        def tc01():
            c = ApiClient()
            st_weak, _ = c.post("/api/auth/register", {
                "email": f"weak.{tag}@centenario.test",
                "password": "123456",
                "confirmPassword": "123456",
            })
            assert st_weak == 422, f"Contraseña débil debe rechazarse con 422, obtuvo {st_weak}"
            st, data = c.post("/api/auth/register", {
                "email": test_email,
                "password": "QaPassword123!",
                "confirmPassword": "QaPassword123!",
                "nombre": "Usuario QA Modulo 1",
            })
            assert st == 201 and data.get("ok") is True, f"{st} {data}"
            assert data["user"]["rol"] == "paciente"
            assert data["redirect"] == "formulario_datos.html"
            return data["user"]["id"]

        test_user_id = runner.check("TC-M1-01", "[RF-C02/RNF-06] Validación clave fuerte (422) y registro paciente (201)", tc01)

        def tc02():
            c = ApiClient()
            st, data = c.post("/api/auth/register", {
                "email": test_email,
                "password": "QaPassword123!",
                "confirmPassword": "QaPassword123!",
            })
            assert st == 409 and data.get("ok") is False, f"{st} {data}"

        runner.check("TC-M1-02", "[RF-C02] Rechazo de correo duplicado en registro (409)", tc02)

        def tc03():
            c = ApiClient()
            st, data = c.login("carlos.ramirez@gmail.com", "ClaveIncorrecta!")
            assert st == 401 and data.get("ok") is False, f"{st} {data}"

        runner.check("TC-M1-03", "[RF-C02/RF-B02] Rechazo de login con credenciales inválidas (401)", tc03)

        def tc04():
            c = ApiClient()
            st, data = c.login("carlos.ramirez@gmail.com", "Paciente123!")
            assert st == 200 and data["redirect"] == "mis_citas.html", f"{st} {data}"
            st_me, me = c.get("/api/auth/me")
            assert st_me == 200 and me["user"]["email"] == "carlos.ramirez@gmail.com", f"{st_me} {me}"
            c.logout()

        runner.check("TC-M1-04", "[RF-C02] Login paciente válido, sesión activa /auth/me y redirección", tc04)

        def tc05():
            c = ApiClient()
            st, data = c.get("/api/auth/google-accounts")
            assert st == 200 and len(data.get("accounts", [])) >= 3, f"{st} {data}"
            st_g, d_g = c.post("/api/auth/google", {
                "email": "carlos.ramirez@gmail.com",
                "nombre": "Carlos Alberto Ramírez Soto",
                "google_id": "google_paciente_1",
            })
            assert st_g == 200 and d_g.get("ok") is True and d_g["user"]["rol"] == "paciente", f"{st_g} {d_g}"
            c.logout()

        runner.check("TC-M1-05", "[RF-C03] Cuentas Google OAuth y autenticación federada (/auth/google)", tc05)

        def tc06():
            c = ApiClient()
            st1, d1 = c.post("/api/auth/forgot-password", {"email": test_email})
            assert st1 == 200 and d1.get("demo_token"), f"{st1} {d1}"
            st2, d2 = c.post("/api/auth/reset-password", {
                "token": d1["demo_token"],
                "password": "NuevaPassword123!",
            })
            assert st2 == 200 and d2.get("ok") is True, f"{st2} {d2}"
            st3, d3 = c.login(test_email, "NuevaPassword123!")
            assert st3 == 200 and d3.get("ok") is True, f"{st3} {d3}"

        runner.check("TC-M1-06", "[RF-C03] Recuperación y restablecimiento de contraseña con token (<1h)", tc06)

        def tc07():
            c = ApiClient()
            assert c.login(test_email, "NuevaPassword123!")[0] == 200
            st_bad, _ = c.post("/api/pacientes", {"nombre": "A", "dni": "123"})
            assert st_bad == 422, f"Esperaba 422, obtuvo {st_bad}"
            st_ok, data = c.post("/api/pacientes", {
                "nombre": "Camila QA",
                "apellidos": "Pruebas Modulo Uno",
                "dni": test_dni,
                "edad": 29,
                "fecha_nacimiento": "1997-05-10",
                "genero": "f",
                "telefono": "987111222",
                "direccion": "Av. Túpac Amaru 123",
                "departamento": "Lima",
                "provincia": "Lima",
                "distrito": "Independencia",
                "nombre_emergencia": "Luis",
                "apellido_emergencia": "Pruebas",
                "parentesco": "Hermano",
                "telefono_emergencia": "987333444",
                "tipo_sangre": "O+",
                "seguro": "si",
                "aseguradora": "Rímac EPS",
                "numero_poliza": "POL-QA-01",
                "acepto_terminos": True,
                "consentimiento_datos": True,
            })
            assert st_ok == 201 and data.get("dni") == test_dni, f"{st_ok} {data}"
            st_perf, perf = c.get("/api/perfil")
            assert st_perf == 200 and perf["data"]["ficha"]["dni"] == test_dni, f"{st_perf} {perf}"

        runner.check("TC-M1-07", "[RF-C04/RF-C15/RF-B03] Validación 422, ficha médica EPS y perfil", tc07)

        def tc08():
            c = ApiClient()
            for intento in range(1, 5):
                st, _ = c.login(test_email, "ClaveMalaQA!")
                assert st == 401, f"Intento {intento} esperaba 401, obtuvo {st}"
            st5, d5 = c.login(test_email, "ClaveMalaQA!")
            assert st5 == 429, f"Intento 5 esperaba bloqueo 429, obtuvo {st5} {d5}"
            st_r1, d_r1 = c.post("/api/auth/forgot-password", {"email": test_email})
            assert st_r1 == 200 and d_r1.get("demo_token")
            assert c.post("/api/auth/reset-password", {"token": d_r1["demo_token"], "password": "NuevaPassword123!"})[0] == 200

        runner.check("TC-M1-08", "[RNF-06] Rate-limit login: 5 intentos fallidos bloquean cuenta (429)", tc08)

        def tc09():
            c = ApiClient()
            assert c.login(test_email, "NuevaPassword123!")[0] == 200
            st_forb, _ = c.get("/api/pacientes")
            assert st_forb == 403, f"Paciente no debe listar pacientes: {st_forb}"
            st_put, d_put = c.put("/api/perfil", {"consentimiento_datos": False})
            assert st_put == 200 and d_put.get("ok") is True, f"{st_put} {d_put}"
            st_del, d_del = c.delete("/api/perfil")
            assert st_del == 200 and d_del.get("ok") is True, f"{st_del} {d_del}"
            st_me, _ = c.get("/api/auth/me")
            assert st_me == 401, f"Tras anonimizar debe cerrarse sesión: {st_me}"

        runner.check("TC-M1-09", "[RF-B02/RF-B06/RNF-07] RBAC /pacientes (403) y derechos ARCO /perfil", tc09)

        def tc10():
            c = ApiClient()
            for view in ("/index.html", "/login.html", "/register.html", "/formulario_datos.html", "/Qsomos.html", "/privacidad.html", "/TerCondiciones.html"):
                st, _ = c.get(view)
                assert st == 200, f"Vista {view} respondió {st}"

        runner.check("TC-M1-10", "[RF-C01/RF-C14/RNF-10] Accesibilidad HTTP 200 de vistas de acceso y legales", tc10)

        def tc11():
            c = ApiClient()
            st, data = c.post("/api/auth/facebook", {
                "email": f"fb.{tag}@centenario.test",
                "nombre": "Usuario Facebook QA",
                "facebook_id": f"fb_{tag}",
            })
            assert st == 200 and data.get("ok") is True, f"NO CUMPLE RF-D01 (OAuth Facebook): esperaba 200, obtuvo {st} {data}"

        runner.check("TC-M1-11", "[RF-D01/RF-C03] Login federado con Facebook (/api/auth/facebook)", tc11)

        def tc12():
            c = ApiClient()
            st_l, d_l = c.login("carlos.ramirez@gmail.com", "Paciente123!")
            assert st_l == 200 and d_l.get("token"), f"Login falló: {st_l} {d_l}"
            token = d_l["token"]
            assert c.logout()[0] == 200
            anon = ApiClient()
            st_me, d_me = anon.get("/api/auth/me", extra_headers={"Cookie": f"centenario_token={token}"})
            assert st_me == 401, f"NO CUMPLE RF-D02 (revocación JWT): cookie JWT previa a logout siguió devolviendo {st_me} ({d_me.get('user', {}).get('email')})"

        runner.check("TC-M1-12", "[RF-D02/RNF-06] Revocación de JWT en servidor tras logout (401)", tc12)
    finally:
        if test_user_id is not None:
            run_db_cleanup(
                f'$db->prepare("DELETE FROM notificaciones WHERE referencia_tipo = \'auth\' AND referencia_id = ?")->execute([{test_user_id}]);'
                f'$db->prepare("DELETE FROM pacientes WHERE dni = ? OR usuario_id = ?")->execute(["{test_dni}", {test_user_id}]);'
                f'$db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([{test_user_id}]);'
                f'$db->prepare("DELETE FROM auditoria_logs WHERE usuario_id = ? OR recurso_id = ?")->execute([{test_user_id}, "{test_dni}"]);'
            )

    return runner.summary()


if __name__ == "__main__":
    raise SystemExit(main())
