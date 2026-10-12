from pathlib import Path
import json,re,hashlib,tarfile,shutil,difflib,base64,html
w=Path(__file__).resolve().parent;repo=w.parent.parent;source=repo/'work/rd25-wp2-wp3-phase0-20261012/source'
e=repo/'diagnostics/RD-25-WP2-WP3_implementation_evidence_20261012';e.mkdir(exist_ok=True)
sha=lambda b:hashlib.sha256(b).hexdigest()
specs={s:json.loads((w/f'spec-wp{s}.json').read_text(encoding='utf-8')) for s in [2,3]}
module='extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php'
archive=Path('C:/Users/14bez/Downloads/RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz')
verified=0
with tarfile.open(archive) as tar:
    for member in tar:
        if member.isfile():
            assert sha(tar.extractfile(member).read())==sha((source/member.name).read_bytes()),member.name
            verified+=1
assert verified==400
ga4=[]
for version in ['4.0.2.3','4.1.0.3']:
    data=json.loads((w/f'ga4-{version}.json').read_text(encoding='utf-8'));ga4.append(data);shutil.copy2(w/f'ga4-{version}.json',e/f'ga4-{version}.json')
modulebytes=(w/'ga4'/module).read_bytes();(e/'ga4-catalog-model.php').write_bytes(modulebytes)
sourceproof={'original_archive_sha256':sha(archive.read_bytes()),'archive_members_byte_unchanged':verified,'ga4_archive_sha256':sha((repo/'RD-25-GA4-model-20261012.tar.gz').read_bytes()),'ga4_catalog_model_sha256':sha(modulebytes),'protected':[],'legacy_resize':[]}
for p in ['catalog/model/tool/image.php','system/library/image.php','catalog/controller/product/thumb.php','catalog/view/template/product/thumb.twig','catalog/view/javascript/checkout-reskin.js']:
    sourceproof['protected'].append({'path':p,'sha256':sha((source/p).read_bytes()),'not_written_by_either_runner':True})
originalcss=(source/'catalog/view/stylesheet/boostershop-ds.css').read_bytes();aftercss=(w/'after-wp3/catalog/view/stylesheet/boostershop-ds.css').read_bytes()
protected=originalcss[originalcss.index(b'/* RD-26 20261009'):originalcss.index(b'/* --- Gallery --- */')]
assert protected in aftercss
sourceproof['protected_css_wp1_media_and_rd26_bytes_identical']=True
for p in ['catalog/controller/product/product.php','catalog/controller/common/cart.php','catalog/controller/checkout/cart.php']:
    before=(source/p).read_bytes();after=(w/'after-wp3'/p).read_bytes();pattern=rb'\$this->model_tool_image->resize\([^\r\n]*'
    old=re.findall(pattern,before);new=re.findall(pattern,after);assert old==new
    sourceproof['legacy_resize'].append({'path':p,'count':len(old),'calls_byte_identical':True,'getProduct_count_before':before.count(b'->getProduct('),'getProduct_count_after':after.count(b'->getProduct('),'getImages_count_before':before.count(b'->getImages('),'getImages_count_after':after.count(b'->getImages(')})
for p in ['catalog/view/template/common/cart.twig','catalog/view/template/checkout/cart.twig']:
    old=re.findall(rb'<script\b.*?</script>',(source/p).read_bytes(),re.S);new=re.findall(rb'<script\b.*?</script>',(w/'after-wp3'/p).read_bytes(),re.S);assert old==new
sourceproof['cart_inline_javascript_byte_identical']=True
sourceproof['no_new_css_important_absolute_fixed_or_timers']=True
for s,spec in specs.items():
    for p,v in spec.items():
        old=(source/p).read_bytes() if s==2 or p not in specs[2] else (w/'after-wp2'/p).read_bytes()
        after=(w/f'after-wp{s}'/p).read_bytes()
        diff=''.join(difflib.unified_diff(old.decode().splitlines(keepends=True),after.decode().splitlines(keepends=True),fromfile=f'before/{p}',tofile=f'after-wp{s}/{p}'))
        dest=e/f'wp{s}-diff'/(p+'.diff');dest.parent.mkdir(parents=True,exist_ok=True);dest.write_text(diff,encoding='utf-8',newline='')
        for rep in v['replacements']:
            for signature in ['!important','position:absolute','position: absolute','position:fixed','position: fixed','setTimeout']:
                assert rep['after'].count(signature)<=rep['before'].count(signature),(p,signature)
        dest=e/f'after-wp{s}'/p;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(after)
(e/'source-preservation.json').write_text(json.dumps(sourceproof,indent=2)+'\n')
for filename in ['runner-checks.json','ui-checks.json','rendered.json']:
    shutil.copy2(w/filename,e/filename)
for p in (w/'runner-fixtures-delivery').glob('*.txt'):
    dest=e/'runner-output'/p.name;dest.parent.mkdir(exist_ok=True);shutil.copy2(p,dest)
for p in (w/'visual').glob('*.png'):shutil.copy2(p,e/p.name)
for p in w.glob('*.py'):
    d=e/'scripts'/p.name;d.parent.mkdir(exist_ok=True);shutil.copy2(p,d)
for name in ['ga4-probe.php','runner.php','render.php','capture.mjs']:shutil.copy2(w/name,e/'scripts'/name)

v=json.loads((w/'ui-checks.json').read_text());pairs=[]
p0=repo/'diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012'
for row in v['records']:
    if not row.get('screenshot'):continue
    image=(e/row['screenshot']).read_bytes();before=''
    if row['width'] in [1440,390]:
        prefix='product-'+str(row['product_id']) if row['surface']=='product' else row['surface']
        f=p0/f'{prefix}-before-{row["width"]}.png'
        if f.exists():before='<figure><figcaption>Before: accepted fresh-source fixture</figcaption><img src="data:image/png;base64,'+base64.b64encode(f.read_bytes()).decode()+'"></figure>'
    pairs.append('<section data-width="'+str(row['width'])+'" data-surface="'+row['surface']+'"><h2>'+html.escape(row['page'])+' · '+str(row['width'])+'</h2><div class="pair">'+before+'<figure><figcaption>After: actual patched Twig and fit payload</figcaption><img src="data:image/png;base64,'+base64.b64encode(image).decode()+'"></figure></div></section>')
(e/'contact-sheet.html').write_text('''<!doctype html><html lang="en"><meta charset="utf-8"><title>RD-25 WP2+WP3 implementation review</title><style>body{font:15px system-ui;margin:20px;background:#eee}header{position:sticky;top:0;background:white;padding:12px;z-index:1}h2{font-size:18px}.pair{display:flex;gap:16px;align-items:flex-start}figure{margin:0;flex:1;min-width:0}img{max-width:100%;height:auto;background:white}section{margin:20px 0;padding:16px;background:white}[hidden]{display:none}</style><header><h1>RD-25 WP2+WP3 — local implementation evidence</h1><p>Actual patched gallery/cart fragments, real source images, synthetic prices/context. All HTTP is mocked. Before is available at 1440/390 for the accepted Phase 0 cases.</p>Width <select id="width"><option value="">All</option><option>1440</option><option>1024</option><option>390</option><option>360</option></select> Surface <select id="surface"><option value="">All</option><option value="product">Product</option><option value="mini-cart">Mini-cart</option><option value="cart">Cart</option></select></header>'''+''.join(pairs)+'''<script>function filter(){document.querySelectorAll('section').forEach(x=>x.hidden=(width.value&&x.dataset.width!==width.value)||(surface.value&&x.dataset.surface!==surface.value))}width.onchange=surface.onchange=filter;</script></html>''',encoding='utf-8')
runnerchecks=json.loads((w/'runner-checks.json').read_text())
checkslen=len(runnerchecks['checks']);assert all(x['pass'] for x in runnerchecks['checks'])
table=[]
for stage in [2,3]:
    for p,val in specs[stage].items():table.append(f'| WP{stage} | `{p}` | `{val["before_sha256"]}` | `{val["after_sha256"]}` |')
anchor_tables=[]
for data in ga4:
    rows=[]
    for a in data['anchors']:
        rows.append('| `'+a['template'].removeprefix('catalog/view/template/')+'` | '+str(a['index'])+' | `'+a['search'].replace('|','\\|')+'` | '+str(a['before'])+' / '+str(a['wp2'])+' / '+str(a['wp3'])+' |')
    anchor_tables.append('### Model VERSION branch '+data['version_branch']+'\n\n| Template | Replacement index | Literal anchor | Before / WP2 / WP3 |\n|---|---|---|---|\n'+'\n'.join(rows))
patchnames=['RD-25-WP2_product-gallery_20261012.php','RD-25-WP3_small-thumbs_20261012.php']
hashes={n:sha((repo/'patches'/n).read_bytes()) for n in patchnames}
report=f'''# RD-25 WP2+WP3 — implementation report

Date: 2026-10-12
Executor: Codex. Authority: owner `go WP2-WP3`; Phase 0 review C1–C3 accepted with mandatory conditions.

## 1. Delivery and scope

Ready for independent Claude review. Production apply, host PHP 8.0.30 runtime and owner QA are pending. No commit, push, deploy, Notion or roadmap status update was performed.

| Runner | SHA256 | Targets |
|---|---|---|
| `patches/{patchnames[0]}` | `{hashes[patchnames[0]]}` | 4 |
| `patches/{patchnames[1]}` | `{hashes[patchnames[1]]}` | 7 |

Implementation follows accepted Phase 0 §9. WP2 adds a separate `bs_gallery` view array, including the primary photo, while retaining legacy `popup`, `thumb`, additional-only `images`, existing resize expressions and API order. Main is 4:5 (600×750 fit, maximum 480px); rail is left at ≥992px, 64×80 with 10px thumb gap, and a horizontal 56×70 row below on smaller screens. No rail for one valid image. Anchors switch the main src/dimensions/kind and literal popup attribute; only the main opens the original lightbox list at the selected index. Whole product remains visible; cut inset is 6%/4%/88%/96%, photo is full-slot contain/bottom. A CSS grid expresses the inset and bounds rail scrolling without new absolute positioning.

WP3 adds separate fit fields to the existing row payload, preserving legacy `thumb` and resize calls. Mini/cart slots are 52×65, generated fit 104×130, cut inset 8%/6%/84%/94%, photo full-slot contain/bottom. The mini-cart keeps direct `row > a > img`; its kind is on the article so the GA4 literal image-link anchor remains intact. Cart AJAX and controls are unchanged. Checkout summary remains its existing 48×48 contain DOM-copy consumer of mini-cart src; `checkout-reskin.js` is untouched. Live-search product admin dimensions remain 64×64; vendor files and settings are untouched. No extra getProduct/getImages calls or DB writes.

Root causes and history: fresh CSS :341–375 square/grey gallery, RD-10 max-height layers :4949–4964, :5124–5125, :5312–5318, RD-12 canonical mini-cart cover rule :7236, and RD-11 inline cart cover rules :20–22. Those original rules were edited/retired at source. Prior mini-cart writers found in `RD-12_minicart-toast_20260921.php` and `RD-12-UI-FIX_toast-canonical-rebuild_20260922.php`; repeated gallery source layers retain retirement comments. No override stack, new !important, absolute/fixed positioning or timer is added. Pixel geometry is documented beside the edited rules.

## 2. Source gates and composition

Input archive SHA256 `{sourceproof['original_archive_sha256']}`. All 400 extracted regular-member bytes remain identical, including all source images. Original 399 export hashes and six-file WP1 gate are retained from Phase 0. Fresh GA4 archive `{sourceproof['ga4_archive_sha256']}` contains two model files; only catalog model `{module}` is relevant/read/hash-pinned. Its SHA256 is `{sourceproof['ga4_catalog_model_sha256']}`. The admin model is not a target.

WP1 model/library/Thumb behavior, metadata v4, fit-v2, source trim/classification, 1.25s admission budget, LOCK_NB, listing media and RD-26 rules are unchanged. Read-only context gates include the catalog GA4 model. WP3 additionally requires the WP2 product controller/Twig hashes and the after-WP2 CSS hash. Header hashes below normalize only the validated asset token; it is read by path prefix and replaced wholesale. No fixed prior token anchor or suffix append.

| Stage | Target | Before SHA256 | After SHA256 |
|---|---|---|---|
{chr(10).join(table)}

Independent backup directories `_patch_backups/RD-25-WP2_product-gallery_20261012-<UTC>-<nonce>/` and `_patch_backups/RD-25-WP3_small-thumbs_20261012-<UTC>-<nonce>/` contain exact originals, candidate files and SHA256 receipts. All file/hash/anchor/context checks precede target writes. PHP candidate and written-file lint, atomic write/readback and restoration protect failures. Mixed markers, missing/drifted targets, malformed tokens and modified GA4 model fail closed. WP2 repeat after WP3 verifies the complete later state and preserves the WP3 token. Each runner self-deletes after successful apply/repeat/rollback; dry-run and failures retain it.

## 3. C1 — GA4 anchors and commerce preservation

The catalog model returns ordered search/replace arrays for the literal injector. Native PHP invoked its relevant methods for both supported VERSION branches; each of the 16 replacement entries per branch has an identical occurrence count at all three states (18 distinct template/search pairs across branches; `</head>` deliberately occurs twice as separate injector entries). The common/cart view is checked against the model's dropdown-cart `replaceCatalogViewCheckoutCartInfoBefore` replacement list. Cart list anchors are also checked on the AJAX-returned template.

Counts of zero are existing missing stock anchors (body, wishlist, old table/dropdown/buttons). They remain zero; this task does not repair older GA4 integration gaps. This is source/injector preservation, not proof of current GA4 event delivery or registered event settings. The exported model is not changed. Both runners also validate the union of anchor counts against current templates and their candidates before writing.

{chr(10).join(anchor_tables)}

`ga4-4.0.2.3.json` and `ga4-4.1.0.3.json` retain every exact replacement and preservation result. Real model replacement output at before/WP2/WP3 has identical ordered `data-ps-track-*` attributes and complete control/form markup for all five templates. This includes product add-to-cart `product_id`/`quantity`, category injection, hidden cart key, quantity steppers/submission/edit URL, remove URL/data-key and mini-cart quantity form. Existing mini/cart inline JS is byte-identical; no AJAX handler or payload field was rewritten. Original product/product content outside the gallery markup/initialization is unchanged. Legacy resize expressions and original product lookup counts are checked in `source-preservation.json`.

## 4. C3 — local checks

| Evidence | Result |
|---|---|
| Runner composition/safety (`runner-checks.json`) | {checkslen}/{checkslen} passed |
| Native real-controller gallery image block + Twig | 8 cases × before/WP2/WP3; 0 PHP warnings |
| Full product Twig JSON-LD | 8/8 exact bytes at all three states |
| Original 700×700 popup URLs | legacy controller fields retained; selected literal href/lightbox src exact |
| UI (`ui-checks.json`) | 40 screenshots at 1440/1024/390/360; 8 interaction groups; 0 JS errors |
| Gallery | 4:5, no grey background/page overflow; left bounded scrolling rail / mobile row; single-image no rail; six-item selection, native keyboard focus, hover/active borders, previous/next/Escape |
| Cart fragments | real unchanged mini/cart JS + exported common.js/Bootstrap; qty buttons trigger one edit each; both fragment reload paths retain fit URLs/direct mini image shape; mini/cart remove work |
| Fit refusal | exact new gallery closure with simulated API refusal retains the precise legacy URL; legacy fallback intrinsic sizes come from config |

Runner checks include WP1→WP2→WP3; own repeats and WP2 repeat after WP3; dry-runs with zero target writes; missing/drifted targets/context/GA4/header and mixed-marker refusal; forced write and target-lint failure restoration; refusal of WP2 rollback while WP3 is active; exact WP3 rollback→after-WP2 and WP2 rollback→WP1; modified-backup refusal; preserving a later valid header token. PHP 8.3.30 CLI/GD and local Chromium were used. Runners use PHP 8.0-compatible syntax; host execution is still required. Fault injection modifies fixture copies only. Source images/model/library were not edited.

Visual cases: #78 sealed OP-15, #80 OP-15 box, #77 Mystery Box, #131 dark Geodude, #144 Album 400 yellow, #96 Games 7 Days, #201 six images, #73 Outlet Mix with blank extra rows. Interactive scripts are actual patched gallery and unchanged cart JS. Cart HTTP responses are mocked locally; prices/context/right product panel are synthetic, not a complete live controller or checkout session. Before contact images are the accepted Phase 0 fixtures at 1440/390; after images come from actual patched templates, not the Phase 0 drawing. Contact sheet: `RD-25-WP2-WP3_implementation_evidence_20261012/contact-sheet.html`.

Phase 0 prose correction: §4 says #73 has one valid image; its own inventory JSON actually contains two (#85 still has one). This implementation uses the real controller's existing skip rule and renders two images for #73; single-image checks use #77/#96. No inventory data or class thresholds changed.

During fixture checking, Enter on the existing cart quantity inputs submitted twice (keydown handler plus normal form submit). Those handlers are byte-identical to input source; this behavior was not changed within the image-only scope. Successful reload checks use the existing + buttons. Separately addressing Enter behavior requires its own scoped cart change.

Cold cost/limits remain the accepted Phase 0 model: worst measured product ≈2.8s and 20-largest cart ≈3.7s on first fully cold local request, then ≈0.05s. These are local image-payload/Twig measurements excluding live DB/network/header/related modules. Existing admission budget and legacy fallback are reused, not a total HTTP time cap. New sizes warm on first use. Production concurrency/latency and live GA4 events are owner QA, not claimed here.

## 5. Owner apply — only after independent review

Upload the two runners into `~/public_html`. Run WP2 first, then WP3; `&&` stops on any failed gate. Each successful runner prints `done=ok`, `self_delete=ok` and its backup. Clear only compiled Twig PHP files via config-derived cache; retain metadata/fit/image caches.

```bash
cd ~/public_html &&
php RD-25-WP2_product-gallery_20261012.php &&
php RD-25-WP3_small-thumbs_20261012.php &&
php -r 'require "config.php"; $rd25_t = realpath(DIR_CACHE . "template"); $rd25_c = realpath(DIR_CACHE); if ($rd25_t !== false) {{ if ($rd25_c === false || $rd25_t !== $rd25_c . "/template") {{ fwrite(STDERR, "Unsafe template cache path\n"); exit(1); }} foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rd25_t, FilesystemIterator::SKIP_DOTS)) as $rd25_f) {{ if ($rd25_f->isFile() && !$rd25_f->isLink() && $rd25_f->getExtension() === "php" && !unlink($rd25_f->getPathname())) {{ fwrite(STDERR, "Twig cache cleanup failed\n"); exit(1); }} }} }} echo "Twig cache refreshed\n";'
```

Ctrl+F5 after successful cache refresh. Do not delete rd25-media-v4 JSON or image/cache/rd25-fit as part of this deploy.

## 6. C2 — mandatory owner QA

**bs-checkout-smoke steps 1–4; no order submission:**

1. Add an available product from its tile/product page. Confirm the success toast and basket quantity/total.
2. Open mini-cart: whole photo, 52×65 slot; change quantity and remove an item; thumbnails/total update and drawer controls still work.
3. Open cart page: change quantity with +/− and remove an item; both cart and mini-cart reload correctly; total/stock/minimum/preorder behavior is unchanged.
4. Proceed to checkout: entry page opens, existing summary photos remain complete in their 48×48 contain boxes, delivery/payment controls load. Stop before submitting an order.

Also inspect at 1440/1024/390/360: booster + dark 3D, single-image #96, six-image #201; rail orientation/scroll, all images complete, primary included, click/Enter switch and lightbox previous/next/Escape, visible focus, long names, no horizontal scroll. Check one category/home to confirm WP1/RD-26 framing and buttons unchanged. Revisit pages to assess warm speed; check PHP/browser logs for new errors and owner GA4 debug view if available.

## 7. Rollback

Re-upload the same corresponding runner, then supply its own backup directory name from the apply log as a relative path beginning `_patch_backups/`. Roll back **WP3 first**, clear compiled Twig cache, then WP2 and refresh cache again. Command forms:

```bash
cd ~/public_html && php RD-25-WP3_small-thumbs_20261012.php --rollback=_patch_backups/<printed-WP3-directory-name>
cd ~/public_html && php RD-25-WP2_product-gallery_20261012.php --rollback=_patch_backups/<printed-WP2-directory-name>
```

Replace the angle-bracket parts with the exact printed directory names; use the cache-refresh command from §5 after each successful rollback. These are command forms, not literal paste-ready placeholders. Receipt hash gates refuse restoring over changed/newer targets or tampered backups; stop and send the output if refused. Metadata/fit outputs can remain unused. Rollback triggers: broken/missing product/cart images, changed JSON-LD/popup URLs, gallery overflow, or a new cart/checkout failure.

## 8. Evidence and remaining gate

Evidence directory `diagnostics/RD-25-WP2-WP3_implementation_evidence_20261012/`: source preservation, every GA4 replacement/count, native payload/schema output, 73 runner checks, 40 screenshots, UI metrics/interactions, actual target diffs and candidate bytes, self-contained contact sheet, reproduction instruments, SHA256 seal. Reproduction uses retained Phase 0 work inputs and fixture-only caches; no credentials, orders or production access.

Next gate: Claude review of both runners/report, then owner deploy and QA. Production success and RD-25 closure are not claimed.
'''
reportfile=repo/'diagnostics/RD-25-WP2-WP3_implementation_report_20261012.md';reportfile.write_text(report,encoding='utf-8',newline='\n')
(e/'scripts/README.md').write_text('Reproduction: restore these instruments into work/rd25-wp2-wp3-phase1-20261012 with retained Phase 0 source/fixture inputs and the fresh GA4 model under ga4/<original path>. Run build.py, ga4-probe.php separately for both VERSION branches, package.py, check-runners.py (its runbase must be fresh), render.php, visuals.py, capture.mjs, then deliver.py. PHP uses an isolated writable cache; no production requests. Native PHP/Chrome may require the approved local sandbox override. Raw hosting archive is not part of this deliverable. runner.php is a generator template, not an owner runner. Owner runners are the two files in patches/.\n',encoding='utf-8')
seal={'report':{'path':reportfile.name,'sha256':sha(reportfile.read_bytes())},'patches':hashes,'files':[{'path':p.relative_to(e).as_posix(),'bytes':p.stat().st_size,'sha256':sha(p.read_bytes())} for p in sorted(e.rglob('*')) if p.is_file() and p.name!='seal.json']}
(e/'seal.json').write_text(json.dumps(seal,indent=2)+'\n')
print(json.dumps({'evidence_files':len(seal['files']),'report_sha256':seal['report']['sha256'],'patches':hashes}))
