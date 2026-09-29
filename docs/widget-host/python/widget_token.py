"""
Firma el token del widget (JWT HS256) en Python, sin dependencias.
Solo en el SERVIDOR: el secreto nunca debe llegar al navegador.

Uso (Flask/Django/FastAPI):
    from widget_token import widget_token
    token = widget_token(email=user.email, name=user.nombre, company=user.cod_compania, branch=user.cod_sucursal)
"""
import base64
import hashlib
import hmac
import json
import os
import time


def _b64url(raw: bytes) -> str:
    return base64.urlsafe_b64encode(raw).rstrip(b"=").decode()


def widget_token(vigencia_segundos: int = 600, **datos) -> str:
    secret = os.environ.get("WIDGET_SECRET", "")

    if len(secret) < 32:
        raise RuntimeError("WIDGET_SECRET no está configurado.")

    ahora = int(time.time())
    claims = {k: v for k, v in datos.items() if v not in (None, "")}
    claims["iat"] = ahora
    claims["exp"] = ahora + max(60, min(3600, vigencia_segundos))

    header = _b64url(json.dumps({"alg": "HS256", "typ": "JWT"}).encode())
    payload = _b64url(json.dumps(claims, ensure_ascii=False).encode())
    firma = _b64url(hmac.new(secret.encode(), f"{header}.{payload}".encode(), hashlib.sha256).digest())

    return f"{header}.{payload}.{firma}"
