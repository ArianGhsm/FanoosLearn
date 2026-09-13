from __future__ import annotations
import hashlib,os,random,re,shutil,subprocess,sys,tempfile,time
from dataclasses import dataclass
from pathlib import Path
from typing import Protocol
sys.path.insert(0,str(Path(__file__).resolve().parents[3]/'packages/python'))
from fanoos_bot.pdf_fingerprint import (
    PdfFingerprintError,WATERMARK_VERSION,canonical_user_id,derive_fingerprint_material,file_sha256,
    secure_page_seed,secure_raster_symbol_layout,
)

class WorkerFailure(RuntimeError):
    def __init__(self,code:str,message:str):super().__init__(message);self.code=code
class CapabilitySource(Protocol):
    def fetch(self,job:dict,destination:Path,max_bytes:int)->None:...
class ArtifactSink(Protocol):
    def publish(self,job:dict,source:Path,checksum:str)->str:...
@dataclass(frozen=True)
class JobLimits:
    max_input_bytes:int
    max_pages:int
    max_seconds:int
    @classmethod
    def parse(cls,value):
        value=value if isinstance(value,dict) else {}
        return cls(max(1024,min(200*1024*1024,int(value.get('max_input_bytes',50*1024*1024)))),max(1,min(2000,int(value.get('max_pages',500)))),max(5,min(1800,int(value.get('max_seconds',300)))))

class CommandPdfInspector:
    def __init__(self,qpdf='qpdf',pdfinfo='pdfinfo'):self.qpdf=qpdf;self.pdfinfo=pdfinfo
    def inspect(self,path:Path,deadline:float)->int:
        timeout=max(.1,deadline-time.monotonic())
        try:subprocess.run([self.qpdf,'--check',str(path)],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=timeout)
        except subprocess.TimeoutExpired as exc:raise WorkerFailure('time_limit','PDF validation timed out') from exc
        except Exception as exc:raise WorkerFailure('render_failed','PDF validation failed') from exc
        timeout=max(.1,deadline-time.monotonic())
        try:out=subprocess.check_output([self.pdfinfo,str(path)],text=True,stderr=subprocess.DEVNULL,timeout=timeout)
        except subprocess.TimeoutExpired as exc:raise WorkerFailure('time_limit','PDF inspection timed out') from exc
        except Exception as exc:raise WorkerFailure('render_failed','PDF inspection failed') from exc
        m=re.search(r'^Pages:\s+(\d+)',out,re.M);return int(m.group(1)) if m else 0

def draw_secure_raster_marks(image,material,page_index:int,dpi:int)->int:
    """Burn the ported secure-raster micro-dot constellation into one rasterized page.

    Positions/bits come verbatim from ``secure_raster_symbol_layout`` (itself a
    literal port of legacy's ``dent_bot.pdf_fingerprint``); only the final ink
    step is FANOOS-specific, since this worker already has a Pillow raster
    image in hand instead of a live PDF page to draw vector shapes on. The
    circle/cross-line style choice and its RNG seed/ranges mirror legacy's
    ``_place_secure_raster_constellation`` exactly; only unit conversion
    (PDF points -> this raster's pixels) and a 0.75px floor -- so a mark
    committed to a fixed-DPI raster immediately can never round away to
    nothing -- are new.
    """
    from PIL import ImageDraw
    scale=dpi/72.0
    width_pt,height_pt=image.width/scale,image.height/scale
    draw=ImageDraw.Draw(image,'RGBA');color=(51,43,56,56);total=0
    for copy_index,layout in enumerate(secure_raster_symbol_layout(width_pt,height_pt,material,page_index)):
        rng=random.Random(int.from_bytes(secure_page_seed(material,page_index,f'mark-style-{copy_index}'.encode('ascii'))[:8],'big'))
        for pair,_logical_index,bit in layout:
            x,y=pair[int(bit)];px,py=x*scale,y*scale
            if rng.random()<0.72:
                r=max(0.75,rng.uniform(0.38,0.54)*scale);draw.ellipse((px-r,py-r,px+r,py+r),fill=color)
            else:
                half=max(0.75,rng.uniform(0.34,0.50)*scale);draw.line((px-half,py,px+half,py),fill=color,width=1);draw.line((px,py-half,px,py+half),fill=color,width=1)
            total+=1
    return total

class PopplerPillowRasterizer:
    def __init__(self,pdftoppm='pdftoppm',font_path:str|None=None,dpi:int=180):self.pdftoppm=pdftoppm;self.font_path=font_path;self.dpi=max(144,min(220,int(dpi)))
    def render(self,source:Path,output:Path,label:str,material,deadline:float)->int:
        try:from PIL import Image,ImageDraw,ImageFont
        except ImportError as exc:raise WorkerFailure('internal_error','Pillow is unavailable') from exc
        work=output.parent/'pages';work.mkdir()
        timeout=max(.1,deadline-time.monotonic())
        try:subprocess.run([self.pdftoppm,'-png','-r',str(self.dpi),str(source),str(work/'page')],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=timeout)
        except subprocess.TimeoutExpired as exc:raise WorkerFailure('time_limit','PDF rasterization timed out') from exc
        except Exception as exc:raise WorkerFailure('render_failed','PDF rasterization failed') from exc
        def key(p):
            m=re.search(r'-(\d+)\.png$',p.name);return int(m.group(1)) if m else 0
        pages=sorted(work.glob('page-*.png'),key=key)
        if not pages:raise WorkerFailure('render_failed','No raster pages were produced')
        font=None
        if self.font_path:
            try:font=ImageFont.truetype(self.font_path,28)
            except Exception:font=None
        font=font or ImageFont.load_default(); images=[]
        for idx,path in enumerate(pages):
            if time.monotonic()>deadline:raise WorkerFailure('time_limit','Rendering timed out')
            im=Image.open(path).convert('RGB');draw=ImageDraw.Draw(im,'RGBA')
            footer=f'{label} · FANOOS {material.trace_code}'
            draw.rectangle((0,max(0,im.height-62),im.width,im.height),fill=(255,255,255,190));draw.text((24,max(8,im.height-52)),footer,font=font,fill=(0,0,0,165))
            draw_secure_raster_marks(im,material,idx,self.dpi)
            images.append(im)
        images[0].save(output,'PDF',save_all=True,append_images=images[1:],resolution=float(self.dpi))
        for im in images:im.close()
        return len(pages)

class ApiCapabilitySource:
    def __init__(self,api):self.api=api
    def fetch(self,job:dict,destination:Path,max_bytes:int)->None:
        data=self.api.media_source_redeem(str(job['job_id']),str(job['lease_token']),str(job['object_capability']),max_bytes)
        if not isinstance(data,bytes) or len(data)<5 or len(data)>max_bytes or not data.startswith(b'%PDF-'):raise WorkerFailure('input_unavailable','Platform source response is not a bounded PDF')
        destination.write_bytes(data);os.chmod(destination,0o600)

class ApiArtifactSink:
    def __init__(self,api):self.api=api
    def publish(self,job:dict,source:Path,checksum:str)->str:
        if not re.fullmatch(r'[0-9a-f]{64}',checksum):raise WorkerFailure('output_invalid','output checksum invalid')
        size=source.stat().st_size
        authorization=self.api.media_authorize_publish(str(job['job_id']),str(job['lease_token']),str(job['completion_key']),checksum,size,'application/pdf')
        capability=str((authorization or {}).get('upload_capability') or '')
        if not capability:raise WorkerFailure('output_invalid','upload capability missing')
        published=self.api.media_publish_pdf(capability,source.read_bytes())
        if not isinstance(published,dict):raise WorkerFailure('output_invalid','artifact publication response invalid')
        ref=str(published.get('artifact_ref') or '')
        if not re.fullmatch(r'pma:[0-9a-f-]{36}',ref,re.I):raise WorkerFailure('output_invalid','artifact reference invalid')
        if str(published.get('checksum_sha256') or '').lower()!=checksum or int(published.get('size') or 0)!=size or published.get('mime')!='application/pdf':raise WorkerFailure('output_invalid','artifact publication metadata mismatch')
        return ref

class PrivateSpoolArtifactSink:
    """Test/reference sink only. Production runtime must use ApiArtifactSink."""
    def __init__(self,root:Path):self.root=root;root.mkdir(parents=True,exist_ok=True);os.chmod(root,0o700)
    def publish(self,job:dict,source:Path,checksum:str)->str:
        job_id=str(job.get('job_id') or '')
        if not re.fullmatch(r'[0-9a-f-]{36}',job_id,re.I):raise WorkerFailure('output_invalid','job id invalid')
        target=self.root/f'{job_id}-{checksum[:16]}.pdf';shutil.copyfile(source,target);os.chmod(target,0o600);return f'pm:{job_id}:{checksum[:16]}'

class JobProcessor:
    RENDERER_ALGORITHM_VERSION='fanoos-raster-v2'
    MAX_OUTPUT_BYTES=100*1024*1024
    def __init__(self,source:CapabilitySource,sink:ArtifactSink,inspector=None,rasterizer=None,temp_root:Path|None=None,fingerprint_key:bytes=b''):self.source=source;self.sink=sink;self.inspector=inspector or CommandPdfInspector();self.rasterizer=rasterizer or PopplerPillowRasterizer();self.temp_root=temp_root;self.fingerprint_key=bytes(fingerprint_key)
    def _fingerprint_material(self,job:dict,src:Path):
        # derive_fingerprint_material fails closed (PdfFingerprintError) on an
        # unset/short key -- self.fingerprint_key must never default to
        # anything but b'' so a missing key cannot silently mark with a
        # predictable secret. This key must never be rotated: rotating it
        # orphans every mark already issued against it (see runtime.env.example).
        try:
            return derive_fingerprint_material(
                self.fingerprint_key,
                issuance_id=f"iss_{job.get('job_id') or ''}",
                user_id=canonical_user_id(str(job.get('user_id') or '')),
                document_id=str(job.get('resource_id') or job.get('job_id') or ''),
                source_hash=file_sha256(src),
                watermark_version=WATERMARK_VERSION,
            )
        except PdfFingerprintError as exc:raise WorkerFailure('internal_error',str(exc)) from exc
    def process(self,job:dict)->dict:
        if str(job.get('renderer_algorithm_version') or '')!=self.RENDERER_ALGORITHM_VERSION:raise WorkerFailure('render_failed','renderer algorithm version is unsupported')
        limits=JobLimits.parse(job.get('limits'));deadline=time.monotonic()+limits.max_seconds
        with tempfile.TemporaryDirectory(prefix='fanoos-pm-',dir=str(self.temp_root) if self.temp_root else None) as d:
            root=Path(d);os.chmod(root,0o700);src=root/'input.pdf';out=root/'output.pdf'
            self.source.fetch(job,src,limits.max_input_bytes)
            if not src.is_file() or src.stat().st_size<5 or src.stat().st_size>limits.max_input_bytes:raise WorkerFailure('input_too_large','input size invalid')
            with src.open('rb') as f:
                if f.read(5)!=b'%PDF-':raise WorkerFailure('output_invalid','input is not a PDF')
            pages=self.inspector.inspect(src,deadline)
            if pages<1:raise WorkerFailure('render_failed','page count unavailable')
            if pages>limits.max_pages:raise WorkerFailure('page_limit','PDF exceeds page limit')
            material=self._fingerprint_material(job,src)
            rendered=self.rasterizer.render(src,out,str(job.get('watermark_label') or 'FANOOS'),material,deadline)
            if rendered!=pages or not out.is_file():raise WorkerFailure('output_invalid','rendered output mismatch')
            size=out.stat().st_size;output_limit=min(self.MAX_OUTPUT_BYTES,limits.max_input_bytes*4)
            if size<1 or size>output_limit:raise WorkerFailure('output_invalid','output size invalid')
            checksum=hashlib.sha256(out.read_bytes()).hexdigest();ref=self.sink.publish(job,out,checksum)
            return {'checksum_sha256':checksum,'size':size,'mime':'application/pdf','artifact_ref':ref}
