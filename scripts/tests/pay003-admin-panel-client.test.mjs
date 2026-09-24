import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..', '..');
const file = path.join(root, 'work', 'pay003-admin-order', 'candidate', 'admin-view', 'order_info.twig');
const twig = fs.readFileSync(file, 'utf8');
const start = twig.indexOf('(function($) {', twig.indexOf('id="pay003-admin-card"'));
const endMarker = '}(jQuery));';
const end = twig.indexOf(endMarker, start);
if (start < 0 || end < 0) throw new Error('PAY003 client block not found');
const source = twig.slice(start, end + endMarker.length);

let checks = 0;
function ok(value, message) {
  checks++;
  if (!value) throw new Error(`FAIL ${message}`);
}

new Function('jQuery', source);
ok(true, 'client JavaScript parses');
ok((twig.match(/id="pay003-admin-card"/g) || []).length === 1, 'single card id');
ok((twig.match(/id="pay003-admin-refresh"/g) || []).length === 1, 'single refresh id');
ok((twig.match(/id="pay003-admin-shipment"/g) || []).length === 1, 'single shipment id');
ok((twig.match(/id="pay003-admin-refund"/g) || []).length === 1, 'single refund id');
ok(source.includes("data: {order_id: orderId}"), 'actions are order-scoped');
ok(source.includes('button.prop(\'disabled\')'), 'disabled action guard');
ok(source.includes('if (busy'), 'in-flight guard');
ok(source.includes('window.confirm'), 'destructive confirmation');
ok(source.includes('.text(data.state_label'), 'bank text uses safe text insertion');
ok(!source.includes('.html('), 'no bank response HTML insertion');
ok(!source.includes('setTimeout'), 'no client polling timer');
ok(!source.includes('agreement_number') && !source.includes('payload'), 'no secret-bearing fields consumed');

console.log(`checks=${checks} result=ok network=0 dom=static`);
