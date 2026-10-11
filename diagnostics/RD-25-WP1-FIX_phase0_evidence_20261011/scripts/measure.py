"""RD-25 WP1-FIX Phase 0 measurement only. No production candidate or patch."""
from pathlib import Path
from PIL import Image
import numpy as np
import json, math, hashlib, tarfile, collections

W = Path(__file__).resolve().parent
R = W.parents[1]
E = R / 'diagnostics/RD-25-WP1-FIX_phase0_evidence_20261011'
BASE = json.loads((R/'diagnostics/RD-25-WP1_media-fit-catalog_evidence_20261009/classification.json').read_text(encoding='utf8'))['assets']
IMG = W/'images'
ARCHIVE = Path('C:/Users/14bez/Downloads/RD-25-WP1-followup-20261009.tar.gz')

def dump(name, data):
    (E/name).write_text(json.dumps(data, ensure_ascii=False, indent=2)+'\n', encoding='utf8')

def bbox(mask, minimum=1, pad=0):
    ys = np.flatnonzero(np.sum(mask, axis=1) >= minimum)
    xs = np.flatnonzero(np.sum(mask, axis=0) >= minimum)
    if len(xs)==0 or len(ys)==0: return None
    h,w=mask.shape
    return [max(0,int(xs[0])-pad),max(0,int(ys[0])-pad),min(w,int(xs[-1])+1+pad),min(h,int(ys[-1])+1+pad)]

def dims(box):
    return [box[2]-box[0],box[3]-box[1]] if box else None

if __name__ == '__main__':
    E.mkdir(exist_ok=True,parents=True)
    sources={a['source'] for a in BASE}
    manifest=[]
    with tarfile.open(ARCHIVE) as tar:
        for m in tar:
            s=m.name.removeprefix('./').removeprefix('image/')
            if s not in sources: continue
            assert m.isfile() and '..' not in Path(s).parts
            b=tar.extractfile(m).read(); p=IMG/s; p.parent.mkdir(parents=True,exist_ok=True); p.write_bytes(b)
            manifest.append({'source':s,'member':m.name,'sha256':hashlib.sha256(b).hexdigest(),'bytes':len(b),'archive_mtime':m.mtime})
    assert len(manifest)==286
    dump('asset-source-manifest.json',{'archive':str(ARCHIVE),'archive_sha256':hashlib.sha256(ARCHIVE.read_bytes()).hexdigest(),'owner_confirmation':'2026-10-11: primary images have not changed since this export','assets':manifest})
    rows=[]
    for i, a in enumerate(BASE):
        p=IMG/a['source']; im=Image.open(p).convert('RGBA'); ar=np.array(im); h,w=ar.shape[:2]
        # GD alpha has seven-bit precision. Match libgd PNG alpha normalization.
        alpha=np.rint((ar[:,:,3].astype(np.float32)//2)*255/127).astype(np.uint8)
        points=[(0,0),(w-1,0),(0,h-1),(w-1,h-1)]
        corners=[ar[y,x,:3].tolist()+[int(alpha[y,x])] for x,y in points]
        rgb=np.array([c[:3] for c in corners],dtype=np.int16)
        bg=np.median(rgb,axis=0)
        dist=np.max(np.abs(ar[:,:,:3].astype(np.int16)-bg),axis=2)
        opaque=bool(np.all(alpha==255)); transparent=bool(np.any(alpha<=16))
        r={'id':i,'source':a['source'],'source_dims':[w,h],'type':Image.open(p).format,'corner_rgba':corners,'v3':a,'background_rgb_median':bg.tolist(),'corner_max_channel_span':int(np.ptp(rgb,axis=0).max()),'all_pixels_opaque':opaque,'has_alpha_le_16':transparent,'alpha_histogram':np.bincount(alpha.ravel(),minlength=256).tolist(),'alpha_bbox':{},'background_bbox':{},'noise_sweep':{}}
        for threshold in [0,4,8,16,24,32,48,64]:
            r['alpha_bbox'][str(threshold)]=bbox(alpha>threshold)
        for tol in [2,4,6,8,10,12,16,20,24,32,40]:
            r['background_bbox'][str(tol)]=bbox((dist>tol)&(alpha>16))
        mask=alpha>16 if transparent else ((dist>12)&(alpha>16))
        for minimum in [1,2,3,4,8,16]:
            b=bbox(mask,minimum)
            r['noise_sweep'][str(minimum)]={'box':b,'excluded_pixels':int(np.sum(mask))-int(np.sum(mask[b[1]:b[3],b[0]:b[2]])) if b else int(np.sum(mask))}
        r['bg_distance_histogram']=np.bincount(dist.astype(np.uint8)[alpha>16],minlength=256).tolist()
        rows.append(r)
        if i%40==0: print('measured',i,flush=True)
    dump('measurements-raw.json',rows)
    summary={'count':len(rows),'alpha_corner_mismatch_to_GD':[]}
    for r in rows:
        if [c[3] for c in r['corner_rgba']] != r['v3']['corner_alpha']: summary['alpha_corner_mismatch_to_GD'].append(r['source'])
    dump('measurement-summary-initial.json',summary)
    for r in rows:
        if r['id'] in [97,98,103,104,105,106,107,108,118,119,122,123,131,135,143,178,179,186,194,213,224,228,235,260,261,282,283,87]:
            print(json.dumps({k:r[k] for k in ['id','source','source_dims','corner_rgba','corner_max_channel_span','all_pixels_opaque','alpha_bbox','background_bbox','noise_sweep']},ensure_ascii=True))
