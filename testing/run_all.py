#!/usr/bin/env python3
"""Ejecutor general para validar los 5 módulos del sistema en secuencia."""

from __future__ import annotations

import os
import subprocess
import sys

ROOT = os.path.dirname(os.path.abspath(__file__))
SUITES = [
    ("Módulo 1 — Acceso y Pacientes", os.path.join(ROOT, "modulo1_acceso_pacientes", "test_modulo1.py")),
    ("Módulo 2 — Citas y Agenda", os.path.join(ROOT, "modulo2_citas_agenda", "test_modulo2.py")),
    ("Módulo 3 — Portal Médico y Recetas", os.path.join(ROOT, "modulo3_portal_medico", "test_modulo3.py")),
    ("Módulo 4 — Farmacia y Laboratorio", os.path.join(ROOT, "modulo4_farmacia_laboratorio", "test_modulo4.py")),
    ("Módulo 5 — Comunidad y Administración", os.path.join(ROOT, "modulo5_comunidad_admin", "test_modulo5.py")),
]


def main() -> int:
    results: list[tuple[str, int]] = []
    for name, script in SUITES:
        proc = subprocess.run([sys.executable, script], check=False)
        results.append((name, proc.returncode))

    print("\n================ RESUMEN GLOBAL (5 MÓDULOS) ================")
    failed = 0
    for name, code in results:
        status = "PASS" if code == 0 else "CON DEFECTOS DETECTADOS (NO CUMPLE)"
        if code != 0:
            failed += 1
        print(f"  [{status}] {name}")
    print("============================================================")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
