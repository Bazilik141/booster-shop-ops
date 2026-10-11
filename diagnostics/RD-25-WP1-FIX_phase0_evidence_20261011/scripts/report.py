"""Publish Phase 0 diagnostic artifacts only; no PHP candidate or runner."""
from measure import W,R,E,IMG,dump,BASE
from pathlib import Path
import json,hashlib,math,collections,shutil,re

A=json.loads((E/'proposed-assets.json').read_text(encoding='utf8'))
RAW=json.loads((E/'measurements-raw.json').read_text(encoding='utf8'))
G=json.loads((E/'source-gate.json').read_text(encoding='utf8'))
S=json.loads((E/'proposal-summary.json').read_text(encoding='utf8'))
H=json.loads((E/'threshold-distributions.json').read_text(encoding='utf8'))
C=json.loads((E/'contact-sheet-index.json').read_text(encoding='utf8'))
NAME='RD-25-WP1-FIX_phase0_report_20261011.md'
REL='RD-25-WP1-FIX_phase0_evidence_20261011'

def link(name,label=None):return f'[{label or name}]({REL}/{name})'
def compact(x):return json.dumps(x,ensure_ascii=False,separators=(',',':'))
def pct(r,stage,slot):
    p=r[stage][slot]
    return f"{p['content_width_pct']:.2f} × {p['content_height_pct']:.2f}"
def path(x):return '`'+x.replace('|','\\|')+'`'
def mdtable(headers,rows):
    def cell(v):return re.sub(r'(?<!\\)\|',r'\\|',str(v))
    return '| '+' | '.join(headers)+' |\n| '+' | '.join(['---']*len(headers))+' |\n'+''.join('| '+' | '.join(cell(v) for v in row)+' |\n' for row in rows)

checks=[]
def check(name,value):
    checks.append({'check':name,'passed':bool(value)});assert value,name
check('Fresh export: all four expected hashes match',G['passed'] and len(G['files'])==4)
check('Exactly the original 286 unique asset sources measured',len(A)==286 and len({r['source'] for r in A})==286 and {r['source'] for r in A}=={r['source'] for r in BASE})
check('Source dimensions, file sizes, decoded JPEG/PNG/WebP types and corner RGBA match approved v3 evidence (MPO uses JPEG first frame)',all(r['source_dims']==[b['width'],b['height']] and (IMG/r['source']).stat().st_size==b['size'] and r['corner_rgba']==[rgb+[alpha] for rgb,alpha in zip(b['corner_rgb'],b['corner_alpha'])] and {'JPEG':2,'MPO':2,'PNG':3,'WEBP':18}[r['type']]==b['type'] for r,b in zip(RAW,BASE)))
def current_kind(r):
    w,h=r['source_dims'];c=r['corner_rgba'];outside=w*100<h*70 or w*100>h*145
    trans=r['type']!='JPEG' and all(x[3]<=16 for x in c)
    white=all(x[3]==255 and min(x[:3])>=240 for x in c)
    return 'cut' if outside or trans or white else 'photo'
check('Recomputed v3 classes match for every measured source',all(current_kind(r)==r['v3']['kind'] for r in RAW))
check('Proposed crop boxes are integer, positive and inside source bounds',all(all(isinstance(v,int) for v in r['crop_box_exclusive']) and 0<=r['crop_box_exclusive'][0]<r['crop_box_exclusive'][2]<=r['source_dims'][0] and 0<=r['crop_box_exclusive'][1]<r['crop_box_exclusive'][3]<=r['source_dims'][1] for r in A))
check('Every above-threshold foreground pixel retained by proposed crop bounds',all(r['above_threshold_content_pixels_removed']==0 for r in A))
check('137 transparent-corner assets retain alpha/content classification',all(A[r['id']]['proposed_class']=='cut' for r in RAW if all(c[3]<=16 for c in r['corner_rgba'])))
check('Album 400 yellow passes safe cover at both slots',A[97]['proposed_class']=='photo' and all(v['passes'] for v in A[97]['cover_check'].values()))
check('Games 7 Days sleeves retain full source and become cut',A[186]['proposed_class']=='cut' and A[186]['crop_box_exclusive']==[0,0,362,500])
check('Four supplied Mystery Box banner assets remain photo and untrimmed',all(A[i]['proposed_class']=='photo' and not A[i]['trim_changes'] for i in [118,119,282,283]))
check('All 95 currently photo-classified 3D asset sources stay photo with unchanged geometry',all(r['proposed_class']=='photo' and r['before']==r['after'] for r in A if r['asset_subgroup']=='3D print' and r['v3_class']=='photo'))
check('Geodude and Ditto remain cut with unchanged source/canvas framing',all(A[i]['proposed_class']=='cut' and A[i]['before']==A[i]['after'] for i in [62,87]))
for slot in ['desktop','mobile']:
    values=[A[i]['after'][slot]['content_height_pct'] for i in [131,143,122]]
    check('OP-13/15/17 content heights differ by less than 5% at '+slot,max(values)/min(values)-1<=.05)
check('Every current cut remains un-cropped within its content bbox after proposal',all(r['after'][slot]['bbox_visible_fraction']==1 for r in A for slot in ['desktop','mobile'] if r['proposed_class']=='cut'))
changed={r['id'] for r in A if r['class_changes'] or r['trim_changes']}
check('Contact sheet includes every changed asset and every named required asset',changed<=set(C['selected_ids']) and set(map(int,C['required_assets']))<=set(C['selected_ids']))
check('Contact sheet: all 15 PNG category pages and 628 tile images exist',all((E/'contact-sheet'/p['filename']).is_file() for p in C['pages']) and all((E/'contact-sheet/tiles'/f'{i:03d}-{stage}-{slot}.png').is_file() for i in C['selected_ids'] for stage in ['before','after'] for slot in ['desktop','mobile']))
check('All four exported code copies remain byte-identical',all(hashlib.sha256((W/'source'/x['path']).read_bytes()).hexdigest()==x['actual'] for x in G['files']))
source_manifest=json.loads((E/'asset-source-manifest.json').read_text(encoding='utf8'))
check('All extracted original image bytes remain unchanged',all(hashlib.sha256((IMG/x['source']).read_bytes()).hexdigest()==x['sha256'] for x in source_manifest['assets']))
protected={'patches/RD-25-WP1_media-fit-catalog_20261009.php':'49ff6c088caf05eecbba47d660af98ce18b85bd50739e1540a3bde637ce01e83','patches/RD-25-WP1_featured-image-payload_20261010.php':'0f7b394c634a05f5b8abb4fa684647015b98442b7dbd604f477c432d9bce2aa3','patches/RD-25-WP1_media-cache-miss_20261010.php':'085cbb193b7c877f03768278acef97dd700d66aea042bee2b584e0997bbc32c3'}
check('Prior r3/F6/cache-miss runners remain unchanged',all(hashlib.sha256((R/p).read_bytes()).hexdigest()==s for p,s in protected.items()))
check('No WP1-FIX PHP patch runner exists',not any((R/'patches').glob('RD-25-WP1-FIX*.php')))
dump('phase0-checks.json',{'checks':checks,'passed':sum(c['passed'] for c in checks),'total':len(checks),'scope':'Measurement artifact consistency and existing crop API probe, not tests of a new implementation.'})

full=['# RD-25 WP1-FIX — complete 286-asset measurement table\n','Date: 2026-10-11. Asset grain, not product-ID rows. Corners in TL/TR/BL/BR order: RGB plus normalized opacity (0 transparent, 255 opaque). Bboxes use exclusive right/bottom endpoints. D = desktop 1:1, M = mobile 4:5. Percentages are content width × height relative to slot; values >100 describe content clipped by cover. For nonuniform photos the whole source bounds are used, not claimed subject segmentation.\n','Machine-readable detail and all threshold sweeps: [proposed-assets.json](proposed-assets.json), [measurements-raw.json](measurements-raw.json), [assets.csv](assets.csv).\n']
for category in ['One-Piece','Pokemon','more-tcg','acsesuary']:
    rows=[r for r in A if r['category']==category]
    full.append(f'## {category} ({len(rows)} assets)\n\n')
    full.append(mdtable(['ID / source','Source / type','Corner RGBA','v3 class / reason','Alpha bbox >16','Background bbox >12','Proposed v4 class / reason','Crop / trim dims','D before → after W×H %','M before → after W×H %'],[[f"#{r['id']:03d} {path(r['source'])}",f"{r['source_dims'][0]}×{r['source_dims'][1]} {r['type']}",path(compact(r['corner_rgba'])),r['v3_class']+' / '+r['v3_reason'],path(compact(r['alpha_bbox_16'])),path(compact(r['background_bbox_12'])),r['proposed_class']+' / '+r['proposed_reason'],path(compact(r['crop_box_exclusive']))+f" / {r['trimmed_dims'][0]}×{r['trimmed_dims'][1]} / {r['trim_method']}",pct(r,'before','desktop')+' → '+pct(r,'after','desktop'),pct(r,'before','mobile')+' → '+pct(r,'after','mobile')] for r in rows]))
(E/'assets-full.md').write_text('\n'.join(full),encoding='utf8')

uniform_op=[r for r in RAW if all(c[3]==255 for c in r['corner_rgba']) and r['corner_max_channel_span']<=8]
distance_sweep={str(t):{'uniform_opaque_assets':len(uniform_op),'bbox_changes_vs_12':sum(r['background_bbox'][str(t)]!=r['background_bbox']['12'] for r in uniform_op),'bbox_area_changes_above_5pct_vs_12':sum(abs((r['background_bbox'][str(t)][2]-r['background_bbox'][str(t)][0])*(r['background_bbox'][str(t)][3]-r['background_bbox'][str(t)][1])/((r['background_bbox']['12'][2]-r['background_bbox']['12'][0])*(r['background_bbox']['12'][3]-r['background_bbox']['12'][1]))-1)>.05 for r in uniform_op if r['background_bbox'][str(t)] and r['background_bbox']['12'])} for t in [2,4,6,8,10,12,16,20,24,32,40]}
dump('background-threshold-sensitivity.json',distance_sweep)

threshold_table=mdtable(['Parameter','Proposed value','Evidence / boundary'],[
['Normalized opacity','content >16','137 transparent-corner sources. Alpha 0 includes faint edge artifacts; 115 bboxes differ from >16, two areas by >5%. >24 changes 52 bboxes; >32 changes 73. No clean universal histogram gap: retain the already reviewed >16 threshold rather than raise it.'],
['Minimum content / line','2 pixels per row or column','At 2 pixels, only 13 transparent bboxes move by one edge pixel (13 above-threshold pixels before padding). Counts 3/4/8/16 exclude 69/177/1,081/5,242 pixels before padding.'],
['Crop safety padding','2 source pixels per side, clamped','All above-threshold pixels are retained in this corpus. If filtering would remove any >threshold pixel after padding, expand to the complete strict bbox. One opaque asset (#233 MS2.jpg) needs that fallback. No arbitrary deletion of a detached product detail.'],
['Page white','each RGB ≥250; alpha255; corner channel span ≤8','Opaque all-corner minimum values have a gap 240..251: largest below is239, smallest in page-white cluster252. Thresholds245/250/252 select the same17 uniform assets; 255 wrongly keeps only9. 250 lies in this measured gap. Per-corner tests also drive R3.'],
['Uniform corner colour','max channel span ≤8','All17 page-white corner sets span0..3; yellow Album400 spans4, multi-album studio source spans5. A conservative8 covers these. Distribution has6/7 absent; 9..12 contains10 other corner sets. It is a conservative limit, not a semantic proof of studio lighting. Only unlocks the bbox safety test.'],
['Background distance','max(|R−bgR|,|G−bgG|,|B−bgB|)>12; bg = per-channel median of corners','Sweep measured for every source. Yellow album: tolerance2/4 catches canvas noise,6 returns x262..1358,8 extends to1230. 10..16 is the foreground plateau; 12 preserves bbox[378,90,1140,952], and raising to16 removes another few edge/shadow pixels. No global colour-distance histogram gap is asserted.'],
['Cover safety','2% of the visible source window on every edge, plus 2px content-bbox expansion','Both centred1:1 and4:5 windows must contain expanded bbox. Album400 passes; the narrow pack/photo cases fail and stay cut. Values selected from exact measured margins, not a whitelist.'],
['Cover band','inclusive0.70..1.45','Existing conservative band retained. Outside-band photo exception requires opaque uniform non-white corners and the two-slot safety test.'],
])

report=f'''# Codex Report — RD-25 WP1-FIX: Phase 0 media trim/classification diagnosis

Date: 2026-10-11
Status: **Phase 0 complete. No patch written. Await owner "go Phase 1".**

## Outcome and scope

The measured proposal fixes D1/D2/D3 within existing CSS geometry: **140 source crop boxes change**, **3 asset classes change**, and **69 assets change rendered content width or height by more than 5%**. Proposed totals: **165 cut / 121 photo** (v3:164/122). This is the 286-source archive corpus, including secondary images and logos, not 286 current product primary rows.

The exact changed-asset set is **143 sources**; the contact sheet covers all143 plus14 unchanged required references = **157 assets**, four views each (desktop/mobile, before/after), **15 grouped PNG pages** and4 compact required-case pages. Start with {link('contact-sheet/index.html','filterable before/after contact sheet')} or {link('contact-sheet/required-01.png','packs')}, {link('contact-sheet/required-02.png','sets and accessories')}, {link('contact-sheet/required-03.png','albums and Pokemon Mystery Boxes')}, {link('contact-sheet/required-04.png','3D / One Piece Mystery Boxes / extra reclassification')}.

Complete asset table: {link('assets-full.md','all286 assets — human-readable table')}; {link('assets.csv','CSV table')}; {link('proposed-assets.json','complete proposed metadata and source/slot geometry')}. Every row includes source/type, four RGBA corners, v3 reason, alpha and background bbox, proposed v4 reason, crop/trim dimensions, and both slot content W/H before/after. {link('measurements-raw.json','all threshold measurements')} retains the full per-source sweeps and256-bin alpha/distance histograms.

## Fresh source gate and provenance

Owner export: `RD-25-WP1-FIX-check-20261011.tar.gz`, **{G['bytes']:,} bytes**, SHA256 `{G['sha256']}`. All4 files match expected installed r3/cache-miss hashes before code analysis; {link('source-gate.json')}.

'''
report+=mdtable(['Fresh export file','SHA256','Gate'],[[path(f['path']),path(f['actual']),'match'] for f in G['files']])
report+=f'''
Images: re-extracted only the286 classified sources from owner's `RD-25-WP1-followup-20261009.tar.gz`, SHA256 `{source_manifest['archive_sha256']}`. Owner confirmed in this session on2026-10-11 that primary photos were not added/replaced since that export. Thus no newer primary source was reported; no9/24 backup used. {link('asset-source-manifest.json')} pins every source image's original bytes. Dimensions, actual decoded types, size, corner RGB and normalized alpha match v3 evidence for all286; v3 classes recompute exactly. Pillow identifies12 JPEG-compatible originals as MPO containers; the measurement uses their first JPEG frame, as the existing GD/JPEG path does, and keeps the MPO container label in the table.

Four bounded public category fetches via the web tool returned502, and one independent Python fetch of One-Piece was disconnected. This establishes an unavailable retrieval path, not a production outage. No storefront crawl or cache warming performed. Product-ID→primary-image mapping was not exported; category groups follow archive directories with named accessory sources in Other manually grouped into acsesuary, and3D grouped there. These group labels are contact-sheet organization, not DB membership claims. Product reference labels for Mega Dream EX and Toploader follow the review's matched assets; their file contents/names are shown without inventing a primary mapping.

Read the current handoff and visual-defect diagnostic first. Where they differ, the current handoff governs: **all non-white mixed corners do not automatically mean cut**; Mystery Box banners remain photo. The earlier original RD-25 handoff is absent from this working checkout but readable on master via `git show`; its framing values agree. The named RD-25 entry is not present in current/master `design/DECISIONS.md`; the owner-requested current handoff plus measured installed source supply the values. No design/governance/status file changed.

## Root cause and crop API

- Exported `catalog/model/tool/image.php::resizeFit()` constructs an Image from the whole source and calls `resizeFit()` without a crop. Library `resizeFit()` at232..269 computes scale using full source width/height at237. Transparent/white margins consume the84%×94% cut box.
- `mediaInfo()` combines outside-aspect, transparent-corner and all-RGB≥240 rules. It cannot distinguish the grey album or mixed white/coloured package corners as required by the new handoff.
- `catalog/controller/product/thumb.php` calculates emitted img dimensions from original metadata width/height. **137 fitted output dimensions change in the proposal**, so controller changes are required; emitted width/height must come from the selected crop dimensions using the same floor/min(1,700/w,875/h) calculation.
- Fresh library has `crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void` at341..350, with exclusive bottom/right endpoints. It allocates a truecolour target but neither disables alpha blending nor saves alpha. It copies using original source width/height, relying on clipping, and provides no bounds/allocation validation.

Existing-method runtime probe (PHP8.3.30+GD, unmodified exported library): crop(2,2,6,6) produces4×4 but alpha63 red becomes opaque RGB128/0/0, and transparent background becomes opaque black. **The existing crop is unusable for transparent cut-outs**; {link('crop-api-probe.json')}. Production remainsPHP8.0; no native8.0 run claimed.

Smallest Phase1 addition: a new `Image::cropFit(int $left, int $top, int $right, int $bottom): void`, exclusive endpoints, validates positive in-bounds rectangle/allocation, prepares transparent target with blending off/savealpha on for PNG/WebP, copies exactly cropped width/height, updates internal dimensions, and releases the old resource. Leave existing crop() and resize() untouched. Call only before the catalog model's resizeFit for cut assets. This is a proposal, **no method implementation or PHP candidate has been written**.

## Threshold selection

{threshold_table}

Distributions and sensitivity: {link('threshold-distributions.json')}, {link('background-threshold-sensitivity.json')}. Their bins are counts of sources or pixels as named; no claim of a statistically unique optimum. The chosen values keep real product details at the existing reviewed opacity threshold, exclude low-opacity halos in bbox discovery, retain2px edge slack, and use fail-safe strict bbox expansion if row/column filtering would discard above-threshold pixels. Hard isolated noise cannot be proven distinct from a detached product detail by line counts alone; future ambiguous sources retain the larger box rather than silently lose content.

### Alpha / minimum line-count histograms

'''
report+=mdtable(['Normalized opacity bin (137 transparent-corner assets)','Pixels'],[[k,f'{v:,}'] for k,v in H['alpha_pixel_bins'].items()])
report+='\n'+mdtable(['Minimum content pixels/line','Bboxes changed vs1','Pixels outside bbox before2px pad'],[[n,x['bbox_changes_vs_1'],f"{x['above_threshold_pixels_excluded_without_pad']:,}"] for n,x in H['minimum_line_count_sensitivity'].items()])
report+='\n### Page-white / corner-uniformity evidence\n\n'
report+=mdtable(['Opaque minimum-corner-RGB range','Asset count'],[[f'{lo}..{hi}',sum(v for k,v in H['opaque_corner_min_rgb_histogram'].items() if lo<=int(k)<=hi)] for lo,hi in [(0,234),(235,239),(240,249),(250,251),(252,254),(255,255)]])
report+='\n'+mdtable(['Opaque corner channel span','Asset count'],[[f'{lo}..{hi}',sum(v for k,v in H['opaque_corner_uniformity_histogram'].items() if lo<=int(k)<=hi)] for lo,hi in [(0,3),(4,5),(6,7),(8,8),(9,12),(13,255)]])
report+='\n### Background-distance histograms / sweep\n\n'
report+=mdtable(['Distance-to-corner-median bin (22 uniform opaque-corner sources)','Pixels'],[[k,f'{v:,}'] for k,v in H['uniform_opaque_background_distance_pixel_bins'].items()])
report+='\n'+mdtable(['Tolerance','Uniform opaque bboxes changed vs12','BBox area changes >5% vs12','Album400 yellow bbox'],[[n,x['bbox_changes_vs_12'],x['bbox_area_changes_above_5pct_vs_12'],path(compact(H['album400yellow_tolerance_sweep'][n]))] for n,x in distance_sweep.items()])
report+=f'''
## Proposed deterministic rule order

1. Test each corner for page white (opaque255, each channel≥250) or transparency (normalized opacity≤16). At least one but fewer than all4 background-like corners → **cut**, reason `mixed_page_white_or_transparent_corners`, regardless of aspect. Do not trim arbitrary nonuniform package art by subtracting its corner median.
2. All4 transparent corners → cut, reason `transparent_corners`; alpha bbox. All4 opaque page-white corners with span≤8 → cut, reason `opaque_page_white_uniform_corners`; distance bbox.
3. Outside inclusive0.70..1.45: only a wholly opaque source with all4 non-white/non-transparent corners and span≤8 can be photo, and only when its distance bbox expanded2px is inside both centred cover windows inset2%. Reason `uniform_non_white_safe_cover_both_slots`. Otherwise cut, `aspect_outside_cover_band_unsafe_cover`.
4. Remaining within-band non-white/mixed-non-white source → photo, `non_white_or_mixed_non_white_corners`. This preserves the4 Mystery Box banners and95 currently-photo3D assets.
5. Cut trim: alpha-based when transparent pixels exist; otherwise use distance bbox only for uniform page-white or eligible uniform non-white backgrounds. Unknown/mixed opaque art keeps full bounds. Remove only outer rows/columns through the final rectangular crop; never rewrite transparency or whiten backgrounds. Photo sources keep full bounds.

Metadata proposal preserves original width/height/stat fields, adds validated crop rectangle/dimensions and trim method, and bumps **RD25_MEDIA_VERSION4**. Fit key prefix becomes **rd25-fit-v2**. Keep v3 metadata/v1 fitted files on disk unused. Invalid crop metadata must cause regeneration through the retained cache-miss guard; preserve lock/atomic-publication behavior. Thumb uses crop dimensions for cut / original dimensions for photo to emit exact served fit dimensions. Existing Twig already consumes those fields, so **no Twig edit is needed**.

### Album400 safe cover, exact data

Yellow album source:1536×1024; four corners `[240,242,241]`, `[243,243,243]`, `[242,241,242]`, `[240,239,241]`, opacity255, span4. These are original pixels; the earlier served-WebP measurement was approximate. Background median `[241,241.5,241.5]`; bbox atdistance12 `[378,90,1140,952]`; expanded bbox `[376,88,1142,954]`.

Desktop centred source window `[256,0,1280,1024]`, safe interior `[276.48,20.48,1259.52,1003.52]`. Mobile window `[358.4,0,1177.6,1024]`, safe interior `[374.784,20.48,1161.216,1003.52]`. Both pass, including left2%-margin clearance1.216px after the2px bbox expansion. Fit rounding700×466 slightly changes the cover ratio; the proposed final content bbox stays wholly inside both slots. This narrow clearance is why exact Phase1 GD/pixel QA is still required. No filename override used.

## Every reclassification

'''
report+=mdtable(['ID / source','v3 → proposed v4','Reason'],[[f"#{r['id']:03d} {path(r['source'])}",r['v3_class']+' → '+r['proposed_class'],r['proposed_reason']] for r in A if r['class_changes']])
report+=f'''
The extra #222 asset is a Pokemon logo/illustration, visible on required sheet4. Its mixed page-white/grey corners trigger R3. Its product-primary use is unverified. Category tile/logo callers that use legacy resize are unchanged; this classification is used by catalog resizeFit callers only.

## Required-case geometry

Values below are **content W×H as% of the slot**, not content occupancy inside the source file. For photographs with nonuniform backgrounds, the measured content bbox is the entire source; a125% cover width is expected cropping, not segmented object width.

'''
ids=[131,143,122,123,135,224,260,228,235,194,213,97,103,104,186,178,179,282,283,118,119,62,87,84]
required=C['required_assets']
report+=mdtable(['Reference / asset ID','Class','Trimmed WxH','Desktop before → after W×H%','Mobile before → after W×H%'],[[required[str(i)]+f' #{i:03d}',A[i]['v3_class']+' → '+A[i]['proposed_class'],f"{A[i]['trimmed_dims'][0]}×{A[i]['trimmed_dims'][1]}",pct(A[i],'before','desktop')+' → '+pct(A[i],'after','desktop'),pct(A[i],'before','mobile')+' → '+pct(A[i],'after','mobile')] for i in ids])
report+=f'''
OP-13/15/17 content heights after proposal are93.603/94.000/93.637% in both slots; spread0.424% relative to shortest, below±5%. OP-17 box height rises73.469→93.363% desktop,58.775→93.363% mobile. Outlet Mix and Toploader stay width-limited: taller full-box rendering is impossible without cropping or changing the approved geometry. Acrylic stand fills height on desktop and width on mobile.

Geodude and Ditto stay cut, with their complete dark rectangles unchanged; nonuniform corners plus narrow aspect do not meet the new safe-cover exception. They were already cut in v3, so the handoff's "dark3D photos unchanged (photo)" is interpreted for the95 existing photo assets, not an instruction to crop those2 narrow references. All6 Album360 archive variations are included to cover the ambiguous "both" references.

Glory of Team Rocket source photo frame remains content: no frame detection/removal is proposed. Source #275 (366×343, matching the visual defect report's fit dimensions despite its filename) retains its opaque light frame; only surrounding alpha padding is trimmed. Source #280 is a separate box image, also retains the frame/product pixels. The owner still replaces the framed/low-resolution source; this fix does not repair it.

## Every rendered-size change above5%

Definition: either content bbox width or height relative to slot changes by >5% **relative to its prior value**, at desktop or mobile; not5 percentage points. Below lists all69. Negative changes for R3 package/logo sources exchange cover clipping for complete-art display. All cut-to-cut trims preserve every above-threshold content pixel; the relative scale can change slightly between axes from integer fit dimensions.

'''
report+=mdtable(['ID / source','Desktop content W×H% before → after','Mobile content W×H% before → after'],[[f"#{r['id']:03d} {path(r['source'])}",pct(r,'before','desktop')+' → '+pct(r,'after','desktop'),pct(r,'before','mobile')+' → '+pct(r,'after','mobile')] for r in A if r['render_change_above_5pct']])
report+=f'''
All140 trim changes including ≤5% cases, corner colour/alpha, and crop bounds are in {link('proposed-assets.json')} and {link('assets-full.md')}; machine subset {link('render-change-above-5pct.json')}. Contact sheet includes every class/trim change regardless of percentage.

## Verification, limits and Phase1 scope

**{len(checks)}/{len(checks)} Phase0 consistency checks passed**; {link('phase0-checks.json')}. These validate source gates, measurement/geometry arithmetic, byte preservation, reference coverage, and complete contact-sheet files. The existing crop method was exercised directly. They are **not runner/regression checks of a new patch**. Visually inspected all4 compact required-case sheets: packs stay complete, album subject stays within both windows, sleeves show all package text, Mystery Box art/dark photos unchanged. Bulk pages are generated from the same measured geometry; no live/browser interactive QA or deployment proof claimed.

Contact previews use Pillow12.3.0 Lanczos resampling and current slot/framing values. Crop rectangles/fit dimensions follow proposed exact arithmetic; output pixels/encoder are not claimed identical to PHP/GD. Percentages reflect the analytical threshold-defined bbox. Byte-level source-image hashes remain intact. Crop probing used PHP8.3.30; future runner must lint/check on productionPHP8.0 and compare actual GD crop/fit bytes/dimensions, cold/warm cache warnings, cache-miss handler restoration, composition withF6, idempotency/rollback before owner deployment.

Phase1 proposed targets, all hash-pinned to the4-file gate:

| Target | Proposed bounded change |
| --- | --- |
| `catalog/model/tool/image.php` | v4 classification/crop metadata, validation and v2 fit key; crop only cut files before fit; keep cache-miss guard and legacy resize byte-identical |
| `system/library/image.php` | Add cropFit helper for alpha-preserving crop; leave crop()/resize()/resizeFit() bodies intact |
| `catalog/controller/product/thumb.php` | Match img dimensions to crop-selected served fit dimensions; preserve caller-source/F6 behavior |
| `catalog/view/template/product/thumb.twig` | Read/pin for context; no edit required |

No CSS/header edit required: the84%×94% box, bottom alignment, desktop1:1/mobile4:5 and cover geometry already produce the desired measured outcomes once input bounds/class are corrected. Existing JSON-LD, gallery,700×700 lightbox, feed and all legacy resize callers/URLs stay outside the proposed target scope. WP2/WP3 stay blocked. No DB, source-image, server, schema, SEO, checkout/payment, CRM or status mutation performed.

This session created only Phase0 report/evidence and local diagnostic scripts under work. Prior r3/F6/cache-miss runners remain byte-identical. **No WP1-FIX patch exists, no commit/push/deploy performed. Stop here; Phase1 requires the owner's exact "go Phase 1".**

## Evidence and reproducibility

{link('source-gate.json')}, {link('asset-source-manifest.json')}, {link('crop-api-probe.json')}, {link('thresholds.json')}, {link('threshold-distributions.json')}, {link('background-threshold-sensitivity.json')}, {link('measurements-raw.json')}, {link('assets-full.md')}, {link('assets.csv')}, {link('proposed-assets.json')}, {link('class-changes.json')}, {link('render-change-above-5pct.json')}, {link('contact-sheet-index.json')}, {link('phase0-checks.json')}, {link('seal.json')}.

Diagnostic source scripts are copied into {link('scripts','evidence scripts')}. They read/extract the owner image export and generate only measurements/preview/report artifacts. They do not alter production sources or generate a patch. For reproduction, run the scripts from their original `work/rd25-wp1-fix-phase0-20261011` location (or restore the evidence copies there), in order: measure.py, propose.py, contact.py, crop-probe.php with the exported source, report.py. Python runtime is Pillow12.3.0/numpy2.3.5, PHP probe is8.3.30+GD. Original image archive stays outside delivery artifacts; raw sources are in `work/rd25-wp1-fix-phase0-20261011/images`.
'''
# Space prose quantities without rewriting identifiers, file paths, hashes or inline code.
parts=re.split(r'(`[^`]*`)',report)
for i in range(0,len(parts),2):
    parts[i]=re.sub(r'\b(all|the|only|on|no|and|span|identifies|retain|at|produces|PHP|before|vs|centred|both|distance|tolerance|Album|Phase|version|threshold|alpha|RGB|bbox|corner|opacity|remaining)(?=\d)',r'\1 ',parts[i])
    parts[i]=re.sub(r'(?<=\d)(?=(?:px|sources|source|assets|asset|files|file|pixels|pixel|uniform|currently|named|required|classified|photo|content|opaque|transparent|fit|metadata|grouped|compact|corner|originals|JPEG|case|cut|value|run|product|rows|checks|total|views)\b)', ' ',parts[i])
report=''.join(parts)
(R/'diagnostics'/NAME).write_text(report,encoding='utf8')
scriptdir=E/'scripts';scriptdir.mkdir(exist_ok=True)
for name in ['measure.py','propose.py','contact.py','report.py','crop-probe.php']:shutil.copyfile(W/name,scriptdir/name)
seal={'report':{'path':'diagnostics/'+NAME,'sha256':hashlib.sha256((R/'diagnostics'/NAME).read_bytes()).hexdigest()},'evidence':[],'protected_prior_patch_sha256':protected}
for p in sorted(E.rglob('*')):
    if p.is_file() and p.name!='seal.json':seal['evidence'].append({'path':p.relative_to(E).as_posix(),'bytes':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()})
dump('seal.json',seal)
print(json.dumps({'report':NAME,'checks':len(checks),'assets':len(A),'contact_sheet_assets':C['unique_assets'],'contact_sheet_pages':len(C['pages']),'class_changes':S['class_changes'],'trim_changes':S['trim_changes'],'render_change_above_5pct':S['render_change_above_5pct'],'patch_created':False},indent=2))
