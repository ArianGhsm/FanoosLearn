import importlib.util,shutil,sys,tempfile,unittest
from pathlib import Path
P=Path(__file__).resolve().parents[2]/'apps/workers/protected-media/worker.py';spec=importlib.util.spec_from_file_location('fanoos_pm_worker',P);worker=importlib.util.module_from_spec(spec);sys.modules[spec.name]=worker;spec.loader.exec_module(worker)
FINGERPRINT_KEY=b'k'*32
class Source:
    def __init__(self,data=b'%PDF-fake'):self.data=data;self.calls=0
    def fetch(self,j,d,max_bytes):self.calls+=1;d.write_bytes(self.data)
class Sink:
    def publish(self,j,p,c):return f"pm:{j['job_id']}:{c[:16]}"
class Inspector:
    def __init__(self,pages=2):self.pages=pages
    def inspect(self,p,d):return self.pages
class FailingInspector:
    def __init__(self,code,message):self.code=code;self.message=message
    def inspect(self,p,d):raise worker.WorkerFailure(self.code,self.message)
class Raster:
    def __init__(self,count=2,output_bytes=b'%PDF-output'):self.count=count;self.output_bytes=output_bytes
    def render(self,s,o,l,m,d):o.write_bytes(self.output_bytes);return self.count
class Api:
    def __init__(self):self.calls=[];self.source=b'%PDF-source';self.published=None
    def media_source_redeem(self,j,l,c,m):self.calls.append(('source',j,l,c,m));return self.source
    def media_authorize_publish(self,j,l,k,checksum,size,mime):self.calls.append(('authorize',j,l,k,checksum,size,mime));return {'upload_capability':'upload-cap'}
    def media_publish_pdf(self,cap,pdf):
        self.calls.append(('publish',cap,pdf));self.published=pdf;return {'artifact_ref':'pma:77777777-7777-4777-8777-777777777777','checksum_sha256':__import__('hashlib').sha256(pdf).hexdigest(),'size':len(pdf),'mime':'application/pdf'}
class ProtectedMediaTest(unittest.TestCase):
    def job(self,**kw):
        j={'job_id':'11111111-1111-4111-8111-111111111111','lease_token':'lease','completion_key':'completion','object_capability':'opaque','renderer_algorithm_version':worker.JobProcessor.RENDERER_ALGORITHM_VERSION,'watermark_label':'کاربر','forensic_id':'ABCDEF123456','user_id':'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee','resource_id':'resource-abc','limits':{'max_input_bytes':1024,'max_pages':10,'max_seconds':30}};j.update(kw);return j
    def test_limits_bounded(self):
        l=worker.JobLimits.parse({'max_input_bytes':9999999999,'max_pages':9999,'max_seconds':9999});self.assertLessEqual(l.max_input_bytes,200*1024*1024);self.assertEqual(l.max_pages,2000);self.assertEqual(l.max_seconds,1800)
    def test_renderer_version_mismatch_fails_closed(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(),Raster(),fingerprint_key=FINGERPRINT_KEY)
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job(renderer_algorithm_version='future-v2'))
        self.assertEqual(c.exception.code,'render_failed')
    def test_renderer_version_is_bumped_from_the_weak_footer_trace_scheme(self):
        self.assertEqual(worker.JobProcessor.RENDERER_ALGORITHM_VERSION,'fanoos-raster-v2')
    def test_non_pdf_rejected(self):
        p=worker.JobProcessor(Source(b'hello'),Sink(),Inspector(),Raster(),fingerprint_key=FINGERPRINT_KEY)
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job())
        self.assertEqual(c.exception.code,'output_invalid')
    def test_corrupt_pdf_fails_closed(self):
        p=worker.JobProcessor(Source(),Sink(),FailingInspector('render_failed','PDF validation failed'),Raster(),fingerprint_key=FINGERPRINT_KEY)
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job())
        self.assertEqual(c.exception.code,'render_failed')
    def test_timeout_fails_closed(self):
        p=worker.JobProcessor(Source(),Sink(),FailingInspector('time_limit','PDF validation timed out'),Raster(),fingerprint_key=FINGERPRINT_KEY)
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job())
        self.assertEqual(c.exception.code,'time_limit')
    def test_oversized_output_fails_closed(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(2),Raster(2,output_bytes=b'%PDF-'+b'x'*5000),fingerprint_key=FINGERPRINT_KEY)
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job(limits={'max_input_bytes':1024,'max_pages':10,'max_seconds':30}))
        self.assertEqual(c.exception.code,'output_invalid')
    def test_page_limit(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(20),Raster(),fingerprint_key=FINGERPRINT_KEY)
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job(limits={'max_input_bytes':1024,'max_pages':2,'max_seconds':30}))
        self.assertEqual(c.exception.code,'page_limit')
    def test_render_count_mismatch(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(2),Raster(1),fingerprint_key=FINGERPRINT_KEY)
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job())
        self.assertEqual(c.exception.code,'output_invalid')
    def test_success_metadata_bounded(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(2),Raster(2),fingerprint_key=FINGERPRINT_KEY);r=p.process(self.job());self.assertEqual(r['mime'],'application/pdf');self.assertRegex(r['checksum_sha256'],r'^[0-9a-f]{64}$');self.assertTrue(r['artifact_ref'].startswith('pm:'))
    def test_fingerprint_key_missing_fails_closed(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(2),Raster(2))
        with self.assertRaises(worker.WorkerFailure) as c:p.process(self.job())
        self.assertEqual(c.exception.code,'internal_error')
    def test_fingerprint_key_missing_never_falls_back_to_a_default(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(2),Raster(2),fingerprint_key=b'')
        self.assertEqual(p.fingerprint_key,b'')
        with self.assertRaises(worker.WorkerFailure):p.process(self.job())
    def test_fingerprint_material_differs_per_recipient(self):
        p=worker.JobProcessor(Source(),Sink(),Inspector(2),Raster(2),fingerprint_key=FINGERPRINT_KEY)
        with tempfile.TemporaryDirectory() as d:
            src=Path(d)/'input.pdf';src.write_bytes(b'%PDF-fake')
            one=p._fingerprint_material(self.job(job_id='11111111-1111-4111-8111-111111111111',user_id='aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'),src)
            two=p._fingerprint_material(self.job(job_id='22222222-2222-4222-8222-222222222222',user_id='ffffffff-1111-4222-8333-444444444444'),src)
        self.assertNotEqual(one.fingerprint_hash,two.fingerprint_hash)
        self.assertNotEqual(one.trace_code,two.trace_code)
        from fanoos_bot.pdf_fingerprint import secure_page_prefix
        self.assertNotEqual(secure_page_prefix(one,0),secure_page_prefix(two,0))
    def test_canonical_user_id_does_not_truncate_a_uuid_to_zero(self):
        # A UUID beginning with a hex letter (a-f) is exactly the case a naive
        # int() cast mangles to 0 in both PHP and Python.
        self.assertNotEqual(worker._canonical_user_id('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'),0)
        self.assertNotEqual(
            worker._canonical_user_id('aaaaaaaa-0000-4000-8000-000000000000'),
            worker._canonical_user_id('ffffffff-0000-4000-8000-000000000000'),
        )
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
    def test_runtime_requires_fingerprint_key_with_no_default(self):
        rp=Path(__file__).resolve().parents[2]/'apps/workers/protected-media/runtime.py';text=rp.read_text();self.assertIn("required('FANOOS_PROTECTED_MEDIA_FINGERPRINT_KEY')",text)
    def test_health_matches_active_capability_runtime_without_claiming_jobs(self):
        hp=Path(__file__).resolve().parents[2]/'apps/workers/protected-media/health.py';text=hp.read_text();self.assertIn('from runtime import build',text);self.assertIn('build()',text);self.assertIn('OK protected-media dependencies/configuration',text);self.assertNotIn('media_claim',text);self.assertNotIn('integration is not frozen',text);self.assertNotIn('worker must remain stopped',text)
    def test_weak_footer_plus_single_trace_mark_is_gone(self):
        text=P.read_text()
        self.assertNotIn("f'{forensic}:{idx}'",text)
        self.assertNotIn("FANOOS·{forensic[:12]}",text)
        self.assertIn('draw_secure_raster_marks',text)
        self.assertIn('material.trace_code',text)

@unittest.skipUnless(shutil.which('qpdf') and shutil.which('pdftoppm'),'qpdf/pdftoppm are not installed on this machine; CI verifies this end-to-end path')
class ProtectedMediaEndToEndTest(unittest.TestCase):
    """Runs the real worker path (real qpdf inspection, real pdftoppm
    rasterization, real secure-raster marking) against a real PDF fixture,
    then re-rasterizes the *output* PDF with pdftoppm and decodes the marks
    back out of it -- the same test-only sampler used in
    test_pdf_fingerprint.py, applied to genuine worker output this time."""
    @staticmethod
    def _fixture_pdf(path:Path)->None:
        from PIL import Image,ImageDraw
        pages=[]
        for index in range(2):
            image=Image.new('RGB',(1240,1754),(255,255,255));draw=ImageDraw.Draw(image)
            draw.text((60,60),f'Fixture page {index+1}',fill=(0,0,0))
            for row in range(15):draw.line((60,140+row*30,1180,140+row*30),fill=(220,220,220))
            pages.append(image)
        pages[0].save(path,'PDF',save_all=True,append_images=pages[1:],resolution=180.0)
    def test_full_worker_round_trip_marks_a_real_pdf_and_decodes_back(self):
        import subprocess,time as _time
        sys_path_added=str(Path(__file__).resolve().parents[2]/'packages/python')
        if sys_path_added not in sys.path:sys.path.insert(0,sys_path_added)
        from fanoos_bot.pdf_fingerprint import derive_fingerprint_material,file_sha256,secure_page_prefix,WATERMARK_VERSION
        from PIL import Image
        from tests.workers.test_pdf_fingerprint import _decode_secure_raster
        with tempfile.TemporaryDirectory() as d:
            root=Path(d);source=root/'source.pdf';self._fixture_pdf(source);output=root/'output.pdf'
            deadline=_time.monotonic()+60
            pages=worker.CommandPdfInspector().inspect(source,deadline)
            self.assertEqual(pages,2)
            material=derive_fingerprint_material(FINGERPRINT_KEY,issuance_id='iss_66666666666666666666666666666666666666',user_id=worker._canonical_user_id('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'),document_id='resource-e2e',source_hash=file_sha256(source),watermark_version=WATERMARK_VERSION)
            rendered=worker.PopplerPillowRasterizer().render(source,output,'FANOOS',material,deadline)
            self.assertEqual(rendered,pages)
            reraster=root/'reraster';reraster.mkdir()
            subprocess.run(['pdftoppm','-png','-r','180',str(output),str(reraster/'page')],check=True,timeout=60)
            page0=Image.open(sorted(reraster.glob('page-*.png'))[0])
            payload,valid,_corrections=_decode_secure_raster(page0,material,0,180)
            expected=secure_page_prefix(material,0)
            self.assertTrue(valid,'real worker output did not decode cleanly')
            self.assertEqual(payload,expected,f'identity in={expected.hex()} identity out={payload.hex()}')

if __name__=='__main__':
    unittest.main()
