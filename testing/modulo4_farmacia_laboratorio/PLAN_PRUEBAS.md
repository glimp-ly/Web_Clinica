# Plan de Pruebas — Módulo 4: Farmacia Virtual y Laboratorio Clínico (Integrante 4)

## 1. Alcance y Requerimientos (`REQUERIMIENTOS.md`)
- **RF**: `RF-C09`, `RF-C10`, `RF-M05` (órdenes lab), `RF-M06`, `RF-B04` (stock), `RF-B05`, `RF-B07` (stock farmacia)
- **RNF**: `RNF-03`, `RNF-04`, `RNF-10`
- **Backend**: `backend_clinica/src/Modules/Modulo4_FarmaciaLaboratorio/` (`Routes.php`, `FarmaciaController`, `LaboratorioController`)
- **Frontend**: `farmacia.html`, `Laboratorio.html` (`farmacia.js`, `script_laboratorio.js`)
- **Tablas MariaDB**: `productos_farmacia`, `pedidos_farmacia`, `pedido_items`, `catalogo_laboratorio`, `ordenes_laboratorio`

## 2. Matriz de Casos de Prueba (10 Automatizados + RNF Manuales)

| ID | Requerimiento | Tipo | Caso de Prueba | Resultado Esperado |
| :--- | :---: | :--- | :--- | :--- |
| `TC-M4-01` | `RF-C09` | Positivo | Catálogo de farmacia (`GET /api/productos`) | `200`, medicamentos activos con precio y stock |
| `TC-M4-02` | `RF-C09` | Regla de Negocio | Compra de antibiótico (`requiere_receta=1`) sin receta | `422`, exige código de receta vigente |
| `TC-M4-03` | `RF-C09`, `RF-B04` | Concurrencia / Borde | Compra superando stock disponible | `409 Conflict` y rollback sin descuento |
| `TC-M4-04` | `RF-C09`, `RF-B05` | Positivo | Compra con cobertura EPS y emisión de boleta (`POST /api/pedidos`) | `201`, `descuento_eps > 0` y código `BOL-F...` |
| `TC-M4-05` | `RF-C09`, `RF-M05` | Integración | Compra de antibiótico validando receta digital (`REC-2026-001`) | `201`, boleta vinculada a `REC-2026-001` |
| `TC-M4-06` | `RF-C10` | Positivo | Catálogo de análisis clínicos (`GET /api/laboratorio/catalogo`) | `200`, lista de exámenes y precios |
| `TC-M4-07` | `RF-C10`, `RF-M05` | Positivo | Generación de orden lab (`POST /api/ordenes-lab`) | `201`, códigos `LAB-...` y `BOL-L...` |
| `TC-M4-08` | `RF-C10`, `RF-M06` | Positivo | Publicación de resultado por médico (`PUT /api/ordenes-lab/{id}`) | `200`, estado `publicado` |
| `TC-M4-09` | `RF-C10`, `RF-M06` | Seguridad / RBAC | Consulta `GET /api/resultados/{codigo}`: anónimo (`403`), titular (`200`), médico tratante (`200`) | Acceso restringido y auditado |
| `TC-M4-10` | `RF-B07`, `RNF-10` | Admin / Smoke | Actualización admin `PUT /api/productos/{id}` (`200`) y vistas `farmacia.html` / `Laboratorio.html` (`200`) | `200` en todas las comprobaciones |
| `TC-M4-11` | `RF-D07` (`RF-C09`) | Defecto (`NO CUMPLE`) | Reutilización de receta digital `REC-2026-001` en una segunda compra | **Esperado**: `409`/`422` (receta ya dispensada) · **Actual**: `201` (permite reutilizarla) |
| `TC-M4-12` | `RF-D08` (`RF-C10`) | Defecto (`NO CUMPLE`) | Orden de laboratorio con cantidad negativa (`cantidad: -2`) | **Esperado**: `422` · **Actual**: `201` (genera orden con total negativo) |

### Verificaciones RNF Complementarias
- `RNF-03`: Lighthouse rendimiento ≥85, tamaño de página <1.5MB y búsqueda instantánea <200ms en vitrina farmacia/laboratorio.
- `RNF-04`: Latencia API `p95 < 500ms` en `POST /api/pedidos`.

## 3. Ejecución
```bash
python3 testing/modulo4_farmacia_laboratorio/test_modulo4.py
```
