from pathlib import Path
import json, html, os, shutil, hashlib
from collections import Counter

w=Path(__file__).resolve().parent
repo=w.parent.parent
source=w/'source'
e=repo/'diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012'
data=json.loads((source/'phase0.json').read_text(encoding='utf-8'))
refs=sorted({html.unescape(x['image']) for x in data['products']+data['additional_images'] if x['image']})

def fixture(name, warm_meta=False, ids=None):
    root=w/name
    (root/'cache').mkdir(parents=True,exist_ok=True)
    use=refs if ids is None else sorted({html.unescape(x['image']) for x in data['products']+data['additional_images'] if x['image'] and x['product_id'] in ids})
    for rel in use:
        a=source/'image'/rel; b=root/'image'/rel
        b.parent.mkdir(parents=True,exist_ok=True)
        if b.exists():
            assert hashlib.sha256(a.read_bytes()).digest()==hashlib.sha256(b.read_bytes()).digest(), b
        else:
            shutil.copy2(a,b)
    if warm_meta:
        for p in (w/'fixture/cache').glob('rd25-media-v4-*.json'):
            (root/'cache'/p.name).write_bytes(p.read_bytes())
    return root

if __name__=='__main__':
    fixture('fixture')
    # The measured plan is finalized after source classification; initial
    # representative product/cart data contains only public image references.
    (e/'plan.json').write_text(json.dumps({'representative_ids':[78,80,77,131,144,96,85], 'cold_product_ids':[85], 'cold_cart_ids':[78,80,77,131,144,96,85]},indent=2)+'\n',encoding='utf-8')
    print('fixture_ready='+str(len(refs))+'_public_images')
