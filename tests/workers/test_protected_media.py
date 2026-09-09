import importlib.util,sys,tempfile,unittest
from pathlib import Path
P=Path(__file__).resolve().parents[2]/'apps/workers/protected-media/worker.py';spec=importlib.util.spec_from_file_location('fanoos_pm_worker',P);worker=importlib.util.module_from_spec(spec);sys.modules[spec.name]=worker;spec.loader.exec_module(worker)
class Source:
    def __init__(self,data=b'%PDF-fake'):self.data=data;self.calls=0
    def fetch(self,j,d,max_bytes):self.calls+=1;d.write_bytes(self.data)
class Sink:
    def publish(self,j,p,c):return f"pm:{j['job_id']}:{c[:16]}"
class Inspector:
    def __init__(self,pages=2):self.pages=pages
    def inspect(self,p,d):return self.pages
class Raster:
    def __init__(self,count=2):self.count=count
    def render(self,s,o,l,f,d):o.write_bytes(b'%PDF-output');return self.count
class Api:
    def __init__(self):self.calls=[];self.source=b'%PDF-source';self.published=None
    def media_source_redeem(self,j,l,c,m):self.calls.append(('source',j,l,c,m));return self.source
    def media_authorize_publish(self,j,l,k,checksum,size,mime):self.calls.append(('authorize',j,l,k,checksum,size,mime));return {'upload_capability':'upload-cap'}
    def media_publish_pdf(self,cap,pdf):
        self.calls.append(('publish',cap,pdf));self.published=pdf;return {'artifact_ref':'pma:77777777-7777-4777-8777-777777777777','checksum_sha256':__import__('hashlib').sha256(pdf).hexdigest(),'size':len(pdf),'mime':'application/pdf'}
class ProtectedMediaTest(unittest.TestCase):
    def job(self,**kw):
        j={'job_id':'11111111-1111-4111-8111-111111111111','lease_token':'lease','completion_key':'completion','object_capability':'opaque','renderer_algorithm_version':'fanoos-raster-v1','watermark_label':'کاربر','forensic_id':'ABCDEF123456','limits':{'max_input_bytes':1024,'max_pages':10,'max_seconds':30}};j.update(kw);return j
    def test_limits_bounded(self):
        l=worker.JobLimits.parse({'max_input_bytes':9999999999,'max_pages':9999,'max_seconds':9999});self.assertLessEqual(l.max_input_bytes,200*1024*1024);self.assertEqual(l.max_pages,2000);self.assertEqual(l.max_seconds,1800)
    def test_renderer_version_mismatch_fails_closed(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(),Raster())
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job(renderer_algorithm_version='future-v2'))
        self.assertEqual(c.exception.code,'render_failed')
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
            src=Path(d)/'x.pdf';src.write_bytes(b'x');sink=worker.PrivateSpoolArtifactSink(Path(d)/'out');ref=sink.publish(self.job(),src,'a'*64);self.assertNotIn('/',ref);self.assertTrue(ref.startswith('pm:'))
    def test_api_source_binds_job_lease_and_capability(self):
        api=Api();source=worker.ApiCapabilitySource(api)
        with tempfile.TemporaryDirectory() as d:
            target=Path(d)/'source.pdf';source.fetch(self.job(),target,1024);self.assertEqual(target.read_bytes(),b'%PDF-source');self.assertEqual(api.calls[0],('source',self.job()['job_id'],'lease','opaque',1024))
    def test_api_artifact_sink_two_step_publish_and_validates_result(self):
        api=Api();sink=worker.ApiArtifactSink(api)
        with tempfile.TemporaryDirectory() as d:
            source=Path(d)/'out.pdf';source.write_bytes(b'%PDF-output');checksum=__import__('hashlib').sha256(source.read_bytes()).hexdigest();ref=sink.publish(self.job(),source,checksum);self.assertTrue(ref.startswith('pma:'));self.assertEqual(api.published,b'%PDF-output');self.assertEqual(api.calls[0][0],'authorize');self.assertEqual(api.calls[1][0],'publish')
    def test_runtime_uses_platform_adapters_not_private_spool(self):
        rp=Path(__file__).resolve().parents[2]/'apps/workers/protected-media/runtime.py';text=rp.read_text();self.assertIn('ApiCapabilitySource',text);self.assertIn('ApiArtifactSink',text);self.assertNotIn('PrivateSpoolArtifactSink',text);self.assertNotIn('canonical object-capability redemption adapter is not frozen',text)
    def test_health_matches_active_capability_runtime_without_claiming_jobs(self):
        hp=Path(__file__).resolve().parents[2]/'apps/workers/protected-media/health.py';text=hp.read_text();self.assertIn('from runtime import build',text);self.assertIn('build()',text);self.assertIn('OK protected-media dependencies/configuration',text);self.assertNotIn('media_claim',text);self.assertNotIn('integration is not frozen',text);self.assertNotIn('worker must remain stopped',text)
    def test_forensic_trace_is_page_bound(self):
        text=P.read_text();self.assertIn("f'{forensic}:{idx}'",text);self.assertIn('FANOOS·{forensic[:12]}',text)
