"""Autonomy guards for the 48h independent Opus QA / host operator bridge."""
import json
import pathlib
import sys
import unittest

ROOT=pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'scripts/classification_v3'))
from qa_runner import strict_verdicts, SCHEMA
from operator_publisher import proc_group, Unsafe
import operator_publisher as pub
from runner import scan_quota
import tempfile
from unittest.mock import patch

class QaAndPublisherTests(unittest.TestCase):
    def entry(self,approved=True):
        return {'question_id':'01a12826-2001-77b8-b832-310bceef9cb7',
          'approved':approved,'answer_fully_supported':approved,
          'all_options_and_official_key_checked':approved,
          'no_material_conflict':approved,
          'edition_policy_checked':approved,
          'physical_page_chapter_sha_checked':approved,
          'reason':'This original page directly supports the complete key, or is pending.'}

    def proposal(self,confidence=.92):
        return {'question_id':self.entry()['question_id'],'confidence':confidence}

    def test_scientific_qa_full_flags_only(self):
        self.assertEqual(strict_verdicts({'batch_id':'abc','verdicts':[self.entry()]},
            'abc',[self.proposal()]),[self.proposal()])
        bad=self.entry()
        bad['no_material_conflict']=False
        self.assertEqual(strict_verdicts({'batch_id':'abc','verdicts':[bad]},
            'abc',[self.proposal()]),[])

    def test_confidence_threshold_fail_closed(self):
        self.assertEqual(strict_verdicts({'batch_id':'abc','verdicts':[self.entry()]},
            'abc',[self.proposal(.84)]),[])

    def test_qa_missing_verdict_rejected(self):
        with self.assertRaises(ValueError):
            strict_verdicts({'batch_id':'abc','verdicts':[]},'abc',[self.proposal()])

    def test_qa_wrong_identity_rejected(self):
        with self.assertRaises(ValueError):
            strict_verdicts({'batch_id':'wrong','verdicts':[self.entry()]},'abc',[self.proposal()])

    def test_qa_too_short_reason_rejected(self):
        bad=self.entry();bad['reason']='Maybe'
        with self.assertRaises(ValueError):
            strict_verdicts({'batch_id':'abc','verdicts':[bad]},'abc',[self.proposal()])

    def group(self,year=1392,basis='retrospective_proxy_1397_plus',kind='residency'):
        return [{'exam_type':kind,'year':year,'round':1,'subject':'endodontics',
                 'basis':basis,'number':137}]

    def test_old_residency_proxy_accepted(self):
        self.assertEqual(proc_group({},self.group())[-1],'retrospective_proxy_1397_plus')

    def test_old_residency_wrong_policy_rejected(self):
        with self.assertRaises(Unsafe):
            proc_group({},self.group(basis='official_exam_year'))

    def test_same_lineage_1396_accepted(self):
        self.assertEqual(proc_group({},self.group(year=1396,basis='same_lineage_proxy'))[-1],'same_lineage_proxy')

    def test_residency_1396_wrong_proxy_rejected(self):
        with self.assertRaises(Unsafe):
            proc_group({},self.group(year=1396,basis='retrospective_proxy_1397_plus'))

    def test_modern_residency_invalid_basis_rejected(self):
        with self.assertRaises(Unsafe):
            proc_group({},self.group(year=1402,basis='same_lineage_proxy'))

    def test_quota_reset_parsing(self):
        result=scan_quota('api_error_status:429, usage_limit_reached. resets 1:10am (UTC)')
        self.assertTrue(result[0])
        self.assertGreater(result[1],0)


class PublisherScientificAttestationTests(unittest.TestCase):
    def fixture(self,root):
        batch='0123456789abcdef'
        case=root/'cases'/('v3-'+batch)
        (root/'operator-requests').mkdir(parents=True)
        (root/'qa-approved').mkdir(parents=True)
        case.mkdir(parents=True)
        question={'question_id':'01a12826-2001-77b8-b832-310bceef9cb7',
          'decision':'proposed','key_class':'final','year':1392,'round':1,
          'exam_type':'residency','number':137,'subject':'endodontics',
          'edition':'torabinejad-endodontics@6e',
          'basis':'retrospective_proxy_1397_plus','reference_year':1406,
          'pdf_page':450,'node_id':'21',
          'quote':'Vertical incisions are parallel with the major blood',
          'confidence':0.92}
        request={'format':'fanoos.classifier-v3.operator-request.v1','batch_id':batch,
          'case_dir':str(case),'selected_editions':[question['edition']],
          'proxy_basis':question['basis'],'created':1794260535}
        rpath=root/'operator-requests'/(batch+'.json')
        rpath.write_text(json.dumps(request))
        draft=case/'decisions.draft.json'
        draft.write_text(json.dumps({'decisions':[question]}))
        (case/'session.out').write_text(json.dumps({'session_id':'research-session'}))
        verdict={'question_id':question['question_id'],'approved':True,
          'answer_fully_supported':True,'all_options_and_official_key_checked':True,
          'no_material_conflict':True,'edition_policy_checked':True,
          'physical_page_chapter_sha_checked':True,
          'reason':'The textbook fully supports the official keyed answer.'}
        model={'session_id':'independent-qa','is_error':False,
          'modelUsage':{'claude-opus-5-5':{}},
          'structured_output':{'batch_id':batch,'verdicts':[verdict]}}
        state=root/'root-state'
        sessions=state/'qa-sessions';attest=state/'qa-attestations'
        sessions.mkdir(parents=True);attest.mkdir()
        output=sessions/(batch+'.json')
        output.write_text(json.dumps(model))
        qa={'format':'fanoos.classifier-v3.independent-opus-qa.v1',
          'batch_id':batch,'approved_count':1,'approved':[question],
          'verdicts':[verdict],'research_draft_sha256':pub.sha(draft),
          'research_request_sha256':pub.sha(rpath),
          'independent_qa_session_id':'independent-qa',
          'qa_output_sha256':pub.sha(output)}
        qp=root/'qa-approved'/(batch+'.json')
        qp.write_text(json.dumps(qa))
        self.attest(root,batch,qa)
        return batch,case,question,qa

    def attest(self,root,batch,qa,**over):
        att={'batch_id':batch,'qa_packet_sha256':pub.sha(root/'qa-approved'/(batch+'.json')),
          'model_output_sha256':qa['qa_output_sha256'],'session_id':qa['independent_qa_session_id'],
          'draft_sha256':qa['research_draft_sha256'],'request_sha256':qa['research_request_sha256'],
          'approved_count':qa['approved_count']}
        att.update(over)
        (root/'root-state'/'qa-attestations'/(batch+'.json')).write_text(json.dumps(att))

    def patched(self,td):
        import os
        root=pathlib.Path(td)
        return [patch.object(pub,'BASE',root),patch.object(pub,'ROOT_UID',os.getuid()),
                patch.object(pub,'QA_SESSIONS',root/'root-state'/'qa-sessions'),
                patch.object(pub,'QA_ATTESTATIONS',root/'root-state'/'qa-attestations')]

    def run_verify(self,td,mutate=None):
        import contextlib
        with contextlib.ExitStack() as st:
            for p in self.patched(td):st.enter_context(p)
            root=pathlib.Path(td)
            batch,case,q,qa=self.fixture(root)
            if mutate:mutate(root,batch,case,q,qa)
            return pub.verify_research_qa(batch)

    def test_independently_opus_qa_attested(self):
        with tempfile.TemporaryDirectory() as td:
            result=self.run_verify(td)
            self.assertEqual(len(result[2]),1)
            self.assertEqual(result[2][0]['number'],137)

    def test_tampered_research_quote_rejected(self):
        def m(root,batch,case,q,qa):
            q['quote']='a fake citation after independent QA'
            (case/'decisions.draft.json').write_text(json.dumps({'decisions':[q]}))
        with tempfile.TemporaryDirectory() as td, self.assertRaises(Unsafe):
            self.run_verify(td,m)

    def test_reused_same_model_session_rejected(self):
        def m(root,batch,case,q,qa):
            (case/'session.out').write_text(json.dumps({'session_id':'independent-qa'}))
        with tempfile.TemporaryDirectory() as td, self.assertRaises(Unsafe):
            self.run_verify(td,m)

    def test_unapproved_scientific_flag_rejected(self):
        def m(root,batch,case,q,qa):
            qa['verdicts'][0]['no_material_conflict']=False
            (root/'qa-approved'/(batch+'.json')).write_text(json.dumps(qa))
            self.attest(root,batch,qa)
        with tempfile.TemporaryDirectory() as td, self.assertRaises(Unsafe):
            self.run_verify(td,m)

    def test_missing_root_attestation_rejected(self):
        def m(root,batch,case,q,qa):
            (root/'root-state'/'qa-attestations'/(batch+'.json')).unlink()
        with tempfile.TemporaryDirectory() as td, self.assertRaises((Unsafe,FileNotFoundError)):
            self.run_verify(td,m)

    def test_worker_forged_packet_not_matching_attestation_rejected(self):
        def m(root,batch,case,q,qa):
            qa['approved_count']=1;qa['created_at_utc']='forged'
            (root/'qa-approved'/(batch+'.json')).write_text(json.dumps(qa))
        with tempfile.TemporaryDirectory() as td, self.assertRaises(Unsafe):
            self.run_verify(td,m)

    def test_worker_case_model_output_not_trusted(self):
        def m(root,batch,case,q,qa):
            out=root/'root-state'/'qa-sessions'/(batch+'.json')
            (case/'qa-session-fixture.json').write_bytes(out.read_bytes())
            out.unlink()
        with tempfile.TemporaryDirectory() as td, self.assertRaises((Unsafe,FileNotFoundError)):
            self.run_verify(td,m)

    def test_attestation_not_root_owned_rejected(self):
        import os
        with tempfile.TemporaryDirectory() as td:
            root=pathlib.Path(td)
            with patch.object(pub,'BASE',root),patch.object(pub,'ROOT_UID',os.getuid()+12345),\
                 patch.object(pub,'QA_SESSIONS',root/'root-state'/'qa-sessions'),\
                 patch.object(pub,'QA_ATTESTATIONS',root/'root-state'/'qa-attestations'):
                batch,*_=self.fixture(root)
                with self.assertRaises(Unsafe):pub.verify_research_qa(batch)

    def test_conflicting_official_key_1391_q178_never_imported(self):
        def m(root,batch,case,q,qa):
            q.update({'year':1391,'number':178})
            (case/'decisions.draft.json').write_text(json.dumps({'decisions':[q]}))
            rp=root/'operator-requests'/(batch+'.json')
            qa.update({'approved':[q],'research_draft_sha256':pub.sha(case/'decisions.draft.json'),
                       'research_request_sha256':pub.sha(rp)})
            (root/'qa-approved'/(batch+'.json')).write_text(json.dumps(qa))
            self.attest(root,batch,qa)
        with tempfile.TemporaryDirectory() as td, self.assertRaises(Unsafe):
            self.run_verify(td,m)


if __name__=='__main__':
    unittest.main()
