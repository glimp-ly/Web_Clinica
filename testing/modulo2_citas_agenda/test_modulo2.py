#!/usr/bin/env python3
"""Suite automatizada del Módulo 2: Especialidades, Agenda y Citas Médicas."""

from __future__ import annotations

import datetime
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from common.api_client import ApiClient, ModuleTestRunner, run_db_cleanup


def main() -> int:
    runner = ModuleTestRunner("Módulo 2 — Especialidades y Citas Médicas")
    print(f"\n=== {runner.module_title} ===")

    created_cita_ids: list[int] = []
    created_codigos: list[str] = []
    noshow_applied = False
    future_date = (datetime.date.today() + datetime.timedelta(days=15)).isoformat()
    reprog_date = (datetime.date.today() + datetime.timedelta(days=16)).isoformat()
    soon_date = datetime.date.today().isoformat()

    try:
        def tc01():
            c = ApiClient()
            st, data = c.get("/api/especialidades")
            assert st == 200 and len(data.get("especialidades", [])) >= 12, f"{st} {data}"
            return data["especialidades"][0]["clave"]

        esp_clave = runner.check("TC-M2-01", "[RF-C08] Consulta de catálogo de especialidades", tc01) or "cardio"

        def tc02():
            c = ApiClient()
            st_bad, _ = c.get("/api/citas/ocupadas?medico_id=0&fecha=bad")
            assert st_bad == 422, f"Esperaba 422, obtuvo {st_bad}"
            st_ok, data = c.get(f"/api/citas/ocupadas?medico_id=1&fecha={future_date}")
            assert st_ok == 200 and "horas_ocupadas" in data and "bloqueos" in data, f"{st_ok} {data}"

        runner.check("TC-M2-02", "[RF-C05/RF-B03] Consulta de disponibilidad horaria /citas/ocupadas", tc02)

        pac = ApiClient()
        assert pac.login("carlos.ramirez@gmail.com", "Paciente123!")[0] == 200

        base_payload = {
            "dni": "71234561",
            "numero_cel": "987654321",
            "email": "carlos.ramirez@gmail.com",
            "especialidad": esp_clave,
            "medico_id": 1,
            "fecha_inicio": future_date,
            "hora_cita": "10:30",
            "turno": "manana",
            "motivo_consulta": "Control QA Módulo 2",
            "acepto_terminos": True,
        }

        def tc03():
            st, data = pac.post("/api/citas", base_payload)
            assert st == 201 and data.get("ok") is True, f"{st} {data}"
            created_cita_ids.append(data["cita"]["id"])
            created_codigos.append(data["cita"]["codigo_cita"])
            adm = ApiClient()
            assert adm.login("admin1@centenario.pe", "Admin123!")[0] == 200
            st_r, rep = adm.get("/api/reportes/resumen")
            assert st_r == 200 and any(data["cita"]["codigo_cita"] in n["asunto"] for n in rep["notificaciones"]), "Notificación no encolada"
            return data["cita"]

        cita = runner.check("TC-M2-03", "[RF-C05/RF-C07] Reserva de cita médica (201) y notificación encolada", tc03)

        def tc04():
            st, data = pac.post("/api/citas", base_payload)
            assert st == 409 and data.get("ok") is False, f"{st} {data}"

        runner.check("TC-M2-04", "[RF-C05/RF-B04] Prevención de solape mismo médico/hora (409)", tc04)

        def tc05():
            assert cita is not None
            st, data = pac.get("/api/mis-citas")
            assert st == 200 and any(x["codigo_cita"] == cita["codigo_cita"] for x in data["citas"]), f"{st} {data}"

        runner.check("TC-M2-05", "[RF-C06/RF-B01] Visualización de nueva cita en /api/mis-citas", tc05)

        def tc06():
            assert cita is not None
            st, data = pac.put(f"/api/citas/{cita['id']}", {
                "accion": "reprogramar",
                "fecha_cita": reprog_date,
                "hora_cita": "11:00",
                "turno": "manana",
            })
            assert st == 200 and data.get("ok") is True, f"{st} {data}"

        runner.check("TC-M2-06", "[RF-C06] Reprogramación válida con >24h de anticipación (200)", tc06)

        def tc07():
            soon_payload = dict(base_payload, fecha_inicio=soon_date, hora_cita="23:30", turno="tarde")
            st_c, d_c = pac.post("/api/citas", soon_payload)
            assert st_c == 201, f"{st_c} {d_c}"
            cid = d_c["cita"]["id"]
            created_cita_ids.append(cid)
            created_codigos.append(d_c["cita"]["codigo_cita"])
            st_del, d_del = pac.delete(f"/api/citas/{cid}")
            assert st_del == 422 and "24 horas" in d_del.get("message", ""), f"{st_del} {d_del}"
            return cid

        soon_cid = runner.check("TC-M2-07", "[RF-C06] Regla 24h bloquea cancelación tardía del paciente (422)", tc07)

        def tc08():
            assert cita is not None
            med = ApiClient()
            assert med.login("juan.perez@centenario.pe", "Medico123!")[0] == 200
            st_est, d_est = med.put(f"/api/citas/{cita['id']}", {
                "estado": "en_atencion",
                "observaciones_atencion": "Paciente ingresó a consultorio QA",
            })
            assert st_est == 200 and d_est.get("ok") is True, f"{st_est} {d_est}"
            st_der, d_der = med.post(f"/api/citas/{cita['id']}/derivar", {
                "especialidad_id": 2,
                "fecha_cita": reprog_date,
                "hora_cita": "12:00",
                "motivo": "Interconsulta QA Módulo 2",
            })
            assert st_der == 201 and d_der.get("id"), f"{st_der} {d_der}"
            created_cita_ids.append(d_der["id"])
            created_codigos.append(d_der["codigo_cita"])

        runner.check("TC-M2-08", "[RF-M03/RF-M07] Cambio a en_atencion y derivación interconsulta", tc08)

        def tc09():
            nonlocal noshow_applied
            assert soon_cid is not None and cita is not None
            med = ApiClient()
            assert med.login("juan.perez@centenario.pe", "Medico123!")[0] == 200
            st_ns, d_ns = med.put(f"/api/citas/{soon_cid}", {"estado": "no_show"})
            assert st_ns == 200 and d_ns.get("ok") is True, f"{st_ns} {d_ns}"
            noshow_applied = True
            st_del, d_del = pac.delete(f"/api/citas/{cita['id']}")
            assert st_del == 200 and d_del.get("ok") is True, f"{st_del} {d_del}"

        runner.check("TC-M2-09", "[RF-M03/RF-C06] Registro no_show (penalidad TyC) y cancelación >24h", tc09)

        def tc10():
            c = ApiClient()
            for view in ("/agendar_cita.html", "/mis_citas.html", "/especialidades.html", "/Servicios.html"):
                st, _ = c.get(view)
                assert st == 200, f"Vista {view} respondió {st}"

        runner.check("TC-M2-10", "[RF-C01/RF-C08] Accesibilidad HTTP 200 de vistas de citas y servicios", tc10)

        def tc11():
            past_payload = dict(base_payload, fecha_inicio="2020-01-15", hora_cita="09:00", turno="manana")
            st, data = pac.post("/api/citas", past_payload)
            if st == 201 and isinstance(data, dict) and data.get("cita"):
                created_cita_ids.append(int(data["cita"]["id"]))
                created_codigos.append(str(data["cita"]["codigo_cita"]))
            assert st == 422, f"NO CUMPLE RF-D03 (fecha pasada): permitió agendar el 2020-01-15 con HTTP {st}"

        runner.check("TC-M2-11", "[RF-D03/RF-C05] Bloqueo de reserva de cita en fecha pasada (422)", tc11)

        def tc12():
            assert cita is not None
            med = ApiClient()
            assert med.login("juan.perez@centenario.pe", "Medico123!")[0] == 200
            st_dup, d_dup = med.post(f"/api/citas/{cita['id']}/derivar", {
                "especialidad_id": 2,
                "fecha_cita": reprog_date,
                "hora_cita": "12:00",
                "motivo": "Interconsulta duplicada en slot ya ocupado QA",
            })
            if st_dup == 201 and isinstance(d_dup, dict) and d_dup.get("id"):
                created_cita_ids.append(int(d_dup["id"]))
                created_codigos.append(str(d_dup["codigo_cita"]))
            assert st_dup == 409, f"NO CUMPLE RF-D04 (solape en derivación): esperaba 409, obtuvo {st_dup}"

        runner.check("TC-M2-12", "[RF-D04/RF-M07] Control de solape de agenda al derivar interconsulta (409)", tc12)
    finally:
        cleanup_stmts: list[str] = []
        if noshow_applied:
            cleanup_stmts.append('$db->prepare("UPDATE pacientes SET penalidad_noshow = GREATEST(0, penalidad_noshow - 20.00), deuda_pendiente = GREATEST(0, deuda_pendiente - 20.00) WHERE dni = ?")->execute(["71234561"]);')
        if created_cita_ids:
            ids_csv = ",".join(str(i) for i in created_cita_ids)
            cods_sql = ",".join(f"'{c}'" for c in created_codigos)
            cleanup_stmts.append(f'$db->exec("DELETE FROM notificaciones WHERE referencia_id IN ({ids_csv}) AND referencia_tipo IN (\'cita\', \'derivacion\')");')
            cleanup_stmts.append(f'$db->exec("DELETE FROM citas WHERE id IN ({ids_csv})");')
            cleanup_stmts.append(f'$db->exec("DELETE FROM auditoria_logs WHERE recurso_id IN ({cods_sql})");')
        if cleanup_stmts:
            run_db_cleanup("".join(cleanup_stmts))

    return runner.summary()


if __name__ == "__main__":
    raise SystemExit(main())
