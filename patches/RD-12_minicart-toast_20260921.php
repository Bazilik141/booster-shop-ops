<?php
declare(strict_types=1);
/* RD-12: mini-cart drawer and add-to-cart toast. No DB, checkout/payment or price calculation changes. */
$id=pathinfo(__FILE__,PATHINFO_FILENAME);$root=getcwd();
function fail12(string $m):void{fwrite(STDERR,"error=$m\n");exit(1);}function one12(string $s,string $o,string $n,string $name):string{$c=substr_count($s,$o);if($c!==1)fail12("anchor_count name=$name expected=1 actual=$c");return str_replace($o,$n,$s);}function lint12(string $f):void{exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($f).' 2>&1',$o,$c);if($c)fail12('php_l_failed '.implode(' | ',$o));}function targetLint12(string $f):void{exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($f).' 2>&1',$o,$c);if($c)throw new RuntimeException('php_l_failed file='.$f.' output='.implode(' | ',$o));}
function twigStorage12(string $root):string{$config=file_get_contents($root.'/config.php');if(!is_string($config)||!preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/",$config,$m))fail12('dir_storage_missing_in_config');return rtrim($m[1],'/\\');}
function twigCompile12(string $root,array $templates):void{$twigRoot=twigStorage12($root).'/vendor/twig/twig/src/';if(!is_file($twigRoot.'Environment.php'))fail12('twig_source_missing');spl_autoload_register(static function(string $class)use($twigRoot):void{if(strncmp($class,'Twig\\',5)!==0)return;$file=$twigRoot.str_replace('\\','/',substr($class,5)).'.php';if(is_file($file))require_once $file;});foreach(['Resources/core.php','Resources/debug.php','Resources/escaper.php','Resources/string_loader.php']as $resource){$file=$twigRoot.$resource;if(is_file($file))require_once $file;}if(!class_exists('Twig\\Environment'))fail12('twig_autoload_missing');$twig=new \Twig\Environment(new \Twig\Loader\ArrayLoader(),['cache'=>false,'autoescape'=>false,'debug'=>true,'auto_reload'=>true]);foreach($templates as $path=>$code){try{$twig->parse($twig->tokenize(new \Twig\Source($code,$path)));}catch(\Twig\Error\SyntaxError $e){fail12('twig_compile_failed file='.$path.' line='.$e->getTemplateLine().' message='.$e->getRawMessage());}}echo 'twig_compile=ok files='.count($templates)."\n";}
lint12(__FILE__);if(!is_file($root.'/config.php'))fail12('run_from_opencart_root_config_missing');
$paths=['catalog/controller/common/cart.php','catalog/view/template/common/cart.twig','catalog/view/javascript/common.js','catalog/view/stylesheet/stylesheet.css','catalog/view/stylesheet/boostershop-ds.css'];foreach($paths as $p)if(!is_file($root.'/'.$p))fail12('target_missing file='.$p);echo 'cwd='.$root."\ntime=".date(DATE_ATOM)."\n";$s=[];foreach($paths as $p)$s[$p]=file_get_contents($root.'/'.$p);if(str_contains($s[$paths[1]],'RD-12 mini-cart drawer')&&str_contains($s[$paths[2]],'bsToast')){echo "already_applied=yes\n";@unlink(__FILE__);exit;}

/* Exactly two read-only data assignments, identical to RD-11. */
$oldCtl=<<<'PHP'
		// Totals
		$data['totals'] = [];

		foreach ($totals as $total) {
			$data['totals'][] = ['text' => $this->currency->format($total['value'], $this->session->data['currency'])] + $total;
		}
PHP;
$newCtl=<<<'PHP'
		// Totals
		$data['totals'] = [];
		$data['shipping_pinta_nova_poshta_free_from'] = is_numeric($this->config->get('shipping_pinta_nova_poshta_free_from')) && (float)$this->config->get('shipping_pinta_nova_poshta_free_from') > 0 ? (float)$this->config->get('shipping_pinta_nova_poshta_free_from') : 2000.0;

		foreach ($totals as $total) {
			if ($total['code'] === 'sub_total') {
				$data['sub_total'] = (float)$total['value'];
			}

			$data['totals'][] = ['text' => $this->currency->format($total['value'], $this->session->data['currency'])] + $total;
		}
PHP;
$s[$paths[0]]=one12($s[$paths[0]],$oldCtl,$newCtl,'rd12_template_data');

$cart=$s[$paths[1]];$openAnchor='<div class="dropdown d-grid">';$scriptAnchor='<script type="text/javascript"><!--';$start=strpos($cart,$openAnchor);$script=strpos($cart,$scriptAnchor,$start);if($start===false||$script===false||$script<=$start||substr_count($cart,$openAnchor)!==1||substr_count($cart,$scriptAnchor)!==1)fail12('mini_cart_structure_missing');
$drawer=<<<'TWIG'
{# RD-12 mini-cart drawer; AJAX URLs and controller values are unchanged. #}
<div class="bs-mini-cart" data-bs-mini-cart>
  <button type="button" class="bs-btn bs-btn-primary bs-btn-sm mini-cart-trigger" data-bs-mini-cart-open aria-label="{{ text_items }}" aria-expanded="false"><svg class="bs-cart-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z" stroke="currentColor" stroke-width="1.6"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0" stroke="currentColor" stroke-width="1.6"/></svg>{% if bs_cart_qty > 0 %}<span class="bs-cart-qty">{{ bs_cart_qty }}</span>{% endif %}<span class="bs-btn-label">{{ text_items }}</span></button>
  <div class="bs-mini-cart__overlay" data-bs-mini-cart-close></div><aside class="bs-mini-cart__panel" role="dialog" aria-modal="true" aria-label="Кошик"><div class="bs-mini-cart__handle" aria-hidden="true"></div><header class="bs-mini-cart__head"><h2>Кошик{% if bs_cart_qty > 0 %}<span> · {{ bs_cart_qty }} товарів</span>{% endif %}</h2><button type="button" data-bs-mini-cart-close aria-label="Закрити">×</button></header>
  {% if products %}<div class="bs-mini-cart__body">{% for product in products %}<article class="bs-mini-cart__row"><a href="{{ product.href }}">{% if product.thumb %}<img src="{{ product.thumb }}" alt="{{ product.name }}"/>{% endif %}</a><div><div class="bs-mini-cart__rowhead"><a href="{{ product.href }}">{{ product.name }}</a><button type="button" class="mini-cart-remove-btn" data-remove-url="{{ remove }}" data-key="{{ product.cart_id }}" aria-label="Видалити">×</button></div><form class="mini-cart-quantity-form" data-edit-url="index.php?route=checkout/cart.edit&language={{ language }}"><div class="bs-mini-cart__line"><div class="bs-mini-cart__stepper"><button type="button" class="bs-mini-cart-qty" data-delta="-1">−</button><input type="number" name="quantity" value="{{ product.quantity }}" min="1" class="mini-cart-quantity-input" data-key="{{ product.cart_id }}" aria-label="Кількість"/><button type="button" class="bs-mini-cart-qty" data-delta="1">+</button></div><strong>{{ product.total }}</strong></div></form></div></article>{% endfor %}</div>{% set bs_free = sub_total >= shipping_pinta_nova_poshta_free_from %}{% set bs_remaining = shipping_pinta_nova_poshta_free_from - sub_total %}<footer class="bs-mini-cart__foot"><div class="bs-mini-cart__shipping{% if bs_free %} is-free{% endif %}">{% if bs_free %}<strong>Доставка Новою поштою — безкоштовно</strong>{% else %}<span>Безкоштовна доставка Новою поштою від {{ shipping_pinta_nova_poshta_free_from }}</span>{% endif %}{% if not bs_free and bs_remaining <= shipping_pinta_nova_poshta_free_from * 0.3 %}<div><i style="width:{{ (sub_total / shipping_pinta_nova_poshta_free_from * 100)|round }}%"></i></div><small>Ще {{ bs_remaining }} — і доставка безкоштовна</small>{% endif %}</div><div class="bs-mini-cart__sum"><span>Сума</span><strong>{% for total in totals %}{% if total.code == 'sub_total' %}{{ total.text }}{% endif %}{% endfor %}</strong></div><a href="{{ checkout }}" class="bs-btn bs-btn-primary">Оформити замовлення</a><div class="bs-mini-cart__links"><button type="button" data-bs-mini-cart-close>← Продовжити покупки</button><a href="{{ cart }}">Відкрити кошик</a></div></footer>{% else %}<div class="bs-empty"><div class="bs-empty__icon" aria-hidden="true">⌑</div><p class="bs-empty__title">Кошик порожній</p><p class="bs-empty__text">Тут з’являться товари, які ви додасте.</p><button type="button" class="bs-btn bs-btn-primary" data-bs-mini-cart-close>До каталогу</button></div>{% endif %}</aside></div>
TWIG;
$s[$paths[1]]=substr($cart,0,$start).$drawer.substr($cart,$script);
$oldClick="$(document).off('click.miniCartDropdown', '#cart .dropdown-menu').on('click.miniCartDropdown', '#cart .dropdown-menu', function(e) {\n    e.stopPropagation();\n});";
$newClick=<<<'JS'
$(document).off('click.miniCartDrawer', '[data-bs-mini-cart-open]').on('click.miniCartDrawer', '[data-bs-mini-cart-open]', function() {
    var cart=$(this).closest('[data-bs-mini-cart]'); cart.addClass('is-open'); $(this).attr('aria-expanded','true'); $('body').addClass('bs-mini-cart-open');
});
$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {
    var cart=$(this).closest('[data-bs-mini-cart]'); if(!cart.length)cart=$('[data-bs-mini-cart]'); cart.removeClass('is-open').find('[data-bs-mini-cart-open]').attr('aria-expanded','false'); $('body').removeClass('bs-mini-cart-open');
});
$(document).off('click.miniCartQty', '.bs-mini-cart-qty').on('click.miniCartQty', '.bs-mini-cart-qty', function() { var input=$(this).closest('form').find('.mini-cart-quantity-input'); input.val(Math.max(1,(parseInt(input.val(),10)||1)+parseInt($(this).data('delta'),10))).trigger('input'); });
JS;
$s[$paths[1]]=one12($s[$paths[1]],$oldClick,$newClick,'drawer_event_handlers');

$oldObserve=<<<'JS'
    $('#alert').observe(function() {
        window.setTimeout(function() {
            $('#alert .alert-dismissible').fadeTo(3000, 0, function() {
                $(this).remove();
            });
        }, 3000);
    });
JS;
$newObserve=<<<'JS'
    window.bsToast = function(type, message) {
        var ok=type==='success', role=ok?'status':'alert', title=ok?'Товар у кошику':'Не вдалося додати';
        var action=ok?'Переглянути кошик':'Відкрити кошик';
        $('#alert').html('<div class="bs-toast bs-toast--'+(ok?'success':'error')+'" role="'+role+'" aria-live="polite"><div class="bs-toast__icon">'+(ok?'✓':'!')+'</div><div class="bs-toast__copy"><strong>'+title+'</strong><span>'+message+'</span></div><a href="index.php?route=checkout/cart" class="bs-btn bs-btn-primary">'+action+'</a><button type="button" class="bs-btn bs-btn-secondary" data-bs-toast-close>Продовжити</button></div>');
        if(ok){window.clearTimeout(window.bsToastTimer);window.bsToastTimer=window.setTimeout(function(){$('#alert').empty();},4000);}
    };
    $(document).on('click.bsToast','[data-bs-toast-close]',function(){$('#alert').empty();});
    $(document).on('click.bsToastOutside',function(e){if($('#alert .bs-toast').length&&!$(e.target).closest('#alert .bs-toast,[data-bs-toggle="tooltip"]').length){$('#alert').empty();}});
JS;
$s[$paths[2]]=one12($s[$paths[2]],$oldObserve,$newObserve,'toast_lifecycle');
$s[$paths[2]]=one12($s[$paths[2]],"$('#alert').prepend('<div class=\"alert alert-danger alert-dismissible\"><i class=\"fa-solid fa-circle-exclamation\"></i> ' + json['error'] + ' <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>');","window.bsToast('danger', json['error']);",'toast_string_error');
$s[$paths[2]]=one12($s[$paths[2]],"$('#alert').prepend('<div class=\"alert alert-danger alert-dismissible\"><i class=\"fa-solid fa-circle-exclamation\"></i> ' + json['error']['warning'] + ' <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>');","window.bsToast('danger', json['error']['warning']);",'toast_object_error');
$s[$paths[2]]=one12($s[$paths[2]],"$('#alert').prepend('<div class=\"alert alert-success alert-dismissible\"><i class=\"fa-solid fa-circle-check\"></i> ' + json['success'] + ' <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>');","window.bsToast('success', json['success']);",'toast_success');
$oldAlert=<<<'CSS'
#alert {
  z-index: 9999;
  position: fixed;
  top: 30%;
  left: 50%;
  width: 400px;
  margin-left: -200px;
}
CSS;
$newAlert=<<<'CSS'
/* RD-12 toast positioning; the component itself is styled in boostershop-ds.css. */
#alert { position:fixed; z-index:var(--bs-z-toast); top:74px; right:24px; width:300px; }
CSS;
$s[$paths[3]]=one12($s[$paths[3]],$oldAlert,$newAlert,'alert_centered_position');
$oldMedia=<<<'CSS'
@media (min-width: 992px) {
  #alert {
    width: 600px;
    margin-left: -300px;
  }
}
@media (min-width: 1140px) {
  #alert {
    width: 600px;
    margin-left: -300px;
  }
}
@media (min-width: 1320px) {
  #alert {
    width: 600px;
    margin-left: -300px;
  }
}
CSS;
$s[$paths[3]]=one12($s[$paths[3]],$oldMedia,"@media (max-width:767.98px){#alert{top:var(--bs-header-height,64px);right:0;left:0;width:auto}}",'alert_legacy_breakpoints');
$css=<<<'CSS'

/* RD-12 mini-cart drawer and toast */
.bs-mini-cart__overlay{position:fixed;z-index:var(--bs-z-modal);inset:0;background:rgba(17,24,39,.45);opacity:0;pointer-events:none;transition:opacity .22s}.bs-mini-cart__panel{position:fixed;z-index:var(--bs-z-modal);top:0;right:0;bottom:0;display:flex;width:380px;max-width:100%;flex-direction:column;background:var(--bs-paper);box-shadow:var(--bs-sh-pop);transform:translateX(100%);transition:transform .26s cubic-bezier(.22,.7,.3,1)}.bs-mini-cart.is-open .bs-mini-cart__overlay{opacity:1;pointer-events:auto}.bs-mini-cart.is-open .bs-mini-cart__panel{transform:translateX(0)}.bs-mini-cart__handle{display:none}.bs-mini-cart__head{display:flex;align-items:center;gap:10px;padding:16px;border-bottom:1px solid var(--bs-line)}.bs-mini-cart__head h2{flex:1;margin:0;font-size:16px}.bs-mini-cart__head h2 span{color:var(--bs-ink-3);font-weight:600}.bs-mini-cart__head button{width:44px;height:44px;border:0;background:transparent;font-size:27px}.bs-mini-cart__body{flex:1;overflow:auto;padding:0 16px}.bs-mini-cart__row{display:grid;grid-template-columns:56px minmax(0,1fr);gap:12px;padding:14px 0;border-bottom:1px solid var(--bs-line-2)}.bs-mini-cart__row img{width:56px;height:56px;object-fit:cover;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}.bs-mini-cart__rowhead{display:flex;gap:8px}.bs-mini-cart__rowhead>a{display:-webkit-box;flex:1;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;color:var(--bs-ink);font-size:13.5px;font-weight:600;line-height:1.4}.bs-mini-cart__rowhead button{width:32px;height:32px;border:0;background:transparent;font-size:21px}.bs-mini-cart__line,.bs-mini-cart__sum,.bs-mini-cart__links{display:flex;align-items:center;justify-content:space-between;gap:10px}.bs-mini-cart__line{margin-top:8px}.bs-mini-cart__stepper{display:flex;align-items:center;height:44px;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}.bs-mini-cart__stepper button{width:44px;height:44px;border:0;background:transparent}.bs-mini-cart__stepper input{width:28px;border:0;text-align:center}.bs-mini-cart__foot{display:grid;gap:12px;padding:14px 16px 16px;border-top:1px solid var(--bs-line)}.bs-mini-cart__shipping{display:grid;gap:6px;padding:10px 12px;border:1px solid var(--bs-line);border-radius:var(--bs-r);font-size:13px}.bs-mini-cart__shipping.is-free{background:var(--bs-green-soft);color:var(--bs-green-hover)}.bs-mini-cart__shipping div{height:6px;border-radius:999px;background:var(--bs-line);overflow:hidden}.bs-mini-cart__shipping i{display:block;height:100%;background:var(--bs-green)}.bs-mini-cart__shipping small{color:var(--bs-ink-3)}.bs-mini-cart__sum strong{font-size:22px}.bs-mini-cart__foot>a{height:48px}.bs-mini-cart__links button{border:0;background:transparent;color:var(--bs-blue);font-weight:600}.bs-mini-cart-open{overflow:hidden}.bs-toast{display:grid;grid-template-columns:28px minmax(0,1fr) auto auto;align-items:center;gap:10px;padding:14px;border:1px solid;border-radius:var(--bs-r);background:var(--bs-paper);box-shadow:var(--bs-sh-pop);animation:bsToastIn .2s ease-out}.bs-toast--success{border-color:#BBE7CC}.bs-toast--error{border-color:#F3C0C0}.bs-toast__icon{display:grid;width:28px;height:28px;place-items:center;border-radius:50%;font-weight:800}.bs-toast--success .bs-toast__icon{background:var(--bs-green-soft);color:var(--bs-green-hover)}.bs-toast--error .bs-toast__icon{background:#FDECEC;color:var(--bs-danger)}.bs-toast__copy{display:grid;gap:2px;font-size:12.5px;color:var(--bs-ink-3);line-height:1.45}.bs-toast__copy strong{font-size:13.5px;color:var(--bs-ink)}.bs-toast .bs-btn{height:38px;padding:0 10px;font-size:13px}@keyframes bsToastIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}@media(max-width:767.98px){.bs-mini-cart__panel{top:auto;left:0;width:auto;max-height:86%;border-radius:var(--bs-r-lg) var(--bs-r-lg) 0 0;transform:translateY(100%)}.bs-mini-cart__handle{display:block;width:40px;height:4px;margin:8px auto 0;border-radius:999px;background:var(--bs-line)}.bs-toast{grid-template-columns:28px minmax(0,1fr);border:0;border-radius:0;background:var(--bs-green);color:#fff}.bs-toast--error{background:var(--bs-danger)}.bs-toast__copy,.bs-toast__copy strong{color:#fff}.bs-toast .bs-btn{grid-row:2}.bs-toast .bs-btn-primary{background:#fff;color:var(--bs-green)}.bs-toast--error .bs-btn-primary{color:var(--bs-danger)}}
CSS;
$s[$paths[4]].=$css;if(!str_contains($s[$paths[4]],'--bs-green-soft:'))$s[$paths[4]]=one12($s[$paths[4]],'  --bs-blue-soft:  #E8EEFB;',"  --bs-blue-soft:  #E8EEFB;\n  --bs-green-soft: #F3FBF6;",'green_soft_token');
twigCompile12($root,[$paths[1]=>$s[$paths[1]]]);
$backup=$root.'/_patch_backups/'.$id.'-'.date('Ymd-His');$written=[];foreach($s as $p=>$v){$d=$backup.'/'.$p;if(!is_dir(dirname($d))&&!mkdir(dirname($d),0755,true))fail12('backup_dir_failed');if(!copy($root.'/'.$p,$d))fail12('backup_failed file='.$p);}echo "backup=$backup\n";try{foreach($s as $p=>$v){if(file_put_contents($root.'/'.$p,$v,LOCK_EX)!==strlen($v))throw new RuntimeException('write_failed file='.$p);$written[]=$p;}targetLint12($root.'/'.$paths[0]);}catch(Throwable $e){foreach($written as $p)@copy($backup.'/'.$p,$root.'/'.$p);fail12($e->getMessage().' restored=yes');}foreach($paths as $p)echo "changed_file=$p\n";echo 'php_l=ok file='.basename(__FILE__)."\nphp_l=ok file={$paths[0]}\ndone=ok\n";@unlink(__FILE__);
