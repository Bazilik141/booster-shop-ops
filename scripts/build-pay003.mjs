import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root=process.cwd(), base=path.join(root,'work/pay003');
const hash=s=>crypto.createHash('sha256').update(s).digest('hex');
const read=p=>fs.readFileSync(p,'utf8');
const files={};
function edit(file, fn) {
  const original=read(path.join(base,'source',file));
  const nl=original.includes('\r\n')?'\r\n':'\n';
  const source=original.replace(/\r\n/g,'\n');
  const edits=[];
  let current=source;
  const replace=(before,after)=>{if(!before||current.split(before).length!==2)throw Error('Anchor '+file+': '+before.slice(0,90));current=current.replace(before,after);edits.push({before:before.replace(/\n/g,nl),after:after.replace(/\n/g,nl)});};
  fn(source,replace);
  const result=current.replace(/\n/g,nl);
  files[file]={before:hash(original),after:hash(result),edits};
  write(file,result);
}
function write(file,value) {const p=path.join(base,'candidate',file);fs.mkdirSync(path.dirname(p),{recursive:true});fs.writeFileSync(p,value);}
function add(file,source) {const text=read(source);if(fs.existsSync(path.join(base,'source',file)))throw Error('New file already exists '+file);files[file]={before:null,after:hash(text),content:Buffer.from(text).toString('base64')};write(file,text);}
for(const p of ['mono_chast','pumb_credit']) {
  edit(`extension/${p}/catalog/controller/payment/${p}.php`,(s,replace)=>{
    if(p==='pumb_credit' && hash(read(path.join(base,'source',`extension/${p}/catalog/controller/payment/${p}.php`)))!=='422d126854263405112bfe2a7267b178cdaf12aaf381dbcb8f97a1840e01ac45')throw Error('Latest amount patch missing');
    const start=s.indexOf('    public function index(): string {'),end=s.indexOf(p==='mono_chast'?'    public function confirm()':'    public function preview()',start);
    const name=p==='pumb_credit'?'ПУМБ':'monobank';
    replace(s.slice(start,end),`    public function index(): string {
        ${p==='pumb_credit'?"if (!$this->pay002Available()) return '';":"if (!$this->config->get('payment_mono_chast_status')) return '';"}
        $this->load->model('checkout/credit');
        $id = (int)($this->session->data['order_id'] ?? 0);
        $arguments = 'language=' . rawurlencode((string)$this->config->get('config_language'));
        ${p==='pumb_credit'?"$arguments .= '&term=' . (int)($this->session->data['pay002_pumb_credit_term'] ?? 3);":''}
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        return $this->load->view('checkout/credit_confirm', [
            'root_id'=>'${p==='pumb_credit'?'pay002-pumb-confirm':'pay001-mono-confirm'}', 'button_id'=>'${p==='pumb_credit'?'pay002-pumb-confirm-button':'button-confirm'}', 'provider_name'=>'${name}',
            'root_json'=>json_encode('${p==='pumb_credit'?'pay002-pumb-confirm':'pay001-mono-confirm'}', $flags),
            'confirm_json'=>json_encode(str_replace('&amp;', '&', $this->url->link('extension/${p}/payment/${p}.confirm', $arguments, true)), $flags),
            'waiting_json'=>json_encode($this->model_checkout_credit->url($id), $flags)
        ]);
    }
`);
    const cs=s.indexOf('    public function confirm(): void {'),ce=s.indexOf('    public function callback()',cs);
    const original=s.slice(cs,ce);
    let confirm=original.replaceAll('$this->reply(', '$this->pay003Reply(');
    const anchor=p==='pumb_credit'?'        $existing = $this->transactionByOrder($orderId, $isTest);':"        $existing = $this->transactionByStoreOrder('OC-' . $orderId);";
    confirm=confirm.replace(anchor,`        $this->load->model('checkout/credit');
        $pay003_context = $this->model_checkout_credit->context($orderId);
        if (!$pay003_context || $pay003_context['provider'] !== '${p}') { $this->reply(['error'=>'Спосіб оплати не відповідає замовленню.']); return; }
        $this->model_checkout_credit->remember($orderId);
${anchor}`);
    if(p==='mono_chast') confirm=confirm.replace('        if ($this->isUsableTransaction($existing)) {',`        // PAY-003: failed/ambiguous existing applications require review, not a new POST.
        if ($existing && !$this->isUsableTransaction($existing)) { $this->pay003Reply(['error'=>'Заявка потребує перевірки підтримкою магазину.']); return; }
        if ($this->isUsableTransaction($existing)) {`);
    replace(original,confirm);
    if (p==='pumb_credit') {
      const es=s.indexOf('    private function replyExistingCreate('),ee=s.indexOf('    /** @return array{owner:bool,transaction:array<string,mixed>} */',es);
      replace(s.slice(es,ee),s.slice(es,ee).replaceAll('$this->reply(', '$this->pay003Reply('));
      replace("        $previous = $existing ? json_decode((string)($existing['payload'] ?? ''), true) : null;", "        $previous = $existing ? json_decode((string)($existing['payload'] ?? ''), true) : null;\n        // PAY-003: retain original environment evidence across callbacks.\n        if (is_array($previous) && isset($previous['pay003'])) $payload['pay003'] = $previous['pay003'];");
    } else {
      replace("        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);", "        // PAY-003: retain original environment evidence across callbacks.\n        $pay003_previous = $this->transactionByStoreOrder($store);\n        $pay003_payload = json_decode((string)($pay003_previous['payload'] ?? ''), true);\n        if (is_array($pay003_payload) && isset($pay003_payload['pay003'])) $payload['pay003'] = $pay003_payload['pay003'];\n        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);");
    }
    const ps=s.indexOf('    public function poll(): void {');
    const pe=s.indexOf(p==='mono_chast'?'    private function createPayload(':'    /** @param array<string,mixed> $transaction */',ps);
    replace(s.slice(ps,pe),`    public function poll(): void {
        // PAY-003: legacy route uses the same ownership, CSRF and rate-limit gate.
        $this->load->controller('checkout/credit.state');
    }
`);
    let fragment=read('scripts/pay003/provider.fragment.php').replaceAll('__PROVIDER__',p);
    fragment=fragment.replace('__READ__',p==='pumb_credit'?`if ((int)$tx['is_test'] !== (int)$this->isTestMode()) return [];
        return $this->api('GET', self::API_CREATE . '/' . rawurlencode((string)$tx['cap_id']));`:`return $this->api(self::API_STATE, ['order_id'=>$tx['mono_order_id']]);`);
    fragment=fragment.replace('__APPLY__',p==='pumb_credit'?`if (empty($tx['is_test'])) $this->applyOrderStatus((int)$current['id'], (string)$tx['state'], false);`:`$this->applyOrderStatus((int)$current['id'], (string)$tx['state'], (string)($tx['order_sub_state'] ?? ''));`);
    replace('    private function reply(array $json): void {',fragment+'    private function reply(array $json): void {');
  });
}
edit('catalog/controller/checkout/success.php',(s,replace)=>{
  replace("\t\t$this->load->language('checkout/success');",read('scripts/pay003/success.fragment.php')+"\t\t$this->load->language('checkout/success');");
  replace("\t\t$order_id = (int)($this->session->data['order_id'] ?? 0);","\t\t$order_id = $pay003_recovery ? $pay003_id : (int)($this->session->data['order_id'] ?? 0);");
  replace("$show_first15_offer = !empty($this->session->data['checkout001_first15_offer_pending']);","$show_first15_offer = !$pay003_recovery && !empty($this->session->data['checkout001_first15_offer_pending']);");
  replace("unset($this->session->data['checkout001_first15_offer_pending']);","if (!$pay003_recovery) unset($this->session->data['checkout001_first15_offer_pending']);");
  replace("\t\tif (isset($this->session->data['order_id'])) {","\t\tif (!$pay003_recovery && isset($this->session->data['order_id'])) {");
  replace("\t\t// TECH First15 hotfix: always clear checkout discount session data on success.\n\t\tunset($this->session->data['coupon']);\n\t\tunset($this->session->data['reward']);","\t\t// PAY-003: historical recovery must not clear discounts for a new checkout.\n\t\tif (!$pay003_recovery) {\n\t\t\tunset($this->session->data['coupon']);\n\t\t\tunset($this->session->data['reward']);\n\t\t}");
});
edit('catalog/controller/account/order.php',(s,replace)=>{
  replace("\t\t\t$data['order_id'] = $order_id;",`\t\t\t$data['order_id'] = $order_id;
            // PAY-003: link exists only for an authenticated owner and credit order.
            $this->load->model('checkout/credit');
            $data['pay003_credit_url'] = $this->model_checkout_credit->context((int)$order_id) ? $this->model_checkout_credit->url((int)$order_id) : '';`);
});
edit('catalog/view/template/account/order_info.twig',(s,replace)=>{
  replace('      {{ content_top }}','      {{ content_top }}\n      {% if pay003_credit_url %}<p><a class="btn btn-primary" href="{{ pay003_credit_url }}">Перевірити статус оплати частинами</a></p>{% endif %}');
});
for(const [file,input] of Object.entries({
  'catalog/model/checkout/credit.php':'model.php','catalog/controller/checkout/credit.php':'controller.php',
  'catalog/view/template/checkout/credit.twig':'credit.twig','catalog/view/template/checkout/credit_confirm.twig':'confirmation.twig',
  'catalog/view/javascript/pay003-credit.js':'credit.js','catalog/view/stylesheet/pay003-credit.css':'credit.css'
}))add(file,'scripts/pay003/'+input);
const guards={};
for(const file of ['catalog/controller/checkout/confirm.php','catalog/model/account/order.php','catalog/view/template/checkout/success.twig'])guards[file]=hash(read(path.join(base,'source',file)));
const spec={files,guards};
fs.writeFileSync(path.join(base,'manifest.json'),JSON.stringify(spec,null,2));
let runner=read('scripts/pay003/runner.php').replace('__SPEC__',Buffer.from(JSON.stringify(spec)).toString('base64')).replace('__ROLLBACK__',Buffer.from(read('scripts/pay003/rollback.php')).toString('base64'));
fs.writeFileSync('patches/PAY-003_credit-wait-recovery_20260831.php',runner);
console.log(JSON.stringify({files:Object.keys(files),hashes:Object.fromEntries(Object.entries(files).map(([k,v])=>[k,v.after]))}));
