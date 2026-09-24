<?php
/** Local-only synthetic UI fixture; no OpenCart boot, credentials or bank calls. */
namespace Opencart\System\Engine { class Model {} }
namespace {
    if (PHP_SAPI !== 'cli-server' && PHP_SAPI !== 'cli') exit;
    $root=dirname(__DIR__,2);$base=$root.'/work/pay003';
    require $base.'/tooling/vendor/autoload.php';
    require $base.'/candidate/catalog/model/checkout/credit.php';
    $model=new \Opencart\Catalog\Model\Checkout\Credit();
    $templates=[];
    foreach(['credit','credit_confirm']as$name)$templates['checkout/'.$name]=file_get_contents($base.'/candidate/catalog/view/template/checkout/'.$name.'.twig');
    $templates['account/order_info']=file_get_contents($base.'/candidate/catalog/view/template/account/order_info.twig');
    $twig=new \Twig\Environment(new \Twig\Loader\ArrayLoader($templates),['autoescape'=>'html']);
    if(PHP_SAPI==='cli'){
        foreach(array_keys($templates)as$name)$twig->load($name);
        $confirm=$twig->render('checkout/credit_confirm',['root_id'=>'pay002-pumb-confirm','button_id'=>'pay002-pumb-confirm-button','provider_name'=>'ПУМБ','root_json'=>json_encode('pay002-pumb-confirm'),'confirm_json'=>json_encode('/synthetic-confirm'),'waiting_json'=>json_encode('/synthetic-wait')]);
        preg_match('~<script>(.*?)</script>~s',$confirm,$m);file_put_contents($base.'/confirmation-rendered.js',$m[1]);
        echo "twig_arrayloader_compile=ok templates=3\n";exit;
    }
    $url=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    $assets=['/catalog/view/javascript/pay003-credit.js'=>$base.'/candidate/catalog/view/javascript/pay003-credit.js', '/catalog/view/stylesheet/pay003-credit.css'=>$base.'/candidate/catalog/view/stylesheet/pay003-credit.css'];
    foreach(['bootstrap.css','boostershop-ds.css','stylesheet.css','booster-typography.css']as$f)$assets['/assets/'.$f]=$base.'/source/catalog/view/stylesheet/'.$f;
    if(isset($assets[$url])){header('Content-Type: '.(str_ends_with($url,'.js')?'text/javascript':'text/css'));readfile($assets[$url]);exit;}
    if($url==='/complete'){echo '<h1>Тест: перехід до підтвердженого замовлення</h1>';exit;}
    $case=$_GET['case']??'waiting';$provider=($_GET['provider']??'pumb_credit')==='mono_chast'?'mono_chast':'pumb_credit';
    $state=$case==='timeout'?'PUSH_TIMEOUT':($case==='confirmed'?'WAITING_STORE_CONFIRM':'WAITING_CLIENT');
    $view=$model->present($provider,$provider==='pumb_credit'?['state'=>$state]:['state'=>'IN_PROCESS','order_sub_state'=>'WAITING_FOR_CLIENT']);
    if($case==='long')$view['message'].=' '.str_repeat('Перевірте заявку в застосунку та дочекайтеся підтвердження банку. ',8);
    if($url==='/state'){
        header('Content-Type: application/json');
        if($case==='network'){http_response_code(503);echo '{}';exit;}
        if($case==='update')$view=$model->present('pumb_credit',['state'=>'REJECTED']);
        echo json_encode($view+['offline'=>false,'redirect'=>$view['confirmed']?'/complete':null],JSON_UNESCAPED_UNICODE);exit;
    }
    if($url!=='/'){http_response_code(404);exit;}
    $data=$view+['allowed'=>true,'order_id'=>1234567890,'provider'=>$provider==='pumb_credit'?'ПУМБ':'monobank','csrf'=>'synthetic-only','state_url'=>'/state?case='.rawurlencode($case),'complete_url'=>'/complete','test'=>$provider==='pumb_credit','account'=>'/?case=waiting','home'=>'/?case=waiting'];
    $data['header']=new \Twig\Markup('<!DOCTYPE html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PAY-003 · локальний тест</title><link rel="stylesheet" href="/assets/bootstrap.css"><link rel="stylesheet" href="/assets/boostershop-ds.css"><link rel="stylesheet" href="/assets/stylesheet.css"><link rel="stylesheet" href="/assets/booster-typography.css"><link rel="stylesheet" href="/catalog/view/stylesheet/pay003-credit.css"></head><body><header class="container py-3">Booster Shop · локальний макет без банку</header>','UTF-8');
    $data['footer']=new \Twig\Markup('<footer class="container">Тестові стани: <a href="/?case=waiting">очікування</a> · <a href="/?case=timeout">час минув</a> · <a href="/?case=long">довгий текст</a> · <a href="/?case=network">немає зв’язку</a> · <a href="/?case=update">оновлення</a> · <a href="/?case=confirmed">підтверджено</a></footer></body></html>','UTF-8');
    $data['header']=new \Twig\Markup(str_replace('<body>', '<body class="bs"><div id="container">', (string)$data['header']), 'UTF-8');
    $data['footer']=new \Twig\Markup(str_replace(['<footer class="container">','</body>'], ['<footer class="bs-footer">','</div></body>'], (string)$data['footer']), 'UTF-8');
    echo $twig->render('checkout/credit',$data);
}
