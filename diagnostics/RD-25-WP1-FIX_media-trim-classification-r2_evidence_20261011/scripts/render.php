<?php
$w=str_replace('\\','/',__DIR__); $r=dirname($w,2);
$e=$r.'/diagnostics/RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011';
require $r.'/work/pay003/tooling/vendor/autoload.php';
$twig=new \Twig\Environment(new \Twig\Loader\FilesystemLoader([$r.'/work/rd25-wp1-fix-phase0-20261011/source/catalog/view/template',$r.'/work/rd25-wp1-20261009/expected/catalog/view/template',$r.'/work/rd25-wp1-20261009/expected/extension/opencart/catalog/view/template']),['autoescape'=>false,'cache'=>false]);
$rows=json_decode(file_get_contents($e.'/gd-assets.json'),true); $cards=[]; $checks=[];
$selected=[62,87,84,97,103,104,105,106,107,108,118,119,122,123,131,135,143,178,179,186,194,213,222,224,228,233,235,260,261,275,280,282,283];
foreach($rows as $a){
 $m=$a['metadata']; $data=['product_id'=>100+$a['id'],'name'=>'#'.str_pad($a['id'],3,'0',STR_PAD_LEFT).' '.basename($a['source']).' — довга назва для перевірки картки','href'=>'#fixture','thumb'=>'','bs_fit_thumb'=>'/image/'.$a['fit_relative'],'bs_media_kind'=>$m['kind'],'bs_media_width'=>$a['served_dimensions'][0],'bs_media_height'=>$a['served_dimensions'][1],'price'=>'2 199 ₴','minimum'=>1,'button_cart'=>'До кошика','cart'=>'/cart','cart_add'=>'/cart.add','bs_state'=>$a['id']===131?'preorder':'','bs_eta'=>'Орієнтовно: грудень 2026','bs_is_rare'=>$a['id']===131];
 $html=$twig->render('product/thumb.twig',$data); preg_match('~<img[^>]* width="([0-9]+)" height="([0-9]+)"~',$html,$match);
 if(!$match || [(int)$match[1],(int)$match[2]]!==$a['served_dimensions'])throw new RuntimeException('Twig dimensions '.$a['id']);
 $checks[]=['id'=>$a['id'],'kind'=>$m['kind'],'served'=>$a['served_dimensions'],'twig_width'=>(int)$match[1],'twig_height'=>(int)$match[2]];
 if(in_array($a['id'],$selected,true))$cards[]=$html;
}
$head='<!doctype html><html lang="uk"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
foreach(['fixture-manrope.css','bootstrap.css','boostershop-ds.css','stylesheet.css','booster-typography.css'] as $css)$head.='<link rel="stylesheet" href="/css/'.$css.'">';
$head.='<body class="bs"><main><div class="container"><div class="row"><div id="content" class="col">';
$tail='</div></div></div></main></body></html>';
$compiled=[];
foreach(['category','search','special','manufacturer_info'] as $page){
 $tpl='product/'.$page.'.twig'; $twig->load($tpl); $compiled[]=$tpl;
 $code=file_get_contents($r.'/work/rd25-wp1-20261009/expected/catalog/view/template/'.$tpl);
 preg_match('~(<div id="product-list"[^>]*>\s*{% for product in products %}.*?{% endfor %}\s*</div>)~s',$code,$match);
 $grid=$twig->createTemplate($match[1])->render(['products'=>$cards]); file_put_contents($e.'/'.$page.'.html',$head.$grid.$tail);
}
foreach(['horizontal','vertical'] as $axis){$grid=$twig->render('module/featured.twig',['axis'=>$axis,'products'=>$cards,'heading_title'=>'Fixture']);file_put_contents($e.'/featured-'.$axis.'.html',$head.$grid.$tail);}
file_put_contents($e.'/twig-dimensions.json',json_encode(['twig_version'=>\Twig\Environment::VERSION,'assets'=>count($checks),'checks'=>$checks,'compiled_grids'=>$compiled],JSON_PRETTY_PRINT));
echo 'Twig dimensions verified='.count($checks)."\n";
