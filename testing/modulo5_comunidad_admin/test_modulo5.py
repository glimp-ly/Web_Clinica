#!/usr/bin/env python3
"""Suite automatizada del Módulo 5: Campañas, FAQ, Afiliaciones, Reportes y Admin."""

from __future__ import annotations

import os
import sys
import uuid

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from common.api_client import ApiClient, ModuleTestRunner, run_db_cleanup


def main() -> int:
    runner = ModuleTestRunner("Módulo 5 — Comunidad, Atención Pública y Administración")
    print(f"\n=== {runner.module_title} ===")

    tag = uuid.uuid4().hex[:6]
    test_dni = "95" + str(int(tag, 16) % 1000000).zfill(6)
    test_campana_id: int | None = None
    created_campana_id: int | None = None
    created_faq_id: int | None = None
    created_contacto_id: int | None = None

    try:
        def tc01():
            c = ApiClient()
            st, data = c.get("/api/health")
            assert st == 200 and data.get("status") == "healthy", f"{st} {data}"

        runner.check("TC-M5-01", "[RNF-05/RF-B01] Verificación de salud del backend y BD (/api/health)", tc01)

        def tc02():
            c = ApiClient()
            st, data = c.get("/api/campanas")
            assert st == 200 and len(data.get("campanas", [])) >= 1, f"{st} {data}"
            activa = next((x for x in data["campanas"] if x["estado"] == "activa" and x["cupos_disponibles"] > 0), None)
            assert activa is not None, "No hay campaña activa con cupos disponibles"
            return int(activa["id"])

        test_campana_id = runner.check("TC-M5-02", "[RF-C11] Consulta pública de campañas de salud y cupos", tc02)

        def tc03():
            assert test_campana_id is not None
            c = ApiClient()
            payload = {"dni": test_dni, "nombre": "Participante QA Campaña", "email": f"qa.camp.{tag}@test.pe"}
            st1, d1 = c.post(f"/api/campanas/{test_campana_id}/inscribir", payload)
            assert st1 == 201 and d1.get("ok") is True, f"{st1} {d1}"
            st2, d2 = c.post(f"/api/campanas/{test_campana_id}/inscribir", payload)
            assert st2 == 409 and d2.get("ok") is False, f"{st2} {d2}"

        runner.check("TC-M5-03", "[RF-C11/RF-B04] Inscripción a campaña (201) y DNI duplicado (409)", tc03)

        def tc04():
            c = ApiClient()
            st_bad, _ = c.post("/api/preguntas", {"nombre": "A", "email": "bad", "pregunta": "corta"})
            assert st_bad == 422, f"Esperaba 422, obtuvo {st_bad}"
            st_ok, data = c.post("/api/preguntas", {
                "nombre": "Visitante QA",
                "email": f"qa.faq.{tag}@test.pe",
                "pregunta": "¿Cuál es el horario de atención de cardiología QA?",
            })
            assert st_ok == 201 and data.get("id"), f"{st_ok} {data}"
            return int(data["id"])

        created_faq_id = runner.check("TC-M5-04", "[RF-C12/RF-B03] Envío de pregunta FAQ con validación (422 y 201)", tc04)

        def tc05():
            c = ApiClient()
            st, data = c.post("/api/contactos", {
                "dni": test_dni,
                "celular": "987654321",
                "correo": f"qa.afiliacion.{tag}@test.pe",
                "checkLegal": True,
            })
            assert st == 201 and data.get("estado") == "ENVIADO", f"{st} {data}"
            return int(data["id"])

        created_contacto_id = runner.check("TC-M5-05", "[RF-C13] Registro público de solicitud de afiliación (/api/contactos)", tc05)

        def tc06():
            pac = ApiClient()
            assert pac.login("carlos.ramirez@gmail.com", "Paciente123!")[0] == 200
            st, _ = pac.get("/api/reportes/resumen")
            assert st == 403, f"Paciente no debe acceder a reportes admin: {st}"

        runner.check("TC-M5-06", "[RF-B02/RF-B07] RBAC bloquea acceso no-admin a /api/reportes/resumen (403)", tc06)

        adm = ApiClient()
        assert adm.login("admin1@centenario.pe", "Admin123!")[0] == 200

        def tc07():
            assert created_faq_id is not None and created_contacto_id is not None
            st_faq, d_faq = adm.put(f"/api/preguntas/{created_faq_id}", {"respuesta": "Atendemos de 8am a 8pm (QA)."})
            assert st_faq == 200 and d_faq.get("ok") is True, f"{st_faq} {d_faq}"
            st_con, d_con = adm.put(f"/api/contactos/{created_contacto_id}", {
                "estado": "EN_SEGUIMIENTO",
                "notas_admin": "Contactado por equipo QA",
            })
            assert st_con == 200 and d_con.get("ok") is True, f"{st_con} {d_con}"

        runner.check("TC-M5-07", "[RF-C12/RF-C13/RF-B07] Respuesta admin a FAQ y seguimiento de afiliaciones", tc07)

        def tc08():
            st, rep = adm.get("/api/reportes/resumen")
            assert st == 200 and rep.get("ok") is True, f"{st} {rep}"
            for key in ("citas_resumen", "citas_por_especialidad", "ventas_farmacia", "ventas_laboratorio", "auditoria", "usuarios"):
                assert key in rep, f"Falta bloque {key} en reportes"
            assert len(rep["auditoria"]) >= 1, "Debe registrar logs de auditoría"

        runner.check("TC-M5-08", "[RF-B06/RF-B07/RNF-09] Reportes KPIs, ventas y trazas de auditoría", tc08)

        def tc09():
            st_c, d_c = adm.post("/api/campanas", {
                "titulo": f"Campaña QA {tag}",
                "descripcion": "Campaña sintética de verificación CRUD",
                "cupos_totales": 25,
                "estado": "activa",
            })
            assert st_c == 201 and d_c.get("id"), f"{st_c} {d_c}"
            cid = int(d_c["id"])
            st_u, _ = adm.put(f"/api/campanas/{cid}", {"cupos_totales": 30})
            assert st_u == 200, f"Error actualizando campaña: {st_u}"
            return cid

        created_campana_id = runner.check("TC-M5-09", "[RF-B07] CRUD admin de campañas de salud (POST y PUT)", tc09)

        def tc10():
            c = ApiClient()
            for view in ("/campa%C3%B1as.html", "/Blog.html", "/faq.html", "/Contacto.html", "/admin.html", "/admin"):
                st, _ = c.get(view)
                assert st == 200, f"Vista {view} respondió {st}"

        runner.check("TC-M5-10", "[RF-C11/RF-C12/RF-C13] Accesibilidad HTTP 200 de vistas comunidad y admin", tc10)
    finally:
        cleanup_stmts: list[str] = []
        if test_campana_id:
            cleanup_stmts.append(f'$st = $db->prepare("DELETE FROM campana_inscripciones WHERE campana_id = ? AND dni = ?"); $st->execute([{test_campana_id}, "{test_dni}"]); if ($st->rowCount() > 0) {{ $db->prepare("UPDATE campanas SET cupos_ocupados = GREATEST(0, cupos_ocupados - 1) WHERE id = ?")->execute([{test_campana_id}]); }}')
            cleanup_stmts.append(f'$db->prepare("DELETE FROM notificaciones WHERE destinatario = ?")->execute(["qa.camp.{tag}@test.pe"]);')
        if created_campana_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM campanas WHERE id = ?")->execute([{created_campana_id}]);')
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso = \'campanas\' AND recurso_id = ?")->execute(["{created_campana_id}"]);')
        if created_faq_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM preguntas_faq WHERE id = ?")->execute([{created_faq_id}]);')
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso = \'preguntas_faq\' AND recurso_id = ?")->execute(["{created_faq_id}"]);')
        if created_contacto_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM contactos_afiliacion WHERE id = ?")->execute([{created_contacto_id}]);')
        if cleanup_stmts:
            run_db_cleanup("".join(cleanup_stmts))

    return runner.summary()


if __name__ == "__main__":
    raise SystemExit(main())
