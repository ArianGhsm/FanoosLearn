#!/usr/bin/env python3
import os,shutil,sys
from pathlib import Path
required=['qpdf','pdfinfo','pdftoppm']
missing=[x for x in required if shutil.which(x) is None]
try:from PIL import Image,features; pillow=True; raqm=bool(features.check('raqm'))
except Exception:pillow=False;raqm=False
if missing or not pillow or not raqm:
    print('FAIL protected-media dependencies',{'missing':missing,'pillow':pillow,'raqm':raqm});raise SystemExit(1)
print('FAIL capability redemption integration is not frozen; worker must remain stopped');raise SystemExit(1)
