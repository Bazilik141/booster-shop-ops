"""Phase 0 measurement visualizations; no original assets are rewritten."""
from measure import W,R,E,IMG,dump
from PIL import Image,ImageDraw,ImageFont
import json,math,textwrap,html,collections

A=json.loads((E/'proposed-assets.json').read_text(encoding='utf8'))
OUT=E/'contact-sheet';OUT.mkdir(exist_ok=True)
TILES=OUT/'tiles';TILES.mkdir(exist_ok=True)
FONT=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',14)
SMALL=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',12)
TITLE=ImageFont.truetype('C:/Windows/Fonts/arialbd.ttf',24)
REQUIRED={62:'Geodude',87:'Ditto cup',84:'Dark 3D slab stand photo',97:'Album 400 yellow',103:'Album 360 white',104:'Album 360 yellow',105:'Album 360 black',106:'Album 360 inside',107:'Album 360 multiple',108:'Album 360 white inside',118:'One Piece Mystery Box Standard',119:'One Piece Mystery Box XL',122:'OP-17 booster',123:'OP-17 booster box',131:'OP-13 booster',135:'EB-02 Rare Pack',143:'OP-15 booster',178:'G7D 9-pocket sheet v1',179:'G7D 9-pocket sheet v2',186:'G7D sleeves 63.5 x 88',194:'Toploader 1 pc (review asset)',213:'Acrylic card stand',224:'Hot Wind Arena KR booster',228:'Mega Dream EX (review asset)',235:'Outlet Mix',260:'Mega Gallade EX',261:'Mega Gallade EX contents',282:'Pokemon Mystery Box Standard',283:'Pokemon Mystery Box XL'}
SELECTED=[r for r in A if r['class_changes'] or r['trim_changes'] or r['id'] in REQUIRED]

def tile(r,stage,slot):
    # The current fit renderer preserves source canvas and floors dimensions;
    # the proposed simulation uses measured crop then the identical fit geometry.
    source=Image.open(IMG/r['source']).convert('RGBA')
    if stage=='after': source=source.crop(tuple(r['crop_box_exclusive']))
    fw,fh=r[stage][slot]['fit_dims']
    fit=source.resize((fw,fh),Image.Resampling.LANCZOS)
    kind=r['v3_class'] if stage=='before' else r['proposed_class']
    w,h=180,(180 if slot=='desktop' else 225)
    if kind=='cut':
        z=min(.84*w/fw,.94*h/fh); rw,rh=max(1,round(fw*z)),max(1,round(fh*z)); x=round(.08*w+(.84*w-rw)/2); y=h-rh
    else:
        z=max(w/fw,h/fh); rw,rh=max(1,round(fw*z)),max(1,round(fh*z)); x=round((w-rw)/2); y=round((h-rh)/2)
    canvas=Image.new('RGBA',(w,h),'white');fit=fit.resize((rw,rh),Image.Resampling.LANCZOS);canvas.alpha_composite(fit,(x,y))
    if kind=='photo':
        rounded=Image.new('L',(w,h),0);ImageDraw.Draw(rounded).rounded_rectangle((0,0,w-1,h-1),radius=10,fill=255)
        final=Image.new('RGBA',(w,h),'white');final.paste(canvas,(0,0),rounded);canvas=final
    canvas=canvas.convert('RGB')
    # Thin grey frame belongs only to the diagnostic sheet, never the proposed UI.
    ImageDraw.Draw(canvas).rectangle((0,0,w-1,h-1),outline='#dddddd')
    return canvas

def row(r):
    panel=Image.new('RGB',(790,360),'#f2f3f4');d=ImageDraw.Draw(panel)
    title=REQUIRED.get(r['id'],r['source'].split('/')[-1]);d.text((10,8),f"#{r['id']:03d} {title[:80]}",font=FONT,fill='#111827')
    label=f"{r['v3_class']} > {r['proposed_class']} | {r['source_dims'][0]}x{r['source_dims'][1]} > {r['trimmed_dims'][0]}x{r['trimmed_dims'][1]} | {r['trim_method']}"
    d.text((10,28),label,font=SMALL,fill='#344054')
    for j,(stage,slot) in enumerate([('before','desktop'),('after','desktop'),('before','mobile'),('after','mobile')]):
        t=tile(r,stage,slot);filename=f"{r['id']:03d}-{stage}-{slot}.png";t.save(TILES/filename)
        x=10+j*195;d.text((x,53),f'{stage} {"1:1" if slot=="desktop" else "4:5"}',font=FONT,fill='#111827');panel.paste(t,(x,75))
        g=r[stage][slot];d.text((x,306),f"content {g['content_width_pct']:.1f}% W / {g['content_height_pct']:.1f}% H",font=SMALL,fill='#344054')
    filename=r['source'].split('/')[-1]
    d.text((10,329),filename[:100],font=SMALL,fill='#344054')
    return panel

pages=[]
for category in ['One-Piece','Pokemon','more-tcg','acsesuary']:
    rows=[r for r in SELECTED if r['category']==category]
    for start in range(0,len(rows),12):
        subset=rows[start:start+12];page=Image.new('RGB',(1600,110+360*math.ceil(len(subset)/2)),'#f2f3f4');d=ImageDraw.Draw(page)
        name=f'{category}-{start//12+1:02d}.png'
        d.text((10,10),f'RD-25 WP1-FIX Phase 0 | {category} | page {start//12+1}',font=TITLE,fill='#111827')
        d.text((10,46),'BEFORE: v3 full source canvas | AFTER: proposed v4 measured crop | geometry unchanged',font=FONT,fill='#344054')
        d.text((10,69),'All source assets retained. Preview uses Pillow resampling, not a deployed PHP implementation. Diagnostic frames only.',font=FONT,fill='#344054')
        for j,r in enumerate(subset): page.paste(row(r),(10+(j%2)*800,100+(j//2)*360))
        page.save(OUT/name);pages.append({'filename':name,'category':category,'asset_ids':[r['id'] for r in subset]})
    print('rendered',category,len(rows),flush=True)
dump('contact-sheet-index.json',{'unique_assets':len(SELECTED),'class_or_trim_changed_assets':sum(r['class_changes'] or r['trim_changes'] for r in A),'required_assets':REQUIRED,'selected_ids':[r['id'] for r in SELECTED],'pages':pages,'not_primary_product_id_mapping':True})

# Compact required-case sheet supports review without opening every bulk page.
for n,ids in enumerate([[131,143,122,123,135,224],[260,228,235,194,213,186],[97,103,104,178,282,283],[62,87,84,222,118,119]],1):
    page=Image.new('RGB',(1600,1180),'#f2f3f4');d=ImageDraw.Draw(page)
    d.text((10,10),f'RD-25 WP1-FIX Phase 0 | required cases {n}',font=TITLE,fill='#111827')
    d.text((10,46),'Before / proposed after at 1:1 and 4:5. Local geometry simulation; no patch or deployment.',font=FONT,fill='#344054')
    for j,i in enumerate(ids): page.paste(row(A[i]),(10+(j%2)*800,90+(j//2)*360))
    page.save(OUT/f'required-{n:02d}.png')

cards=[]
for r in SELECTED:
    images=''.join(f'<figure><figcaption>{stage} {slot}</figcaption><img src="tiles/{r["id"]:03d}-{stage}-{slot}.png" width="180" height="{180 if slot=="desktop" else 225}"><small>{r[stage][slot]["content_width_pct"]:.1f}% W / {r[stage][slot]["content_height_pct"]:.1f}% H</small></figure>' for stage,slot in [('before','desktop'),('after','desktop'),('before','mobile'),('after','mobile')])
    cards.append(f'<article data-category="{r["category"]}" data-change="{str(r["class_changes"] or r["trim_changes"]).lower()}"><h3>#{r["id"]:03d} {html.escape(REQUIRED.get(r["id"],r["source"].split("/")[-1]))}</h3><p class="source">{html.escape(r["source"])}</p><p>{r["v3_class"]} → {r["proposed_class"]}; crop {r["crop_box_exclusive"]}; {html.escape(r["proposed_reason"])}</p><div class="tiles">{images}</div></article>')
links=' '.join(f'<a href="{p["filename"]}">{p["category"]} {p["filename"][-6:-4]}</a>' for p in pages)
document='''<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RD-25 WP1-FIX Phase 0 contact sheet</title><style>body{font:15px Arial,sans-serif;color:#111827;background:#f2f3f4;margin:24px}header{max-width:1100px}label{display:inline-block;margin:12px}input,select{font:inherit;padding:8px}.tiles{display:flex;flex-wrap:wrap;gap:16px}figure{margin:0}figcaption{font-weight:bold;padding-bottom:8px}small{display:block;margin-top:6px}article{background:white;border:1px solid #ddd;padding:16px;margin-bottom:20px;break-inside:avoid}.source{overflow-wrap:anywhere;color:#344054}h3{margin:0}.links a{display:inline-block;margin:8px}button{font:inherit;padding:8px}@media print{nav{display:none}article{page-break-inside:avoid}}</style><header><h1>RD-25 WP1-FIX — Phase 0</h1><p>Before: installed v3 fit geometry. After: proposed v4 measured crop/classification, with the same CSS geometry. These are local Pillow simulations, not deployed PHP outputs.</p><p>All class/trim changes plus required unchanged references are included. Source paths and asset IDs are the measurement grain, not primary product IDs. Percentages describe the alpha/background-distance content bbox; they do not segment a photographed subject on a nonuniform dark background.</p></header><nav><label>Category <select id="category"><option value="">All</option><option>One-Piece</option><option>Pokemon</option><option>more-tcg</option><option>acsesuary</option></select></label><label>Search <input id="search" type="search"></label><label><input id="changes" type="checkbox"> Changes only</label><span id="count"></span><details><summary>Download PNG contact sheet pages</summary><p class="links">'''+links+'''</p></details></nav><main>'''+''.join(cards)+'''</main><script>const cards=[...document.querySelectorAll('article')];function filter(){let n=0;const category=document.querySelector('#category').value,q=document.querySelector('#search').value.toLowerCase(),changed=document.querySelector('#changes').checked;cards.forEach(c=>{c.hidden=!!((category&&c.dataset.category!==category)||(q&&!c.textContent.toLowerCase().includes(q))||(changed&&c.dataset.change!=='true'));if(!c.hidden)n++;});document.querySelector('#count').textContent=n+' assets';}document.querySelectorAll('input,select').forEach(e=>e.addEventListener('input',filter));filter();</script></html>'''
(OUT/'index.html').write_text(document,encoding='utf8')
print('pages',len(pages),'assets',len(SELECTED))
