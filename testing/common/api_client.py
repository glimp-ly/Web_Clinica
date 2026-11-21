"""Utilidades HTTP y de ejecución compartidas para las 5 suites de testing."""

from __future__ import annotations

import http.cookiejar
import json
import os
import subprocess
import urllib.error
import urllib.request
from typing import Any

BASE_URL = os.environ.get("BASE_URL", "http://localhost:8989").rstrip("/")
DOCKER_CONTAINER = os.environ.get("DOCKER_CONTAINER", "clinica_centenario_web")


class ApiClient:
    """Cliente HTTP con persistencia de cookies (JWT HttpOnly) por sesión."""

    def __init__(self, base_url: str = BASE_URL) -> None:
        self.base_url = base_url.rstrip("/")
        self.cookies = http.cookiejar.CookieJar()
        self._opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def request(self, path: str, method: str = "GET", body: dict[str, Any] | None = None) -> tuple[int, Any]:
        data = json.dumps(body).encode("utf-8") if body is not None else None
        headers = {"Content-Type": "application/json"} if body is not None else {}
        req = urllib.request.Request(f"{self.base_url}{path}", data=data, headers=headers, method=method)
        try:
            with self._opener.open(req, timeout=10) as resp:
                raw = resp.read().decode("utf-8", errors="replace")
                return resp.status, self._parse(raw)
        except urllib.error.HTTPError as err:
            raw = err.read().decode("utf-8", errors="replace")
            return err.code, self._parse(raw)

    def get(self, path: str) -> tuple[int, Any]:
        return self.request(path, "GET")

    def post(self, path: str, body: dict[str, Any] | None = None) -> tuple[int, Any]:
        return self.request(path, "POST", body)

    def put(self, path: str, body: dict[str, Any] | None = None) -> tuple[int, Any]:
        return self.request(path, "PUT", body)

    def delete(self, path: str) -> tuple[int, Any]:
        return self.request(path, "DELETE")

    def login(self, email: str, password: str) -> tuple[int, Any]:
        return self.post("/api/auth/login", {"email": email, "password": password})

    def logout(self) -> tuple[int, Any]:
        return self.post("/api/auth/logout")

    @staticmethod
    def _parse(raw: str) -> Any:
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            return raw


class ModuleTestRunner:
    """Ejecutor uniforme de casos de prueba con reporte legible por módulo."""

    def __init__(self, module_title: str) -> None:
        self.module_title = module_title
        self.passed = 0
        self.failed = 0
        self.failures: list[tuple[str, str]] = []

    def check(self, tc_id: str, description: str, fn) -> Any:
        try:
            result = fn()
            self.passed += 1
            print(f"  [PASS] {tc_id}: {description}")
            return result
        except AssertionError as exc:
            self.failed += 1
            msg = str(exc) or "Assertion failed"
            self.failures.append((tc_id, msg))
            print(f"  [FAIL] {tc_id}: {description} -> {msg}")
        except Exception as exc:  # noqa: BLE001
            self.failed += 1
            self.failures.append((tc_id, f"{type(exc).__name__}: {exc}"))
            print(f"  [ERROR] {tc_id}: {description} -> {type(exc).__name__}: {exc}")
        return None

    def summary(self) -> int:
        total = self.passed + self.failed
        print(f"\n==> {self.module_title}: {self.passed}/{total} pruebas exitosas")
        if self.failed:
            for tc_id, err in self.failures:
                print(f"    - {tc_id}: {err}")
            return 1
        return 0


def run_db_cleanup(php_body: str) -> None:
    """Ejecuta limpieza de datos sintéticos en MariaDB dentro del contenedor Docker."""
    script = (
        'require "/var/www/backend_clinica/src/Core/Response.php";'
        'require "/var/www/backend_clinica/config/database.php";'
        "$db = App\\Config\\Database::getConnection();"
        + php_body
    )
    subprocess.run(
        ["docker", "exec", DOCKER_CONTAINER, "php", "-r", script],
        check=False,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
