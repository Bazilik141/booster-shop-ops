<?php
/** PAY-002 WP2 — gated PUMB provider in the existing credit drawer. */
declare(strict_types=1);
const PATCH_ID = 'PAY-002_pumb-checkout-card_20260828';
function out(string $s): void { echo $s . PHP_EOL; }
function fail(string $s): void { throw new RuntimeException('ERROR: ' . $s); }
function need(bool $ok, string $s): void { if (!$ok) fail($s); }
function rx(string $s, string $p, string $r, string $label): string { $matches=[]; $count=preg_match_all($p,$s,$matches); need($count===1,'anchor count for '.$label.' is '.$count.', expected 1'); $n=0; $v=preg_replace($p,$r,$s,1,$n); need($n===1&&is_string($v),'replacement for '.$label.' failed'); return $v; }
function lint(string $p): void { $o=[]; exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($p).' 2>&1',$o,$x); need($x===0,'php -l failed for '.$p.': '.implode(' ',$o)); out('php_l=ok file='.$p); }
function writeFileChecked(string $p,string $v): void { $n=file_put_contents($p,$v); need($n!==false&&$n===strlen($v),'write failed: '.$p); }
function replaceCounted(string $s,string $a,string $b,int $expected,string $label): string { $count=substr_count($s,$a); need($count===$expected,'anchor count for '.$label.' is '.$count.', expected '.$expected); return str_replace($a,$b,$s); }
function back(string $root,string $dir,string $rel): void { $to=$dir.'/'.$rel; if(!is_dir(dirname($to))&&!mkdir(dirname($to),0755,true)&&!is_dir(dirname($to)))fail('backup directory failed'); need(copy($root.'/'.$rel,$to),'backup failed: '.$rel); }
function assertPay002ConfirmGate(string $source): void {
    $start = strpos($source, '    public function confirm(): void {');
    need($start !== false, 'confirm gate assertion failed: confirm() missing');
    $after = substr($source, $start + strlen('    public function confirm(): void {'));
    $next = preg_match('/^    (?:public|protected|private)\s+(?:static\s+)?function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/m', $after, $match, PREG_OFFSET_CAPTURE);
    need($next === 1, 'confirm gate assertion failed: confirm() boundary missing');
    $body = substr($source, $start, (int)$match[0][1] + strlen('    public function confirm(): void {'));
    $language = '$this->load->language(\'extension/pumb_credit/payment/pumb_credit\');';
    $gate = 'if (!$this->pay002Available() || !$orderId)';
    $languageCount = substr_count($body, $language);
    $gateCount = substr_count($body, $gate);
    need($languageCount === 1, 'confirm gate assertion failed: language load count=' . $languageCount);
    need($gateCount === 1, 'confirm gate assertion failed: gate count=' . $gateCount);
    need(strpos($body, $language) < strpos($body, $gate), 'confirm gate assertion failed: language load must precede gate');
}
try {
$root=getcwd()?:'.'; need(is_file($root.'/config.php'),'Run from OpenCart public_html (config.php missing).'); require_once $root.'/config.php';
$files=['catalog/controller/checkout/payment_method.php','catalog/view/template/checkout/payment_method.twig','extension/pumb_credit/catalog/controller/payment/pumb_credit.php']; foreach($files as $f)need(is_file($root.'/'.$f),'missing live file: '.$f);
$marker=$root.'/extension/pumb_credit/.pay002-checkout-card-marker'; if(is_file($marker)){out('already_applied=yes');exit(0);}
$s=[];foreach($files as $f){$v=file_get_contents($root.'/'.$f);need(is_string($v),'cannot read '.$f);$s[$f]=$v;}
$c=rx($s[$files[0]],'/\$pay001_gate \?\?= \$this->pay001MonoChastGate\(\);/',"\$pay001_gate ??= \$this->pay001MonoChastGate();\n\t\t\$pay002_gate = \$this->pay002PumbGate();",'PUMB gate');
$c=rx($c,'/(\t\tif \(\$pay001_mono_method\) \{\R\t\t\t\$payment_methods\[\x27mono_chast\x27\] = \$pay001_mono_method;\R\t\t\})\R\R\t\treturn \$payment_methods;/',"\$1\n\t\t\$pay002_method = \$this->pay002PumbMethod(\$pay002_gate);\n\t\tif (\$pay002_method) \$payment_methods['pumb_credit'] = \$pay002_method;\n\n\t\treturn \$payment_methods;",'PUMB injection');
$c=rx($c,'/(\t\t\tif \(!\$selected_payment\) \{\R\t\t\t\t\$json\[\x27error\x27\] = \$this->language->get\(\x27error_payment_method\x27\);\R\t\t\t\})/',"\$1\n\t\t\tif (!\$json && preg_match('/^pumb_credit\\.pumb_credit_([0-9]{1,2})\$/', (string)(\$selected_payment['code'] ?? ''), \$m)) \$this->session->data['pay002_pumb_credit_term'] = (int)\$m[1];",'PUMB term persistence');
$helpers=<<<'PHP'
    private function pay002PumbConfigured(): bool { if (!$this->config->get('payment_pumb_credit_status')) return false; foreach (['payment_pumb_credit_api_base','payment_pumb_credit_oauth_url','payment_pumb_credit_oauth_username','payment_pumb_credit_oauth_password','payment_pumb_credit_point_of_sale_code','payment_pumb_credit_partner_name'] as $key) if (trim((string)$this->config->get($key)) === '') return false; return (bool)$this->config->get('payment_pumb_credit_public') || !empty($this->session->data['pay002_pumb_preview']); }
    private $pay002_checkout_payable = null;
    private function pay002CheckoutPayable(): float {
        if ($this->pay002_checkout_payable !== null) return (float)$this->pay002_checkout_payable;
        $this->load->model('checkout/booster_coupon');
        $this->model_checkout_booster_coupon->prepareCouponTotal();
        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;
        $this->load->model('checkout/cart');
        ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);
        $this->pay002_checkout_payable = round(max(0.0, (float)$total), 2);
        return (float)$this->pay002_checkout_payable;
    }
    private function pay002PumbGate(): array { $min=(float)($this->config->get('payment_pumb_credit_min_total') ?: 500); $max=(float)$this->config->get('payment_pumb_credit_max_total'); $configured=$this->pay002PumbConfigured(); $g=['configured'=>$configured,'available'=>false,'reason'=>'config','threshold'=>round($min,2),'payable'=>0.0,'remaining'=>round($min,2)]; if(!$configured)return $g; foreach($this->cart->getProducts() as $p)if((int)($p['stock']??0)<1){$g['reason']='preorder';return $g;} $payable = $this->pay002CheckoutPayable(); $g['payable']=$payable; $g['remaining']=round(max(0.0,$min-$payable),2); if($payable<$min){$g['reason']='threshold';return $g;} if($max>0&&$payable>$max){$g['reason']='maximum';return $g;} $g['available']=true;$g['reason']='';$g['remaining']=0.0;return $g; }
    private function pay002PumbMethod(array $g): array { if(empty($g['available']))return []; $x=json_decode((string)$this->config->get('payment_pumb_credit_terms'),true);$terms=is_array($x)?array_values(array_unique(array_map('intval',$x))):[3,4,5];$terms=array_values(array_filter([3,4,5],static fn(int $v):bool=>in_array($v,$terms,true)));if(!$terms)$terms=[3,4,5];$preferred=(int)($this->session->data['pay002_pumb_credit_term']??$terms[0]);if(!in_array($preferred,$terms,true))$preferred=$terms[0];$options=[];foreach($terms as $term)$options['pumb_credit_'.$term]=['code'=>'pumb_credit.pumb_credit_'.$term,'name'=>'Сплачувати частинами ПУМБ'];return ['code'=>'pumb_credit','name'=>'Сплачувати частинами ПУМБ','option'=>$options,'pay002_credit'=>true,'pay002_preferred'=>$preferred,'pay002_total'=>(float)$g['payable'],'sort_order'=>(int)$this->config->get('payment_pumb_credit_sort_order')]; }
PHP;
$c=rx($c,'/\tprivate function pay001MonoChastConfigured\(\): bool \{/', $helpers."\n\tprivate function pay001MonoChastConfigured(): bool {",'PUMB helpers');
$sharedPayablePattern=<<<'REGEX'
/\R\t\t\$this->load->model\('checkout\/booster_coupon'\);.*?\R\t\t\$gate\['remaining'\] = round\(max\(0\.0, \$threshold - \$payable\), 2\);/s
REGEX;
$c=rx($c,$sharedPayablePattern,"\n\t\t\$payable = \$this->pay002CheckoutPayable();\n\t\t\$gate['payable'] = round(\$payable, 2);\n\t\t\$gate['remaining'] = round(max(0.0, \$threshold - \$payable), 2);",'shared payable');
$p=rx($s[$files[2]],'/    public function index\(\): string \{ return \'\'; \}/',<<<'PHP'
    public function index(): string {
        if (!$this->pay002Available()) return '';
        $language=rawurlencode((string)$this->config->get('config_language')); $term=(int)($this->session->data['pay002_pumb_credit_term']??3); $url=$this->url->link('extension/pumb_credit/payment/pumb_credit.confirm','language='.$language.'&term='.$term,true); $success=$this->url->link('checkout/success','language='.$language,true); $json=json_encode(str_replace('&amp;','&',$url),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); $successJson=json_encode(str_replace('&amp;','&',$success),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); if(!is_string($json)||!is_string($successJson))return '';
        return '<div id="pay002-pumb-confirm"><button type="button" id="pay002-pumb-confirm-button" class="btn btn-primary">Надіслати заявку в ПУМБ</button><div id="pay002-pumb-confirm-error" role="alert" aria-live="polite"></div><script>(function($){var u='.$json .',success='.$successJson .';$("#pay002-pumb-confirm").on("click.pay002","#pay002-pumb-confirm-button",function(e){e.preventDefault();var $button=$(this),$error=$("#pay002-pumb-confirm-error");$button.prop("disabled",true);$error.text("");$.post(u,{},function(j){if(j&&j.error){$error.text(j.error);$button.prop("disabled",false);return;}if(j&&j.redirect){window.location.href=j.redirect;return;}if(j&&!j.error){window.location.href=success;return;}$error.text("Не вдалося надіслати заявку.");$button.prop("disabled",false);},"json").fail(function(){ $error.text("Не вдалося надіслати заявку.");$button.prop("disabled",false);});});})(jQuery);</script></div>';
    }
PHP
,'PUMB index');
assertPay002ConfirmGate($p);
$t=$s[$files[1]];$t=rx($t,"/if \(choice\.indexOf\('mono_chast'\) !== -1/","if (choice.indexOf('pumb_credit') !== -1 || choice.indexOf('пумб') !== -1) {       return 'pumb_credit';     }      if (choice.indexOf('mono_chast') !== -1",'PUMB choice');$t=rx($t,'/if \(group && group\.pay001_credit && group\.option\)/','if (group && (group.pay001_credit || group.pay002_credit) && group.option)','PUMB flatten');$t=rx($t,'/var monoOptions = \[\];/','var monoOptions = []; var pumbOptions = [];','PUMB options');$t=rx($t,'/var match = option && option\.code.*?\R\s*if \(match\) monoOptions\.push\(.*?\);/s','var match = option && option.code ? String(option.code).match(/(mono_chast|pumb_credit)_(\\d)/) : null;             if (match) (match[1] === \'pumb_credit\' ? pumbOptions : monoOptions).push({ code: option.code, count: Number(match[2]) });','PUMB parsing');$t=rx($t,'/if \(monoOptions\.length\)/','if (monoOptions.length || pumbOptions.length)','PUMB list');$t=rx($t,'/var preferred = Number\(group\.pay001_preferred\) \|\| monoOptions\[0\]\.count;/','var sourceOptions = group.pay002_credit ? pumbOptions : monoOptions;             var preferred = Number(group[group.pay002_credit ? \'pay002_preferred\' : \'pay001_preferred\']) || sourceOptions[0].count;','PUMB preferred');$t=rx($t,'/var preferredOption = monoOptions\[0\];/','var preferredOption = sourceOptions[0];','PUMB preferred option');$t=rx($t,'/\$\.each\(monoOptions, function\(_, item\) \{/','$.each(sourceOptions, function(_, item) {','PUMB term loop');$t=rx($t,"/id: 'mono_chast',\R\s*pay001Credit: true,/","id: group.pay002_credit ? 'pumb_credit' : 'mono_chast',               pay001Credit: true, pay002Credit: !!group.pay002_credit,",'PUMB identity');$t=rx($t,'/monoOptions: monoOptions/','monoOptions: monoOptions, pumbOptions: pumbOptions','PUMB transport');$t=rx($t,'/var match = String\\(selected\\)\\.match\\([^;]+;\\s*/','var isPumb = !!option.pay002Credit;     var match = String(selected).match(/(mono_chast|pumb_credit)_(\\d)/);','PUMB drawer match');$t=rx($t,'/var count = match \? Number\(match\[1\]\) : option\.preferred;/','var count = match ? Number(match[2]) : option.preferred;     var termOptions = isPumb ? option.pumbOptions : option.monoOptions;','PUMB drawer options');$t=rx($t,'/\$\.each\(option\.monoOptions, function\(_, item\)/','$.each(termOptions, function(_, item)','PUMB drawer loop');$t=rx($t,"/if \(!found && option\.pay001Credit && option\.monoOptions\)/",'if (!found && (option.pay001Credit || option.pay002Credit) && (option.monoOptions || option.pumbOptions))','PUMB find');$t=rx($t,'/option\.monoOptions, function\(_, monoOption\)/','(option.pay002Credit ? option.pumbOptions : option.monoOptions), function(_, monoOption)','PUMB find source');$t=rx($t,"/if \(normalizeChoice\(code\) !== 'mono_chast' && typeof window\.bsPay001SetCreditGate === 'function'\)/","if (['mono_chast','pumb_credit'].indexOf(normalizeChoice(code)) === -1 && typeof window.bsPay001SetCreditGate === 'function')",'PUMB preserve drawer');
$drawerReplacement=<<<'JS'
  function pay001Drawer(option, selectedCode) {
    if (option.pay001Blocked) {
      return $('<div class="pay001-checkout-drawer" data-pay001-drawer><div class="pay001-credit-warning" role="alert">' + escapeHtml(pay001GateMessage(option.gate, false)) + '</div></div>');
    }
    var isPumb = !!option.pay002Credit;
    var selected = selectedCode || option.code;
    var match = String(selected).match(/(mono_chast|pumb_credit)_(\d)/);
    var count = match ? Number(match[2]) : option.preferred;
    var termOptions = isPumb ? option.pumbOptions : option.monoOptions;
    var providerImage = isPumb ? 'catalog/view/image/payment/pay001-pumb.svg' : 'catalog/view/image/payment/pay001-mono-label.png';
    var providerAlt = isPumb ? 'ПУМБ' : 'monobank';
    var providerLabel = isPumb ? 'Сплачуйте частинами ПУМБ' : 'Покупка частинами monobank';
    var html = '<div class="pay001-checkout-drawer" data-pay001-drawer>' +
      '<article class="pay001-checkout-provider"><div class="pay001-checkout-provider__head"><img src="' + providerImage + '" alt="' + providerAlt + '"><strong>' + providerLabel + '</strong></div>' +
      '<div class="pay001-parts">';
    $.each(termOptions, function(_, item) { html += '<button type="button" data-pay001-checkout-part="' + item.count + '" data-pay001-code="' + escapeHtml(item.code) + '"' + (item.count === count ? ' class="is-active"' : '') + '>' + item.count + ' ' + window.pay001PaymentsWord(item.count) + '</button>'; });
    html += '</div><div class="pay001-summary"><span>Сума в кредит<strong>' + pay001Money(option.total) + '</strong></span><span>Щомісячний платіж<strong data-pay001-monthly>' + pay001Money(option.total / count) + '</strong></span><span>Платежів до завершення<strong data-pay001-left>' + Math.max(count - 1, 0) + '</strong></span></div><p>Кредит буде оформлено на номер телефону: <strong data-pay001-phone></strong></p></article>' +
      (isPumb ? '' : '<article class="pay001-checkout-provider pay001-checkout-provider--soon"><div class="pay001-checkout-provider__head"><img src="catalog/view/image/payment/pay001-pumb.svg" alt="ПУМБ"><strong>Сплачуйте частинами ПУМБ</strong><em>СКОРО БУДЕ</em></div><small>До 5 платежів</small></article>') + '</div>';
    var $drawer = $(html);
    pay001SyncPhone($drawer);
    return $drawer;
  }
  function renderPaymentMethods
JS;
$t=rx($t,'/  function pay001Drawer\(option, selectedCode\) \{.*?  function renderPaymentMethods/s',$drawerReplacement,'PUMB drawer render');

$t=rx($t,"/if \(choice === 'mono_chast'\) \{\R\s*return code\.indexOf\('mono_chast\.'\) === 0;\R\s*\}/","if (choice === 'mono_chast' || choice === 'pumb_credit') { return code.indexOf(choice + '.') === 0; }",'PUMB choice match');
$t=rx($t,"/normalizeChoice\(bsPaymentPendingChoice \|\| current\) === 'mono_chast' \|\| !!gate\.selected/","(normalizeChoice(bsPaymentPendingChoice || current) === 'mono_chast' || normalizeChoice(bsPaymentPendingChoice || current) === 'pumb_credit') || !!gate.selected",'PUMB credit intent');
$t=replaceCounted($t,'pay001Credit: true, pay002Credit','pay001Credit: !group.pay002_credit, pay002Credit',1,'PUMB identity');
$t=replaceCounted($t,'option.pay001Credit','(option.pay001Credit || option.pay002Credit)',7,'PUMB option flags');
$t=replaceCounted($t,'selected.pay001Credit','(selected.pay001Credit || selected.pay002Credit)',2,'PUMB selected flags');
$t=replaceCounted($t,'!desiredOption.pay001Credit','!(desiredOption.pay001Credit || desiredOption.pay002Credit)',1,'PUMB desired flags');
$t=replaceCounted($t,'group.pay001_total','(group.pay002_total || group.pay001_total)',1,'PUMB total transport');
$stamp=date('Ymd-His');$backup=$root.'/_patch_backups/'.PATCH_ID.'-'.$stamp;foreach($files as $f)back($root,$backup,$f);try{writeFileChecked($root.'/'.$files[0],$c);writeFileChecked($root.'/'.$files[1],$t);writeFileChecked($root.'/'.$files[2],$p);lint($root.'/'.$files[0]);lint($root.'/'.$files[2]);$writtenP=file_get_contents($root.'/'.$files[2]);need(is_string($writtenP),'cannot read generated PUMB controller');assertPay002ConfirmGate($writtenP);out('generated_confirm_gate=ok');writeFileChecked($marker,'PAY-002 WP2 applied '.date('c').PHP_EOL);out('cwd='.$root);out('backup='.$backup);out('changed='.implode(',',$files));out('database_touched=no');out('done=ok');}catch(Throwable $e){foreach($files as $f)@copy($backup.'/'.$f,$root.'/'.$f);fail('source restored: '.$e->getMessage());}if(!@unlink(__FILE__))out('self_delete=failed remove_uploaded_patch_manually=yes');else out('self_delete=ok');
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
