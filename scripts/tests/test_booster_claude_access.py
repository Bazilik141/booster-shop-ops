"""Pure readiness/privacy cases; no CLI, credentials or owner SSH invocation."""
from pathlib import Path
import importlib.util
import json
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location("booster_access", Path(__file__).resolve().parents[1] / "booster_claude_access.py")
access = importlib.util.module_from_spec(spec)
spec.loader.exec_module(access)


class AccessTest(unittest.TestCase):
    def setUp(self):
        self.profile = Path("C:/fictional-owner")
        self.data = {"configDirectory": str(self.profile / ".claude"),
                     "loggedIn": True, "authMethod": "claude.ai", "subscriptionType": "pro",
                     "email": "PRIVATE_CANARY", "accessToken": "PRIVATE_CANARY"}

    def test_saved_login_reused_without_private_fields(self):
        r = access.classify_auth(self.data, 0, self.profile)
        self.assertTrue(r["saved_subscription"])
        self.assertFalse(r["owner_login_needed"])
        self.assertNotIn("PRIVATE_CANARY", json.dumps(r))

    def test_missing_login_is_distinct_from_context_mismatch(self):
        self.data.update(loggedIn=False, authMethod="none")
        self.assertTrue(access.classify_auth(self.data, 1, self.profile)["owner_login_needed"])
        self.data["configDirectory"] = "C:/different-fictional-context"
        self.assertFalse(access.classify_auth(self.data, 1, self.profile)["owner_login_needed"])

    def test_wrong_sources_never_become_login_instructions(self):
        for change in [{"apiKeySource": "env"}, {"authMethod": "api_key"}, {"subscriptionType": None}]:
            d = dict(self.data, **change)
            r = access.classify_auth(d, 0, self.profile)
            self.assertFalse(r["saved_subscription"])
            self.assertFalse(r["owner_login_needed"])

    def test_provider_environment_removed_without_changing_parent(self):
        with patch.dict(access.os.environ, {"ANTHROPIC_API_KEY": "PRIVATE_CANARY", "CLAUDE_CODE_OAUTH_TOKEN": "PRIVATE_CANARY", "CLAUDE_CONFIG_DIR": "preserve-context"}):
            child = access.child_environment()
            self.assertNotIn("ANTHROPIC_API_KEY", child)
            self.assertNotIn("CLAUDE_CODE_OAUTH_TOKEN", child)
            self.assertEqual(child["CLAUDE_CONFIG_DIR"], "preserve-context")
            self.assertEqual(access.os.environ["ANTHROPIC_API_KEY"], "PRIVATE_CANARY")


if __name__ == "__main__":
    unittest.main()
