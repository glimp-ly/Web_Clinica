#!/usr/bin/env python3
"""Suite automatizada del Módulo 4: Farmacia Virtual y Laboratorio Clínico."""

from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from common.api_client import ApiClient, ModuleTestRunner, run_db_cleanup


def main() -> int:
    runner = ModuleTestRunner("Módulo 4 — Farmacia Virtual y Laboratorio Clínico")
    print(f"\n=== {runner.module_title} ===")

    created_pedido_ids: list[int] = []
    created_boletas: list[str] = []
    bought_products: list[int] = []
    created_orden_id: int | None = None
    created_orden_code: str | None = None
    created_bad_orden_id: int | None = None
    created_bad_orden_code: str | None = None

    try:
        def tc01():
            c = ApiClient()
            st, data = c.get("/api/productos")
            assert st == 200 and len(data.get("productos", [])) >= 4, f"{st} {data}"
            return data["productos"]

        productos = runner.check("TC-M4-01", "[RF-C09] Consulta del catálogo de productos de farmacia", tc01) or []

        pac = ApiClient()
        assert pac.login("carlos.ramirez@gmail.com", "Paciente123!")[0] == 200

        receta_prod = next((p for p in productos if int(p["requiere_receta"]) == 1), None)
        otc_prod = next((p for p in productos if int(p["requiere_receta"]) == 0 and int(p["stock"]) >= 2), None)

        def tc02():
            assert receta_prod is not None, "No existe producto con receta en catálogo"
            st, data = pac.post("/api/pedidos", {
                "dni": "71234561",
                "nombre": "Carlos Ramírez",
                "metodo_pago": "tarjeta_visa",
                "usar_eps": True,
                "items": [{"id": receta_prod["id"], "quantity": 1}],
            })
            assert st == 422 and "receta" in data.get("message", "").lower(), f"{st} {data}"

        runner.check("TC-M4-02", "[RF-C09] Bloqueo de compra de antibiótico sin código de receta (422)", tc02)

        def tc03():
            assert otc_prod is not None
            st, data = pac.post("/api/pedidos", {
                "dni": "71234561",
                "nombre": "Carlos Ramírez",
                "metodo_pago": "yape",
                "usar_eps": False,
                "items": [{"id": otc_prod["id"], "quantity": 99999}],
            })
            assert st == 409 and "stock" in data.get("message", "").lower(), f"{st} {data}"

        runner.check("TC-M4-03", "[RF-C09/RF-B04] Control atómico de stock insuficiente en farmacia (409)", tc03)

        def tc04():
            assert otc_prod is not None
            st, data = pac.post("/api/pedidos", {
                "dni": "71234561",
                "nombre": "Carlos Ramírez",
                "metodo_pago": "tarjeta_visa",
                "usar_eps": True,
                "items": [{"id": otc_prod["id"], "quantity": 1}],
            })
            assert st == 201 and data.get("boleta"), f"{st} {data}"
            b = data["boleta"]
            assert float(b["descuento_eps"]) > 0, f"Debe aplicar descuento EPS: {b}"
            assert float(b["total_pagado"]) < float(b["subtotal"]), f"Total debe ser menor al subtotal: {b}"
            created_pedido_ids.append(int(b["pedido_id"]))
            created_boletas.append(str(b["codigo_boleta"]))
            bought_products.append(int(otc_prod["id"]))

        runner.check("TC-M4-04", "[RF-C09/RF-B05] Compra farmacia con cobertura EPS y boleta electrónica", tc04)

        def tc05():
            assert receta_prod is not None
            st, data = pac.post("/api/pedidos", {
                "dni": "71234561",
                "nombre": "Carlos Ramírez",
                "metodo_pago": "yape",
                "codigo_receta": "REC-2026-001",
                "usar_eps": False,
                "items": [{"id": receta_prod["id"], "quantity": 1}],
            })
            assert st == 201 and data["boleta"]["codigo_receta"] == "REC-2026-001", f"{st} {data}"
            created_pedido_ids.append(int(data["boleta"]["pedido_id"]))
            created_boletas.append(str(data["boleta"]["codigo_boleta"]))
            bought_products.append(int(receta_prod["id"]))

        runner.check("TC-M4-05", "[RF-C09/RF-M05] Compra de antibiótico validando receta digital vigente", tc05)

        def tc06():
            c = ApiClient()
            st, data = c.get("/api/laboratorio/catalogo")
            assert st == 200 and len(data.get("analisis", [])) >= 4, f"{st} {data}"

        runner.check("TC-M4-06", "[RF-C10] Consulta del catálogo de análisis clínicos de laboratorio", tc06)

        def tc07():
            st_neg, _ = pac.post("/api/ordenes-lab", {
                "dni_paciente": "71234561",
                "nombre_paciente": "Carlos Alberto Ramírez Soto",
                "metodo_pago": "yape",
                "analisis": [{"nombre": "Glucosa", "cantidad": -2}],
            })
            assert st_neg == 422, f"Cantidad negativa en orden lab debe devolver 422, obtuvo {st_neg}"
            st, data = pac.post("/api/ordenes-lab", {
                "dni_paciente": "71234561",
                "nombre_paciente": "Carlos Alberto Ramírez Soto",
                "metodo_pago": "yape",
                "analisis": [{"nombre": "Glucosa", "cantidad": 1}],
            })
            assert st == 201 and data.get("orden"), f"{st} {data}"
            o = data["orden"]
            return int(o["id"]), str(o["codigo_orden"])

        ord_res = runner.check("TC-M4-07", "[RF-C10/RF-M05] Validación cantidad>0 (422) y generación orden lab (201)", tc07)
        if ord_res:
            created_orden_id, created_orden_code = ord_res

        def tc08():
            assert created_orden_id is not None
            med = ApiClient()
            assert med.login("juan.perez@centenario.pe", "Medico123!")[0] == 200
            st, data = med.put(f"/api/ordenes-lab/{created_orden_id}", {
                "estado": "publicado",
                "resultado_resumen": "Glucosa basal 90 mg/dL (Normal QA)",
                "observaciones_lab": "Validado QA Módulo 4",
            })
            assert st == 200 and data.get("ok") is True, f"{st} {data}"

        runner.check("TC-M4-08", "[RF-C10/RF-M06] Publicación de resultados clínicos por médico (200)", tc08)

        def tc09():
            assert created_orden_code is not None
            anon = ApiClient()
            assert anon.get(f"/api/resultados/{created_orden_code}")[0] == 403
            st_own, d_own = pac.get(f"/api/resultados/{created_orden_code}")
            assert st_own == 200 and d_own["resultado"]["estado"] == "publicado", f"{st_own} {d_own}"
            med = ApiClient()
            assert med.login("juan.perez@centenario.pe", "Medico123!")[0] == 200
            st_med, d_med = med.get(f"/api/resultados/{created_orden_code}")
            assert st_med == 200 and d_med["resultado"]["codigo_orden"] == created_orden_code, f"{st_med} {d_med}"

        runner.check("TC-M4-09", "[RF-C10/RF-M06] Acceso a resultados: 403 anónimo, 200 titular y médico tratante", tc09)

        def tc10():
            assert otc_prod is not None
            adm = ApiClient()
            assert adm.login("admin1@centenario.pe", "Admin123!")[0] == 200
            st_upd, _ = adm.put(f"/api/productos/{otc_prod['id']}", {"categoria": otc_prod["categoria"]})
            assert st_upd == 200
            for view in ("/farmacia.html", "/Laboratorio.html"):
                assert adm.get(view)[0] == 200, f"Vista {view} no devolvió 200"

        runner.check("TC-M4-10", "[RF-B07/RNF-10] Gestión admin de inventario farmacia y vistas HTTP 200", tc10)

        def tc11():
            assert receta_prod is not None
            st2, data2 = pac.post("/api/pedidos", {
                "dni": "71234561",
                "nombre": "Carlos Ramírez",
                "metodo_pago": "yape",
                "codigo_receta": "REC-2026-001",
                "usar_eps": False,
                "items": [{"id": receta_prod["id"], "quantity": 1}],
            })
            if st2 == 201 and isinstance(data2, dict) and data2.get("boleta"):
                created_pedido_ids.append(int(data2["boleta"]["pedido_id"]))
                created_boletas.append(str(data2["boleta"]["codigo_boleta"]))
                bought_products.append(int(receta_prod["id"]))
            assert st2 in (409, 422), f"NO CUMPLE RF-D07 (reutilización de receta): permitió reusar REC-2026-001 con HTTP {st2}"

        runner.check("TC-M4-11", "[RF-D07/RF-C09] Bloqueo de reutilización de receta ya dispensada (409/422)", tc11)

        def tc12():
            assert receta_prod is not None
            pac2 = ApiClient()
            assert pac2.login("jorge.chavez@gmail.com", "Paciente123!")[0] == 200
            st, data = pac2.post("/api/pedidos", {
                "dni": "73456783",
                "nombre": "Jorge Luis Chávez Díaz",
                "metodo_pago": "yape",
                "codigo_receta": "REC-2026-001",
                "usar_eps": False,
                "items": [{"id": receta_prod["id"], "quantity": 1}],
            })
            if st == 201 and isinstance(data, dict) and data.get("boleta"):
                created_pedido_ids.append(int(data["boleta"]["pedido_id"]))
                created_boletas.append(str(data["boleta"]["codigo_boleta"]))
                bought_products.append(int(receta_prod["id"]))
            assert st in (403, 422), f"NO CUMPLE RF-D08 (titularidad receta): permitió al DNI 73456783 comprar con REC-2026-001 del DNI 71234561 (HTTP {st})"

        runner.check("TC-M4-12", "[RF-D08/RF-C09] Validación de titularidad (DNI) de receta digital en farmacia (403/422)", tc12)
    finally:
        cleanup_stmts: list[str] = []
        for pid in bought_products:
            cleanup_stmts.append(f'$db->prepare("UPDATE productos_farmacia SET stock = stock + 1 WHERE id = ?")->execute([{pid}]);')
        for ped_id in created_pedido_ids:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM pedidos_farmacia WHERE id = ?")->execute([{ped_id}]);')
        for bol in created_boletas:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso_id = ?")->execute(["{bol}"]);')
        if created_orden_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM ordenes_laboratorio WHERE id = ?")->execute([{created_orden_id}]);')
        if created_orden_code:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso_id = ?")->execute(["{created_orden_code}"]);')
        if created_bad_orden_id:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM ordenes_laboratorio WHERE id = ?")->execute([{created_bad_orden_id}]);')
        if created_bad_orden_code:
            cleanup_stmts.append(f'$db->prepare("DELETE FROM auditoria_logs WHERE recurso_id = ?")->execute(["{created_bad_orden_code}"]);')
        if cleanup_stmts:
            run_db_cleanup("".join(cleanup_stmts))

    return runner.summary()


if __name__ == "__main__":
    raise SystemExit(main())
