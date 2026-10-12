from pathlib import Path
import json,hashlib,re,difflib,shutil,base64,html
w=Path(__file__).resolve().parent;repo=w.parent.parent;sha=lambda b:hashlib.sha256(b).hexdigest();e=repo/'diagnostics/RD-25-WP2-FIX_gallery-frame-ratio_evidence_20261012';e.mkdir(exist_ok=True)
gate=json.loads((w/'source-gate.json').read_text());spec=json.loads((w/'spec.json').read_text());data=json.loads((w/'rendered.json').read_text(encoding='utf-8'));ui=json.loads((w/'ui-checks.json').read_text());checks=json.loads((w/'runner-checks.json').read_text());module='extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php'
for row in gate['files']:assert sha((w/'source'/row['path']).read_bytes())==row['sha256']
name='RD-25-WP2-FIX_gallery-frame-ratio_20261012.php';patch=repo/'patches'/name;patchsha=sha(patch.read_bytes())
proof={'fresh_source_gate':len(gate['files']),'source_bytes_unchanged':True,'javascript_byte_identical':True,'jsonld_cases':len(data['schema']),'legacy_resize_calls':[],'protected_css':[]}
T='catalog/view/template/product/product.twig';C='catalog/view/stylesheet/boostershop-ds.css'
old=(w/'source'/T).read_bytes();new=(w/'after'/T).read_bytes();assert re.findall(rb'<script\b.*?</script>',old,re.S)==re.findall(rb'<script\b.*?</script>',new,re.S)
for p in ['catalog/controller/product/product.php']:
 a=(w/'source'/p).read_bytes();b=(w/'after'/p).read_bytes();pattern=rb'\$this->model_tool_image->(?:resize|resizeFit)\([^\r\n]*';assert re.findall(pattern,a)==re.findall(pattern,b);proof['legacy_resize_calls'].append({'path':p,'original_and_fit_calls_byte_identical':True,'getProduct_calls_unchanged':a.count(b'->getProduct(')==b.count(b'->getProduct('),'getImages_calls_unchanged':a.count(b'->getImages(')==b.count(b'->getImages(')})
a=(w/'source'/C).read_bytes();b=(w/'after'/C).read_bytes()
for label,chunk in [('listing/RD26',a[:a.index(b'/* --- Gallery:')]),('WP3 and later CSS',a[a.index(b'/* RD-12 mini-cart drawer and toast */'):])]:assert chunk in b;proof['protected_css'].append({'region':label,'sha256':sha(chunk),'bytes_identical':True})
old=repo/'work/rd25-wp2-wp3-phase0-20261012/source';inventory=json.loads((repo/'diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012/measured-inventory.json').read_text(encoding='utf-8'));ids=set(map(int,data['products']));assets={image['source'] for p in inventory['inventory'] if p['product_id'] in ids for image in p['images']}
for src in assets:assert sha((old/'image'/src).read_bytes())==inventory['assets'][src]['sha256']
proof['reused_photo_archive']='RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz';proof['reused_original_photo_bytes_verified']=len(assets)
(e/'preservation.json').write_text(json.dumps(proof,indent=2)+'\n')
for filename in ['source-gate.json','spec.json','rendered.json','runner-checks.json','ui-checks.json','ga4-4.0.2.3.json','ga4-4.1.0.3.json']:shutil.copy2(w/filename,e/filename)
for p in (w/'visual').glob('*.png'):shutil.copy2(p,e/p.name)
for p in (w/'runner-fixtures-final').glob('*.txt'):dest=e/'runner-output'/p.name;dest.parent.mkdir(exist_ok=True);shutil.copy2(p,dest)
for p in w.glob('*.py'):dest=e/'scripts'/p.name;dest.parent.mkdir(exist_ok=True);shutil.copy2(p,dest)
for name2 in ['render.php','ga4-probe.php','capture.mjs','check-sheet.mjs']:shutil.copy2(w/name2,e/'scripts'/name2)
shutil.copy2(w/'source'/module,e/'ga4-catalog-model.php')
hashrows=[]
for p,v in spec['targets'].items():
 a=(w/'source'/p).read_bytes();b=(w/'after'/p).read_bytes();f=e/'diffs'/(p+'.diff');f.parent.mkdir(parents=True,exist_ok=True);f.write_text(''.join(difflib.unified_diff(a.decode().splitlines(keepends=True),b.decode().splitlines(keepends=True),fromfile='before/'+p,tofile='after/'+p)),encoding='utf-8',newline='')
 f=e/'after'/p;f.parent.mkdir(parents=True,exist_ok=True);f.write_bytes(b)
 hashrows.append('| `'+p+'` | `'+v['before_sha256']+'` | `'+v['after_sha256']+'` |')
 for rep in v['replacements']:
  for x in ['!important','setTimeout','position:absolute','position: absolute','position:fixed','position: fixed']:assert rep['after'].count(x)<=rep['before'].count(x)
ratio_rows=[]
for id,p in data['products'].items():
 item=p['after']['payload']['bs_gallery'][0];ratio_rows.append('| '+id+' | '+p['after']['name']+' | '+str(item['main_width'])+'×'+str(item['main_height'])+' | '+format(p['after']['payload']['bs_gallery_ratio'],'.6g')+' | '+item['kind']+' |')
anchorrows=[]
for version in ['4.0.2.3','4.1.0.3']:
 a=json.loads((w/f'ga4-{version}.json').read_text(encoding='utf-8'))
 for r in a['anchors']:assert r['before']==r['after'];anchorrows.append('| '+version+' | `'+r['template'].removeprefix('catalog/view/template/')+'` | '+str(r['index'])+' | `'+r['search']+'` | '+str(r['before'])+' / '+str(r['after'])+' |')
pairs=[]
for width in [1440,1024,390,360]:
 for id,p in data['products'].items():
  pics=[]
  for state in ['before','after']:
   f=e/f'product-{id}-{state}-{width}.png';pics.append('<figure><figcaption>'+state+'</figcaption><img src="data:image/png;base64,'+base64.b64encode(f.read_bytes()).decode()+'"></figure>')
  pairs.append('<section data-width="'+str(width)+'" data-product="'+id+'"><h2>#'+id+' · '+str(width)+' — '+html.escape(p['after']['name'])+'</h2><div class="pair">'+''.join(pics)+'</div></section>')
options=''.join('<option>'+id+'</option>' for id in data['products'])
(e/'contact-sheet.html').write_text('''<!doctype html><html lang="en"><meta charset="utf-8"><title>RD-25 WP2-FIX before/after</title><style>body{font:15px system-ui;margin:20px;background:#eee}header{position:sticky;top:0;padding:12px;background:white;z-index:1}h1{font-size:24px}h2{font-size:18px}section{background:white;padding:16px;margin:20px 0}.pair{display:flex;gap:16px;align-items:flex-start}figure{margin:0;flex:1;min-width:0}img{max-width:100%;height:auto}[hidden]{display:none}</style><header><h1>RD-25 WP2-FIX — primary frame / N1</h1><p>Fresh exported gallery source and actual patched Twig; retained October 12 photo originals; synthetic right panel/prices; local HTTP only.</p>Width <select id="width"><option value="">All</option><option>1440</option><option>1024</option><option>390</option><option>360</option></select> Product <select id="product"><option value="">All</option>'''+options+'''</select></header>'''+''.join(pairs)+'''<script>const widthFilter=document.getElementById('width'),productFilter=document.getElementById('product');function filter(){document.querySelectorAll('section').forEach(x=>x.hidden=(widthFilter.value&&x.dataset.width!==widthFilter.value)||(productFilter.value&&x.dataset.product!==productFilter.value))}widthFilter.onchange=productFilter.onchange=filter;</script></html>''',encoding='utf-8')
report=f'''# RD-25 WP2-FIX — gallery frame ratio and N1 report

Date: 2026-10-12
Executor: Codex. Authority: owner gallery-frame decision and `handoff_RD-25-WP2-FIX_gallery-frame-ratio_20261012.md`.

## 1. Delivery / gate

Ready for Claude review; no commit, push, deploy or task status update. Runner `patches/{name}`, SHA256 `{patchsha}`. Only four targets; rollback of this fix alone returns the accepted WP2+WP3 state. Host PHP 8.0.30 execution and owner QA are pending.

Fresh owner archive `{gate['archive']}`, SHA256 `{gate['sha256']}`: **5/5 expected hashes matched** before implementation. Its four target files and GA4 catalog model remain byte-identical in the intake copy. Header expected hash normalizes only a validated token; all other target hashes are exact. References/photos/model/library come from the accepted Oct 12 Phase 0 archive, not the Sep 24 backup. {len(assets)} distinct reused photo originals for the eight cases match the original measurement SHA256s; this is local source validation, not a new live image export. No photo files were supplied/replaced in this five-file export.

| Target | Before SHA256 | After SHA256 |
|---|---|---|
{chr(10).join(hashrows)}

Header values are normalized SHA256s. Actual token changes `rd25-wp3-20261012` → `rd25-wp2-fix-20261012` by reading/validating its path prefix and replacing the token wholesale. Repeat preserves a later valid token.

## 2. Root cause and implementation

Fresh `boostershop-ds.css:344` fixes the main frame at 4:5; `:353` gives both main and rail images bottom alignment. On a square primary photo this leaves its spare vertical space above it. Those WP2 source rules are edited directly. Prior writers: WP2 source gallery rebuild and removal of RD-10 height layers; WP3 changed only mini-cart source rules. No override layer, !important, new positioning or timer is added.

The controller computes `max(0.8, min(1.0, primary.main_width / max(1, primary.main_height)))` after building the view-only gallery. These are the primary **served fit** dimensions. Twig exposes the numeric result as `--bs-gallery-ratio` on the gallery; main aspect-ratio consumes it once. The existing JS is byte-identical and never rewrites this property, so selecting additional images does not change the frame. Desktop max-width 480px, mobile full-width, rail sizes/scroll, lightbox list/order and cut inset grid are retained. Main and rail `photo`/`legacy` images are contain/centre; the more specific cut rule remains contain/bottom with 6%/4%/88%/96% inset.

N1: the gallery helper takes an additional-image flag. When main fit is refused, an additional item's main uses its existing popup plus `config_image_popup_width/height` (700×700 in the export), while its small fallback remains the existing 100×100 additional thumb. Successful fit still overrides URL/dimensions as before. Primary legacy fallback stays unchanged. Legacy `popup`, `thumb`, additional-only `images`, original resize/resizeFit expressions, product API order, JSON-LD and original 700×700 lightbox URLs remain intact. No new image size/cache version or classification rule is introduced.

| Product | Case | Served primary dimensions | Frame ratio | Kind |
|---|---|---|---|---|
{chr(10).join(ratio_rows)}

If primary fit is refused, its served legacy dimensions determine the fixed frame for that request; a later warmed request may use the fit ratio. Thumbnail selection still cannot change it. Larger/wider additional photos are contained in the fixed primary frame; intentional empty space is permitted. No source is upscaled by a new policy.

## 3. Protected scope / GA4

WP1 model/library/Thumb are read-only hash gates. Listing/RD-26 bytes before the gallery and the WP3/later CSS region are byte-identical. Cart, mini-cart, checkout templates/controllers/JS, product add-to-cart/options/sticky ATC, commerce/schema/SEO/vendor files are not targets. All existing inline product scripts are byte-identical; legacy and fit calls/product lookup counts are checked in `preservation.json`.

Native fresh GA4 catalog model methods were invoked for both VERSION branches. All 16 replacement entries per branch have equal before/after counts; zero-count stock anchors stay zero. Exact replacement output contains identical ordered `data-ps-track-*` attributes and complete form/control markup in all five relevant templates, including product_id/quantity and cart keys/edit/remove controls. Untouched cart checker references use accepted WP3 bytes. These are injector/source proofs, not production GA4 event-delivery claims. The runner pins the fresh GA4 model SHA256 and checks every distinct relevant anchor in both current templates and candidates before writing.

| VERSION branch | Template | Replacement index | Literal anchor | Before / fix |
|---|---|---|---|---|
{chr(10).join(anchorrows)}

## 4. Local checks and visual evidence

- **32/32 runner checks**: apply on WP2+WP3, candidate byte parity, zero-write dry-run, self-delete, repeat, later valid token preservation, mixed/missing/drifted source/context/GA4/header refusal, forced write/target-lint restoration, changed-header rollback refusal, exact rollback to WP2+WP3.
- **8/8 full Twig JSON-LD blocks** are byte-identical before/after, with native original image controller blocks. Zero PHP warnings. N1 tested with all fit/metadata refused and with main refused while small fit is available; each additional main is the exact popup and 700×700, small stays correct.
- **64 screenshots / 32 before-after pairs**: eight required cases at 1440/1024/390/360, native photo bytes and actual before/patched gallery Twig. **28 interaction groups** select every thumbnail of multi-image cases by keyboard, check focus/selected src/literal popup/kind alignment, invariant frame style/height, bounded rail and lightbox/Escape; #201 also verifies its six-item list and previous/next. No horizontal page scroll or JS errors. All images load with contain; centre/bottom matches the kind. Square primary photos fill square frames without the previous band.

Contact sheet: `RD-25-WP2-FIX_gallery-frame-ratio_evidence_20261012/contact-sheet.html` (self-contained, filters by width/product). Screenshots were visually inspected for Album 360 black at 390, Album 400 yellow at 1440 and six-image Abyss Eye at 1024. Product information/price panel is synthetic; native gallery and lightbox are real. HTTP is mocked locally; no production request or full checkout proof is claimed. PHP 8.3.30 CLI/GD and local Chromium were used; runner syntax is PHP 8.0-compatible.

No new fit sizes/version means the existing WP2 cache is reused. There is no extra source scan, image API call or DB lookup introduced for the ratio. No media pre-warm/cache clear is required by this geometry fix; only compiled Twig refresh and browser stylesheet reload are needed.

## 5. Owner run — after independent review

Upload this one runner to `~/public_html`. It creates `_patch_backups/RD-25-WP2-FIX_gallery-frame-ratio_20261012-<UTC>-<nonce>/` with verified originals, candidate files and receipts before target writes. PHP self/candidate/target lint, atomic readback and failure restoration are included. Gates reject unexpected source or partial markers before writes. Successful apply/repeat/rollback self-delete; dry-run/failures retain the runner. Syntax check result: `No syntax errors detected in {name}`.

Run command and config-derived compiled-Twig cleanup together; keep image and rd25 metadata caches:

```bash
cd ~/public_html &&
php {name} &&
php -r 'require "config.php"; $rd25_t = realpath(DIR_CACHE . "template"); $rd25_c = realpath(DIR_CACHE); if ($rd25_t !== false) {{ if ($rd25_c === false || $rd25_t !== $rd25_c . "/template") {{ fwrite(STDERR, "Unsafe template cache path\\n"); exit(1); }} foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rd25_t, FilesystemIterator::SKIP_DOTS)) as $rd25_f) {{ if ($rd25_f->isFile() && !$rd25_f->isLink() && $rd25_f->getExtension() === "php" && !unlink($rd25_f->getPathname())) {{ fwrite(STDERR, "Twig cache cleanup failed\\n"); exit(1); }} }} }} echo "Twig cache refreshed\\n";'
```

Expected: `done=ok`, `self_delete=ok`, printed backup and `Twig cache refreshed`. Stop and send the output on a failed source gate; do not bypass hashes. Ctrl+F5 after success.

## 6. Owner QA and rollback

Desktop and phone: Album 360 white/black, Pokeball box, OP-15 booster/box, Mystery Box and six-image Abyss Eye. Square photos should no longer have the empty band above; packs keep bottom alignment. Switch thumbs (frame height fixed), open lightbox and test previous/next/Escape. Add to cart from the product page once; confirm existing toast/mini-cart. Cart/checkout are unchanged by this runner. Also inspect Album 400 yellow centred in its square frame.

Rollback: re-upload the **same** runner and run `php {name} --rollback=_patch_backups/<exact printed directory name>` in `~/public_html`, then use the compiled-Twig cleanup command above and Ctrl+F5. Replace the bracketed directory with the actual name from the apply log. It restores only this fix's four original files to WP2+WP3; no WP3 rollback is required. Receipt hashes refuse changed targets/tampered backups. Rollback triggers: broken images, unexpected frame jump/overflow, changed popup/JSON-LD, or a new product add-to-cart failure.

## 7. Remaining gate / artifacts

Claude review, then owner deployment/QA. RD-25 remains active; no closure or status change is authorized here.

Evidence folder holds source gate, fresh GA4 model/method replacement arrays, complete native payload/schema/fallback proof, runner logs/results, all UI metrics and screenshots, exact target diffs/candidate bytes, reproduction instruments and SHA256 seal. Reproduction uses retained Phase 0 images/model and a fixture cache; scripts should be restored under `work/rd25-wp2-fix-20261012/` with those inputs. Raw hosting archives/credentials are not part of the evidence deliverable.
'''
reportfile=repo/'diagnostics/RD-25-WP2-FIX_gallery-frame-ratio_report_20261012.md';reportfile.write_text(report,encoding='utf-8',newline='\n')
seal={'patch':{'path':patch.name,'sha256':patchsha},'report':{'path':reportfile.name,'sha256':sha(reportfile.read_bytes())},'files':[{'path':p.relative_to(e).as_posix(),'sha256':sha(p.read_bytes()),'bytes':p.stat().st_size} for p in sorted(e.rglob('*')) if p.is_file() and p.name!='seal.json']};(e/'seal.json').write_text(json.dumps(seal,indent=2)+'\n');print(json.dumps({'evidence_files':len(seal['files']),'patch_sha256':patchsha,'report_sha256':seal['report']['sha256']}))
