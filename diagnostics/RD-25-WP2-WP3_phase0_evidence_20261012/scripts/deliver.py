from pathlib import Path
from collections import Counter
import csv, hashlib, html, json, re, shutil
from PIL import Image, ImageOps, ImageDraw

w=Path(__file__).resolve().parent;repo=w.parent.parent
e=repo/'diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012';source=w/'source'
load=lambda n:json.loads((e/n).read_text(encoding='utf-8'))
gate=load('source-gate.json'); measured=load('measured-inventory.json');cost=load('cold-cost.json');visual=load('visual-metrics.json');vm=load('visual-manifest.json');schema=load('schema-preservation.json')
assert not visual['js_errors'] and len([r for r in visual['records'] if r.get('screenshot')])==36
assert len(cost['results'])==32 and all(not r['warnings'] for r in cost['results'])
assert all(c['jsonld_bytes_identical'] and c['image_array_identical'] for c in schema['checks'])
assert load('metadata-validation.json')['assets']==245
inventory=measured['inventory'];byid={p['product_id']:p for p in inventory}
single=[p for p in inventory if p['image_count']==1];many=[p for p in inventory if p['image_count']>5]
classes=Counter(a['metadata']['kind'] for a in measured['assets'].values())
with (e/'product-image-inventory.csv').open('w',encoding='utf-8-sig',newline='') as f:
    writer=csv.writer(f);writer.writerow(['product_id','name','images','unique_images','primary_kind','additional_kinds','date_available'])
    for p in inventory:writer.writerow([p['product_id'],p['name'],p['image_count'],p['unique_image_count'],p['images'][0]['kind'],';'.join(i['kind'] for i in p['images'][1:]),p['date_available']])

# Click-through image comparison; uses screenshots only, no hidden local server
# or rd25.local resource resolution is needed to review the contact sheet.
cards=[]
for width in [1440,390]:
    for p in vm['pages']:
        if p['state']!='before':continue
        stem=p['page'].removesuffix('-before.html'); title=f'{stem} · {width}px'
        label=byid[p['product_id']]['name'] if p.get('product_id') else ('Seven representative real products; synthetic basket and prices')
        a=f'{stem}-before-{width}.png';b=f'{stem}-after-{width}.png'
        cards.append(f'<section class="pair" data-width="{width}" data-surface="{p["surface"]}"><h2>{html.escape(title)}</h2><p>{html.escape(label)}</p><div class="images"><figure><figcaption>Before — current source</figcaption><a href="{a}"><img src="{a}" loading="lazy" alt="Before {html.escape(title)}"></a></figure><figure><figcaption>After — Phase 0 proposal</figcaption><a href="{b}"><img src="{b}" loading="lazy" alt="After {html.escape(title)}"></a></figure></div></section>')
page='''<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RD-25 WP2+WP3 — Phase 0</title><style>body{margin:0;background:#eef1f4;color:#18202b;font:15px/1.5 system-ui}header,main{max-width:1250px;margin:auto;padding:20px}h1{font-size:26px}nav{display:flex;gap:16px;flex-wrap:wrap}label{display:flex;gap:8px;align-items:center}select{padding:8px;font:inherit}.pair{background:white;padding:20px;margin:20px 0;border-radius:10px}.pair h2{font-size:20px;margin:0}.images{display:grid;grid-template-columns:1fr 1fr;gap:16px}figure{margin:0}figcaption{font-weight:600;margin:8px 0}img{width:100%;height:auto;border:1px solid #d7dde4}a:focus-visible{outline:3px solid #1464d8}button{font:inherit;padding:8px} [hidden]{display:none!important}@media(max-width:600px){.images{grid-template-columns:1fr}header,main{padding:12px}}</style><header><h1>RD-25 WP2+WP3 — Phase 0 comparison</h1><p>Verified 2026-10-12 export. Real product images and exported gallery/cart markup. The proposal is a diagnostic drawing, not a patch or a deployed result. Product information panel, basket and prices are illustrative; the mini-cart is displayed as an isolated open panel.</p><nav><label>Viewport<select id="width"><option value="all">Both</option><option>1440</option><option>390</option></select></label><label>Surface<select id="surface"><option value="all">All</option><option value="product">Product gallery</option><option value="mini-cart">Mini-cart</option><option value="cart">Cart page</option></select></label></nav><p>Click an image for its full-resolution screenshot.</p></header><main>'''+''.join(cards)+'''</main><script>function filter(){document.querySelectorAll('.pair').forEach(x=>{x.hidden=(width.value!=='all'&&x.dataset.width!==width.value)||(surface.value!=='all'&&x.dataset.surface!==surface.value)})}const width=document.getElementById('width'),surface=document.getElementById('surface');width.addEventListener('change',filter);surface.addEventListener('change',filter);</script></html>'''
(e/'contact-sheet.html').write_text(page,encoding='utf-8')

for width in [1440,390]:
    rows=[]
    for id in vm['popup_preservation']:
        pid=id['product_id']; thumbs=[]
        for state in ['before','after']:
            im=Image.open(e/f'product-{pid}-{state}-{width}.png').convert('RGB')
            if width==1440:im=im.crop((0,0,width//2,min(im.height,780)))
            im.thumbnail((500,620))
            panel=Image.new('RGB',(510,650),'white');panel.paste(im,((510-im.width)//2,30));ImageDraw.Draw(panel).text((12,8),f'#{pid} | {state}',fill='black');thumbs.append(panel)
        row=Image.new('RGB',(1040,670),'#e5e9ee');row.paste(thumbs[0],(5,10));row.paste(thumbs[1],(525,10));rows.append(row)
    sheet=Image.new('RGB',(1040,len(rows)*670),'white')
    for i,row in enumerate(rows):sheet.paste(row,(0,i*670))
    sheet.save(e/f'product-contact-{width}.jpg',quality=90)

snippets={
 'catalog/controller/product/product.php':[(268,269),(346,368)],
 'catalog/view/template/product/product.twig':[(54,78),(970,978),(980,992)],
 'catalog/view/stylesheet/boostershop-ds.css':[(340,377),(4949,4964),(5124,5125),(5312,5320),(6708,6716),(7236,7236)],
 'catalog/controller/common/cart.php':[(70,81),(98,103)],
 'catalog/controller/checkout/cart.php':[(158,170),(220,225)],
 'catalog/view/javascript/checkout-reskin.js':[(1152,1179),(1255,1267)],
 'catalog/model/catalog/product.php':[(789,793)],
 'extension/ps_live_search/catalog/controller/module/ps_live_search.php':[(145,148),(163,167)],
 'extension/ps_live_search/admin/controller/module/ps_live_search.php':[(110,120)],
}
maps=[]
for file,ranges in snippets.items():
    lines=(source/file).read_text(encoding='utf-8').splitlines()
    maps.append({'path':file,'sha256':hashlib.sha256((source/file).read_bytes()).hexdigest(),'sections':[{'start':a,'end':b,'lines':[f'{i+1}: {lines[i]}' for i in range(a-1,min(b,len(lines)))]} for a,b in ranges]})
(e/'source-map.json').write_text(json.dumps(maps,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')

post=[]
for record in gate['files']:
    p=source/record['path'];digest=hashlib.sha256(p.read_bytes()).hexdigest();assert digest==record['sha256'],record['path']
    post.append({'path':record['path'],'unchanged':True})
(e/'source-integrity-after.json').write_text(json.dumps({'original_members':len(post),'source_bytes_unchanged':True,'records':post},indent=2)+'\n',encoding='utf-8')

costrows=[]
for r in cost['results']:
    fit=sum(t.get('fit_images',int(t.get('fit',False))) for t in r['trace'])
    n=sum(t.get('images',1) for t in r['trace'])
    costrows.append(f'| {r["case"]} | {r["initial_cache"]} | {r["request"]} | {fit}/{n} | {r["wall_seconds"]:.6f} |')
gate_table='\n'.join(f'| `{r["path"]}` | `{r["actual"]}` | matches |' for r in gate['wp1_gate'])
counts_table='\n'.join(f'| {n} | {c} |' for n,c in sorted(Counter(p['image_count'] for p in inventory).items()))
single_table='\n'.join(f'| {p["product_id"]} | {p["name"]} |' for p in single)
many_table='\n'.join(f'| {p["product_id"]} | {p["image_count"]} | {p["name"]} |' for p in many)

report=f'''# RD-25 WP2+WP3 — joint Phase 0 report

Date: 2026-10-12
Executor: Codex, explicitly assigned by the owner. The addendum's pending executor line is superseded by this session's instruction.
Status: Phase 0 complete; await **go WP2-WP3**. No patch authored, commit, push, deployment or roadmap/status mutation in this stage.

## Scope and precedence

Owner decision 2026-10-11 combines WP2+WP3 into one stage, one Phase 0/review and one deploy session, with **two future runners and separate backups/rollback**. Read the addendum first, then base handoff §4 WP2/WP3. Reuse the accepted v4 `mediaInfo()` / fit-v2 `resizeFit()` / `cropFit()` without new classifier thresholds, metadata versions or resizer behavior. Whole product visible; empty space accepted. Diagnostics below use copied live inputs and prototype-only markup/CSS. They do not edit the extracted PHP/Twig/CSS files.

## 1. Fresh export and source gate

Archive: `RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz`, {Path(gate['archive']).stat().st_size:,} bytes.
SHA256: `{gate['sha256']}`.
Owner export UTC: `{gate['export_utc']}`; collector revision 3; hosting PHP **8.0.30**; `done=ok`, no missing files, zero DB/site writes. All **399 exported file hashes and lengths match**; 400 regular archive members including the manifest, no unsafe members. Original source mtimes were retained for the real cache keys. After all diagnostics, **400/400 extracted member bytes still match** the archive.

All accepted WP1 checkpoints match exactly:

| File | Fresh SHA256 | Gate |
|---|---|---|
{gate_table}

Header token: `rd25-wp1-photo-contain-20261011`. Metadata v4, fit key v2, 1.25s cumulative request admission budget, LOCK_NB fallback remain unchanged. See `source-gate.json` and `source-integrity-after.json` in the evidence directory.

The collector initially rejected a legitimate `..png` filename and did not decode the two `&amp;` image paths. Revision 3 resolved both. Raw DB paths and explicit HTML-entity resolution evidence are retained; there is no DB filename repair.

## 2. Product gallery map and root cause

| Area | Current live source | Observed behavior |
|---|---|---|
| Payload | `catalog/controller/product/product.php:346–368` | Primary thumb `resize(...,500,500)`; additional thumbs 100×100. Primary and additional popup callers remain 700×700. `getImages()` already supplies additional image paths; no extra product lookup is needed. |
| Markup | `catalog/view/template/product/product.twig:56–76` | Main anchor uses `popup`; main img uses `thumb`. Additional anchors use `image.popup` / `image.thumb`, 72×72 intrinsic markup. No primary-image thumbnail in the current rail. |
| Base gallery CSS | `boostershop-ds.css:341–375` | Main background `var(--bs-surface,#f8fafc)`, initial square image aspect, horizontal wrapping 72×72 links. |
| Later constraints | `boostershop-ds.css:4949–4964`, `:5124–5125`, `:5312–5318` | 460px maximum height on desktop; repeated 340px constraints at mobile scopes. These create the landscape main-image area and must be edited/retired at the source in WP2, rather than overridden again. |
| Lightbox | controller `:268–269`; Twig `:970–978` | Exported jQuery 3.7.1 and Magnific Popup; `.magnific-popup` delegates **every anchor** to the image lightbox, with gallery enabled. A thumbnail click currently opens the lightbox; it does not switch the main image. No separate gallery-switch code in `booster-product-polish.js`. |
| JSON-LD dependency | Twig `:980–992` | `image` is the primary `popup` followed by every `images[].popup`. **Do not insert the new primary thumbnail into legacy `images`.** Add a separate gallery-view array instead; retain the original popup/resize callers and schema fields. |
| Order | `catalog/model/catalog/product.php:789–793` | `getImages()` orders by `sort_order ASC`. Preserve its returned order in Phase 1. The export orders tied rows by image for determinism; the contact sheet is not proof of live tie ordering. |

DB theme override: the owner collector queried the actual `DB_PREFIX . 'theme'`, using owner-held config rather than a guessed prefix. Required `route`/`code` columns exist, status **checked**, **zero matching rows** for product/product, product/thumb, common/cart and checkout routes. Active configured theme: `basic`. No DB theme patch is indicated. Evidence: `theme-and-image-settings.json`.

Prior implementation history: the source retains the RD-10 gallery height layers, RD-11 cart inline styling, and RD-12 mini-cart/checkout compatibility. Matching patch history includes `RD-11_cart-page_20260921.php`, `RD-12_minicart-toast_20260921.php`, `RD-12-UI-FIX_toast-canonical-rebuild_20260922.php`, and `RD-12_checkout-thumbnail-compat_20260922.php`. The prototype has temporary drawing overrides; Phase 1 must edit the identified source rules, without stacking overrides or adding `!important`.

## 3. Mini-cart, cart and checkout map

| Caller / renderer | Image source and size | Scope decision |
|---|---|---|
| `catalog/controller/common/cart.php:76` → `common/cart.twig:41` | Existing cart service supplies primary image. Current 120×120 resize is displayed at 56×56; DS row/image rule `:7236` uses cover. Quantity/remove reload uses the same `common/cart` fragment. | WP3 adds fit 104×130 and class/dimensions to this existing payload; edits the row/image source rule and existing Twig only. Preserve direct `.bs-mini-cart__row > a > img`. |
| `catalog/controller/checkout/cart.php:163` → `checkout/cart_list.twig:28` | `getList()` supplies 120×120 legacy thumb and renders cart_list. `checkout/cart.twig:20–22` owns row sizing: 96×96 desktop, 72×72 mobile, cover. AJAX `checkout/cart.list` reloads this fragment. | WP3 adds fit 104×130; 52×65 slot on both widths. Update cart_list markup **and the inline source CSS in cart.twig**. Preserve quantity, minimum, preorder, stock, remove and totals code. |
| Checkout summary | `checkout-reskin.js:1152–1179` finds matching alt/name in `#cart .bs-mini-cart__row > a > img`; `:1259–1262` copies **img.src only** into its own `.bs-co-item__img`. DS `:6708–6716` sets 48×48, contain. | This is DOM URL reuse, **not a shared Twig partial**. Keep checkout template/JS/48×48 rule outside WP3's conditional summary scope. It will inherit the mini-cart fit URL and keep whole-image contain; source class/inset is not propagated. Changing the summary to 52×65 would require explicit scope expansion. |
| Related products | Existing `product/thumb` listing path, already covered by WP1 | No second renderer/resizer rewrite; keep the protected Thumb files and `.bs-pcard__media*` rules unchanged. |

There are no new `getProduct()` calls needed for WP2/WP3: product primary/additional paths and cart primary paths already exist in the caller payload. No business/cart/order data was exported or used; fixture prices and basket quantities are synthetic.

**Checkout QA gate:** WP3 necessarily touches `checkout/cart.twig` and `checkout/cart_list.twig`. The handoff says any checkout template change requires owner `bs-checkout-smoke` steps 1–4, so that gate applies even though the checkout summary and order/payment logic remain outside the patch.

## 4. Live-search admin setting

Module code `module_ps_live_search`; product keys `module_ps_live_search_product_image_width` and `module_ps_live_search_product_image_height` are both **64**. The catalog module directly calls legacy resize with those values (`ps_live_search.php:147`) and returns thumb dimensions (`:166–167`). Admin fields exist; validation permits 16–128 independently (`admin/controller/module/ps_live_search.php:110–120`). Thus 52×65 can be set by the owner in the module's admin product-image size fields, if requested later. This stage changes neither setting nor vendor code, and a size setting alone does not adopt the new fit/classification API. Category/manufacturer image sizes also remain 64×64. Module enabled/disabled values were intentionally not exported, so this report makes no new status claim.

## 5. Complete product image inventory

Scope: **134 enabled store-0 products**, with primary plus additional refs. All dates are available by the export date; no blank primary image and no missing nonempty source. Export includes 169 additional rows, of which **22 are empty strings** and are skipped by the real product controller. Effective refs: **281**; **245 unique source files**, measured with the exact unchanged v4 helper: **{classes['cut']} cut / {classes['photo']} photo**. All 245 cached metadata arrays then match `mediaInfo()` exactly; zero warnings. No classification/trim thresholds changed. The helper was called directly for the inventory so the request admission budget did not truncate a catalogue-wide diagnostic; measured request-cost scenarios use the actual public methods and budget.

| Effective gallery images | Products |
|---|---|
{counts_table}

Full per-product source/class/reason/dimensions inventory: `measured-inventory.json`; compact product table: `product-image-inventory.csv`. Blank extra rows explain why SQL row counts misleadingly suggested 11 images for #85 and 6 for #73; both actually have one image. The existing skip behavior is preserved, with no DB cleanup in scope.

### Single-image products — no empty rail

| Product ID | Name |
|---|---|
{single_table}

### More than five valid images — rail overflow case

| Product ID | Images | Name |
|---|---|---|
{many_table}

## 6. Proposed geometry and interactive contract

| Surface | Slot | Fit request | Framing |
|---|---|---|---|
| Product main | 4:5; desktop main max-width 480; full gallery column on mobile | 600×750 | contain + center bottom; cut in 88%×96% box (left 6%, top 4%) per base WP2; photo full slot with existing radius token; no grey backing |
| Gallery thumbnails | ≥992: 64×80 left rail, 10px gap, constrained to main height with vertical overflow; <992: 56×70 horizontal scroll row | 128×160 | same class framing, active 2px blue border, other 1px line border, visible keyboard focus |
| Mini-cart / cart rows | 52×65 | 104×130 | contain + center bottom; cut in 84%×94% box (8% / 6% inset); photo full slot; existing small radius |

Include the primary image in the **separate gallery-view array**, show no rail for a single image, use native focusable anchors with explicit photo labels, and update the main fit src, intrinsic dimensions, class and literal popup attribute on selection. Prevent thumbnail lightbox delegation; the main-image lightbox opens the existing primary + additional popup list at the selected index, with previous/next and Escape preserved. Keep the legacy `popup`, `thumb`, `images` fields and the original resize calls available; fit refusal falls back to their existing URLs.

Use `getAttribute`/`setAttribute` for literal popup URL transfer. Assigning normalized DOM `.href` text can change non-ASCII/escaped URL bytes even when navigation is equivalent. The Phase 0 prototype exercises the attribute-preserving approach. Low-resolution originals cannot gain detail from a larger slot; no source-image replacement or upscaling policy change is included.

## 7. Before/after contact sheet and local proof

Open [contact sheet](RD-25-WP2-WP3_phase0_evidence_20261012/contact-sheet.html). It filters by viewport/surface and links full-resolution screenshots. Static product overviews: [1440](RD-25-WP2-WP3_phase0_evidence_20261012/product-contact-1440.jpg), [390](RD-25-WP2-WP3_phase0_evidence_20261012/product-contact-390.jpg).

Cases: #78 sealed OP-15 booster; #80 booster box; #77 Mystery Box; #131 dark Geodude 3D source (classified cut by existing rules); #144 Album 400 yellow; #96 Games 7 Days 63.5×88; #201 six-image damaged-film booster box. These seven also form the illustrated mini-cart/cart basket. Product name/image bytes and relevant Twig are real; the right information column, prices, basket/controls data are illustrative. The mini-cart is isolated as an open panel, not a full screenshot of the live header/checkout.

**36 native Chromium screenshots** at 1440 and 390: seven product pages plus mini-cart and cart, each before/after. Proposed 4:5 main/thumb slots, whole-image contain + bottom, loaded generated files, no document horizontal overflow. Enter on the sixth thumbnail switches the main image; actual exported Magnific Popup opens the exact selected 700×700 URL, previous/next and Escape work at both widths. Zero JS errors. The native popup URL lists match before/after, and full product Twig JSON-LD is byte-identical in **7/7 fixtures**. See `visual-metrics.json`, `visual-manifest.json` and `schema-preservation.json`. This is a proposed-payload/layout proof; no deployed patch or full live-page QA is claimed.

## 8. Measured cold cost — full local page Twig

Native local PHP **8.3.30/GD**; host export reports **8.0.30**. Unmodified live model/library, source bytes and mtimes; new fit dimensions above. Both warm-metadata/empty-new-fit and empty-metadata/empty-fit were measured in isolated copies; legacy popup/thumb files were warmed first, representing the deployed site. Every row is a fresh PHP request, resetting the static admission accumulator. Full exported product/cart page Twig renders with synthetic non-image context; product gallery is substituted as a diagnostic payload only; persistent compiled Twig cache is off.

The timing excludes live DB queries, application/controller execution, header mini-cart, related modules, HTTP/network and browser paint. It is a local page-render component measurement, **not a full production HTTP response time or an enforced page deadline**. First restricted-sandbox runs were discarded because PHP reported the local cache unreadable/unwritable and refused all fit output; accepted runs explicitly gate cache writability and use the permitted local runtime. All accepted native runs have zero warnings.

Worst-case source selection: #201 has the most valid photos (6); #99 has the highest summed source measurement cost (3 images, 17,265,600 source pixels); #132 has the largest primary (12,102,048 pixels, two images). Cart: synthetic 20-item basket containing the largest primary images among the exported products, in descending area order; not an observed customer basket. Plan, traces, memory and output lengths are in `cold-cost.json`.

| Case | Initial cache | Request | Fit images / images | Wall seconds |
|---|---|---|---|---|
{chr(10).join(costrows)}

The existing 1.25s limit is a decode/measurement **admission budget**. One admitted source can overrun; fit decode/resample/encode is outside it. Thus first-time new-fit creation still takes up to about 3.74s in the measured cart case; after warming the same page Twig is about 0.04s. #99 temporarily falls back for one image; a later request fills it. Keep the exact existing budget and LOCK_NB fallback. Main + small gallery fits create two outputs per source. Source fields and metadata version do not change, so a later size warm-up targets only missing fit keys. Production latency and concurrent hosting load remain owner QA items; a CLI pre-warm is optional future tooling, not implemented here.

## 9. Two-runner implementation plan after approval

**WP2** `patches/RD-25-WP2_product-gallery_<date>.php`: product controller, product Twig, gallery source rules in `boostershop-ds.css`, and dynamic header stylesheet token. Add separate gallery data; preserve original popup/thumb/images and JSON-LD block; reuse getImages order; replace the single existing lightbox initialization rather than attaching competing handlers. Remove/update the identified old main/rail/height rules at source. Do not change product info, options, cart submission, related listings, schema, og:image, commerce or vendor code.

**WP3** `patches/RD-25-WP3_small-thumbs_<date>.php`: common/cart controller + Twig, checkout/cart controller, checkout/cart_list Twig, checkout/cart inline source CSS, scoped mini-cart DS rules and dynamic header token. Retain direct mini-cart img shape; no checkout-reskin.js/summary rewrite or vendor live-search mutation. No extra getProduct calls or DB writes.

Fresh source hashes in the gate bind the first runner. WP3 must bind the reviewed **after-WP2** CSS/header state and unchanged original hashes of its independent files; those candidate after-hashes will be calculated only during Phase 1. Read-only gates protect the accepted model/library/Thumb files and WP1/RD-26 rules. Each runner preflights all hashes/anchor counts before writing, rejects partial/mixed markers, owns its content marker, backup and rollback receipt, syntax restore gate and self-delete. Header cache tokens are read/validated and replaced wholesale, never anchored to a particular token or appended.

Required future composition checks: WP1→WP2→WP3, repeat independently without changing later tokens, failure before target writes, forced write/lint restoration, WP3 rollback returning to after-WP2, then WP2 rollback returning to WP1. Separate backup directories mean rollback order **WP3 first, WP2 second**. Review/new owner approval precedes any deployment. Phase 1 UI checks must include 1440/1024/390/360, real long names, single/six-image cases, keyboard/hover/focus/active, gallery/lightbox, fragment reloads and owner checkout smoke steps 1–4.

## 10. Intake cleanup and remaining gate

Owner explicitly requested cleanup of failed export attempts. Command saved as `diagnostics/RD-25-WP2-WP3_export-cleanup_20261012.sh`; it removes only this chain's collector names (home and optional public_html copies), temporary export directories, and older WP2-WP3 check archives. It preserves the accepted `RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz`, older WP1 archives, site images/caches and patch backups. The command is supplied for the owner, not executed against hosting by Codex. Local evidence/reproduction fixtures are retained.

All requested Phase 0 inputs are present. **Stop here and await the owner's exact `go WP2-WP3`.** No Phase 1 patch, commit, push or deploy is authorized by this report.
'''
report_path=repo/'diagnostics/RD-25-WP2-WP3_phase0_report_20261012.md'
report_path.write_text(report,encoding='utf-8')
scripts=e/'scripts';scripts.mkdir(exist_ok=True)
for p in [w/'probe.php',w/'prepare.py',w/'cost.py',w/'visuals.py',w/'capture.mjs',Path(__file__),repo/'work/rd25-wp2-wp3-intake-20261012.py']:
    shutil.copyfile(p,scripts/p.name)
(scripts/'README.md').write_text('''# Reproduction

These are Phase 0 instruments, not hosting patch runners. Restore the staged scripts into `work/rd25-wp2-wp3-phase0-20261012/`; the intake script belongs in `work/`. Their default paths depend on that location. Original scripts and fixtures remain there on the owner machine. Input archive is in Downloads and identified by source-gate.json SHA256. Never substitute a stale source snapshot.

Order: intake (new source directory, verifies every archive hash and preserves mtime); prepare; probe measure; probe validate-inventory; finalize measured plan; probe render; visuals; capture; cost on fresh isolated roots; probe schema; deliver. Re-running cost requires fresh root names because its cold-cache state matters. Calling native PHP in a sandbox that reports cache inaccessible is rejected by probe.php; accepted local PHP runs used the permitted filesystem runtime. Scripts do not contact production or a database.

PHP: `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`, memory_limit=1024M. Twig autoload: `work/pay003/tooling/vendor/autoload.php`. Python runtime: bundled codex-primary-runtime Python (Pillow). Node: bundled runtime, Playwright with installed local headless Chrome. Browser requests are fulfilled from local source/fixture files, with all other origins blocked. Auxiliary Manrope font fixture files come from the earlier WP1 tooling snapshot and are not substituted live target code. All source PHP/Twig/CSS under the fresh source directory is read-only in these instruments.

The HTML comparison uses only screenshot files and can be opened directly without the diagnostic route. Individual prototype HTML pages require capture.mjs's local resource routing; they are not stand-alone deployed pages.
''',encoding='utf-8')
sealed=[]
for p in sorted(e.rglob('*')):
    if p.is_file() and p.name!='seal.json':sealed.append({'path':p.relative_to(e).as_posix(),'bytes':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()})
(e/'seal.json').write_text(json.dumps({'scope':'Phase 0 only; no patch','report':{'path':str(report_path.relative_to(repo)).replace('\\','/'),'sha256':hashlib.sha256(report_path.read_bytes()).hexdigest()},'evidence':sealed},indent=2)+'\n',encoding='utf-8')
print(json.dumps({'report':str(report_path),'evidence_files':len(sealed),'inventory_products':len(inventory),'single_image_products':len(single),'over_five_products':[(p['product_id'],p['image_count']) for p in many],'screenshots':36,'source_members_unchanged':len(post)}))
