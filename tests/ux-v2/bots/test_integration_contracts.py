from __future__ import annotations

import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[3]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


class IntegrationContractTest(unittest.TestCase):
    def test_secure_web_download_reauthorizes_and_never_uses_capability_url(self):
        service = read("apps/platform/src/Content/SecureObjectDownloadService.php")
        kernel = read("apps/platform/src/Http/ApiKernel.php")
        web = read("apps/platform/public/assets/app.js")
        contract = read("contracts/openapi/core-v1.yaml")

        self.assertIn("$this->tokens->verify", service)
        self.assertIn("$this->authorizer->decide", service)
        self.assertIn("resource_version_id", service)
        self.assertIn("object_id", service)
        self.assertIn("hash_equals", service)
        self.assertNotIn("'storage_key' =>", service)
        self.assertNotIn("'path' =>", service)

        self.assertIn("$request->method === 'POST' && $suffix === '/downloads/consume'", kernel)
        self.assertIn("requireDownload()->redeem", kernel)
        self.assertIn("/downloads/consume`, { download_token: served.download_token }", web)
        self.assertNotIn("?download_token=", web)
        self.assertNotIn("&download_token=", web)

        self.assertIn("version: 1.2.0", contract)
        self.assertIn("/workspaces/{workspaceId}/downloads/consume:", contract)
        self.assertIn("The capability is supplied in the POST body", contract)

    def test_web_assessment_uses_canonical_attempt_revision_and_server_scoring(self):
        exam = read("apps/platform/src/Content/ExamService.php")
        kernel = read("apps/platform/src/Http/ApiKernel.php")
        web = read("apps/platform/public/assets/app.js")
        learning = read("apps/platform/public/assets/ui-v2/product-learning.js")

        self.assertIn("'questions' => $this->safeQuestions($definition)", exam)
        self.assertIn("private function safeQuestions", exam)
        self.assertNotIn("'answer' => $question['answer']", exam.split("private function safeQuestions", 1)[1].split("private function answers", 1)[0])
        self.assertIn("score_basis_points", exam)

        self.assertIn("/assessments/([0-9a-f-]+)/attempts", kernel)
        self.assertIn("/attempts/([0-9a-f-]+)$", kernel)
        self.assertIn("/attempts/([0-9a-f-]+)/submit", kernel)
        self.assertIn("/attempts/([0-9a-f-]+)/review", kernel)

        self.assertIn("revision: Number(attempt.revision || 0)", web)
        self.assertIn("startAssessment", web)
        self.assertIn("saveAssessment", web)
        self.assertIn("submitAssessment", web)
        self.assertIn("renderAssessmentAttempt", learning)
        self.assertIn("renderAssessmentResult", learning)
        self.assertNotIn("score_basis_points =", web)
        self.assertNotIn("correct_count =", web)

    def test_schedule_authority_is_workspace_timezone_not_host_or_device(self):
        resolver = read("apps/platform/src/Core/ScheduleWindowResolver.php")
        projection = read("apps/platform/src/Core/ScheduleProjectionService.php")
        web = read("apps/platform/public/assets/app.js")
        bot_reads = read("apps/platform/src/Core/BotReadProjectionService.php")

        self.assertIn("SELECT timezone_name FROM tenant_workspaces", resolver)
        self.assertIn("DateTimeZone((string) $name)", resolver)
        self.assertIn("setTimezone($utc)", resolver)
        self.assertIn("ScheduleWindowResolver", projection)
        self.assertIn("workspaceTimezone() || 'UTC'", web)
        self.assertIn("UI.localDateKey(new Date().toISOString(), timezone)", web)
        self.assertIn("$this->workspaceTimezone($workspaceId)", bot_reads)

    def test_final_bot_runtimes_use_integrated_canonical_course_application(self):
        telegram = read("apps/telegram-bot/runtime.py")
        bale = read("apps/bale-bot/runtime.py")
        integrated = read("packages/python/fanoos_bot/integrated_application.py")

        self.assertIn("from fanoos_bot.integrated_application import ApplicationConfig, BotApplication", telegram)
        self.assertIn("from fanoos_bot.integrated_application import ApplicationConfig, BotApplication", bale)
        self.assertIn("projection.get(\"courses\")", integrated)
        self.assertIn("course_list_screen(", integrated)
        self.assertIn('"academic.courses.page"', integrated)
        # Stage 5 academic journeys intentionally reuse the base canonical
        # schedule projection helper; it does not create a bot-side data store.
        self.assertIn("self._schedule_window(", integrated)
        self.assertNotIn("self._grade_items(", integrated)
        self.assertNotIn("self._resource_items(", integrated)
        self.assertIn('action == "coursep"', integrated)

    def test_web_management_navigation_requires_explicit_canonical_capability(self):
        platform = read("apps/platform/src/Core/WorkspacePlatformService.php")
        web = read("apps/platform/public/assets/app.js")

        self.assertIn("'management_available' => $managementAvailable", platform)
        self.assertIn("'membership.manage'", platform)
        self.assertIn("'resource.review'", platform)
        self.assertIn("'payment.reconcile'", platform)
        self.assertIn("result.data?.management_available === true", web)
        self.assertNotIn("const allowed = !!result.ok;", web)

    def test_update_server_surface_remains_telegram_private_only(self):
        application = read("packages/python/fanoos_bot/application.py")
        internal = read("apps/platform/src/Http/InternalApiKernel.php")

        self.assertIn('self.platform == "telegram"', application)
        self.assertIn("and private", application)
        self.assertIn("can_manage_deployments", application)
        self.assertIn("if ($platform !== 'telegram')", internal)
        self.assertIn("deployment_channel_forbidden", internal)


if __name__ == "__main__":
    unittest.main()
