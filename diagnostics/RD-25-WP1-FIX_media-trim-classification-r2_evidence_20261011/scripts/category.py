from pathlib import Path
import json,subprocess,hashlib,os,shutil,html
W=Path(__file__).resolve().parent;R=W.parents[1];E=R/'diagnostics/RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011';PHP='C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'
owner=E/'category-owner-lookup.json'
if owner.exists():
 data=json.loads(owner.read_text(encoding='utf8'));assert data['done']=='ok' and data['db_writes']==data['file_writes']==0
 dataset={'scope':'Owner current public enabled store-0 acsesuary and descendants primary product images, '+data['utc'],'products':data['products']}
 tag='real-category'
else:
 raw=json.loads((R/'diagnostics/RD-25-WP1-FIX_phase0_evidence_20261011/proposed-assets.json').read_text(encoding='utf8'))
 selected=[a for a in raw if a['category']=='acsesuary']
 dataset={'scope':'Conservative entire archive acsesuary/3D asset superset, includes secondary images; NOT claimed actual primary category membership','products':[{'product_id':900000+a['id'],'image':a['source']} for a in selected]};tag='archive-superset'
 # Largest image first to exercise the highest-cost primary-sized photo admission.
 dataset['products'].sort(key=lambda a:next(x['source_dims'][0]*x['source_dims'][1] for x in selected if x['source']==a['image']),reverse=True)
raw_assets=json.loads((R/'diagnostics/RD-25-WP1-FIX_phase0_evidence_20261011/asset-source-manifest.json').read_text(encoding='utf8'))['assets'];allowed={a['source'] for a in raw_assets}
dimensions={a['source']:a['source_dims'][0]*a['source_dims'][1] for a in json.loads((R/'diagnostics/RD-25-WP1-FIX_phase0_evidence_20261011/proposed-assets.json').read_text(encoding='utf8'))}
unknown=[p['image'] for p in dataset['products'] if p['image'] and html.unescape(p['image']) not in allowed];assert not unknown,('Fresh originals required for unknown primary paths',unknown)
dataset['products'].sort(key=lambda p:dimensions.get(html.unescape(p['image']),0),reverse=True)
ds=E/(tag+'-dataset.json');ds.write_text(json.dumps(dataset,indent=2,ensure_ascii=False),encoding='utf8');f=W/(tag+'-fixture-final');assert not f.exists(),f
(f/'cache').mkdir(parents=True)
for p in dataset['products']:
 if not p['image']:continue
 image=html.unescape(p['image']);source=R/'work/rd25-wp1-fix-phase0-20261011/images'/image;assert source.is_file(),p
 target=f/'image'/image;target.parent.mkdir(parents=True,exist_ok=True);os.link(source,target)
def run(mode):
 proc=subprocess.run([PHP,str(W/'category-probe.php'),str(f),str(ds),mode],capture_output=True,encoding='utf8');assert proc.returncode==0,proc.stdout+proc.stderr
 if mode!='legacy-warm':shutil.copyfile(f/(mode+'.json'),E/(tag+'-'+mode+'.json'));shutil.copyfile(f/(mode+'.html'),E/(tag+'-'+mode+'.html'))
 return proc.stdout
run('legacy-warm')
# WP1-FIX cold means v4 metadata/v2 fit cache cold; deployed legacy thumbs are warm.
assert not list((f/'cache').glob('rd25-media-v4-*.json'))
for mode in ['cold','second-request','third-request']:
 data=json.loads(run(mode));print(json.dumps({k:v for k,v in data.items() if k not in ('trace','warnings')}))
