from pathlib import Path
from PIL import Image,ImageDraw,ImageFont
import json,html,math
W=Path(__file__).resolve().parent;R=W.parents[1];E=R/'diagnostics/RD-25-WP1-FIX_media-trim-classification_evidence_20261011';OUT=E/'contact-sheet';OUT.mkdir(exist_ok=True)
A=json.loads((E/'gd-assets.json').read_text());required=[62,84,87,97,103,104,105,106,107,108,118,119,122,123,131,135,143,178,179,186,194,213,222,224,228,233,235,260,261,275,280,282,283];font=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',13)
def tile(a,slot):
 im=Image.open(R/'work/rd25-wp1-fix-phase0-20261011/images'/a['fit_relative']).convert('RGBA');w,h=180,180 if slot=='desktop' else 225;fw,fh=im.size
 if a['metadata']['kind']=='cut':z=min(.84*w/fw,.94*h/fh);rw,rh=round(fw*z),round(fh*z);x,y=round(.08*w+(.84*w-rw)/2),h-rh
 else:z=max(w/fw,h/fh);rw,rh=round(fw*z),round(fh*z);x,y=round((w-rw)/2),round((h-rh)/2)
 im=im.resize((max(1,rw),max(1,rh)),Image.Resampling.LANCZOS);canvas=Image.new('RGBA',(w,h),'white');canvas.alpha_composite(im,(x,y));return canvas.convert('RGB')
names=[]
for page in range(math.ceil(len(required)/6)):
 ids=required[page*6:(page+1)*6];canvas=Image.new('RGB',(790,300*len(ids)+40),'#f2f3f4');d=ImageDraw.Draw(canvas)
 d.text((12,10),'Before desktop/mobile | Actual GD fit desktop/mobile',font=font,fill='black')
 for n,i in enumerate(ids):
  a=next(x for x in A if x['id']==i); y=40+n*300;d.text((10,y),f"#{i:03d} {a['metadata']['kind']} {Path(a['source']).name[:84]}",font=font,fill='black');d.text((10,y+18),str(a['metadata']['crop_box_exclusive'])+' '+a['metadata']['trim_method'],font=font,fill='black')
  for j,slot in enumerate(['desktop','mobile']):
    oldpath=R/'diagnostics/RD-25-WP1-FIX_phase0_evidence_20261011/contact-sheet/tiles'/f'{i:03d}-before-{slot}.png'
    if oldpath.exists():before=Image.open(oldpath)
    else:
     # #233's strict fallback retains the full source canvas and existing cut class.
     # Its actual fit is also the before fit geometry; no missing Phase 0 file is invented.
     assert i==233 and a['metadata']['crop_box_exclusive']==[0,0,a['metadata']['width'],a['metadata']['height']]
     before=tile(a,slot)
    canvas.paste(before,(10+j*195,y+42));canvas.paste(tile(a,slot),(400+j*195,y+42))
 p=OUT/f'actual-gd-{page+1:02d}.png';canvas.save(p);names.append(p.name)
(OUT/'index.html').write_text('<!doctype html><meta charset="utf-8"><title>RD25 actual GD fits</title><h1>Phase 1 actual generated fits</h1><p>33 required/reference assets; Phase 0 before tiles vs actual PHP/GD fit files. Original pixels unchanged. Desktop/mobile slot approximation; browser screenshots are separate.</p>'+''.join(f'<p><a href="{n}">{n}</a></p><img style="max-width:100%" src="{n}">' for n in names),encoding='utf8')
print(json.dumps({'assets':len(required),'pages':len(names)}))
