from pathlib import Path
import json, subprocess, time
from prepare import fixture, w, e

php=r'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'
plan=json.loads((e/'plan.json').read_text())
results=[]
for scenario, ids in [('product',[i]) for i in plan['cold_product_ids']]+[('cart',plan['cold_cart_ids'])]:
    tag='product-'+str(ids[0]) if scenario=='product' else 'cart-20-largest'
    for warm_metadata in [True,False]:
        state='fit-cold' if warm_metadata else 'metadata-and-fit-cold'
        root=fixture('cost-full-'+tag+'-'+state,warm_metadata,ids)
        cmd=[php,'-d','memory_limit=1024M',str(w/'probe.php')]
        warmed=subprocess.run(cmd+['legacy-warm',str(root)],capture_output=True,text=True,encoding='utf-8')
        if warmed.returncode: raise RuntimeError(warmed.stderr)
        for request in [1,2,3,4]:
            p=subprocess.run(cmd+['cost',str(root),scenario]+([str(ids[0])] if scenario=='product' else []),capture_output=True,text=True,encoding='utf-8')
            if p.returncode: raise RuntimeError(p.stderr)
            r=json.loads(p.stdout)
            r.update(case=tag,initial_cache=state,request=request,measured_scope='Real model image operations and full exported product/cart page Twig with diagnostic gallery payload and synthetic non-image data, legacy cache warm; excludes live DB, full controller execution, header mini-cart, HTTP/network and browser rendering; Twig compiled without persistent cache')
            results.append(r)
            print(json.dumps({'case':tag,'state':state,'request':request,'seconds':r['wall_seconds'],'warnings':len(r['warnings']),'fit':sum(t.get('fit_images',int(t.get('fit',False))) for t in r['trace'])}),flush=True)
(e/'cold-cost.json').write_text(json.dumps({'php': '8.3.30', 'hosting_php_from_export':'8.0.30', 'plan':plan,'results':results},indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
