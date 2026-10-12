from pathlib import Path
import json, hashlib
import build
w=build.w;repo=build.repo;sha=build.sha
module='extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php'
context={p:sha(build.original[p]) for p in ['catalog/model/tool/image.php','system/library/image.php','catalog/controller/product/thumb.php','catalog/view/template/product/thumb.twig']}
context[module]=sha((w/'ga4'/module).read_bytes())
anchors={}
for version in ['4.0.2.3','4.1.0.3']:
    data=json.loads((w/f'ga4-{version}.json').read_text())
    for a in data['anchors']:
        anchors[(a['template'],a['search'])]={'template':a['template'],'search':a['search'],'index':a['index'],'count':a['before']}
template=(w/'runner.php').read_text()
for stage in [2,3]:
    slug='product-gallery' if stage==2 else 'small-thumbs'
    task=f'RD-25-WP{stage}_{slug}_20261012'
    spec={'targets':build.specs[stage],'context':dict(context),'ga4_anchors':list(anchors.values()),'repeat_hashes':{p:[v['after_sha256']] for p,v in build.specs[stage].items() if p!=build.HEADER}}
    if stage==2:
        spec['repeat_hashes'][build.CSS].append(build.specs[3][build.CSS]['after_sha256'])
        spec['later_css_hash']=build.specs[3][build.CSS]['after_sha256']
        spec['later_files']={p:v['after_sha256'] for p,v in build.specs[3].items() if p not in [build.HEADER,build.CSS]}
    else:
        for p in ['catalog/controller/product/product.php','catalog/view/template/product/product.twig']:
            spec['context'][p]=build.specs[2][p]['after_sha256']
    out=template.replace('@TASK@',task).replace('@FILENAME@',task+'.php').replace('@MARKER@',f'RD-25-WP{stage} 20261012').replace('@TOKEN@',f'rd25-wp{stage}-20261012').replace('@DESCRIPTION@','4:5 product gallery with keyboard selection and original lightbox URLs.' if stage==2 else '52x65 mini-cart/cart fit thumbnails; commerce controls unchanged.').replace('@SPEC@',json.dumps(spec,ensure_ascii=False,indent=2))
    (repo/'patches'/f'{task}.php').write_text(out,encoding='utf-8',newline='\n')
print(json.dumps({'runners':2,'ga4_distinct_anchors':len(anchors),'model_sha256':context[module]}))
