"""Compatibility contract for visible source-year/proxy provenance."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class SourceClassificationProvenanceTests(unittest.TestCase):
    def test_expand_only_columns_preserve_legacy_rows(self):
        migration = (ROOT / "database/migrations/0048_bank_source_reference_provenance.sql").read_text()
        self.assertIn("fanoos:rollback-compatible=expand", migration)
        self.assertFalse((ROOT / "database/migrations/0048_bank_source_reference_basis.sql").exists())
        self.assertIn("ADD COLUMN reference_year SMALLINT UNSIGNED NULL", migration)
        self.assertIn("ADD COLUMN classification_basis VARCHAR(48) NULL", migration)
        self.assertIn("ADD COLUMN classification_note VARCHAR(300) NULL", migration)
        self.assertIn("reference_year IS NULL", migration)
        self.assertIn("classification_basis IS NOT NULL", migration)
        self.assertIn("classification_note IS NOT NULL", migration)
        self.assertIn("classification_note IS NULL", migration)
        self.assertIn("classification_basis = 'official_exam_year' AND classification_note IS NULL", migration)
        self.assertNotIn("nearest_verified_edition_proxy", migration)
        self.assertNotIn("UPDATE bank_question_sources", migration)
        for basis in ["official_exam_year", "retrospective_proxy_1397_plus",
                      "nearest_same_lineage_proxy", "updated_reference_proxy"]:
            self.assertIn(basis, migration)

    def test_guarded_writer_persists_actual_year_basis_and_note(self):
        writer = (ROOT / "apps/platform/src/Bank/SourceOnlyPublisher.php").read_text()
        self.assertIn("reference_year,classification_basis,classification_note", writer)
        self.assertIn("'reference_year' => $src['reference_year']", writer)
        self.assertIn("'classification_basis' => $src['classification_basis']", writer)
        self.assertIn("'classification_note' => $src['classification_note']", writer)
        self.assertIn("Nearest same-lineage proxy must name the actual reference year", writer)
        self.assertIn("mb_strlen(trim($proxyNote)) > 300", writer)

    def test_runner_projection_and_contract_expose_proxy_label(self):
        facts = (ROOT / "apps/platform/src/Bank/BankQuestionFacts.php").read_text()
        api = (ROOT / "contracts/openapi/core-v1.yaml").read_text()
        ui = (ROOT / "apps/platform/public/assets/web/pages/question-facts.js").read_text()
        runner = (ROOT / "apps/platform/public/assets/web/pages/runner-view.js").read_text()
        for field in ["reference_year", "classification_basis", "classification_note"]:
            self.assertIn(f"link.{field}", facts)
        self.assertIn("classification_basis", ui)
        self.assertIn("classification_note", ui)
        self.assertIn("referenceNote", ui)
        self.assertIn("facts.referenceNote", runner)
        self.assertIn("facts.scopeNote", runner)
        self.assertIn("                    reference_year:", api)
        self.assertIn("                    classification_basis:", api)
        self.assertIn("                    classification_note:", api)
        self.assertIn("nearest_same_lineage_proxy", ui)

    def test_same_lineage_proxy_is_a_separate_fail_closed_validator_path(self):
        validator = (ROOT / "scripts/references/apply_classification.py").read_text()
        auditor = (ROOT / "scripts/references/audit_private_study_batches.py").read_text()
        operator = (ROOT / "scripts/references/import_verified_sources.php").read_text()
        for text in [validator, auditor, operator]:
            self.assertIn("nearest_same_lineage_proxy", text)
        self.assertIn("is_selected_same_lineage_proxy", validator)
        self.assertIn("valid_proxy_note", validator)
        self.assertIn("--allow-nearest", validator)
        self.assertIn("historical audit only", validator)
        self.assertIn("same-lineage-proxy-", operator)
        self.assertIn("BackupManifest::verify", operator)


if __name__ == "__main__":
    unittest.main()
