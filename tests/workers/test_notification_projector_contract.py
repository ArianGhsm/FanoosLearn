import unittest
from pathlib import Path
class ProjectorContractTest(unittest.TestCase):
    def test_runtime_uses_only_internal_projection_client(self):
        text=(Path(__file__).resolve().parents[2]/'apps/workers/notification-projector/runtime.py').read_text()
        self.assertIn('project_notifications()',text);self.assertNotIn('sqlite3',text);self.assertNotIn('mysql',text.lower())
