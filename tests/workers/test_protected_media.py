import importlib.util,sys,tempfile,unittest
from pathlib import Path
P=Path(__file__).resolve().parents[2]/'apps/workers/protected-media/worker.py';spec=importlib.util.spec_from_file_location('fanoos_pm_worker',P);worker=importlib.util.module_from_spec(spec);sys.modules[spec.name]=worker;spec.loader.exec_module(worker)
class Source:
    def __init__(self,data=b'%PDF-fake'):self.data=data;self.calls=0
    def fetch(self,c,d,max_bytes):self.calls+=1;d.write_bytes(self.data)
class Sink:
    def publish(self,j,p,c):return f'pm:{j}:{c[:16]}'
class Inspector:
    def __init__(self,pages=2):self.pages=pages
    def inspect(self,p,d):return self.pages
class Raster:
    def __init__(self,count=2):self.count=count
    def render(self,s,o,l,f,d):o.write_bytes(b'%PDF-output');return self.count
class ProtectedMediaTest(unittest.TestCase):
    def job(self,**kw):
        j={'job_id':'11111111-1111-4111-8111-111111111111','object_capability':'opaque','watermark_label':'کاربر','forensic_id':'ABCDEF123456','limits':{'max_input_bytes':1024,'max_pages':10,'max_seconds':30}};j.update(kw);return j
    def test_limits_bounded(self):
        l=worker.JobLimits.parse({'max_input_bytes':9999999999,'max_pages':9999,'max_seconds':9999});self.assertLessEqual(l.max_input_bytes,200*1024*1024);self.assertEqual(l.max_pages,2000);self.assertEqual(l.max_seconds,1800)
    def test_non_pdf_rejected(self):
        p=worker.JobProcessor(Source(b'hello'),Sink(),Inspector(),Raster())
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job())
        self.assertEqual(c.exception.code,'output_invalid')
    def test_page_limit(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(20),Raster())
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job(limits={'max_input_bytes':1024,'max_pages':2,'max_seconds':30}))
        self.assertEqual(c.exception.code,'page_limit')
    def test_render_count_mismatch(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(2),Raster(1))
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job())
        self.assertEqual(c.exception.code,'output_invalid')
    def test_success_metadata_bounded(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(2),Raster(2));r=p.process(self.job());self.assertEqual(r['mime'],'application/pdf');self.assertRegex(r['checksum_sha256'],r'^[0-9a-f]{64}$');self.assertTrue(r['artifact_ref'].startswith('pm:'))
    def test_private_spool_ref_no_path(self):
        with tempfile.TemporaryDirectory() as d:
            src=Path(d)/'x.pdf';src.write_bytes(b'x');sink=worker.PrivateSpoolArtifactSink(Path(d)/'out');ref=sink.publish('11111111-1111-4111-8111-111111111111',src,'a'*64);self.assertNotIn('/',ref);self.assertTrue(ref.startswith('pm:'))
    def test_runtime_build_is_fail_closed(self):
        rp=Path(__file__).resolve().parents[2]/'apps/workers/protected-media/runtime.py';text=rp.read_text();self.assertIn('canonical object-capability redemption adapter is not frozen',text);self.assertNotIn('FANOOS_CAPABILITY_REDEMPTION_READY',text)
    def test_forensic_trace_is_page_bound(self):
        text=P.read_text();self.assertIn("f'{forensic}:{idx}'",text);self.assertIn('FANOOS·{forensic[:12]}',text)
