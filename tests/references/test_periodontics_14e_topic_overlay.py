"""Regression contract: 160 topics, zero official citation writes."""
import ast
import pathlib
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCE = ROOT / "scripts/references/build_periodontics_14e_topic_overlay.py"


class PeriodonticsTopicContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.content = SOURCE.read_text()

    def test_all_years_and_manual_conflicts_are_covered(self):
        tree = ast.parse(self.content)
        names = {x.targets[0].id for x in tree.body
                 if isinstance(x, ast.Assign) and len(x.targets) == 1
                 and isinstance(x.targets[0], ast.Name)}
        self.assertIn("TOPICS_1398", names)
        self.assertIn("CROSSWALK_13E", names)
        self.assertIn("QUESTION_OVERRIDES_13E", names)
        self.assertIn("QUESTION_OVERRIDES_14E", names)
        self.assertIn("VERIFIED_PRINTED_PAGE", names)
        self.assertIn("160", self.content)
        self.assertIn("14e VERSIONED CHAPTER CONTENTS ONLY", self.content)

    def test_topic_layer_cannot_write_back_to_source_or_answer(self):
        self.assertNotIn("UPDATE bank_question_sources", self.content)
        self.assertNotIn("INSERT INTO bank_question_sources", self.content)
        self.assertIn("publication_eligible_as_official_year_reference", self.content)
        self.assertIn("EXPECTED_INPUT_SHA256", self.content)
        self.assertIn("source_database", "source_database")

if __name__ == "__main__":
    unittest.main()
