from pathlib import Path
import re, json, hashlib

w=Path(__file__).resolve().parent; repo=w.parent.parent
source=repo/'work/rd25-wp2-wp3-phase0-20261012/source'
sha=lambda b:hashlib.sha256(b).hexdigest()
HEADER='catalog/view/template/common/header.twig'; CSS='catalog/view/stylesheet/boostershop-ds.css'
prefix='catalog/view/stylesheet/boostershop-ds.css?v='
def normalize(p,b):
    if p!=HEADER:return b
    pattern=rb'('+re.escape(prefix.encode())+rb')([A-Za-z0-9._-]{1,64})(?=["\'])'
    assert len(re.findall(pattern,b))==1
    return re.sub(pattern,rb'\g<1>__RD25_TOKEN__',b)

original={p.relative_to(source).as_posix():p.read_bytes() for p in source.rglob('*') if p.is_file() and p.suffix in ['.php','.twig','.css','.js']}
current=dict(original); specs={2:{},3:{}}
def change(stage,p,before,after):
    b=current[p]; nl='\r\n' if b'\r\n' in b else '\n'
    def encode(s):return s.replace('\r\n','\n').replace('\n',nl).encode()
    old,new=encode(before),encode(after)
    assert b.count(old)==1,(p,b.count(old),before[:70])
    target=specs[stage].setdefault(p,{'before_sha256':sha(normalize(p,b)),'replacements':[]})
    current[p]=b.replace(old,new)
    target['replacements'].append({'before':old.decode(),'after':new.decode()})
    target['after_sha256']=sha(normalize(p,current[p]))

product='catalog/controller/product/product.php'
anchor="\t\t\t$this->load->model('tool/image');"
change(2,product,anchor,anchor+'''
			// RD-25-WP2 20261012: view-only fit payload; legacy popup/thumb/images stay intact.
			$data['bs_gallery'] = [];
			$bs_gallery_item = function (string $filename, string $popup, string $legacy, int $legacy_width, int $legacy_height): array {
				$item = ['popup' => $popup, 'main' => $legacy, 'small' => $legacy,
					'kind' => 'legacy', 'main_width' => $legacy_width, 'main_height' => $legacy_height,
					'small_width' => $legacy_width, 'small_height' => $legacy_height];
				$main = $this->model_tool_image->resizeFit($filename, 600, 750);
				$small = $this->model_tool_image->resizeFit($filename, 128, 160);
				$media = $this->model_tool_image->mediaInfo($filename);
				if ($media && ($main !== '' || $small !== '')) {
					$item['kind'] = $media['kind'];
					foreach (['main' => [600, 750, $main], 'small' => [128, 160, $small]] as $key => $fit) {
						if ($fit[2] === '') { continue; }
						$scale = min(1.0, $fit[0] / $media['crop_width'], $fit[1] / $media['crop_height']);
						$item[$key] = $fit[2];
						$item[$key . '_width'] = max(1, (int)floor($media['crop_width'] * $scale));
						$item[$key . '_height'] = max(1, (int)floor($media['crop_height'] * $scale));
					}
				}
				return $item;
			};''')
change(2,product,"\t\t\t$data['images'] = [];",'''\t\t\tif ($data['thumb'] !== '') {
				$data['bs_gallery'][] = $bs_gallery_item($product_info['image'], $data['popup'], $data['thumb'], (int)$this->config->get('config_image_thumb_width'), (int)$this->config->get('config_image_thumb_height'));
			}

			$data['images'] = [];''')
anchor="\t\t\t\t\t\t'thumb' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_additional_width'), $this->config->get('config_image_additional_height'))\n\t\t\t\t\t];"
change(2,product,anchor,anchor+'''
					$bs_legacy_image = $data['images'][count($data['images']) - 1];
					$data['bs_gallery'][] = $bs_gallery_item($result['image'], $bs_legacy_image['popup'], $bs_legacy_image['thumb'], (int)$this->config->get('config_image_additional_width'), (int)$this->config->get('config_image_additional_height'));''')

twig='catalog/view/template/product/product.twig'
text=current[twig].decode();old=re.search(r'        <div class="bs-product__gallery magnific-popup">.*?        </div>\r?\n        {% endif %}',text,re.S).group()
change(2,twig,old,'''        {# RD-25-WP2 20261012: gallery-only data; legacy images remains additional-only for JSON-LD. #}
        {% if bs_gallery %}
        {% set bs_main = bs_gallery|first %}
        <div class="bs-product__gallery magnific-popup{% if bs_gallery|length > 1 %} bs-product__gallery--rail{% endif %}" data-bs-gallery>
          <div class="bs-product__main-img">
            <a href="{{ bs_main.popup }}" title="{{ heading_title }}" data-bs-gallery-main data-bs-media-kind="{{ bs_main.kind }}">
              <img src="{{ bs_main.main }}" title="{{ heading_title }}" alt="{{ heading_title }}" width="{{ bs_main.main_width }}" height="{{ bs_main.main_height }}" decoding="async"/>
            </a>
          </div>
          {% if bs_gallery|length > 1 %}
          <div class="bs-product__thumbs" aria-label="Фотографії товару">
            {% for image in bs_gallery %}
              <a href="{{ image.popup }}" title="{{ heading_title }}" data-bs-gallery-thumb data-bs-media-kind="{{ image.kind }}" data-bs-gallery-src="{{ image.main|escape('html_attr') }}" data-bs-gallery-width="{{ image.main_width }}" data-bs-gallery-height="{{ image.main_height }}" aria-label="Фото {{ loop.index }}: {{ heading_title|escape('html_attr') }}"{% if loop.first %} aria-current="true"{% endif %}>
                <img src="{{ image.small }}" title="{{ heading_title }}" alt="{{ heading_title }}" width="{{ image.small_width }}" height="{{ image.small_height }}" loading="lazy" decoding="async"/>
              </a>
            {% endfor %}
          </div>
          {% endif %}
        </div>
        {% endif %}
        {% endif %}''')
old="""    $('.magnific-popup').magnificPopup({
        type: 'image',
        delegate: 'a',
        gallery: {
            enabled: true
        }
    });"""
new="""    // RD-25-WP2 gallery switcher; literal attributes preserve the existing 700x700 URLs.
    $('[data-bs-gallery]').each(function() {
        var gallery = this;
        var main = gallery.querySelector('[data-bs-gallery-main]');
        var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('[data-bs-gallery-thumb]'));
        var selected = 0;
        if (!main) { return; }
        $(gallery).on('click.rd25Gallery', '[data-bs-gallery-thumb]', function(event) {
            event.preventDefault();
            selected = thumbs.indexOf(this);
            main.setAttribute('href', this.getAttribute('href'));
            main.setAttribute('data-bs-media-kind', this.getAttribute('data-bs-media-kind'));
            var image = main.querySelector('img');
            image.setAttribute('src', this.getAttribute('data-bs-gallery-src'));
            image.setAttribute('width', this.getAttribute('data-bs-gallery-width'));
            image.setAttribute('height', this.getAttribute('data-bs-gallery-height'));
            thumbs.forEach(function(thumb) { thumb.removeAttribute('aria-current'); });
            this.setAttribute('aria-current', 'true');
        });
        $(main).on('click.rd25Gallery', function(event) {
            event.preventDefault();
            var links = thumbs.length ? thumbs : [main];
            $.magnificPopup.open({
                items: links.map(function(link) { return { src: link.getAttribute('href'), title: link.getAttribute('title') }; }),
                type: 'image',
                gallery: { enabled: links.length > 1 }
            }, selected);
        });
    });"""
change(2,twig,old,new)

css=current[CSS].decode();old=css[css.index('/* --- Gallery --- */'):css.index('/* --- Info panel --- */')].rstrip()
change(2,CSS,old,'''/* --- Gallery: RD-25-WP2 20261012 --- */
/* Approved 4:5 geometry: main max 480px, desktop rail 64x80 / 10px gap;
   mobile rail 56x70. Cut inset is left 6%, top 4%, width 88%, height 96%.
   Grid tracks express percentages of the slot without absolute positioning. */
.bs-product__gallery { min-width: 0; align-self: start; }
.bs-product__gallery .bs-product__main-img { width: 100%; max-width: 480px; aspect-ratio: 4 / 5; }
.bs-product__main-img > a,
.bs-product__thumbs > a { display: grid; grid-template-columns: 1fr; grid-template-rows: 1fr; }
.bs-product__main-img > a { width: 100%; height: 100%; }
.bs-product__main-img > a[data-bs-media-kind="cut"],
.bs-product__thumbs > a[data-bs-media-kind="cut"] { grid-template-columns: 6% 88% 6%; grid-template-rows: 4% 96%; }
.bs-product__main-img > a[data-bs-media-kind="cut"] > img,
.bs-product__thumbs > a[data-bs-media-kind="cut"] > img { grid-column: 2; grid-row: 2; }
.bs-product__gallery .bs-product__main-img img,
.bs-product__thumbs img { display: block; width: 100%; height: 100%; min-width: 0; min-height: 0; object-fit: contain; object-position: center bottom; }
.bs-product__main-img > a[data-bs-media-kind="photo"] > img { border-radius: var(--bs-r); }
.bs-product__thumbs { display: flex; gap: 10px; flex-wrap: nowrap; min-width: 0; margin-top: 10px; overflow-x: auto; padding: 3px; }
.bs-product__thumbs > a { box-sizing: border-box; flex: 0 0 56px; width: 56px; height: 70px; border: 1px solid var(--bs-line); border-radius: var(--bs-r-sm); overflow: hidden; }
.bs-product__thumbs > a:hover { border-color: var(--bs-gold); }
.bs-product__thumbs > a[aria-current="true"] { border: 2px solid var(--bs-blue); }
.bs-product__thumbs > a:focus-visible,
.bs-product__main-img > a:focus-visible { outline: 2px solid var(--bs-blue); outline-offset: 2px; }
@media (min-width: 992px) {
  .bs-product__gallery--rail { display: grid; grid-template-columns: 70px minmax(0, 480px); gap: 10px; }
  .bs-product__gallery--rail .bs-product__main-img { grid-column: 2; grid-row: 1; }
  /* Zero intrinsic height and percentage min-height bind the rail to the main row. */
  .bs-product__gallery--rail .bs-product__thumbs { grid-column: 1; grid-row: 1; flex-direction: column; height: 0; min-height: 100%; overflow-y: auto; overflow-x: hidden; margin-top: 0; }
  .bs-product__thumbs > a { flex: 0 0 80px; width: 64px; height: 80px; }
}
@media (max-width: 991.98px) {
  .bs-product__gallery .bs-product__main-img { max-width: none; }
}''')
old=css[css.index('/* -- Product page gallery: max-height constraint'):css.index('/* -- Product page meta panel')].rstrip()
change(2,CSS,old,'/* RD-25-WP2: obsolete RD-10 gallery max-height layer retired at its source. */')
change(2,CSS,'  .bs-product__gallery .bs-product__main-img { max-height: 340px; }\n  .bs-product__gallery .bs-product__main-img img { max-height: 340px; }','  /* RD-25-WP2: 4:5 gallery replaces the obsolete mobile height cap. */')
change(2,CSS,'''  .bs-product__gallery .bs-product__main-img {
    max-height: 340px;
  }
  .bs-product__gallery .bs-product__main-img img {
    max-height: 340px;
    aspect-ratio: auto;
  }''','  /* RD-25-WP2: repeated RD-10D2 mobile gallery cap retired. */')

def token(stage):
    b=normalize(HEADER,current[HEADER]);specs[stage][HEADER]={'before_sha256':sha(b),'after_sha256':sha(b),'replacements':[{'before':prefix+'__RD25_TOKEN__','after':prefix+'__RD25_TOKEN__'}]}
    current[HEADER]=b.replace(b'__RD25_TOKEN__',f'rd25-wp{stage}-20261012'.encode())
token(2)
for p,b in current.items():
    if b!=original[p]: f=w/'after-wp2'/p;f.parent.mkdir(parents=True,exist_ok=True);f.write_bytes(b)

for p in ['catalog/controller/common/cart.php','catalog/controller/checkout/cart.php']:
    anchor="\t\t\t$data['products'][] = [\n\t\t\t\t'thumb'        => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),"
    change(3,p,anchor,'''\t\t\t// RD-25-WP3 20261012: use the existing row source; LOCK_NB/budget refusal keeps legacy thumb.
			$bs_small = ['bs_fit_thumb' => '', 'bs_media_kind' => 'legacy', 'bs_media_width' => 120, 'bs_media_height' => 120];
			$bs_fit = $this->model_tool_image->resizeFit($product['image'], 104, 130);
			if ($bs_fit !== '' && ($bs_media = $this->model_tool_image->mediaInfo($product['image']))) {
				$bs_scale = min(1.0, 104 / $bs_media['crop_width'], 130 / $bs_media['crop_height']);
				$bs_small = ['bs_fit_thumb' => $bs_fit, 'bs_media_kind' => $bs_media['kind'],
					'bs_media_width' => max(1, (int)floor($bs_media['crop_width'] * $bs_scale)),
					'bs_media_height' => max(1, (int)floor($bs_media['crop_height'] * $bs_scale))];
			}

'''+anchor)
    # Only the image payload is added; original row fields and legacy resize expression are preserved.
    change(3,p,"\t\t\t] + $product;","\t\t\t] + $bs_small + $product;")

p='catalog/view/template/common/cart.twig'
change(3,p,'{# RD-12 mini-cart drawer; AJAX URLs and controller values are unchanged. #}','{# RD-25-WP3 20261012: fit thumbnails; direct row > a > img retained for checkout DOM copy. #}\n{# RD-12 mini-cart drawer; AJAX URLs and controller values are unchanged. #}')
change(3,p,'<article class="bs-mini-cart__row">','<article class="bs-mini-cart__row" data-bs-media-kind="{{ product.bs_media_kind }}">')
change(3,p,'<img src="{{ product.thumb }}" alt="{{ product.name }}"/>','<img src="{{ product.bs_fit_thumb ?: product.thumb }}" alt="{{ product.name }}" width="{{ product.bs_media_width }}" height="{{ product.bs_media_height }}" loading="lazy" decoding="async"/>')
p='catalog/view/template/checkout/cart_list.twig'
change(3,p,'{# RD-11 cart list: controller-supplied values only. #}','{# RD-25-WP3 20261012: image display only; quantity and remove controls unchanged. #}\n  {# RD-11 cart list: controller-supplied values only. #}')
change(3,p,'<a class="bs-cart-row__thumb" href="{{ product.href }}">','<a class="bs-cart-row__thumb" href="{{ product.href }}" data-bs-media-kind="{{ product.bs_media_kind }}">')
change(3,p,'<img src="{{ product.thumb }}" alt="{{ product.name }}" title="{{ product.name }}"/>','<img src="{{ product.bs_fit_thumb ?: product.thumb }}" alt="{{ product.name }}" title="{{ product.name }}" width="{{ product.bs_media_width }}" height="{{ product.bs_media_height }}" loading="lazy" decoding="async"/>')

change(3,CSS,'.bs-mini-cart__row{display:grid;grid-template-columns:56px minmax(0,1fr);gap:12px;padding:14px 0;border-bottom:1px solid var(--bs-line-2)}.bs-mini-cart__row img{width:56px;height:56px;object-fit:cover;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}','''/* RD-25-WP3 20261012: 52x65 slots; cut inset left8/top6/width84/height94%; photos full-slot. */
.bs-mini-cart__row{display:grid;grid-template-columns:52px minmax(0,1fr);gap:12px;padding:14px 0;border-bottom:1px solid var(--bs-line-2)}
.bs-mini-cart__row > a{display:grid;box-sizing:border-box;width:52px;height:65px;grid-template-columns:1fr;grid-template-rows:1fr}
.bs-mini-cart__row[data-bs-media-kind="cut"] > a{grid-template-columns:8% 84% 8%;grid-template-rows:6% 94%}
.bs-mini-cart__row[data-bs-media-kind="cut"] > a > img{grid-column:2;grid-row:2}
.bs-mini-cart__row img{display:block;width:100%;height:100%;min-width:0;min-height:0;object-fit:contain;object-position:center bottom;border-radius:var(--bs-r-sm)}''')
p='catalog/view/template/checkout/cart.twig'
change(3,p,'/* RD-11 cart page */','/* RD-25-WP3 20261012: 52x65 image slots; cut inset left8/top6/width84/height94%; photos full-slot. */\n/* RD-11 cart page */')
change(3,p,'.bs-cart-row{display:grid;grid-template-columns:96px minmax(0,1fr);gap:16px;padding:18px 0;border-bottom:1px solid var(--bs-line-2)}.bs-cart-row__thumb img{display:block;width:96px;height:96px;object-fit:cover;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}','''.bs-cart-row{display:grid;grid-template-columns:52px minmax(0,1fr);gap:16px;padding:18px 0;border-bottom:1px solid var(--bs-line-2)}.bs-cart-row__thumb{display:grid;box-sizing:border-box;width:52px;height:65px;grid-template-columns:1fr;grid-template-rows:1fr}.bs-cart-row__thumb[data-bs-media-kind="cut"]{grid-template-columns:8% 84% 8%;grid-template-rows:6% 94%}.bs-cart-row__thumb[data-bs-media-kind="cut"] img{grid-column:2;grid-row:2}.bs-cart-row__thumb img{display:block;width:100%;height:100%;min-width:0;min-height:0;object-fit:contain;object-position:center bottom;border-radius:var(--bs-r-sm)}''')
change(3,p,'.bs-cart-row{grid-template-columns:72px minmax(0,1fr);gap:12px;padding:14px 0}.bs-cart-row__thumb img{width:72px;height:72px}','.bs-cart-row{grid-template-columns:52px minmax(0,1fr);gap:12px;padding:14px 0}')
token(3)
for p,b in current.items():
    if b!=original[p]: f=w/'after-wp3'/p;f.parent.mkdir(parents=True,exist_ok=True);f.write_bytes(b)
for stage in [2,3]: (w/f'spec-wp{stage}.json').write_text(json.dumps(specs[stage],ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps({'wp2_targets':len(specs[2]),'wp3_targets':len(specs[3])}))
