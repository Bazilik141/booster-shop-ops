<?php
declare(strict_types=1);
// Isolated synthetic test data; no production requests, sessions, credentials or customer data.
$root=__DIR__.'/fixture';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path !== '/' && $path !== '/index.php') {
    if (!empty($_GET['baseline']) && $path==='/catalog/view/stylesheet/boostershop-ds.css') {
        header('Content-Type:text/css'); readfile(__DIR__.'/source'.$path); return;
    }
    if (is_file($root.$path)) {
        $ext=pathinfo($path,PATHINFO_EXTENSION);
        $types=['js'=>'text/javascript','css'=>'text/css','woff2'=>'font/woff2'];
        header('Content-Type:'.($types[$ext]??'text/plain')); readfile($root.$path); return;
    }
    if (str_ends_with($path,'.svg') || str_ends_with($path,'.png')) {
        header('Content-Type:image/svg+xml'); echo '<svg xmlns="http://www.w3.org/2000/svg" width="270" height="84"><rect width="270" height="84" fill="#f0f2f8"/><text x="12" y="48" font-size="24">Fixture</text></svg>'; return;
    }
    header('Content-Type:'.(str_ends_with($path,'.css')?'text/css':'text/javascript')); return;
}
$src=$root.'/storage/vendor/twig/twig/src/';
spl_autoload_register(static function($c)use($src){if(strncmp($c,'Twig\\',5)!==0)return;$f=$src.str_replace('\\','/',substr($c,5)).'.php';if(is_file($f))require_once $f;});
foreach(['core.php','debug.php','escaper.php','string_loader.php']as$r)if(is_file($src.'Resources/'.$r))require_once $src.'Resources/'.$r;
$twig=new Twig\Environment(new Twig\Loader\FilesystemLoader([!empty($_GET['baseline'])?__DIR__.'/source':$root,$root]),['cache'=>false,'autoescape'=>false,'debug'=>true]);
$twig->addExtension(new Twig\Extension\DebugExtension());
function tpl($p,$data){global $twig;return $twig->render($p,$data);}
$base='http://'.$_SERVER['HTTP_HOST'].'/';
$route=$_GET['route']??'product/category';
$quantity=(int)($_GET['qty']??$_COOKIE['fixtureqty']??3);
if(in_array($route,['checkout/cart.add','checkout/cart.edit','checkout/cart.remove'],true)){
    $quantity=$route==='checkout/cart.remove'?0:($route==='checkout/cart.add'?$quantity+1:(int)($_POST['quantity']??1));
    setcookie('fixtureqty',(string)$quantity,0,'/');header('Content-Type:application/json');echo json_encode(['success'=>'Fixture cart updated','total'=>'Мій кошик - 999999.00₴']);return;
}
function cartHtml($quantity){global $base;return tpl('catalog/view/template/common/cart.twig',[
 'products'=>$quantity?[['quantity'=>$quantity,'cart_id'=>1,'name'=>'Long synthetic product name Pokémon Scarlet & Violet — Prismatic Evolutions','href'=>'#p1','thumb'=>'image/f.svg','total'=>'999999.00₴']]:[],
 'text_items'=>'Мій кошик - 999999.00₴','totals'=>[['code'=>'sub_total','text'=>'999999.00₴']],
 'sub_total'=>999999,'shipping_pinta_nova_poshta_free_from'=>2000,'checkout'=>'index.php?route=checkout/checkout','cart'=>'index.php?route=checkout/cart','remove'=>$base.'index.php?route=checkout/cart.remove','language'=>'uk-ua'
]);}
if($route==='common/cart.info'){header('Content-Type:text/html');echo cartHtml($quantity);return;}
if($route==='checkout/cart.list'){echo '<div>Fixture cart</div>';return;}
if($route==='checkout/checkout'){echo '<h1>Fixture checkout entry</h1>';return;}
$filter=isset($_GET['filter'])?explode(',',$_GET['filter']):[];
$total=48;
if(in_array('12',$filter,true))$total=24;
if(in_array('13',$filter,true))$total=30;
if(in_array('24',$filter,true))$total=6;
if(in_array('15',$filter,true)&&in_array('24',$filter,true))$total=0;
$page=max(1,(int)($_GET['page']??1));$size=8;
$urlParams=['route'=>'product/category','language'=>'uk-ua','path'=>59];
if(!empty($_GET['nofilter']))$urlParams['nofilter']=1;
if(!empty($_GET['nosubs']))$urlParams['nosubs']=1;
foreach(['filter','sort','order','limit']as$key)if(isset($_GET[$key]))$urlParams[$key]=$_GET[$key];
$action=$urlParams;unset($action['filter']);
$actionUrl=$base.'index.php?'.http_build_query($action);
$groups=[['filter_group_id'=>1,'name'=>'Тип товару','filter'=>[['filter_id'=>12,'name'=>'Бустер'],['filter_id'=>13,'name'=>'Бустер бокс'],['filter_id'=>15,'name'=>'Неймовірнодовгийнеперервнийтекстдляхарактеристикидовжиноюпонадстандартнийліміт — аксесуар']]],['filter_group_id'=>2,'name'=>'Країна','filter'=>[['filter_id'=>24,'name'=>'Японія'],['filter_id'=>22,'name'=>'США/Європа']]]];
$module=!empty($_GET['nofilter'])?'':tpl('extension/opencart/catalog/view/template/module/filter.twig',['heading_title'=>'Фільтр','button_filter'=>'Пошук','filter_groups'=>$groups,'filter_category'=>$filter,'action'=>$actionUrl]);
$sorts=[];foreach([['p.sort_order','ASC','За замовчуванням'],['p.price','ASC','Ціна (за зростанням)'],['p.price','DESC','Ціна (за спаданням)']]as$s){$q=$urlParams;$q['sort']=$s[0];$q['order']=$s[1];$sorts[]=['value'=>$s[0].'-'.$s[1],'text'=>$s[2],'href'=>$base.'index.php?'.http_build_query($q)];}
$cards=[];for($i=0;$i<min($size,max(0,$total-($page-1)*$size));$i++){
 $id=100+($page-1)*$size+$i;
 $cards[]=tpl('catalog/view/template/product/thumb.twig',['product_id'=>$id,'thumb'=>'image/f.svg','name'=>'Pokémon TCG: Scarlet & Violet — Prismatic Evolutions Elite Trainer Box (Англійське видання), довга назва товару','href'=>'#product'.$id,'price'=>'999999.00₴','special'=>'','minimum'=>1,'cart_add'=>$base.'index.php?route=checkout/cart.add','cart'=>'index.php?route=common/cart.info&language=uk-ua','button_cart'=>'Купити','bs_state'=>'','bs_eta'=>'']);
}
$clean=$base.'index.php?route=product/category&language=uk-ua&path=59';
$links=[['rel'=>'canonical','href'=>$clean]];
if($page*$size<$total){$q=$urlParams;$q['page']=$page+1;$links[]=['rel'=>'next','href'=>$base.'index.php?'.http_build_query($q)];}
if($page>1){$q=$urlParams;$q['page']=$page-1;$links[]=['rel'=>'prev','href'=>$base.'index.php?'.http_build_query($q)];}
$robots=count(array_intersect(['filter','sort','order','limit'],array_keys($_GET)))?'noindex,follow':'index,follow';
$header=tpl('catalog/view/template/common/header.twig',[
 'direction'=>'ltr','lang'=>'uk','title'=>'Pokémon','base'=>$base,'description'=>'Fixture category','keywords'=>'','robots'=>$robots,'route'=>'product/category','meta_url'=>$clean,'meta_image'=>'',
 'jquery'=>'catalog/view/javascript/jquery/jquery-3.7.1.min.js','bootstrap'=>'catalog/view/stylesheet/bootstrap.css','icons'=>'catalog/view/stylesheet/icons.css','stylesheet'=>'catalog/view/stylesheet/stylesheet.css','styles'=>[],'scripts'=>[],'links'=>$links,'analytics'=>[],'icon'=>'','logo'=>'image/logo.svg','name'=>'Booster Shop','home'=>$base,'logged'=>false,'account'=>'#account','login'=>'#login','order'=>'#order','text_account'=>'Акаунт','cart'=>cartHtml($quantity),'menu'=>''
]);
$header=preg_replace('~<script type="text/javascript">\s*\(function\(c,l,a,r,i,t,y\).*?</script>~s','',$header);
if(!empty($_GET['baseline']))$header=preg_replace('~(boostershop-ds\.css\?v=[^"\']+)~','$1&baseline=1',$header);
$subs=!empty($_GET['nosubs'])?[]:[['name'=>'Бустери','href'=>'#sub1','product_count'=>48,'active'=>true],['name'=>'Бустер бокси','href'=>'#sub2','product_count'=>13,'active'=>false]];
$data=['header'=>$header,'footer'=>'</main></body></html>','breadcrumbs'=>[['text'=>'Головна','href'=>$base],['text'=>'Pokémon','href'=>$clean]],'column_left'=>'','column_right'=>$module?'<aside id="column-right" class="col-3 d-none d-md-block">'.$module.'</aside>':'','content_top'=>'','content_bottom'=>'','heading_title'=>'Pokémon','category_code'=>'pokemon','category_heading_short'=>'','category_is_subcategory'=>false,'product_total'=>$total,'products_total_label'=>'товарів','sub_categories'=>$subs,'sorts'=>$sorts,'current_sort'=>($_GET['sort']??'p.sort_order').'-'.($_GET['order']??'ASC'),'text_sort'=>'Сортування','products'=>$cards,'categories'=>$subs,'pagination'=>'','results'=>'Показано з 1 по '.count($cards).' із '.$total.' (6 сторінок)','description'=>'','continue'=>'/','button_continue'=>'Продовжити','text_no_results'=>'Немає товарів','reset_url'=>$clean,'active_filters'=>[]];
header('Content-Type:text/html; charset=utf-8');echo tpl('catalog/view/template/product/category.twig',$data);
