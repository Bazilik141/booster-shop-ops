#!/usr/bin/env python3
"""One-time OAuth helper for AUTO-003 Search Console credentials.

Reads client_secret.json from the repository root, opens the system browser,
and prints a refresh token for .env.review. It never writes secrets to disk.
"""

from __future__ import annotations

import base64
import hashlib
import json
import secrets
import sys
import urllib.parse
import urllib.request
import webbrowser
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parent.parent
CLIENT_SECRET_PATH = REPO_ROOT / "client_secret.json"
SCOPE = "https://www.googleapis.com/auth/webmasters.readonly"


def base64url(value: bytes) -> str:
    return base64.urlsafe_b64encode(value).rstrip(b"=").decode("ascii")


def main() -> int:
    if not CLIENT_SECRET_PATH.exists():
        raise RuntimeError("client_secret.json not found in repository root")
    config = json.loads(CLIENT_SECRET_PATH.read_text(encoding="utf-8"))
    installed = config.get("installed") or config.get("web")
    if not installed or not installed.get("client_id"):
        raise RuntimeError("client_secret.json does not contain installed/web OAuth client credentials")

    received: dict[str, str] = {}

    class CallbackHandler(BaseHTTPRequestHandler):
        def do_GET(self):  # noqa: N802
            query = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query)
            received.update({key: values[0] for key, values in query.items() if values})
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.end_headers()
            self.wfile.write("<h1>Authorization received. You can close this tab.</h1>".encode("utf-8"))

        def log_message(self, _format, *_args):
            return

    server = HTTPServer(("127.0.0.1", 0), CallbackHandler)
    redirect_uri = f"http://127.0.0.1:{server.server_port}"
    verifier = base64url(secrets.token_bytes(64))
    challenge = base64url(hashlib.sha256(verifier.encode("ascii")).digest())
    state = secrets.token_urlsafe(24)
    auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" + urllib.parse.urlencode({
        "client_id": installed["client_id"],
        "redirect_uri": redirect_uri,
        "response_type": "code",
        "scope": SCOPE,
        "access_type": "offline",
        "prompt": "consent",
        "code_challenge": challenge,
        "code_challenge_method": "S256",
        "state": state,
    })
    print("[AUTO-003] Opening Google authorization in your default browser...")
    print("[AUTO-003] If it does not open, use this URL:\n" + auth_url)
    webbrowser.open(auth_url, new=1)
    server.timeout = 300
    server.handle_request()
    if received.get("state") != state:
        raise RuntimeError("OAuth state mismatch or authorization timed out")
    if received.get("error"):
        raise RuntimeError(f"OAuth denied: {received['error']}")
    if not received.get("code"):
        raise RuntimeError("OAuth response did not include an authorization code")

    body = urllib.parse.urlencode({
        "client_id": installed["client_id"],
        "client_secret": installed.get("client_secret", ""),
        "code": received["code"],
        "code_verifier": verifier,
        "redirect_uri": redirect_uri,
        "grant_type": "authorization_code",
    }).encode("utf-8")
    request = urllib.request.Request("https://oauth2.googleapis.com/token", data=body, headers={"Content-Type": "application/x-www-form-urlencoded"}, method="POST")
    with urllib.request.urlopen(request, timeout=20) as response:
        token_data = json.loads(response.read())
    refresh_token = token_data.get("refresh_token")
    if not refresh_token:
        raise RuntimeError("Google did not return a refresh_token; retry after revoking the app grant or use prompt=consent")
    print("\n[AUTO-003] Add these values to .env.review (do not commit it):")
    print(f"GSC_CLIENT_ID={installed['client_id']}")
    print(f"GSC_CLIENT_SECRET={installed.get('client_secret', '')}")
    print(f"GSC_REFRESH_TOKEN={refresh_token}")
    print("GSC_SITE_URL=https://boosterok.com.ua/")
    print("[AUTO-003] done=ok")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (OSError, RuntimeError, json.JSONDecodeError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise SystemExit(1)
