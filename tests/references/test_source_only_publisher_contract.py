"""Guard production source-only publisher against accidental bank question writes."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
SERVICE = ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php"
CLI = ROOT / "scripts/references/import_verified_sources.php"


class PublisherSafetyContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.service = SERVICE.read_text()
        cls.cli = CLI.read_text()

    def test_only_source_row_insert(self):
        statements = re.findall(r"(?<!FOR )(?:INSERT INTO|UPDATE|DELETE FROM|REPLACE INTO)\s+([a-z_]+)",
                                self.service, flags=re.IGNORECASE | re.MULTILINE)
        self.assertEqual([s.lower() for s in statements], ["bank_question_sources"])

    def test_explicit_scope_gate_and_no_human_override(self):
        self.assertIn("v.is_official=1", self.service)
        self.assertIn("scope_chapters", self.service)
        self.assertIn("override_human", self.service)
        self.assertIn("already exists", self.service)
        self.assertIn("FOR UPDATE", self.service)

    def test_atomic_chapter_and_pdf_page_contract(self):
        # The legacy page/printed label alone must never yield a source row.
        for marker in [
            "A chapter source requires its exact pdf_page and verified PDF SHA-256.",
            "sourcePdfPage !== $pdfPage",
            "sourcePdfHash",
            "pdf_page,printed_page,pdf_sha256",
            "'pdf_page' => $src['pdf_page']",
            "'pdf_sha256' => $src['pdf_sha256']",
        ]:
            self.assertIn(marker, self.service)

    def test_question_and_answer_preflight(self):
        for marker in ["question !== $original", "realChoices !== $original",
                       "actual !== $original", "status'] !== 'published'"]:
            self.assertIn(marker, self.service)

    def test_dry_run_rolls_back(self):
        self.assertIn("$db->rollBack();", self.service)
        self.assertIn("$db->commit();", self.service)
        self.assertIn("if ($apply)", self.service)


    def test_parallel_coordinator_stage_is_narrowly_scoped(self):
        """Alternate immutable inputs cannot bypass the source-only writer."""
        for marker in [
            "'package-root'", "W0[1-8]-audit-stage",
            "classification/coordinator/", "realpath($candidate)",
            "realpath(dirname($auditPath)) !== $auditDir",
            'realpath($candidate) !== $candidate',
        ]:
            self.assertIn(marker, self.cli)
        self.assertIn("W03-endo-q1-audit-stage", self.cli)
        self.assertIn("Endodontics Q1 coordinator stage may only map", self.cli)
        # Receipt and verified full backup must remain under original root.
        self.assertIn("realpath(dirname($receipt)) !== $reportDir", self.cli)
        self.assertIn("BackupManifest::verify", self.cli)
        # Whether canonical or staged, compare all three protected blobs
        # to the pinned provenance audit before calling atomic publisher.
        for marker in ["hash_file('sha256', $studyPath)",
                       "hash_file('sha256', $validatedPath)",
                       "hash_file('sha256', $decisionsPath)"]:
            self.assertIn(marker, self.cli)
        self.assertIn("SourceOnlyPublisher::run", self.cli)

    def test_1399_oral_medicine_q43_only_stage(self):
        self.assertIn("W01-1399-oralmedicine-q43-20261012", self.cli)
        self.assertIn("$year === 1399 && $subject === 'oral-medicine'", self.cli)
        self.assertIn("$stem === 'oral-medicine-q43' && $expected === 1", self.cli)
        self.assertIn("BackupManifest::verify", self.cli)
        self.assertIn("hash_file('sha256', $decisionsPath)", self.cli)

    def test_1399_radiology_stage_cannot_be_repurposed(self):
        self.assertIn("W01-radiology-next-20261012-v2", self.cli)
        self.assertIn("$year === 1399 && $subject === 'oral-radiology'", self.cli)
        self.assertIn("$stem === 'radiology-remain' && $expected === 4", self.cli)
        self.assertIn("1399 W01 radiology evidence stage is exclusive", self.cli)
        self.assertIn("BackupManifest::verify", self.cli)
        self.assertIn("hash_file('sha256', $decisionsPath)", self.cli)

    def test_1398_exception_list_is_explicit_and_year_scoped(self):
        """1398 additions remain constrained to exact, independently audited batches."""
        self.assertIn("$year === 1398 && $subject === 'endodontics' && in_array($stem, ['endodontics', 'endodontics-q9', 'endodontics-q1', 'endodontics-location-final'], true)", self.cli)
        self.assertIn("$year === 1398 && $subject === 'endodontics'", self.service)
        self.assertIn("$decision['edition'] !== 'torabinejad-endodontics@5e'", self.service)
        self.assertIn("1398 endodontics requires exact announced Torabinejad 5e", self.service)
        self.assertIn("$subject === 'periodontics' && in_array($stem, ['periodontics', 'periodontics-followup', 'periodontics-certified-20261012', 'periodontics-sourcefree'], true)", self.cli)
        self.assertIn("classification/decisions/1398-{$stem}.json", self.cli)
        self.assertIn("periodontics-certified-20261012", self.cli)
        self.assertIn("$year === 1398 && $subject === 'periodontics'", self.service)
        self.assertIn("$decision['edition'] !== 'carranza-periodontology@12e'", self.service)
        self.assertIn("1398 periodontics requires exact announced Carranza 12e", self.service)
        self.assertIn("$subject === 'community-dentistry' && $stem === 'community'", self.cli)
        self.assertIn("$year === 1398 && $subject === 'community-dentistry'", self.service)
        self.assertIn("$decision['edition'] !== 'national-oral-health@1394'", self.service)
        self.assertIn("1398 community dentistry requires the exact national oral-health 1394 edition", self.service)
        self.assertIn("W02-community-final-audit-stage", self.cli)
        self.assertIn("$year === 1398 && $subject === 'oral-surgery' && in_array($stem, ['surgery', 'surgery-modern-hupp7'], true)", self.cli)
        self.assertIn("recovery-1398-oral-surgery-malamed7-20261021", self.cli)
        self.assertIn("$expected === 2", self.cli)
        self.assertIn("$year === 1398 && $subject === 'pediatric-dentistry' && $stem === 'pediatric-sourcefree'", self.cli)
        self.assertIn("recovery-1398-pediatric-sourcefree-20261021", self.cli)
        self.assertIn("recovery-1398-periodontics-sourcefree-20261021", self.cli)
        self.assertIn("recovery-1398-surgery-modern-hupp7-20261021", self.cli)
        self.assertIn("123 => ['malamed-medical-emergencies@7e', '3', 110]", self.service)
        self.assertIn("124 => ['malamed-medical-emergencies@7e', '11', 211]", self.service)
        self.assertIn("$year === 1398 && $subject === 'oral-surgery' && $stem === 'surgery'", (ROOT / "scripts/references/audit_published_assessment.php").read_text())
        # The global gate remains in effect for 1398 batches outside this explicit list.
        self.assertIn("$year < 1399", self.service)
        self.assertIn("$year < 1399", self.cli)

    def test_apply_requires_fresh_verified_backup_and_receipt(self):
        for marker in ["BackupManifest::verify", "database.sql",
                       "posix_geteuid", "research", "receipt",
                       "hash_file('sha256'", "year < 1399"]:
            self.assertIn(marker, self.cli)


if __name__ == "__main__":
    unittest.main()
