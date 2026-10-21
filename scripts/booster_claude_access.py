"""Read-only native Claude subscription discovery. Never logs in or reads secrets.

For ordinary Booster tasks. GSS keeps its separately accepted transport.
This helper does not launch model requests, SSH, key tools or owner diagnostics.
"""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys


class AccessUnavailable(RuntimeError):
    pass


def child_environment() -> dict[str, str]:
    # Do not mutate the parent, select API billing or substitute an auth store.
    excluded = {"CLAUDE_CODE_OAUTH_TOKEN", "CLAUDE_CODE_USE_BEDROCK",
                "CLAUDE_CODE_USE_VERTEX", "CLAUDE_CODE_USE_FOUNDRY",
                "CLAUDE_CODE_BARE", "CLAUDE_CODE_SIMPLE"}
    return {k: v for k, v in os.environ.items()
            if not k.startswith("ANTHROPIC_") and k not in excluded}


def resolve_cli(profile: Path | None = None) -> Path:
    profile = profile or Path(os.environ["USERPROFILE"])
    native = profile / ".local/bin/claude.exe"
    if native.is_file():
        return native
    installed = []
    for extension in (profile / ".vscode/extensions").glob("anthropic.claude-code-*-win32-x64"):
        version = re.fullmatch(r"anthropic\.claude-code-(\d+)\.(\d+)\.(\d+)-win32-x64", extension.name)
        exe = extension / "resources/native-binary/claude.exe"
        if version and exe.is_file():
            installed.append((tuple(map(int, version.groups())), exe))
    if installed:
        return max(installed, key=lambda x: x[0])[1]
    raise AccessUnavailable("native_CLI_not_found_not_a_login_failure")


def classify_auth(data: dict, exit_code: int, profile: Path) -> dict:
    """Emit only protocol readiness, never account/org/email/token fields."""
    config = data.get("configDirectory")
    default_context = isinstance(config, str) and os.path.normcase(os.path.normpath(config)) == os.path.normcase(str(profile / ".claude"))
    result = {"default_config_directory": default_context,
              "saved_subscription": False, "owner_login_needed": False}
    if not default_context:
        result["status"] = "auth_context_unknown_or_different_do_not_relogin"
    elif exit_code == 0 and data.get("loggedIn") is True and data.get("authMethod") == "claude.ai" and data.get("subscriptionType") in ("pro", "max", "team", "enterprise") and not data.get("apiKeySource"):
        result.update(status="saved_subscription_ready", saved_subscription=True)
    elif exit_code == 1 and data.get("loggedIn") is False and data.get("authMethod") == "none":
        result.update(status="no_saved_login_in_default_context", owner_login_needed=True)
    else:
        result["status"] = "auth_source_or_status_requires_assessment_do_not_relogin"
    return result


def inspect_access() -> dict:
    if os.environ.get("CLAUDE_CONFIG_DIR"):
        return {"status": "custom_config_context_requires_assessment", "owner_login_needed": False}
    profile = Path(os.environ["USERPROFILE"])
    cli = resolve_cli(profile)
    digest = hashlib.sha256(cli.read_bytes()).hexdigest()
    env = child_environment()
    version = subprocess.run([str(cli), "--version"], capture_output=True,
                             text=True, encoding="utf-8", env=env, timeout=20)
    if version.returncode or not re.fullmatch(r"\d+\.\d+\.\d+ \(Claude Code\)\s*", version.stdout):
        raise AccessUnavailable("native_CLI_version_unrecognized")
    auth = subprocess.run([str(cli), "--safe-mode", "--setting-sources", "",
                           "auth", "status"], capture_output=True, text=True,
                          encoding="utf-8", env=env, timeout=30)
    try:
        data = json.loads(auth.stdout)
        if not isinstance(data, dict):
            raise ValueError()
    except (ValueError, TypeError):
        raise AccessUnavailable("auth_status_unreadable_not_a_login_instruction") from None
    if hashlib.sha256(cli.read_bytes()).hexdigest() != digest:
        raise AccessUnavailable("native_CLI_changed_during_status")
    result = classify_auth(data, auth.returncode, profile)
    result.update(cli_path=str(cli), cli_version=version.stdout.strip(), cli_sha256=digest,
                  credentials_file_read=False, model_request_sent=False,
                  ssh_or_owner_diagnostic_invoked=False,
                  scope="current_Windows_user_default_CLI_store_not_all_Claude_surfaces",
                  limit="Saved-login status is not a guarantee of future OAuth refresh or every model request.")
    return result


def main() -> int:
    if sys.argv[1:] not in ([], ["status"]):
        print(json.dumps({"status": "only_readonly_status_supported"}))
        return 2
    try:
        result = inspect_access()
    except (AccessUnavailable, OSError, KeyError, subprocess.SubprocessError) as error:
        reason = str(error) if isinstance(error, AccessUnavailable) else "local_status_unavailable"
        result = {"status": reason, "owner_login_needed": False}
    print(json.dumps(result, ensure_ascii=True))
    return 0 if result.get("saved_subscription") else 1


if __name__ == "__main__":
    raise SystemExit(main())
