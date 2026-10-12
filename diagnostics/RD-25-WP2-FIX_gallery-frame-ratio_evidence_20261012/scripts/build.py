from pathlib import Path
import json,hashlib,re
w=Path(__file__).resolve().parent;repo=w.parent.parent;stage=repo/'work/rd25-wp2-wp3-phase1-20261012';sha=lambda b:hashlib.sha256(b).hexdigest()
H='catalog/view/template/common/header.twig';C='catalog/view/stylesheet/boostershop-ds.css';P='catalog/controller/product/product.php';T='catalog/view/template/product/product.twig';GA='extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php'
original={p.relative_to(w/'source').as_posix():p.read_bytes() for p in (w/'source').rglob('*') if p.is_file()};current=dict(original);targets={}
def norm(p,b):return re.sub(rb'(catalog/view/stylesheet/boostershop-ds\.css\?v=)[A-Za-z0-9._-]{1,64}(?=["\'])',rb'\g<1>__RD25_TOKEN__',b) if p==H else b
def replace(p,old,new):
 nl='\r\n' if b'\r\n' in current[p] else '\n';old=old.replace('\r\n','\n').replace('\n',nl).encode();new=new.replace('\r\n','\n').replace('\n',nl).encode()
 assert current[p].count(old)==1,(p,old[:60])
 spec=targets.setdefault(p,{'before_sha256':sha(norm(p,current[p])),'replacements':[]});spec['replacements'].append({'before':old.decode(),'after':new.decode()});current[p]=current[p].replace(old,new);spec['after_sha256']=sha(norm(p,current[p]))
replace(P,'int $legacy_width, int $legacy_height): array {','int $legacy_width, int $legacy_height, bool $additional = false): array {')
replace(P,"$item = ['popup' => $popup, 'main' => $legacy, 'small' => $legacy,\n\t\t\t\t\t'kind' => 'legacy', 'main_width' => $legacy_width, 'main_height' => $legacy_height,",'''// RD-25-WP2-FIX 20261012: N1 main fallback uses additional popup; small fallback remains legacy.
				$item = ['popup' => $popup, 'main' => $additional ? $popup : $legacy, 'small' => $legacy,
					'kind' => 'legacy',
					'main_width' => $additional ? (int)$this->config->get('config_image_popup_width') : $legacy_width,
					'main_height' => $additional ? (int)$this->config->get('config_image_popup_height') : $legacy_height,''')
replace(P,"$bs_legacy_image['thumb'], (int)$this->config->get('config_image_additional_width'), (int)$this->config->get('config_image_additional_height'));","$bs_legacy_image['thumb'], (int)$this->config->get('config_image_additional_width'), (int)$this->config->get('config_image_additional_height'), true);")
anchor="\t\t\tif ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {\n\t\t\t\t$data['price'] = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);"
replace(P,anchor,'''\t\t\t// Ratio belongs to the primary served image and is fixed for this page's gallery.
			$data['bs_gallery_ratio'] = 0.8;
			if ($data['bs_gallery']) {
				$bs_primary = $data['bs_gallery'][0];
				$data['bs_gallery_ratio'] = max(0.8, min(1.0, $bs_primary['main_width'] / max(1, $bs_primary['main_height'])));
			}

'''+anchor)
replace(T,'data-bs-gallery>','data-bs-gallery style="--bs-gallery-ratio:{{ bs_gallery_ratio }}">')
replace(T,'{# RD-25-WP2 20261012: gallery-only data;', '{# RD-25-WP2-FIX 20261012: primary ratio stays fixed when selecting thumbs. #}\n        {# RD-25-WP2 20261012: gallery-only data;')
replace(C,'.bs-product__gallery .bs-product__main-img { width: 100%; max-width: 480px; aspect-ratio: 4 / 5; }','/* RD-25-WP2-FIX 20261012: primary served ratio clamped to 0.8..1; photo centred, cut bottom. */\n.bs-product__gallery .bs-product__main-img { width: 100%; max-width: 480px; aspect-ratio: var(--bs-gallery-ratio, 4 / 5); }')
replace(C,'/* Approved 4:5 geometry: main max 480px, desktop rail 64x80 / 10px gap;','/* Primary-based main frame, max 480px; desktop rail 64x80 / 10px gap;')
replace(C,'.bs-product__thumbs > a[data-bs-media-kind="cut"] > img { grid-column: 2; grid-row: 2; }','.bs-product__thumbs > a[data-bs-media-kind="cut"] > img { grid-column: 2; grid-row: 2; object-position: center bottom; }')
replace(C,'.bs-product__thumbs img { display: block; width: 100%; height: 100%; min-width: 0; min-height: 0; object-fit: contain; object-position: center bottom; }','.bs-product__thumbs img { display: block; width: 100%; height: 100%; min-width: 0; min-height: 0; object-fit: contain; object-position: center center; }')
b=norm(H,current[H]);targets[H]={'before_sha256':sha(b),'after_sha256':sha(b),'replacements':[{'before':'catalog/view/stylesheet/boostershop-ds.css?v=__RD25_TOKEN__','after':'catalog/view/stylesheet/boostershop-ds.css?v=__RD25_TOKEN__'}]};current[H]=b.replace(b'__RD25_TOKEN__',b'rd25-wp2-fix-20261012')
for p,b in current.items():f=w/'after'/p;f.parent.mkdir(parents=True,exist_ok=True);f.write_bytes(b)
oldspec=json.loads((stage/'spec-wp2.json').read_text());context={p:sha((repo/'work/rd25-wp2-wp3-phase0-20261012/source'/p).read_bytes()) for p in ['catalog/model/tool/image.php','system/library/image.php','catalog/controller/product/thumb.php','catalog/view/template/product/thumb.twig']};context[GA]=sha(original[GA])
anchors={}
for version in ['4.0.2.3','4.1.0.3']:
 for a in json.loads((stage/f'ga4-{version}.json').read_text())['anchors']:anchors[(a['template'],a['search'])]={'template':a['template'],'search':a['search'],'index':a['index'],'count':a['before']}
spec={'targets':targets,'context':context,'ga4_anchors':list(anchors.values()),'repeat_hashes':{p:[v['after_sha256']] for p,v in targets.items() if p!=H}}
template=(stage/'runner.php').read_text().replace('Approved Phase 0 20261012; review C1-C3 and owner go WP2-WP3.','Owner go WP2-FIX; gallery-frame-ratio handoff 20261012.').replace('Source: RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz + fresh GA4 model.','Source: RD-25-WP2-FIX-check-20261012-182229-3901452.tar.gz; all 5 hashes matched.').replace('Root causes: gallery square/grey and RD-10 repeated height caps; cart cover squares.','Root cause: WP2 fixed 4:5 main frame and bottom-aligned photographs.').replace('Edit original gallery / RD-12 mini-cart / RD-11 cart CSS, no override stack.','Edit original gallery rules only. WP3/listing CSS and cart/checkout files unchanged.').replace('Rollback order WP3, then WP2. Each receipt refuses changed targets or mixed states.','Rollback this fix alone returns WP2+WP3. Receipt refuses changed targets/mixed states.').replace('cache after both applies or rollback','cache after apply or rollback').replace('gallery at 1440/1024/390/360; bs-checkout-smoke steps 1-4, no order submit.','gallery at 1440/1024/390/360; thumb switch, lightbox, product add-to-cart once.')
task='RD-25-WP2-FIX_gallery-frame-ratio_20261012'
out=template.replace('@TASK@',task).replace('@FILENAME@',task+'.php').replace('@MARKER@','RD-25-WP2-FIX 20261012').replace('@TOKEN@','rd25-wp2-fix-20261012').replace('@DESCRIPTION@','primary-image frame ratio, centred photos and additional popup fallback.').replace('@SPEC@',json.dumps(spec,ensure_ascii=False,indent=2))
(repo/'patches'/f'{task}.php').write_text(out,encoding='utf-8',newline='\n');(w/'spec.json').write_text(json.dumps(spec,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'targets':4,'patch_sha256':sha(out.encode())}))
