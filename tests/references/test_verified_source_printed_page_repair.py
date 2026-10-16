from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]

class PrintedPageRepairContract(unittest.TestCase):
    def test_only_source_page_metadata_changes(self):
        text = (ROOT / 'scripts/references/repair_verified_source_printed_pages.php').read_text()
        updates = re.findall(r'UPDATE\s+([a-z_]+)\s+SET\s+(.+?)\s+WHERE', text, re.I | re.S)
        self.assertEqual([x[0].lower() for x in updates], ['bank_question_sources'])
        for field in ['page', 'printed_page', 'pdf_page', 'pdf_sha256']:
            self.assertRegex(updates[0][1], rf'\b{field}\s*=')
        self.assertNotRegex(text, r'\b(?:DELETE|TRUNCATE)\b')
        self.assertIn('page <=> :old_legacy AND printed_page <=> :old_printed', text)
        self.assertIn("questions_changed'=>0,'choices_changed'=>0,'answers_changed'=>0", text)

if __name__ == '__main__':
    unittest.main()
