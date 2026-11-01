"""Ensayo HTTP de cinco sesiones contra la demostración local; nunca contra TIS."""
import argparse
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
import json
from pathlib import Path
import threading
import time
from urllib.parse import urlparse, urlencode
from urllib.request import Request, build_opener, HTTPCookieProcessor, ProxyHandler
from urllib.error import HTTPError
from http.cookiejar import CookieJar


class Response:
    def __init__(self, response):
        self.status_code = response.code
        self.url = response.geturl()
        self.text = response.read().decode("utf-8")

    def raise_for_status(self):
        if self.status_code >= 400:
            raise RuntimeError(f"Respuesta HTTP inesperada: {self.status_code}.")


class LocalSession:
    def __init__(self):
        self.opener = build_opener(ProxyHandler({}), HTTPCookieProcessor(CookieJar()))

    def get(self, url, timeout):
        return Response(self.opener.open(url, timeout=timeout))

    def post(self, url, data=None, json=None, headers=None, timeout=20):
        payload = __import__("json").dumps(json).encode() if json is not None else urlencode(data).encode()
        fields = {"Content-Type": "application/json" if json is not None else "application/x-www-form-urlencoded"}
        fields.update(headers or {})
        request = Request(url, data=payload, headers=fields, method="POST")
        try:
            return Response(self.opener.open(request, timeout=timeout))
        except HTTPError as error:
            return Response(error)


class CsrfParser(HTMLParser):
    token = None

    def handle_starttag(self, tag, attrs):
        fields = dict(attrs)
        if tag == "input" and fields.get("name") == "_token":
            self.token = fields.get("value")
        if tag == "meta" and fields.get("name") == "csrf-token":
            self.token = fields.get("content")


def csrf(html):
    parser = CsrfParser()
    parser.feed(html)
    if not parser.token:
        raise RuntimeError("No se encontró el token CSRF en la página.")
    return parser.token


arguments = argparse.ArgumentParser()
arguments.add_argument("--url", default="http://127.0.0.1:8018")
arguments.add_argument("--accounts", default="storage/app/demo-access.json")
arguments.add_argument("--student", type=int, required=True)
arguments.add_argument("--exam", type=int, required=True)
args = arguments.parse_args()
if urlparse(args.url).hostname not in ("127.0.0.1", "localhost", "::1"):
    raise SystemExit("Este ensayo solo admite el servidor local.")
accounts = [item for item in json.loads(Path(args.accounts).read_text(encoding="utf-8"))
            if item["estado"] == "activo"][:5]
if len(accounts) != 5:
    raise SystemExit("Se necesitan cinco cuentas activas de demostración.")


def login(account):
    session = LocalSession()
    response = session.get(args.url + "/login", timeout=20)
    response.raise_for_status()
    response = session.post(args.url + "/login", data={
        "_token": csrf(response.text), "email": account["email"], "password": account["password"],
    }, timeout=20)
    response.raise_for_status()
    if urlparse(response.url).path == "/login":
        raise RuntimeError("Una cuenta de prueba no pudo iniciar sesión.")
    return account, session, csrf(response.text)


with ThreadPoolExecutor(max_workers=5) as pool:
    sessions = list(pool.map(login, accounts))
    navigation_times = []

    def navigate(item):
        start = time.perf_counter()
        response = item[1].get(args.url + "/dashboard", timeout=20)
        if response.status_code != 200:
            raise RuntimeError(f"El panel devolvió {response.status_code}.")
        return round((time.perf_counter() - start) * 1000)

    for _ in range(3):
        navigation_times.extend(pool.map(navigate, sessions))

    barrier = threading.Barrier(5)

    def authorize(item):
        account, session, token = item
        barrier.wait(timeout=20)
        start = time.perf_counter()
        response = session.post(args.url + "/verificacion/ingreso", json={
            "estudiante_id": args.student, "examen_id": args.exam, "metodo": "CODIGO_UNIV",
        }, headers={"Accept": "application/json", "X-CSRF-TOKEN": token}, timeout=20)
        return {"role": account["rol"], "status": response.status_code,
                "milliseconds": round((time.perf_counter() - start) * 1000)}

    authorizations = list(pool.map(authorize, sessions))

if sorted(item["status"] for item in authorizations) != [201, 403, 409, 409, 409]:
    raise RuntimeError("La prueba de autorización simultánea no tuvo los estados esperados: "
                       + str([item["status"] for item in authorizations]))
result = {"sessions": 5, "dashboard_requests": len(navigation_times),
          "dashboard_max_ms": max(navigation_times), "authorizations": authorizations,
          "single_admission": True, "all_responses_expected": True}
Path("storage/app/http-check.json").write_text(json.dumps(result, indent=2), encoding="utf-8")
print(json.dumps(result))

