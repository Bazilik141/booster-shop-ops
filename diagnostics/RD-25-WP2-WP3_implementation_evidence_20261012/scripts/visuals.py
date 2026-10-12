from pathlib import Path
import json,re,html
w=Path(__file__).resolve().parent;repo=w.parent.parent
data=json.loads((w/'rendered.json').read_text(encoding='utf-8'));v=w/'visual';v.mkdir(exist_ok=True)
source=repo/'work/rd25-wp2-wp3-phase0-20261012/source'
base='<link rel="stylesheet" href="/css/bootstrap.css"><link rel="stylesheet" href="/css/boostershop-ds.css"><link rel="stylesheet" href="/css/stylesheet.css"><link rel="stylesheet" href="/css/fixture-manrope.css">'
scripts='<script src="/catalog/view/javascript/jquery/jquery-3.7.1.min.js"></script><script src="/catalog/view/javascript/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/catalog/view/javascript/common.js"></script><script src="/catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js"></script><link rel="stylesheet" href="/catalog/view/javascript/jquery/magnific/magnific-popup.css">'
style='body{margin:0;padding:24px 0}.fixture-title{font-size:20px;margin-bottom:16px}.fixture-info{padding:16px;font-size:15px}.fixture-info h2{font-size:20px;line-height:1.4}.fixture-mini .bs-mini-cart__panel{position:relative;inset:auto;transform:none;width:380px;max-width:100%;height:auto;max-height:none;min-height:600px;border:1px solid var(--bs-line)}.fixture-mini .bs-mini-cart__body{overflow:visible}.fixture-mini .bs-mini-cart__overlay,.fixture-mini .mini-cart-trigger{display:none}.fixture-mini{max-width:380px;margin:0 auto}'
def doc(title,body,extra=''):return '<!doctype html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+html.escape(title)+'</title>'+base+extra+'<style>'+style+'</style>'+scripts+'</head><body class="bs"><div id="alert"></div>'+body+'</body></html>'
manifest=[]
for id,states in data['products'].items():
    p=states['wp3'];title='Product #'+id+' — patched fixture'
    body='<main class="container"><h1 class="fixture-title">'+title+'</h1><div class="bs-product__layout">'+p['html']+'<section class="bs-product__info fixture-info"><h2>'+html.escape(p['name'])+'</h2><p>Local patched gallery; synthetic product information and price.</p><strong>100 ₴</strong><p><button class="bs-btn bs-btn-primary">Купити</button></p></section></div></main><script>'+p['js']+'</script>'
    file=f'product-{id}.html';(v/file).write_text(doc(title,body),encoding='utf-8');manifest.append({'page':file,'surface':'product','product_id':int(id)})
cartcode=(w/'after-wp3/catalog/view/template/checkout/cart.twig').read_text(encoding='utf-8')
cartcss='\n'.join(re.findall(r'<style[^>]*>(.*?)</style>',cartcode,re.S));(v/'cart.css').write_text(cartcss,encoding='utf-8')
cartjs=re.findall(r'<script[^>]*>(.*?)</script>',cartcode,re.S)[0].replace('{{ language }}','uk-ua')
mini=data['cart']['wp3']['common/cart.twig']['injected_html'];cart=data['cart']['wp3']['checkout/cart_list.twig']['injected_html']
for surface in ['mini-cart','cart','reload']:
    if surface=='mini-cart':body='<main class="container"><div id="cart" class="fixture-mini">'+mini+'</div><div id="shopping-cart" hidden></div></main>'
    elif surface=='cart':body='<main id="checkout-cart" class="container"><div id="shopping-cart">'+cart+'</div><div id="cart" hidden>'+mini+'</div></main><script>'+cartjs+'</script>'
    else:body='<main id="checkout-cart" class="container"><div id="cart" class="fixture-mini">'+mini+'</div><div id="shopping-cart">'+cart+'</div></main><script>'+cartjs+'</script>'
    (v/(surface+'.html')).write_text(doc(surface,body,'<link rel="stylesheet" href="/cart.css">'),encoding='utf-8')
    if surface!='reload':manifest.append({'page':surface+'.html','surface':surface})
for template,b in data['cart']['wp3'].items():
    name='mini-fragment.html' if template=='common/cart.twig' else 'cart-fragment.html'
    (v/name).write_text(b['injected_html'],encoding='utf-8')
(v/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
print('visual_pages='+str(len(manifest)))
