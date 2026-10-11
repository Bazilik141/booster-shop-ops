"""Phase 0 geometry/threshold simulation. Never emits PHP or a production patch."""
from measure import W,R,E,IMG,BASE,dump,bbox,dims
from PIL import Image, ImageDraw, ImageFont
import numpy as np
import math, json, collections, csv, html

ALPHA=16
MINIMUM=2
PAD=2
WHITE=250
UNIFORM=8
DISTANCE=12
SAFETY=.02

def expanded(box,w,h):
    return [max(0,box[0]-PAD),max(0,box[1]-PAD),min(w,box[2]+PAD),min(h,box[3]+PAD)] if box else [0,0,w,h]

def cover_window(w,h,ratio):
    cw,ch=(ratio*h,h) if w/h>=ratio else (w,w/ratio)
    return [(w-cw)/2,(h-ch)/2,(w+cw)/2,(h+ch)/2]

def safe_cover(box,w,h):
    out={}
    for label,ratio in [('desktop',1),('mobile',.8)]:
        b=cover_window(w,h,ratio); mx=(b[2]-b[0])*SAFETY; my=(b[3]-b[1])*SAFETY
        inner=[b[0]+mx,b[1]+my,b[2]-mx,b[3]-my]
        out[label]={'window':b,'safe_window':inner,'content_box':box,'passes':box[0]>=inner[0] and box[1]>=inner[1] and box[2]<=inner[2] and box[3]<=inner[3]}
    return out

def geometry(w,h,kind,content,trim,label):
    cw,ch=dims(trim); scale=min(1,700/cw,875/ch); fw=max(1,math.floor(cw*scale)); fh=max(1,math.floor(ch*scale))
    slotw,sloth=(100,100) if label=='desktop' else (100,125)
    if kind=='cut':
        z=min(.84*slotw/fw,.94*sloth/fh); dx=.08*slotw+(.84*slotw-fw*z)/2; dy=sloth-fh*z
    else:
        z=max(slotw/fw,sloth/fh); dx=(slotw-fw*z)/2; dy=(sloth-fh*z)/2
    # Normalize source content into the generated fit pixels; account for floor rounding.
    b=[dx+(content[0]-trim[0])*fw/cw*z,dy+(content[1]-trim[1])*fh/ch*z,dx+(content[2]-trim[0])*fw/cw*z,dy+(content[3]-trim[1])*fh/ch*z]
    fullw=max(0,b[2]-b[0]); fullh=max(0,b[3]-b[1]); vw=max(0,min(slotw,b[2])-max(0,b[0])); vh=max(0,min(sloth,b[3])-max(0,b[1]))
    return {'fit_dims':[fw,fh],'content_width_pct':round(100*fullw/slotw,3),'content_height_pct':round(100*fullh/sloth,3),'visible_content_width_pct':round(100*vw/slotw,3),'visible_content_height_pct':round(100*vh/sloth,3),'bbox_visible_fraction':round(vw*vh/(fullw*fullh),5) if fullw*fullh else 0,'source_content_bbox':content,'slot_content_bbox':b}

raw=json.loads((E/'measurements-raw.json').read_text(encoding='utf8'))
results=[];hist=collections.Counter(); sweeps=[]
for r in raw:
    i=r['id']; w,h=r['source_dims']; corners=r['corner_rgba']; full=[0,0,w,h]
    white=[c[3]==255 and min(c[:3])>=WHITE for c in corners]; trans=[c[3]<=ALPHA for c in corners]
    bg_corners=[a or b for a,b in zip(white,trans)]
    uniform=r['corner_max_channel_span']<=UNIFORM
    outside=w*100<h*70 or w*100>h*145
    basebg=r['background_bbox'][str(DISTANCE)]
    coverbox=expanded(basebg,w,h)
    cover=safe_cover(coverbox,w,h)
    if any(bg_corners) and not all(bg_corners): kind,reason='cut','mixed_page_white_or_transparent_corners'
    elif all(trans): kind,reason='cut','transparent_corners'
    elif all(white) and uniform: kind,reason='cut','opaque_page_white_uniform_corners'
    elif outside:
        if r['all_pixels_opaque'] and not any(bg_corners) and uniform and basebg and all(v['passes'] for v in cover.values()):
            kind,reason='photo','uniform_non_white_safe_cover_both_slots'
        else: kind,reason='cut','aspect_outside_cover_band_unsafe_cover'
    else: kind,reason='photo','non_white_or_mixed_non_white_corners'
    mode='none'; trim=full; strict=full; content=full; excluded=0
    if r['has_alpha_le_16']:
        content=r['alpha_bbox'][str(ALPHA)] or full
    elif uniform:
        content=basebg or full
    if kind=='cut':
        if r['has_alpha_le_16']: mode='alpha'; strict=r['alpha_bbox'][str(ALPHA)] or full
        elif uniform and (all(white) or (r['all_pixels_opaque'] and outside)): mode='background_distance'; strict=basebg or full
        # Mixed package corners never give permission to subtract a nonuniform edge colour.
        if mode!='none':
            ar=np.array(Image.open(IMG/r['source']).convert('RGBA')); alpha=np.rint((ar[:,:,3].astype(np.float32)//2)*255/127).astype(np.uint8)
            if mode=='alpha': mask=alpha>ALPHA
            else: mask=(np.max(np.abs(ar[:,:,:3].astype(np.int16)-np.array(r['background_rgb_median'])),axis=2)>DISTANCE)&(alpha>ALPHA)
            trim=expanded(bbox(mask,MINIMUM),w,h)
            excluded=int(np.sum(mask))-int(np.sum(mask[trim[1]:trim[3],trim[0]:trim[2]]))
            # Do not silently discard above-threshold pixels when minimum-run filtering isolates a tip/noise.
            # Current corpus has none after pad; future data falls back to the complete strict bbox.
            if excluded: trim=expanded(strict,w,h); mode+=':strict_bbox_fallback'; excluded=0
    else: trim=full
    # Glory of Team Rocket box's source frame is intentionally retained in the simulated bbox.
    old={label:geometry(w,h,r['v3']['kind'],content,full,label) for label in ['desktop','mobile']}
    new={label:geometry(w,h,kind,content,trim,label) for label in ['desktop','mobile']}
    gains={label:{axis:round(100*(new[label]['content_'+axis+'_pct']/old[label]['content_'+axis+'_pct']-1),3) for axis in ['width','height']} for label in ['desktop','mobile']}
    changed_class=kind!=r['v3']['kind']; changed_trim=trim!=full
    changed5=any(abs(x)>5 for g in gains.values() for x in g.values())
    p=r['source'].split('/')[1]
    category={'One Piece':'One-Piece','Pokemon':'Pokemon','Other':'more-tcg','Accsesuary':'acsesuary','3D print':'acsesuary'}[p]
    if i in [176,178,179,186,187,192,194,195,207,210,211,212,213]: category='acsesuary'
    result={'id':i,'source':r['source'],'category':category,'asset_subgroup':p,'source_dims':[w,h],'type':r['type'],'corner_rgba':corners,'v3_class':r['v3']['kind'],'v3_reason':r['v3']['class_reason'],'alpha_bbox_16':r['alpha_bbox']['16'],'background_bbox_12':basebg,'background_rgb':r['background_rgb_median'],'corner_span':r['corner_max_channel_span'],'page_white_corners':white,'transparent_corners':trans,'uniform':uniform,'cover_check':cover,'proposed_version':4,'proposed_class':kind,'proposed_reason':reason,'trim_method':mode,'crop_box_exclusive':trim,'trimmed_dims':dims(trim),'above_threshold_content_pixels_removed':excluded,'content_bbox_for_render':content,'before':old,'after':new,'relative_render_change_pct':gains,'class_changes':changed_class,'trim_changes':changed_trim,'render_change_above_5pct':changed5}
    results.append(result); hist[(kind,reason)]+=1
dump('proposed-assets.json',results)
dump('class-changes.json',[r for r in results if r['class_changes']])
dump('render-change-above-5pct.json',[r for r in results if r['render_change_above_5pct']])
dump('thresholds.json',{'alpha_content_gt':ALPHA,'minimum_content_pixels_per_row_or_column':MINIMUM,'bbox_safety_pad_px':PAD,'above_threshold_excluded_pixels_policy':'expand to strict bbox if any; source pixels are never rewritten','page_white_each_channel_min':WHITE,'uniform_max_corner_channel_span':UNIFORM,'background_distance_metric':'max channel absolute distance to channel-wise median of four corners','background_content_distance_gt':DISTANCE,'cover_safety_fraction_per_axis':SAFETY,'aspect_cover_band':[.70,1.45],'fit_dimensions':[700,875]})
summary={'assets':len(results),'before':dict(collections.Counter(r['v3_class'] for r in results)),'proposed':dict(collections.Counter(r['proposed_class'] for r in results)),'class_changes':sum(r['class_changes'] for r in results),'trim_changes':sum(r['trim_changes'] for r in results),'render_change_above_5pct':sum(r['render_change_above_5pct'] for r in results),'reasons':{'/'.join(k):v for k,v in hist.items()},'categories':{c:dict(collections.Counter(r['proposed_class'] for r in results if r['category']==c)) for c in ['One-Piece','Pokemon','more-tcg','acsesuary']}}
dump('proposal-summary.json',summary)

def boxarea(b): return (b[2]-b[0])*(b[3]-b[1]) if b else 0
trans_raw=[r for r in raw if all(c[3]<=16 for c in r['corner_rgba'])]
op=[r for r in raw if all(c[3]==255 for c in r['corner_rgba'])]
def frequency(values): return {str(k):v for k,v in sorted(collections.Counter(values).items())}
stats={'opaque_corner_min_rgb_histogram':frequency(min(min(c[:3]) for c in r['corner_rgba']) for r in op),'opaque_corner_uniformity_histogram':frequency(r['corner_max_channel_span'] for r in op),'transparent_asset_count':len(trans_raw),'alpha_pixel_bins':{label:sum(sum(r['alpha_histogram'][lo:hi+1]) for r in trans_raw) for label,lo,hi in [('0',0,0),('1-4',1,4),('5-8',5,8),('9-16',9,16),('17-32',17,32),('33-64',33,64),('65-254',65,254),('255',255,255)]},'alpha_sensitivity':{str(t):{'bbox_changes_vs_16':sum(r['alpha_bbox'][str(t)]!=r['alpha_bbox']['16'] for r in trans_raw),'area_change_above_5pct':sum(abs(boxarea(r['alpha_bbox'][str(t)])/boxarea(r['alpha_bbox']['16'])-1)>.05 for r in trans_raw if boxarea(r['alpha_bbox']['16']))} for t in [0,4,8,16,24,32,48,64]},'minimum_line_count_sensitivity':{str(n):{'bbox_changes_vs_1':sum(r['noise_sweep'][str(n)]['box']!=r['noise_sweep']['1']['box'] for r in trans_raw),'above_threshold_pixels_excluded_without_pad':sum(r['noise_sweep'][str(n)]['excluded_pixels'] for r in trans_raw)} for n in [1,2,3,4,8,16]},'uniform_opaque_background_distance_pixel_bins':{label:sum(sum(r['bg_distance_histogram'][lo:hi+1]) for r in op if r['corner_max_channel_span']<=UNIFORM) for label,lo,hi in [('0-2',0,2),('3-4',3,4),('5-8',5,8),('9-12',9,12),('13-16',13,16),('17-24',17,24),('25-32',25,32),('33-255',33,255)]},'album400yellow_tolerance_sweep':raw[97]['background_bbox'],'page_white_threshold_sweep':{str(t):sum(all(min(c[:3])>=t for c in r['corner_rgba']) and r['corner_max_channel_span']<=UNIFORM for r in op) for t in [235,240,245,250,252,255]}}
dump('threshold-distributions.json',stats)
with (E/'assets.csv').open('w',encoding='utf-8-sig',newline='') as f:
    writer=csv.writer(f); writer.writerow(['id','category','source','source_w','source_h','type','corner_rgba','v3_class','v3_reason','alpha_bbox_gt16','background_bbox_gt12','v4_class','v4_reason','crop_box_exclusive','trim_w','trim_h','before_desktop_content_w_pct','before_desktop_content_h_pct','after_desktop_content_w_pct','after_desktop_content_h_pct','before_mobile_content_w_pct','before_mobile_content_h_pct','after_mobile_content_w_pct','after_mobile_content_h_pct','class_changes','trim_changes','render_change_above_5pct'])
    for r in results:
        writer.writerow([r['id'],r['category'],r['source'],*r['source_dims'],r['type'],json.dumps(r['corner_rgba']),r['v3_class'],r['v3_reason'],json.dumps(r['alpha_bbox_16']),json.dumps(r['background_bbox_12']),r['proposed_class'],r['proposed_reason'],json.dumps(r['crop_box_exclusive']),*r['trimmed_dims'],r['before']['desktop']['content_width_pct'],r['before']['desktop']['content_height_pct'],r['after']['desktop']['content_width_pct'],r['after']['desktop']['content_height_pct'],r['before']['mobile']['content_width_pct'],r['before']['mobile']['content_height_pct'],r['after']['mobile']['content_width_pct'],r['after']['mobile']['content_height_pct'],r['class_changes'],r['trim_changes'],r['render_change_above_5pct']])
print(json.dumps(summary,indent=2))
print(json.dumps([{'id':r['id'],'source':r['source'],'before':r['v3_class'],'after':r['proposed_class'],'reason':r['proposed_reason']} for r in results if r['class_changes']],ensure_ascii=True,indent=2))
