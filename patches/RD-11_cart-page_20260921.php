<?php
declare(strict_types=1);
/* RD-11: cart page only. No DB, checkout/payment, URL or price calculation changes. */
$id = pathinfo(__FILE__, PATHINFO_FILENAME); $root = getcwd();
function fail11(string $m): void { fwrite(STDERR, "error=$m\n"); exit(1); }
function once11(string $s, string $old, string $new, string $name): string { $n=substr_count($s,$old); if($n!==1) fail11("anchor_count name=$name expected=1 actual=$n"); return str_replace($old,$new,$s); }
function lint11(string $f): void { exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($f).' 2>&1',$o,$c); if($c) fail11('php_l_failed '.implode(' | ',$o)); }
function targetLint11(string $f): void { exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($f).' 2>&1',$o,$c); if($c) throw new RuntimeException('php_l_failed file='.$f.' output='.implode(' | ',$o)); }
function twigStorage11(string $root):string{$config=file_get_contents($root.'/config.php');if(!is_string($config)||!preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/",$config,$m))fail11('dir_storage_missing_in_config');return rtrim($m[1],'/\\');}
function twigCompile11(string $root,array $templates):void{$twigRoot=twigStorage11($root).'/vendor/twig/twig/src/';if(!is_file($twigRoot.'Environment.php'))fail11('twig_source_missing');spl_autoload_register(static function(string $class)use($twigRoot):void{if(strncmp($class,'Twig\\',5)!==0)return;$file=$twigRoot.str_replace('\\','/',substr($class,5)).'.php';if(is_file($file))require_once $file;});foreach(['Resources/core.php','Resources/debug.php','Resources/escaper.php','Resources/string_loader.php']as $resource){$file=$twigRoot.$resource;if(is_file($file))require_once $file;}if(!class_exists('Twig\\Environment'))fail11('twig_autoload_missing');$twig=new \Twig\Environment(new \Twig\Loader\ArrayLoader(),['cache'=>false,'autoescape'=>false,'debug'=>true,'auto_reload'=>true]);foreach($templates as $path=>$code){try{$twig->parse($twig->tokenize(new \Twig\Source($code,$path)));}catch(\Twig\Error\SyntaxError $e){fail11('twig_compile_failed file='.$path.' line='.$e->getTemplateLine().' message='.$e->getRawMessage());}}echo 'twig_compile=ok files='.count($templates)."\n";}
lint11(__FILE__); if(!is_file($root.'/config.php')) fail11('run_from_opencart_root_config_missing');
$paths=['catalog/controller/checkout/cart.php','catalog/view/template/checkout/cart.twig','catalog/view/template/checkout/cart_list.twig','catalog/view/stylesheet/boostershop-ds.css'];
foreach($paths as $p) if(!is_file($root.'/'.$p)) fail11('target_missing file='.$p);
echo 'cwd='.$root."\ntime=".date(DATE_ATOM)."\n";
$src=[]; foreach($paths as $p) $src[$p]=file_get_contents($root.'/'.$p);
if(str_contains($src[$paths[2]],'RD-11 cart list')) { echo "already_applied=yes\n"; @unlink(__FILE__); exit; }

/* Exactly two read-only data assignments: admin default 2000 and raw pre-coupon sub_total. */
$oldController=<<<'PHP'
			foreach ($totals as $result) {
				$data['totals'][] = ['text' => $price_status ? $this->currency->format($result['value'], $this->session->data['currency']) : ''] + $result;
			}
PHP;
$newController=<<<'PHP'
			foreach ($totals as $result) {
				if ($result['code'] === 'sub_total') {
					$sub_total = (float)$result['value'];
				}

				$data['totals'][] = ['text' => $price_status ? $this->currency->format($result['value'], $this->session->data['currency']) : ''] + $result;
			}
PHP;
$src[$paths[0]]=once11($src[$paths[0]],$oldController,$newController,'rd11_template_data');
$src[$paths[0]]=once11($src[$paths[0]],"\t\t// Display prices\n\t\tif (\$this->customer->isLogged() || !\$this->config->get('config_customer_price')) {\n\t\t\t(\$this->model_checkout_cart->getTotals)(\$totals, \$taxes, \$total);","\t\t// Display prices\n\t\t\$sub_total = 0.0;\n\n\t\tif (\$this->customer->isLogged() || !\$this->config->get('config_customer_price')) {\n\t\t\t(\$this->model_checkout_cart->getTotals)(\$totals, \$taxes, \$total);",'rd11_subtotal_default');
$src[$paths[0]]=once11($src[$paths[0]],"\t\t}\n\n\t\t\$data['modules'] = [];","\t\t}\n\n\t\t\$data['shipping_pinta_nova_poshta_free_from'] = is_numeric(\$this->config->get('shipping_pinta_nova_poshta_free_from')) && (float)\$this->config->get('shipping_pinta_nova_poshta_free_from') > 0 ? (float)\$this->config->get('shipping_pinta_nova_poshta_free_from') : 2000.0;\n\t\t\$data['sub_total'] = \$sub_total;\n\n\t\t\$data['modules'] = [];",'rd11_data_after_price_gate');

$oldShell=<<<'TWIG'
<style>
#checkout-cart .bs-table-model-hidden {
    display: none !important;
}

#checkout-cart .bs-checkout-cta {
    background: #19a447;
    border-color: #168a3d;
    border-radius: 7px;
    box-shadow: 0 6px 14px rgba(25, 164, 71, 0.22);
    font-weight: 700;
    padding: 10px 18px;
    transition: background-color .16s ease, border-color .16s ease, box-shadow .16s ease, transform .16s ease;
}

#checkout-cart .bs-checkout-cta:hover {
    background: #158b3d;
    border-color: #127a35;
    box-shadow: 0 8px 18px rgba(25, 164, 71, 0.3);
    transform: translateY(-1px);
}

#checkout-cart .bs-checkout-cta:active {
    box-shadow: 0 3px 8px rgba(25, 164, 71, 0.22);
    transform: translateY(0);
}
</style>
TWIG;
$newShell=<<<'TWIG'
<style>
/* RD-11 cart page */
#checkout-cart{padding-bottom:48px}#checkout-cart #content{padding-bottom:48px}.bs-cart-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start}.bs-cart-card{background:var(--bs-paper);border:1px solid var(--bs-line);border-radius:var(--bs-r);box-shadow:var(--bs-sh-sm)}.bs-cart-lines{padding:4px 20px 20px}.bs-cart-summary-wrap{position:sticky;top:16px}.bs-cart-summary{display:grid;gap:14px;padding:20px}.bs-cart-summary h2{margin:0;font-size:16px}.bs-cart-summary__row,.bs-cart-summary__total{display:flex;justify-content:space-between;gap:12px}.bs-cart-summary__rows{display:grid;gap:8px;font-size:14px;color:var(--bs-ink-3)}.bs-cart-summary__total{padding-top:12px;border-top:1px solid var(--bs-line);color:var(--bs-ink);font-weight:700}.bs-cart-summary__total strong{font-size:24px}.bs-cart-shipping{display:grid;gap:7px;padding:10px 12px;border:1px solid var(--bs-line);border-radius:var(--bs-r);background:var(--bs-bg);font-size:13px;line-height:1.45}.bs-cart-shipping--free{background:var(--bs-green-soft)}.bs-cart-shipping--free strong{color:var(--bs-green-hover)}.bs-cart-shipping__bar{height:6px;overflow:hidden;border-radius:999px;background:var(--bs-line)}.bs-cart-shipping__bar span{display:block;height:100%;background:var(--bs-green)}.bs-cart-shipping small{color:var(--bs-ink-3);font-size:12px}.bs-cart-row{display:grid;grid-template-columns:96px minmax(0,1fr);gap:16px;padding:18px 0;border-bottom:1px solid var(--bs-line-2)}.bs-cart-row__thumb img{display:block;width:96px;height:96px;object-fit:cover;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}.bs-cart-row__body{display:grid;gap:10px;min-width:0}.bs-cart-row__head{display:flex;gap:12px;align-items:start}.bs-cart-row__name{display:-webkit-box;flex:1;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;color:var(--bs-ink);font-size:15px;font-weight:600;line-height:1.4}.bs-cart-row__total{font-size:17px;white-space:nowrap}.bs-cart-row__unit,.bs-cart-row__options{margin:0;color:var(--bs-ink-3);font-size:12.5px}.bs-cart-row__unit span,.bs-cart-row__minimum{color:var(--bs-danger);font-weight:600}.bs-cart-row__controls{display:flex;flex-wrap:wrap;align-items:center;gap:14px}.bs-cart-stepper{display:inline-flex;align-items:center;height:44px;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm);background:#fff}.bs-cart-stepper button{width:44px;height:44px;border:0;background:transparent;font-size:20px}.bs-cart-stepper input{width:34px;border:0;text-align:center;font-weight:700}.bs-cart-remove{color:var(--bs-ink-3);font-size:13px;font-weight:600}.bs-cart-continue{min-height:44px;margin-top:16px;border:1px solid var(--bs-blue);border-radius:var(--bs-r-sm);background:#fff;color:var(--bs-blue);font-weight:700}.bs-cart-checkout{display:inline-flex;min-height:48px;align-items:center;justify-content:center;border:1px solid var(--bs-green);border-radius:var(--bs-r-sm);background:var(--bs-green);color:#fff;font-weight:700;text-decoration:none}.bs-cart-checkout:hover{background:var(--bs-green-d);border-color:var(--bs-green-d);color:#fff}.bs-cart-checkout[aria-disabled=true]{border-color:var(--bs-line);background:var(--bs-line-2);color:var(--bs-ink-2);cursor:not-allowed;pointer-events:none}.bs-cart-stock-notice{margin-bottom:16px;padding:12px 14px;border:1px solid var(--bs-warning-line);border-radius:var(--bs-r);background:var(--bs-warning-bg);color:var(--bs-warning-fg);font-size:13px}.bs-cart-mobile-bar{display:none}.bs-cart-recommendations{margin-top:40px}.bs-cart-recommendations h2{font-size:18px}
@media(max-width:767.98px){ #checkout-cart{padding-bottom:110px}.bs-cart-grid{grid-template-columns:1fr;gap:16px}.bs-cart-lines{padding:2px 14px 14px}.bs-cart-summary-wrap{position:static}.bs-cart-summary{padding:16px}.bs-cart-row{grid-template-columns:72px minmax(0,1fr);gap:12px;padding:14px 0}.bs-cart-row__thumb img{width:72px;height:72px}.bs-cart-row__name{font-size:14px}.bs-cart-row__total{font-size:15px}.bs-cart-mobile-bar{position:fixed;z-index:var(--bs-z-sticky);bottom:0;left:0;right:0;display:flex;align-items:center;gap:12px;padding:10px 16px 14px;border-top:1px solid var(--bs-line);background:var(--bs-paper);box-shadow:0 -6px 20px rgba(17,24,39,.07)}.bs-cart-mobile-bar__sum{display:grid}.bs-cart-mobile-bar__sum small{font-size:11.5px;color:var(--bs-ink-3)}.bs-cart-mobile-bar__sum strong{font-size:19px}.bs-cart-mobile-bar .bs-cart-checkout{flex:1}.bs-cart-recommendations{margin-top:28px;overflow-x:auto}}
</style>
TWIG;
$src[$paths[1]]=once11($src[$paths[1]],$oldShell,$newShell,'rd11_cart_css');
$oldJs=<<<'JS'
    function normalizeText(value) {
        return $.trim(String(value || '').replace(/\s+/g, ' '));
    }

    function hideModelColumns() {
        $('#shopping-cart table').each(function() {
            var table = $(this);

            table.find('th, td').removeClass('bs-table-model-hidden');

            table.find('tr').first().children('th, td').each(function(index) {
                if (normalizeText($(this).text()).toLowerCase() === 'модель') {
                    var column = index + 1;

                    table.find('tr').each(function() {
                        $(this).children('th, td').eq(column - 1).addClass('bs-table-model-hidden');
                    });
                }
            });
        });
    }

    function highlightCheckoutButtons() {
        $('#shopping-cart a, #shopping-cart button, #shopping-cart input[type="submit"]').each(function() {
            var button = $(this);
            var text = normalizeText(button.is('input') ? button.val() : button.text()).toLowerCase();

            if (text === 'оформити замовлення') {
                button.addClass('bs-checkout-cta');
            }
        });
    }

    function enhanceCart() {
        hideModelColumns();
        highlightCheckoutButtons();
    }
JS;
$newJs=<<<'JS'
    function enhanceCart() { $('#shopping-cart input[name="quantity"]').each(function(){ $(this).data('last-known-value', $(this).val()); }); }

    $('#shopping-cart').on('click', '.bs-cart-qty-btn', function() {
        var input=$(this).closest('form').find('input[name="quantity"]');
        input.val(Math.max(1,(parseInt(input.val(),10)||1)+parseInt($(this).data('delta'),10))).trigger('input');
    });
JS;
$src[$paths[1]]=once11($src[$paths[1]],$oldJs,$newJs,'rd11_remove_model_hider');

$oldListStart="  <style>"; $pos=strpos($src[$paths[2]],$oldListStart); $emptyElse="{% else %}\n  <div class=\"bs-empty\""; $else=strpos($src[$paths[2]],$emptyElse,$pos); if($pos===false||$else===false||substr_count($src[$paths[2]],$emptyElse)!==1) fail11('cart_list_top_level_empty_structure_missing');
$newList=<<<'TWIG'
  {# RD-11 cart list: controller-supplied values only. #}
  {% set bs_items = 0 %}{% for product in products %}{% set bs_items = bs_items + product.quantity %}{% endfor %}
  <h1>{{ heading_title }}{% if weight %} ({{ weight }}){% endif %}</h1>
  {% if pay002_cart_minimum_checkout_blocked or pay002_cart_stock_warning %}<div class="bs-cart-stock-notice" role="alert"><strong>Перевірте кількість товарів у кошику.</strong> Одного або кількох товарів немає в наявності в обраній кількості — зменште кількість або приберіть позицію.</div>{% endif %}
  <div class="bs-cart-grid"><section class="bs-cart-card bs-cart-lines" id="output-cart">{% for product in products %}<article class="bs-cart-row"><a class="bs-cart-row__thumb" href="{{ product.href }}">{% if product.thumb %}<img src="{{ product.thumb }}" alt="{{ product.name }}" title="{{ product.name }}"/>{% endif %}</a><div class="bs-cart-row__body"><div class="bs-cart-row__head"><a class="bs-cart-row__name" href="{{ product.href }}">{{ product.name }}</a><strong class="bs-cart-row__total">{{ product.total }}</strong></div><div class="bs-cart-row__unit">{{ product.price }} / шт.{% if not product.stock %}<span> · немає в наявності</span>{% endif %}</div>{% if product.option %}<ul class="bs-cart-row__options">{% for option in product.option %}<li>{{ option.name }}: {{ option.value }}</li>{% endfor %}</ul>{% endif %}<form method="post" data-oc-target="#shopping-cart" class="bs-cart-row__controls"><div class="bs-cart-stepper"><button type="button" class="bs-cart-qty-btn" data-delta="-1" aria-label="Менше">−</button><input type="text" name="quantity" value="{{ product.quantity }}" inputmode="numeric" aria-label="Кількість {{ product.name }}"/><button type="button" class="bs-cart-qty-btn" data-delta="1" aria-label="Більше">+</button></div><input type="hidden" name="key" value="{{ product.cart_id }}"/><button type="submit" formaction="{{ edit }}" class="visually-hidden"></button><a href="{{ product.remove }}" class="bs-cart-remove"><i class="fa-solid fa-trash-can"></i> Видалити</a></form>{% if product.minimum_value and product.quantity < product.minimum_value %}<div class="bs-cart-row__minimum">Мінімальна кількість для цього товару: {{ product.minimum_value }}</div>{% endif %}</div></article>{% endfor %}<a href="{{ continue }}" class="bs-btn bs-cart-continue">← Продовжити покупки</a></section>
  {% set bs_free = sub_total >= shipping_pinta_nova_poshta_free_from %}{% set bs_remaining = shipping_pinta_nova_poshta_free_from - sub_total %}<aside class="bs-cart-summary-wrap"><div class="bs-cart-card bs-cart-summary"><h2>Разом</h2><div class="bs-cart-summary__rows"><div class="bs-cart-summary__row"><span>Товари ({{ bs_items }})</span><span>{% for total in totals %}{% if total.code == 'sub_total' %}{{ total.text }}{% endif %}{% endfor %}</span></div><div class="bs-cart-summary__row"><span>Доставка</span><span>за тарифами перевізника</span></div></div><div class="bs-cart-summary__total"><span>До сплати</span><strong>{% for total in totals %}{% if total.code == 'sub_total' %}{{ total.text }}{% endif %}{% endfor %}</strong></div><div class="bs-cart-shipping{% if bs_free %} bs-cart-shipping--free{% endif %}">{% if bs_free %}<strong>Доставка Новою поштою — безкоштовно</strong>{% else %}<span>Безкоштовна доставка Новою поштою від {{ shipping_pinta_nova_poshta_free_from }}</span>{% endif %}{% if not bs_free and bs_remaining <= shipping_pinta_nova_poshta_free_from * 0.3 %}<div class="bs-cart-shipping__bar"><span style="width:{{ (sub_total / shipping_pinta_nova_poshta_free_from * 100)|round }}%"></span></div><small>Ще {{ bs_remaining }} — і доставка безкоштовна</small>{% endif %}</div>{% if pay002_cart_stock_checkout_blocked or pay002_cart_minimum_checkout_blocked %}<span class="bs-cart-checkout" aria-disabled="true">Виправте кількість товарів</span>{% else %}<a class="bs-cart-checkout" href="{{ checkout }}">Оформити</a>{% endif %}<a href="{{ continue }}" class="bs-btn bs-cart-continue">Продовжити покупки</a></div></aside></div>
  <div class="bs-cart-mobile-bar"><span class="bs-cart-mobile-bar__sum"><small>До сплати</small><strong>{% for total in totals %}{% if total.code == 'sub_total' %}{{ total.text }}{% endif %}{% endfor %}</strong></span>{% if pay002_cart_stock_checkout_blocked or pay002_cart_minimum_checkout_blocked %}<span class="bs-cart-checkout" aria-disabled="true">Виправте кількість</span>{% else %}<a class="bs-cart-checkout" href="{{ checkout }}">Оформити</a>{% endif %}</div>
  {% if modules %}<section class="bs-cart-recommendations"><h2>Часто беруть разом</h2>{% for module in modules %}{{ module }}{% endfor %}</section>{% endif %}
TWIG;
$src[$paths[2]]=substr($src[$paths[2]],0,$pos).$newList.substr($src[$paths[2]],$else);
if(!str_contains($src[$paths[3]],'--bs-green-soft:')) $src[$paths[3]]=once11($src[$paths[3]],'  --bs-blue-soft:  #E8EEFB;',"  --bs-blue-soft:  #E8EEFB;\n  --bs-green-soft: #F3FBF6;",'green_soft_token');
twigCompile11($root,[$paths[1]=>$src[$paths[1]],$paths[2]=>$src[$paths[2]]]);
$backup=$root.'/_patch_backups/'.$id.'-'.date('Ymd-His'); $written=[];
foreach($src as $p=>$s){$target=$root.'/'.$p;$copy=$backup.'/'.$p;if(!is_dir(dirname($copy))&&!mkdir(dirname($copy),0755,true))fail11('backup_dir_failed');if(!copy($target,$copy))fail11('backup_failed file='.$p);}
echo "backup=$backup\n";try{foreach($src as $p=>$s){$target=$root.'/'.$p;if(file_put_contents($target,$s,LOCK_EX)!==strlen($s))throw new RuntimeException('write_failed file='.$p);$written[]=$p;}targetLint11($root.'/'.$paths[0]);}catch(Throwable $e){foreach($written as $p)@copy($backup.'/'.$p,$root.'/'.$p);fail11($e->getMessage().' restored=yes');}
foreach($paths as $p)echo "changed_file=$p\n";echo 'php_l=ok file='.basename(__FILE__)."\nphp_l=ok file={$paths[0]}\ndone=ok\n";@unlink(__FILE__);
