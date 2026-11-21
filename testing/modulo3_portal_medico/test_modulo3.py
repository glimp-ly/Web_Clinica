#!/usr/bin/env python3
"""Suite automatizada del Módulo 3: Portal Médico, Staff, Historia Clínica y Recetas."""

from __future__ import annotations

import datetime
import os
import sys
import uuid

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from common.api_client import ApiClient, ModuleTestRunner, run_db_cleanup


def main() -> int:
    runner = ModuleTestRunner("Módulo 3 — Portal Médico, Historia Clínica y Recetas")
    print(f"\n=== {runner.module_title} ===")

    created_disp_id: int | None = None
    created_bad_disp_id: int | None = None
    created_hc_id: int | None = None
    created_receta_id: int | None = None
    created_receta_code: str | None = None
    created_bad_receta_id: int | None = None
    created_bad_receta_code: str | None = None
    created_medico_id: int | None = None
    tag = uuid.uuid4().hex[:5]
    test_med_email = f"dr.qa.{tag}@centenario.test"
    block_date = (datetime.date.today() + datetime.timedelta(days=20)).isoformat()

    try:
        def tc01():
            c = ApiClient()
            st, data = c.get("/api/medicos?search=Juan")
            assert st == 200 and len(data.get("medicos", [])) >= 1, f"{st} {data}"

        runner.check("TC-M3-01", "[RF-C08] Consulta y búsqueda pública del staff médico (/api/medicos)", tc01)

        med = ApiClient()
        st_m, d_m = med.login("juan.perez@centenario.pe", "Medico123!")
        assert st_m == 200 and d_m["redirect"] == "medico.html"

        def tc02():
            st, data = med.get("/api/medicos/mi-resumen")
            assert st == 200 and data.get("ok") is True, f"{st} {data}"
            for key in ("medico", "citas_hoy", "citas_proximas", "todas_citas", "estadisticas"):
                assert key in data, f"Falta clave {key}"

        runner.check("TC-M3-02", "[RF-M01/RF-M08] Dashboard y estadísticas del médico (/medicos/mi-resumen)", tc02)

        def tc03():
            pac = ApiClient()
            assert pac.login("carlos.ramirez@gmail.com", "Paciente123!")[0] == 200
            st, _ = pac.get("/api/medicos/mi-resumen")
            assert st == 403, f"Paciente no debe acceder a mi-resumen: {st}"

        runner.check("TC-M3-03", "[RF-M01/RF-B02] RBAC bloquea acceso de paciente a /medicos/mi-resumen (403)", tc03)

        def tc04():
            st_post, d_post = med.post("/api/medicos/mi-disponibilidad", {
                "fecha": block_date,
                "turno": "tarde",
                "hora_inicio": "14:00:00",
                "hora_fin": "18:00:00",
                "estado": "bloqueado",
                "motivo": "Congreso QA Módulo 3",
            })
            assert st_post == 200 and d_post.get("ok") is True, f"{st_post} {d_post}"
            st_get, d_get = med.get("/api/medicos/mi-disponibilidad")
            assert st_get == 200
            match = [b for b in d_get["disponibilidad"] if b["motivo"] == "Congreso QA Módulo 3"]
            assert match, "No apareció el bloqueo registrado"
            return int(match[0]["id"])

        created_disp_id = runner.check("TC-M3-04", "[RF-M02] Registro y consulta de bloqueo en disponibilidad médica", tc04)

        def tc05():
            pac = ApiClient()
            assert pac.login("carlos.ramirez@gmail.com", "Paciente123!")[0] == 200
            st_blk, d_blk = pac.post("/api/citas", {
                "dni": "71234561",
                "numero_cel": "987654321",
                "email": "carlos.ramirez@gmail.com",
                "especialidad": "cardio",
                "medico_id": 1,
                "fecha_inicio": block_date,
                "hora_cita": "15:00",
                "turno": "tarde",
                "acepto_terminos": True,
            })
            assert st_blk == 409, f"Bloqueo médico debe impedir reserva con 409, obtuvo {st_blk} {d_blk}"
            assert created_disp_id is not None
            st_del, d_del = med.delete(f"/api/medicos/mi-disponibilidad/{created_disp_id}")
            assert st_del == 200 and d_del.get("ok") is True, f"{st_del} {d_del}"

        runner.check("TC-M3-05", "[RF-M02] Bloqueo impide agendar al paciente (409) y eliminación DELETE", tc05)

        def tc06():
            st, data = med.get("/api/pacientes/71234561/historia")
            assert st == 200 and data["paciente"]["dni"] == "71234561", f"{st} {data}"
            pac2 = ApiClient()
            st_l, d_l = pac2.login("jorge.chavez@gmail.com", "Paciente123!")
            assert st_l == 200, f"Login paciente tercero falló: {st_l} {d_l}"
            st_forb, _ = pac2.get("/api/pacientes/71234561/historia")
            assert st_forb == 403, f"Otro paciente no debe ver historia ajena: {st_forb}"

        runner.check("TC-M3-06", "[RF-M04/RNF-07] Historia clínica: 200 médico tratante y 403 tercero", tc06)

        def tc07():
            st_bad, _ = med.post("/api/pacientes/71234561/historia", {"motivo": ""})
            assert st_bad == 422, f"Esperaba 422, obtuvo {st_bad}"
            st_ok, data = med.post("/api/pacientes/71234561/historia", {
                "motivo": "Control QA cardiológico",
                "anamnesis": "Paciente asintomático en prueba QA",
                "diagnostico_cie10": "Z00.0",
                "diagnostico": "Examen médico general QA",
                "tratamiento": "Continuar dieta saludable",
                "evolucion": "Estable",
            })
            assert st_ok == 201 and data.get("historia_id"), f"{st_ok} {data}"
            return int(data["historia_id"])

        created_hc_id = runner.check("TC-M3-07", "[RF-M04/RF-B03] Registro de evolución CIE-10 auditada (422 y 201)", tc07)

        def tc08():
            st_ok, data = med.post("/api/recetas", {
                "dni_paciente": "71234561",
                "diagnostico": "Prevención QA Módulo 3",
                "indicaciones": "Tomar con agua cada 12h",
                "medicamentos": [{"nombre": "Paracetamol 500mg", "dosis": "1 tableta c/12h"}],
            })
            assert st_ok == 201 and data.get("codigo_receta"), f"{st_ok} {data}"
            st_list, d_list = med.get("/api/recetas?dni=71234561")
            assert st_list == 200 and any(r["codigo_receta"] == data["codigo_receta"] for r in d_list["recetas"])
            return int(data["id"]), str(data["codigo_receta"])

        rec_res = runner.check("TC-M3-08", "[RF-M05] Emisión y consulta de receta digital vigente (/api/recetas)", tc08)
        if rec_res:
            created_receta_id, created_receta_code = rec_res

        def tc09():
            st_forb, _ = med.post("/api/medicos", {"nombre": "No permitido"})
            assert st_forb == 403, f"Médico no debe crear médicos: {st_forb}"
            adm = ApiClient()
            assert adm.login("admin1@centenario.pe", "Admin123!")[0] == 200
            st_new, d_new = adm.post("/api/medicos", {
                "nombre": f"Dr. QA {tag}",
                "cmp": f"CMP-QA-{tag}",
                "dni": "48" + str(int(tag, 16) % 1000000).zfill(6),
                "especialidad_id": 1,
                "email": test_med_email,
                "experiencia_anios": 5,
            })
            assert st_new == 201 and d_new.get("id"), f"{st_new} {d_new}"
            mid = int(d_new["id"])
            assert adm.put(f"/api/medicos/{mid}", {"experiencia_anios": 6})[0] == 200
            assert adm.delete(f"/api/medicos/{mid}")[0] == 200
            return mid

        created_medico_id = runner.check("TC-M3-09", "[RF-B07/RF-B02] CRUD admin de staff médico y bloqueo RBAC (403)", tc09)

        def tc10():
            c = ApiClient()
            for view in ("/medico.html", "/medico", "/staff_medico.html"):
                st, _ = c.get(view)
                assert st == 200, f"Vista {view} respondió {st}"

        runner.check("TC-M3-10", "[RF-M01/RF-C08] Accesibilidad HTTP 200 de /medico, medico.html y staff", tc10)

        def tc11():
            nonlocal created_bad_disp_id
            st, data = med.post("/api/medicos/mi-disponibilidad", {
                "fecha": block_date,
                "turno": "tarde",
                "hora_inicio": "18:00:00",
                "hora_fin": "08:00:00",
                "estado": "bloqueado",
                "motivo": f"Rango invertido QA {tag}",
            })
            if st == 200:
                _, d_get = med.get("/api/medicos/mi-disponibilidad")
                match = [b for b in d_get.get("disponibilidad", []) if b["motivo"] == f"Rango invertido QA {tag}"]
                if match:
                    created_bad_disp_id = int(match[0]["id"])
            assert st == 422, f"NO CUMPLE RF-D05 (hora_inicio >= hora_fin): aceptó 18:00-08:00 con HTTP {st}"

        runner.check("TC-M3-11", "[RF-D05/RF-M02] Rechazo de rango horario invertido en disponibilidad (422)", tc11)

        def tc12():
            nonlocal created_bad_receta_id, created_bad_receta_code
            st, data = med.post("/api/recetas", {
                "dni_paciente": "71234561",
                "cita_id": 999999,
                "diagnostico": "Receta cita inexistente QA",
                "indicaciones": "Prueba integridad referencial",
                "medicamentos": [{"nombre": "Paracetamol 500mg", "dosis": "1 c/8h"}],
            })
            if st == 201 and isinstance(data, dict) and data.get("id"):
                created_bad_receta_id = int(data["id"])
                created_bad_receta_code = str(data["codigo_receta"])
            assert st in (404, 422), f"NO CUMPLE RF-D06 (cita_id inexistente): aceptó cita_id=999999 con HTTP {st}"

        runner.check("TC-M3-12", "[RF-D06/RF-M05] Validación de existencia de cita_id al emitir receta (404/422)", tc12)
    finally:
        cleanup_stmts: list[str] = []
        if created_disp_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM medico_disponibilidad WHERE id = ?")->execute([{created_disp_id}]);')
        if created_bad_disp_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM medico_disponibilidad WHERE id = ?")->execute([{created_bad_disp_id}]);')
        if created_hc_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM historias_clinicas WHERE id = ?")->execute([{created_hc_id}]);')
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso = \'historias_clinicas\' AND recurso_id = ?")->execute(["{created_hc_id}"]);')
        if created_receta_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM notificaciones WHERE referencia_tipo = \'receta\' AND referencia_id = ?")->execute([{created_receta_id}]);')
            cleanup_stmts.append(f'$db->prepare("DELETE FROM recetas WHERE id = ?")->execute([{created_receta_id}]);')
        if created_receta_code:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso_id = ?")->execute(["{created_receta_code}"]);')
        if created_bad_receta_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM notificaciones WHERE referencia_tipo = \'receta\' AND referencia_id = ?")->execute([{created_bad_receta_id}]);')
            cleanup_stmts.append(f'$db->prepare("DELETE FROM recetas WHERE id = ?")->execute([{created_bad_receta_id}]);')
        if created_bad_receta_code:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso_id = ?")->execute(["{created_bad_receta_code}"]);')
        if created_medico_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM medicos WHERE id = ?")->execute([{created_medico_id}]);')
            cleanup_stmts.append(f'$db->prepare("DELETE FROM usuarios WHERE email = ?")->execute(["{test_med_email}"]);')
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso = \'medicos\' AND recurso_id = ?")->execute(["{created_medico_id}"]);')
        if cleanup_stmts:
            run_db_cleanup("".join(cleanup_stmts))

    return runner.summary()


if __name__ == "__main__":
    raise SystemExit(main())
