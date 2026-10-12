from pathlib import Path
import json, re, html

w=Path(__file__).resolve().parent; repo=w.parent.parent
e=repo/'diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012'; source=w/'source'
fr=json.loads((e/'rendered-fragments.json').read_text(encoding='utf-8'))
me=json.loads((e/'measured-inventory.json').read_text(encoding='utf-8'))
inventory={str(p['product_id']):p for p in me['inventory']}
base='<link rel="stylesheet" href="/css/bootstrap.css"><link rel="stylesheet" href="/css/boostershop-ds.css"><link rel="stylesheet" href="/css/stylesheet.css"><link rel="stylesheet" href="/css/fixture-manrope.css">'
cart_source=(source/'catalog/view/template/checkout/cart.twig').read_text(encoding='utf-8')
cart_css='\n'.join(re.findall(r'<style[^>]*>(.*?)</style>',cart_source,re.S))
(e/'cart-source.css').write_text(cart_css,encoding='utf-8')
fixture_style='body{padding:24px 0;margin:0}h1.fixture-title{font-size:22px;line-height:1.35;margin:0 0 16px}.fixture-info{padding:16px;font-size:15px}.fixture-info h2{font-size:20px;line-height:1.4}.fixture-info p{max-width:42ch}.fixture-mini .bs-mini-cart__panel{position:relative;inset:auto;transform:none;width:380px;max-width:100%;height:1120px;max-height:none}.fixture-mini .bs-mini-cart__overlay{display:none}.fixture-mini .mini-cart-trigger{display:none}.fixture-mini .bs-mini-cart__body{overflow:visible}.fixture-mini .bs-mini-cart__panel{height:auto;min-height:600px}.fixture-mini{max-width:380px;margin:0 auto}.fixture-mini .bs-mini-cart__panel{border:1px solid var(--bs-line)}'
scripts='''<script src="/catalog/view/javascript/jquery/jquery-3.7.1.min.js"></script><script src="/catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js"></script><link rel="stylesheet" href="/catalog/view/javascript/jquery/magnific/magnific-popup.css">'''
# Gallery-only proof; quantity/remove, payment and other production JS is not
# executed. Lightbox uses the actual exported Magnific Popup implementation.
afterjs='''<script>
document.querySelectorAll('.prototype-gallery').forEach(function(g){
 var main=g.querySelector('[data-gallery-main]');var links=[...g.querySelectorAll('[data-gallery-thumb]')];
 links.forEach(function(a){a.addEventListener('click',function(ev){ev.preventDefault();main.setAttribute('href',a.getAttribute('href'));main.dataset.kind=a.dataset.kind;main.querySelector('img').src=a.dataset.main;links.forEach(function(x){x.removeAttribute('aria-current')});a.setAttribute('aria-current','true')})});
 main.addEventListener('click',function(ev){ev.preventDefault();var all=links.length?links:[main];var index=Math.max(0,all.findIndex(function(x){return x.href===main.href}));$.magnificPopup.open({items:all.map(function(x){return {src:x.getAttribute('href')}}),type:'image',gallery:{enabled:all.length>1}},index)});
});
</script>'''
beforejs="<script>$('.magnific-popup').magnificPopup({type:'image',delegate:'a',gallery:{enabled:true}});</script>"
def clean(s):return re.sub(r'<script\b.*?</script>','',s,flags=re.S|re.I)
def document(title,body,after,extra=''):
    return '<!doctype html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'+html.escape(title)+'</title>'+base+extra+'<style>'+fixture_style+'</style>'+('<link rel="stylesheet" href="/prototype.css">' if after else '')+'</head><body class="bs'+(' prototype' if after else '')+'">'+body+'</body></html>'

preservation=[]; manifest=[]
for id,p in fr['product'].items():
    for state in ['before','after']:
        name=inventory[id]['name'];title=f'Product #{id} — {state}'
        body='<main class="container"><h1 class="fixture-title">'+html.escape(title)+'</h1><div class="bs-product__layout">'+p[state]['html']+'<section class="bs-product__info fixture-info"><h2>'+html.escape(name)+'</h2><p>Phase 0 visual fixture. Product identity and image bytes are real; price and controls below are illustrative.</p><strong>100 ₴</strong><p><button class="bs-btn bs-btn-primary">Купити</button></p></section></div></main>'+scripts+(afterjs if state=='after' else beforejs)
        file=f'product-{id}-{state}.html';(e/file).write_text(document(title,body,state=='after'),encoding='utf-8');manifest.append({'page':file,'surface':'product','product_id':int(id),'state':state})
    before=[x['popup'] for x in p['before']['items']];after=[x['popup'] for x in p['after']['items']]
    assert before==after and all('700x700' in url for url in before)
    assert all(x['fit'] for x in p['after']['items'])
    preservation.append({'product_id':int(id),'popup_urls':before,'byte_identical':True,'after_fit_images':len(after)})

for state in ['before','after']:
 for template,b in fr['cart'][state].items():
    mini=template=='common/cart.twig';surface='mini-cart' if mini else 'cart'
    fragment=clean(b['html'])
    if state=='after':
        for id in fr['product']:
            kind=inventory[id]['images'][0]['kind']
            # Keep the direct row > a > img shape used by checkout-reskin.js.
            fragment=fragment.replace(('href="#product-'+id+'">'),('href="#product-'+id+'" class="rd25-thumb rd25-thumb--'+kind+'">')) if mini else fragment.replace(('class="bs-cart-row__thumb" href="#product-'+id+'"'),('class="bs-cart-row__thumb rd25-thumb rd25-thumb--'+kind+'" href="#product-'+id+'"'))
        assert all(x['fit'] for x in b['trace'])
    title=f'{surface} — {state}';body='<main'+('' if mini else ' id="checkout-cart"')+' class="container"><h1 class="fixture-title">'+title+'</h1><div id="'+('cart' if mini else 'shopping-cart')+'" class="'+('fixture-mini' if mini else 'fixture-cart')+'">'+fragment+'</div></main>'
    file=f'{surface}-{state}.html';(e/file).write_text(document(title,body,state=='after','<link rel="stylesheet" href="/cart-source.css">' if not mini else ''),encoding='utf-8');manifest.append({'page':file,'surface':surface,'state':state})
(e/'visual-manifest.json').write_text(json.dumps({'pages':manifest,'popup_preservation':preservation,'scope':'Real exported gallery/cart Twig fragments, native original/fit images; after is a diagnostic-only drawing, not a patch; mini-cart displayed as an isolated open panel; product right panel and prices are synthetic'},indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
print('fixture_pages='+str(len(manifest)))
