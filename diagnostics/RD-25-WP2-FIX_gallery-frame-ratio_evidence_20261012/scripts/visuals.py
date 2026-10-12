from pathlib import Path
import json,html
w=Path(__file__).resolve().parent;v=w/'visual';v.mkdir(exist_ok=True);d=json.loads((w/'rendered.json').read_text(encoding='utf-8'));pages=[]
scripts='<script src="/catalog/view/javascript/jquery/jquery-3.7.1.min.js"></script><script src="/catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js"></script><link rel="stylesheet" href="/catalog/view/javascript/jquery/magnific/magnific-popup.css">'
for id,states in d['products'].items():
 for state,p in states.items():
  title=f'#{id} — {state}';body='<main class="container"><h1 class="fixture-title">'+title+'</h1><div class="bs-product__layout">'+p['html']+'<section class="bs-product__info fixture-info"><h2>'+html.escape(p['name'])+'</h2><p>Actual gallery; synthetic price/right panel.</p><strong>100 ₴</strong><p><button class="bs-btn bs-btn-primary">Купити</button></p></section></div></main><script>'+p['js']+'</script>'
  doc='<!doctype html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+title+'</title><link rel="stylesheet" href="/css/bootstrap.css"><link rel="stylesheet" href="/css/'+state+'/boostershop-ds.css"><link rel="stylesheet" href="/css/stylesheet.css"><link rel="stylesheet" href="/css/fixture-manrope.css"><style>body{margin:0;padding:24px 0}.fixture-title{font-size:20px;margin-bottom:16px}.fixture-info{padding:16px;font-size:15px}.fixture-info h2{font-size:20px;line-height:1.4}</style>'+scripts+'</head><body class="bs">'+body+'</body></html>'
  name=f'product-{id}-{state}.html';(v/name).write_text(doc,encoding='utf-8');pages.append({'page':name,'product_id':int(id),'state':state,'ratio':p['payload'].get('bs_gallery_ratio',.8),'images':len(p['payload']['bs_gallery'])})
(v/'manifest.json').write_text(json.dumps(pages,indent=2)+'\n');print('pages='+str(len(pages)))
