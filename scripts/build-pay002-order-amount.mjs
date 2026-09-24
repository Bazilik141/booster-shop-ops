import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root=process.cwd();
const file='extension/pumb_credit/catalog/controller/payment/pumb_credit.php';
const base=path.join(root,'work/pay002-order-amount');
const source=fs.readFileSync(path.join(root,'work/pay002-final-audit/source',file),'utf8');
const hash=s=>crypto.createHash('sha256').update(s).digest('hex');
if(hash(source)!=='e526b2f07f9ae3950c777357a5b30636219eaf0650ffe466025bdab0e36e9e11')throw Error('Owner-confirmed source mismatch');
const nl=source.includes('\r\n')?'\r\n':'\n';
const eol=s=>s.replace(/\r?\n/g,nl);
const beforeMethod=source.slice(source.indexOf('    private function createPayload('),source.indexOf('    private function oauthToken('));
const fragment=eol(fs.readFileSync('scripts/pay002-amount-payload.fragment.php','utf8').trimEnd())+nl;
const edits=[
 {label:'build and validate invoice before reserving a new application',before:eol('        try {\n            $reservation = $this->reserveCreate($orderId, $isTest);'),after:eol(`        // PAY-002-ORDER-AMOUNT: fail before reservation/OAuth/bank on invalid totals.
        try {
            $payload = $this->createPayload($order, $term);
        } catch (\\Throwable $exception) {
            $this->reply(['error' => 'Не вдалося узгодити суму замовлення для ПУМБ. Зверніться до підтримки магазину.']);
            return;
        }

        try {
            $reservation = $this->reserveCreate($orderId, $isTest);`)},
 {label:'reuse validated invoice after winning the existing reservation guard',before:eol("        $payload = $this->createPayload($order, $term);\n        $response = $this->api('POST', self::API_CREATE, $payload);"),after:"        $response = $this->api('POST', self::API_CREATE, $payload);"},
 {label:'allocate persisted order total to bank goods in exact cents',before:beforeMethod,after:fragment},
];
let candidate=source;
for(const e of edits){if(!e.before||candidate.split(e.before).length!==2)throw Error('Anchor: '+e.label);candidate=candidate.replace(e.before,e.after);}
const spec={files:{[file]:{before:hash(source),after:hash(candidate),edits}},guards:{}};
fs.mkdirSync(path.dirname(path.join(base,'candidate',file)),{recursive:true});
fs.writeFileSync(path.join(base,'candidate',file),candidate);
fs.writeFileSync(path.join(base,'manifest.json'),JSON.stringify(spec,null,2));
let runner=fs.readFileSync('patches/PAY-002_cart-canonical-stock_20260831.php','utf8');
runner=runner.replace(/\/\*\*[\s\S]*?\*\//,`/**
 * PAY-002 PUMB order amount reconciliation. PHP 8.0, one controller only.
 * Uses persisted order total (UAH), exact cents, proportional goods allocation.
 * Up to two unit-price buckets per product preserve quantity and exact sum.
 * Invalid/unrepresentable data fails before reservation or bank requests.
 * No deployment DB writes, settings, Mono, delivery, UI or callback changes.
 * Existing applications are not recalculated or recreated.
 * Rollback: restore the target from the printed backup; no DB rollback needed.
 */`);
runner=runner.replaceAll('PAY-002_cart-canonical-stock_20260831','PAY-002_pumb-order-amount_20260831');
runner=runner.replace(/base64_decode\('[^']+'/,"base64_decode('"+Buffer.from(JSON.stringify(spec)).toString('base64')+"'");
fs.writeFileSync('patches/PAY-002_pumb-order-amount_20260831.php',runner);
console.log(JSON.stringify({target:file,before:hash(source),after:hash(candidate),edits:edits.length}));
